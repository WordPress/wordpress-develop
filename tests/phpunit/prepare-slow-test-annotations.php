#!/usr/bin/env php
<?php

/**
 * Flags slow PHPUnit tests with GitHub Actions annotations and a run summary.
 *
 * Usage:
 *
 *     php tests/phpunit/prepare-slow-test-annotations.php <junit-file> \
 *         [threshold-seconds] [max-annotations]
 *
 * @package WordPress
 * @subpackage UnitTests
 */

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

if ( $argc < 2 || $argc > 4 ) {
	fwrite(
		STDERR,
		"Usage: php tests/phpunit/prepare-slow-test-annotations.php <junit-file> "
		. "[threshold-seconds] [max-annotations]\n"
	);
	exit( 1 );
}

try {
	$file              = $argv[1];
	$threshold_value   = $argv[2] ?? '1.0';
	$max_annotations   = $argv[3] ?? '20';

	if ( ! is_numeric( $threshold_value ) || (float) $threshold_value < 0 ) {
		throw new RuntimeException( 'The slow-test threshold must be a non-negative number.' );
	}

	if ( ! ctype_digit( $max_annotations ) || (int) $max_annotations < 1 ) {
		throw new RuntimeException( 'The maximum annotation count must be a positive integer.' );
	}

	if ( ! is_readable( $file ) ) {
		throw new RuntimeException( 'The JUnit report could not be read.' );
	}

	$threshold             = (float) $threshold_value;
	$max_annotations       = (int) $max_annotations;
	$reader                = new XMLReader();
	$previous_libxml_state = libxml_use_internal_errors( true );
	$reader_is_open        = false;

	libxml_clear_errors();

	try {
		if ( ! $reader->open( $file, null, LIBXML_NONET | LIBXML_COMPACT ) ) {
			throw new RuntimeException( 'The JUnit report could not be opened.' );
		}

		$reader_is_open = true;
		$slow_tests     = array();

		while ( $reader->read() ) {
			if ( XMLReader::ELEMENT !== $reader->nodeType || 'testcase' !== $reader->name ) {
				continue;
			}

			$time = $reader->getAttribute( 'time' );

			// A testcase without numeric timing (for example a skipped test) carries
			// no slow-test signal, so it is ignored rather than treated as an error.
			if ( ! is_numeric( $time ) ) {
				continue;
			}

			if ( (float) $time <= $threshold ) {
				continue;
			}

			$slow_tests[] = array(
				'name'         => (string) $reader->getAttribute( 'name' ),
				'class'        => (string) $reader->getAttribute( 'class' ),
				'file'         => wp_phpunit_relative_path( (string) $reader->getAttribute( 'file' ) ),
				'line'         => (string) $reader->getAttribute( 'line' ),
				'time'         => (float) $time,
				'time_display' => $time,
			);
		}

		$xml_errors = libxml_get_errors();
	} finally {
		if ( $reader_is_open ) {
			$reader->close();
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $previous_libxml_state );
	}

	foreach ( $xml_errors as $xml_error ) {
		if ( LIBXML_ERR_WARNING < $xml_error->level ) {
			throw new RuntimeException( 'The JUnit report contains invalid XML.' );
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

	$slow_tests = array_slice( $slow_tests, 0, $max_annotations );

	if ( ! $slow_tests ) {
		wp_phpunit_write_summary( "No PHPUnit tests exceeded {$threshold_value}s.\n" );
		exit( 0 );
	}

	// GitHub Actions renders at most 10 warning annotations per step, so the inline
	// annotations are capped there while the summary table below can list more.
	foreach ( array_slice( $slow_tests, 0, 10 ) as $test ) {
		$properties = array();

		if ( '' !== $test['file'] ) {
			$properties[] = 'file=' . wp_phpunit_escape_command_property( $test['file'] );
		}

		if ( '' !== $test['line'] ) {
			$properties[] = 'line=' . wp_phpunit_escape_command_property( $test['line'] );
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
	$summary .= "| Test | Time (s) | File:line |\n";
	$summary .= "| --- | ---: | --- |\n";

	foreach ( $slow_tests as $test ) {
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
