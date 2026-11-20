<?php
/**
 * Compares the snapshots WP_PHPUnit_Leak_Listener writes, see tests/phpunit/README.md.
 *
 * php diff.php run.jsonl                  What each test and class leaves behind
 *                                         (test_start vs test_end, class_start vs class_end).
 *                                         WP_TESTS_LEAKS_SCOPE=class runs: install vs class end.
 * php diff.php alone.jsonl seq.jsonl ID   State the class ID inherits: its class_start
 *                                         in run A vs run B.
 */

const USAGE = "Usage: php diff.php run.jsonl\n       php diff.php a.jsonl b.jsonl ClassName\n";

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

		$lines[] = sprintf( "  %s\n      before: %s\n      after:  %s", $key, $before ? $before[1] : '(absent)', $after ? $after[1] : '(absent)' );
	}
	sort( $lines );

	return $lines;
}

function report( $title, array $lines ) {
	if ( ! $lines ) {
		return;
	}

	echo "== $title (" . count( $lines ) . " changed)\n" . implode( "\n", $lines ) . "\n\n";
}

function class_start( $snaps, $id ) {
	foreach ( $snaps as $snap ) {
		if ( 'class_start' === $snap['point'] && $snap['id'] === $id ) {
			return $snap['state'];
		}
	}

	fwrite( STDERR, "No class_start for $id\n" );
	exit( 1 );
}

if ( 4 === $argc ) {
	report( "{$argv[3]} class_start: {$argv[1]} -> {$argv[2]}", compare( class_start( load( $argv[1] ), $argv[3] ), class_start( load( $argv[2] ), $argv[3] ) ) );
	exit( 0 );
}

if ( 2 !== $argc ) {
	fwrite( STDERR, USAGE );
	exit( 1 );
}

$snaps   = load( $argv[1] );
$count   = 0;
$orphans = 0;
$open    = array();
foreach ( $snaps as $snap ) {
	++$count;
	if ( 'class_leaks' === $snap['point'] ) {
		report( "class {$snap['id']} leaves, unlike the install", compare( $snap['installed'], $snap['state'] ) );
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

	$where = empty( $snap['child'] ) ? '' : ' (isolated child)';
	report( "$kind {$snap['id']}$where leaves", compare( $open[ $slot ], $snap['state'] ) );
	unset( $open[ $slot ] );
}

// A run that crashed, or is still running, leaves cut lines and starts with no end, the run_start included.
$unreadable = $snaps->getReturn();
if ( ! $count || $unreadable || $orphans || $open ) {
	fwrite( STDERR, "Incomplete run: $count snapshots, $unreadable unreadable lines, " . count( $open ) . " starts with no end, $orphans ends with no start.\n" );
	exit( 1 );
}
