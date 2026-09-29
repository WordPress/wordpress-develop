#!/usr/bin/env php
<?php

/**
 * Flags slow PHPUnit tests with GitHub Actions annotations and a run summary.
 *
 * Usage:
 *
 *     php tests/phpunit/prepare-slow-test-annotations.php <junit-file> \
 *         [threshold-seconds] [max-summary-tests] [timing-metrics-file]
 *
 * @package WordPress
 * @subpackage UnitTests
 */

require_once __DIR__ . '/includes/class-wp-phpunit-timing-metrics.php';

/**
 * Escapes a GitHub Actions workflow command message.
 *
 * @param string $value Message to escape.
 * @return string Escaped message.
 */
function wp_phpunit_escape_command_message( $value ) {
	return str_replace(
		array( '%', "\r", "\n" ),
		array( '%25', '%0D', '%0A' ),
		$value
	);
}

/**
 * Escapes a GitHub Actions workflow command property.
 *
 * @param string $value Property value to escape.
 * @return string Escaped property value.
 */
function wp_phpunit_escape_command_property( $value ) {
	return str_replace(
		array( ',', ':' ),
		array( '%2C', '%3A' ),
		wp_phpunit_escape_command_message( $value )
	);
}

/**
 * Escapes text for a Markdown table cell.
 *
 * @param string $value Cell value to escape.
 * @return string Escaped cell value.
 */
function wp_phpunit_escape_markdown_cell( $value ) {
	return str_replace(
		array( '|', "\r", "\n" ),
		array( '\\|', ' ', ' ' ),
		$value
	);
}

/**
 * Converts a container-absolute test path to a repository-relative one.
 *
 * PHPUnit records absolute paths (the repository is mounted at /var/www in the
 * Docker environment). GitHub annotations need repository-relative paths to
 * resolve to a line, so a known workspace prefix is stripped when present.
 *
 * @param string $file Path recorded in the JUnit report.
 * @return string Repository-relative path, or the input unchanged.
 */
function wp_phpunit_relative_path( $file ) {
	if ( '' === $file ) {
		return '';
	}

	$prefixes  = array( '/var/www/' );
	$workspace = getenv( 'GITHUB_WORKSPACE' );

	if ( is_string( $workspace ) && '' !== $workspace ) {
		$prefixes[] = rtrim( $workspace, '/' ) . '/';
	}

	foreach ( $prefixes as $prefix ) {
		if ( 0 === strncmp( $file, $prefix, strlen( $prefix ) ) ) {
			return substr( $file, strlen( $prefix ) );
		}
	}

	return $file;
}

/**
 * Appends a summary to GitHub Actions or writes it to standard output.
 *
 * @param string $summary Markdown summary.
 * @return void
 * @throws RuntimeException If the GitHub Actions summary cannot be written.
 */
function wp_phpunit_write_summary( $summary ) {
	$summary_file = getenv( 'GITHUB_STEP_SUMMARY' );

	if ( false === $summary_file || '' === $summary_file ) {
		echo $summary;
		return;
	}

	if ( false === file_put_contents( $summary_file, $summary, FILE_APPEND ) ) {
		throw new RuntimeException( 'The GitHub Actions step summary could not be written.' );
	}
}

if ( $argc < 2 || $argc > 5 ) {
	fwrite(
		STDERR,
		'Usage: php tests/phpunit/prepare-slow-test-annotations.php <junit-file> '
		. "[threshold-seconds] [max-summary-tests] [timing-metrics-file]\n"
	);
	exit( 1 );
}

try {
	$file                    = $argv[1];
	$threshold_value         = $argv[2] ?? '1.0';
	$max_summary_tests_value = $argv[3] ?? '20';
	$timing_metrics_file     = $argv[4] ?? null;

	if ( ! is_numeric( $threshold_value ) || (float) $threshold_value < 0 ) {
		throw new RuntimeException( 'The slow-test threshold must be a non-negative number.' );
	}

	if ( ! ctype_digit( $max_summary_tests_value ) || (int) $max_summary_tests_value < 1 ) {
		throw new RuntimeException( 'The maximum summary test count must be a positive integer.' );
	}

	$threshold         = (float) $threshold_value;
	$max_summary_tests = (int) $max_summary_tests_value;
	$slow_tests        = array();
	$timing_metrics    = WP_PHPUnit_Timing_Metrics::from_file(
		$file,
		static function ( $testcase ) use ( &$slow_tests, $threshold ) {
			if ( $testcase['time'] <= $threshold ) {
				return;
			}

			$testcase['file'] = wp_phpunit_relative_path( $testcase['file'] );
			$slow_tests[]     = $testcase;
		}
	);

	if ( null !== $timing_metrics_file ) {
		$timing_metrics_json = json_encode( $timing_metrics, JSON_THROW_ON_ERROR );

		if ( false === file_put_contents( $timing_metrics_file, $timing_metrics_json . "\n" ) ) {
			throw new RuntimeException( 'The PHPUnit timing metrics file could not be written.' );
		}
	}

	usort(
		$slow_tests,
		static function ( $left, $right ) {
			if ( $left['time'] === $right['time'] ) {
				return strcmp( $left['class'] . '::' . $left['name'], $right['class'] . '::' . $right['name'] );
			}

			return $right['time'] <=> $left['time'];
		}
	);

	if ( ! $slow_tests ) {
		wp_phpunit_write_summary( "No PHPUnit tests exceeded {$threshold_value}s.\n" );
		exit( 0 );
	}

	$total_slow_tests = count( $slow_tests );
	$summary_tests    = array_slice( $slow_tests, 0, $max_summary_tests );

	// GitHub Actions renders at most 10 warning annotations per step, so the inline
	// annotations are capped there while the summary table below can list more.
	foreach ( array_slice( $slow_tests, 0, 10 ) as $test ) {
		$properties = array();

		if ( '' !== $test['file'] ) {
			$properties[] = 'file=' . wp_phpunit_escape_command_property( $test['file'] );

			if ( '' !== $test['line'] ) {
				$properties[] = 'line=' . wp_phpunit_escape_command_property( $test['line'] );
			}
		}

		$properties[] = 'title=' . wp_phpunit_escape_command_property( 'Slow PHPUnit test' );
		$message      = sprintf(
			'%s::%s took %ss',
			$test['class'],
			$test['name'],
			$test['time_display']
		);

		printf(
			"::warning %s::%s\n",
			implode( ',', $properties ),
			wp_phpunit_escape_command_message( $message )
		);
	}

	$summary = "### Slowest PHPUnit tests (main suite, over {$threshold_value}s)\n\n";

	if ( $total_slow_tests > count( $summary_tests ) ) {
		$summary .= sprintf(
			"Showing the %d slowest of %d tests above the threshold.\n\n",
			count( $summary_tests ),
			$total_slow_tests
		);
	}

	$summary .= "| Test | Time (s) | File:line |\n";
	$summary .= "| --- | ---: | --- |\n";

	foreach ( $summary_tests as $test ) {
		$location = $test['file'];

		if ( '' !== $test['line'] ) {
			$location .= ( '' !== $location ? ':' : 'Line ' ) . $test['line'];
		}

		if ( '' === $location ) {
			$location = '&mdash;';
		}

		$summary .= sprintf(
			"| %s::%s | %s | %s |\n",
			wp_phpunit_escape_markdown_cell( $test['class'] ),
			wp_phpunit_escape_markdown_cell( $test['name'] ),
			$test['time_display'],
			wp_phpunit_escape_markdown_cell( $location )
		);
	}

	wp_phpunit_write_summary( $summary );
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . "\n" );
	exit( 1 );
}
