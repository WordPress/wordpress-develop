<?php
/**
 * Compares the snapshots WP_PHPUnit_Leak_Listener writes, see tests/phpunit/README.md.
 *
 * php diff.php run.jsonl                  What each test and class leaves behind
 *                                         (test_start vs test_end, class_start vs class_end).
 *                                         WP_TESTS_LEAKS_SCOPE=class runs: install vs class end.
 * php diff.php alone.jsonl seq.jsonl ID   State the class ID inherits: its class_start
 *                                         in run A vs run B, minus run noise (timestamps,
 *                                         env, install rows): keys whose first class_start,
 *                                         before any class ran, already differs.
 */

const CHANGES_FOUND = 2;

const USAGE = "Usage: php diff.php run.jsonl\n       php diff.php a.jsonl b.jsonl ClassName\n";

// A dropped cache entry is a miss, which reads the source again: not a leak, e.g. the test framework's flushes.
const CACHE_AREAS = '/^(cache|mc)\|/';

// Yields one snapshot at a time: a full run's file can exceed the memory limit. Returns the unreadable line count.
function load( $file ) {
	$handle = fopen( $file, 'r' );
	if ( false === $handle ) {
		fwrite( STDERR, "Cannot read $file\n" );
		exit( 1 );
	}

	$unreadable = 0;
	$line       = fgets( $handle );
	while ( false !== $line ) {
		$snap = json_decode( $line, true );
		if ( isset( $snap['pid'], $snap['point'], $snap['id'], $snap['state'] ) ) {
			yield $snap;
		} else {
			++$unreadable;
		}

		$line = fgets( $handle );
	}

	fclose( $handle );

	return $unreadable;
}

function compare( array $a, array $b ) {
	$lines = array();
	foreach ( array_unique( array_merge( array_keys( $a ), array_keys( $b ) ) ) as $key ) {
		$before = $a[ $key ] ?? null;
		$after  = $b[ $key ] ?? null;
		if ( $before && $after && $before[0] === $after[0] ) {
			continue;
		}

		if ( ! $after && preg_match( CACHE_AREAS, $key ) ) {
			continue;
		}

		$lines[] = sprintf( "  %s\n      before: %s\n      after:  %s", $key, preview( $before ), preview( $after ) );
	}
	sort( $lines );

	return $lines;
}

// One line: a multi-line value, e.g. a schema, would read as more keys.
function preview( $entry ) {
	return $entry ? preg_replace( '/\s*\n\s*/', ' ', $entry[1] ) : '(absent)';
}

// Returns whether it reported anything.
function report( $title, array $lines ) {
	if ( ! $lines ) {
		return false;
	}

	echo "== $title (" . count( $lines ) . " changed)\n" . implode( "\n", $lines ) . "\n\n";

	return true;
}

// The first class_start and the class ID's.
function class_starts( $file, $id ) {
	$first = null;
	foreach ( load( $file ) as $snap ) {
		if ( 'class_start' !== $snap['point'] ) {
			continue;
		}

		$first = $first ?? $snap['state'];
		if ( $snap['id'] === $id ) {
			return array( $first, $snap['state'] );
		}
	}

	fwrite( STDERR, "No class_start for $id in $file\n" );
	exit( 1 );
}

if ( 4 === $argc ) {
	list( $first_a, $a ) = class_starts( $argv[1], $argv[3] );
	list( $first_b, $b ) = class_starts( $argv[2], $argv[3] );

	$noise = array_diff_key( $first_a, $first_b ) + array_diff_key( $first_b, $first_a );
	foreach ( array_intersect_key( $first_a, $first_b ) as $key => $value ) {
		if ( $value[0] !== $first_b[ $key ][0] ) {
			$noise[ $key ] = true;
		}
	}

	$lines = compare( array_diff_key( $a, $noise ), array_diff_key( $b, $noise ) );
	exit( report( "{$argv[3]} class_start: {$argv[1]} -> {$argv[2]}, " . count( $noise ) . ' run noise keys ignored', $lines ) ? CHANGES_FOUND : 0 );
}

if ( 2 !== $argc ) {
	fwrite( STDERR, USAGE );
	exit( 1 );
}

$snaps   = load( $argv[1] );
$count   = 0;
$orphans = 0;
$open    = array();
$changes = false;
foreach ( $snaps as $snap ) {
	++$count;
	if ( 'class_leaks' === $snap['point'] ) {
		$changes = report( "class {$snap['id']} leaves, unlike the install", compare( $snap['installed'], $snap['state'] ) ) || $changes;
		continue;
	}

	list( $kind, $edge ) = explode( '_', $snap['point'] );
	$slot                = $snap['pid'] . '|' . $kind . '|' . $snap['id'];

	if ( 'start' === $edge ) {
		$open[ $slot ] = $snap['state'];
		continue;
	}

	if ( ! isset( $open[ $slot ] ) ) {
		++$orphans;
		continue;
	}

	$where   = empty( $snap['child'] ) ? '' : ' (isolated child)';
	$changes = report( "$kind {$snap['id']}$where leaves", compare( $open[ $slot ], $snap['state'] ) ) || $changes;
	unset( $open[ $slot ] );
}

// A run that crashed, or is still running, leaves cut lines and starts with no end, the run_start included.
$unreadable = $snaps->getReturn();
if ( ! $count || $unreadable || $orphans || $open ) {
	fwrite( STDERR, "Incomplete run: $count snapshots, $unreadable unreadable lines, " . count( $open ) . " starts with no end, $orphans ends with no start.\n" );
	exit( 1 );
}

exit( $changes ? CHANGES_FOUND : 0 );
