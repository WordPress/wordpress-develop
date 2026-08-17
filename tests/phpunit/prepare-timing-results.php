#!/usr/bin/env php
<?php

/**
 * Prepares aggregate PHPUnit timing metrics for publication to CodeVitals.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

require_once __DIR__ . '/includes/class-wp-phpunit-timing-metrics.php';

if ( 5 !== $argc ) {
	fwrite( STDERR, "Usage: prepare-timing-results.php <junit-file> <branch> <hash> <timestamp>\n" );
	exit( 1 );
}

try {
	$timestamp = new DateTimeImmutable( $argv[4] );
	$payload   = array(
		'branch'    => $argv[2],
		'hash'      => $argv[3],
		'baseHash'  => $argv[3],
		'timestamp' => $timestamp->format( DATE_ATOM ),
		'metrics'   => WP_PHPUnit_Timing_Metrics::from_file( $argv[1] ),
	);

	echo json_encode( $payload, JSON_THROW_ON_ERROR ) . "\n";
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . "\n" );
	exit( 1 );
}
