#!/usr/bin/env php
<?php

/**
 * Prepares aggregate PHPUnit timing metrics for publication to CodeVitals.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

require_once __DIR__ . '/includes/class-wp-phpunit-timing-metrics.php';

if ( 5 !== $argc && 6 !== $argc ) {
	fwrite( STDERR, "Usage: prepare-timing-results.php <junit-file> <branch> <hash> <timestamp> [timing-metrics-file]\n" );
	exit( 1 );
}

try {
	if ( 6 === $argc ) {
		if ( ! is_readable( $argv[5] ) ) {
			throw new RuntimeException( 'The prepared PHPUnit timing metrics could not be read.' );
		}

		$timing_metrics_json = file_get_contents( $argv[5] );

		if ( false === $timing_metrics_json ) {
			throw new RuntimeException( 'The prepared PHPUnit timing metrics could not be read.' );
		}

		$timing_metrics = json_decode( $timing_metrics_json, true, 512, JSON_THROW_ON_ERROR );
		$metric_keys    = array(
			'phpunit-suite-time',
			'phpunit-p95-test-time',
			'phpunit-p99-test-time',
			'phpunit-max-test-time',
			'phpunit-tests-over-500ms',
			'phpunit-tests-over-1s',
		);

		if (
			! is_array( $timing_metrics )
			|| array_keys( $timing_metrics ) !== $metric_keys
			|| count( $timing_metrics ) !== count( array_filter( $timing_metrics, 'is_numeric' ) )
		) {
			throw new RuntimeException( 'The prepared PHPUnit timing metrics are invalid.' );
		}
	} else {
		$timing_metrics = WP_PHPUnit_Timing_Metrics::from_file( $argv[1] );
	}

	$timestamp = new DateTimeImmutable( $argv[4] );
	$payload   = array(
		'branch'      => $argv[2],
		'hash'        => $argv[3],
		'baseHash'    => $argv[3],
		'baseMetrics' => new stdClass(),
		'timestamp'   => $timestamp->format( DATE_ATOM ),
		'metrics'     => $timing_metrics,
	);

	echo json_encode( $payload, JSON_THROW_ON_ERROR ) . "\n";
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . "\n" );
	exit( 1 );
}
