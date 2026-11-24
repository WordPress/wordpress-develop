<?php
/**
 * Checks diff.php reports each leak the leak-probe suite plants, see tests/phpunit/README.md.
 *
 * php check-probes.php run.jsonl
 *
 * Set WP_TESTS_LEAKS_SCOPE as for the run. Exits with 1 on a missed leak, in-process state an
 * isolated child reports, or a failed diff.php.
 */

// Report title => patterns that must each match a reported key.
const PER_TEST = array(
	'test Tests_Leak_Probe::test_leaks_option_cache'       => array( '/^stale\|runtime\|1\|options\|alloptions\|blogname$/', '/^stale\|runtime\|1\|options\|leak_probe_not_autoloaded$/' ),
	'test Tests_Leak_Probe::test_leaks_notoptions_cache'   => array( '/^db\|\w+options\|leak_probe_missing$/', '/^stale\|runtime\|1\|options\|notoptions\|leak_probe_missing$/' ),
	'test Tests_Leak_Probe::test_leaks_network_option_cache' => array( '/^stale\|runtime\|1\|site-options\|1:site_admins$/' ),
	'test Tests_Leak_Probe::test_leaks_cache_global_group' => array( '/^global\|wp_object_cache\|global_groups$/' ),
	'test Tests_Leak_Probe::test_leaks_function_static_var' => array( '/^fstatic\|leak_probe_counter\(\)\|\$calls$/', '/^fstatic\|Tests_Leak_Probe::method_counter\(\)\|\$calls$/' ),
	'test Tests_Leak_Probe::test_leaks_static_property'    => array( '/^static\|Tests_Leak_Probe::\$leaked$/' ),
	'test Tests_Leak_Probe::test_leaks_constant'           => array( '/^const\|LEAK_PROBE_CONSTANT$/' ),
	'test Tests_Leak_Probe::test_leaks_auto_increment'     => array( '/^autoinc\|\w+posts$/' ),
	'test Tests_Leak_Probe::test_leaks_deep_global_change' => array( '/^global\|leak_probe_deep\|l1$/' ),
	'test Tests_Leak_Probe::test_leaks_big_array_key'      => array( '/^global\|leak_probe_big\|2500$/' ),
	'test Tests_Leak_Probe::test_leaks_type_only_change'   => array( '/^global\|leak_probe_int$/' ),
	'test Tests_Leak_Probe::test_leaks_closure_swap'       => array( '/^global\|leak_probe_cb$/' ),
	'test Tests_Leak_Probe::test_leaks_wpdb_and_server'    => array( '/^global\|_SERVER\|LEAK_PROBE$/', '/^global\|wpdb\|show_errors$/' ),
	'test Tests_Leak_Probe::test_leaks_process_state'      => array( '/^env\|LEAK_PROBE$/', '/^ini\|precision$/', '/^ini\|error_reporting$/', '/^proc\|autoloaders$/', '/^proc\|error_handler$/', '/^proc\|error_reporting$/', '/^proc\|exception_handler$/', '/^proc\|libxml_errors$/', '/^proc\|locale$/', '/^proc\|mb_encoding$/', '/^proc\|timezone$/', '/^proc\|umask$/' ),
	'test Tests_Leak_Probe::test_leaks_files'              => array( '/^file\|.*\/wp-content\/leak-probe\.txt$/', '/^file\|(?!.*\/wp-content\/).*\/leak-probe\.txt$/' ),
	'test Tests_Leak_Probe::test_leaks_schema'             => array( '/^schema\|\w+options$/' ),
	'test Tests_Leak_Probe::test_leaks_temp_table'         => array( '/^temptable\|\w+leak_probe_tmp$/' ),
	'test Tests_Leak_Probe::test_leaks_in_child (isolated child)' => array( '/^db\|\w+links\|\d+$/' ),
);

const CLASS_SCOPE = array(
	'class Tests_Leak_Probe' => array( '/^db\|\w+links\|\d+$/', '/^schema\|\w+options$/' ),
);

// Planted in the in-memory object cache, which a persistent cache's tear_down() flush clears.
const RUNTIME_CACHE_ONLY = array(
	'/^stale\|runtime\|1\|options\|alloptions\|blogname$/',
	'/^stale\|runtime\|1\|options\|leak_probe_not_autoloaded$/',
	'/^stale\|runtime\|1\|options\|notoptions\|leak_probe_missing$/',
	'/^stale\|runtime\|1\|site-options\|1:site_admins$/',
);

const MULTISITE_ONLY = array( '/^stale\|runtime\|1\|site-options\|1:site_admins$/' );

// An isolated child's report holds only these, see OUTLIVES_PROCESS in the listener.
const CHILD_AREAS = '/^(db|schema|mc|file|stale\|memcached)\|/';

const CHILD_SUFFIX = ' (isolated child)';

// Report header and changed key lines, see report() in diff.php.
const HEADER = '/^== (.+) leaves(?:, unlike the install)? \(\d+ changed\)$/';

const KEY_LINE = '/^  (\S.*)$/';

// The keys of the run's first non-empty snapshot.
function first_keys( $file ) {
	$handle = fopen( $file, 'r' );
	if ( false === $handle ) {
		fwrite( STDERR, "Cannot read $file\n" );
		exit( 1 );
	}

	$line = fgets( $handle );
	while ( false !== $line ) {
		$snap = json_decode( $line, true );
		if ( ! empty( $snap['state'] ) ) {
			fclose( $handle );
			return array_keys( $snap['state'] );
		}

		$line = fgets( $handle );
	}

	fclose( $handle );
	fwrite( STDERR, "No snapshot in $file\n" );
	exit( 1 );
}

if ( 2 !== $argc ) {
	fwrite( STDERR, "Usage: php check-probes.php run.jsonl\n" );
	exit( 1 );
}

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/diff.php' ) . ' ' . escapeshellarg( $argv[1] ), $output, $code );
if ( 0 !== $code ) {
	fwrite( STDERR, "diff.php failed with $code.\n" );
	exit( 1 );
}

$reported = array();
$title    = null;
foreach ( $output as $line ) {
	if ( preg_match( HEADER, $line, $m ) ) {
		$title              = $m[1];
		$reported[ $title ] = array();
		continue;
	}

	if ( null !== $title && preg_match( KEY_LINE, $line, $m ) ) {
		$reported[ $title ][] = $m[1];
	}
}

$multisite     = (bool) preg_grep( '/^schema\|\w+blogs$/', first_keys( $argv[1] ) );
$runtime_cache = 'true' !== getenv( 'LOCAL_PHP_MEMCACHED' );
$expected      = 'class' === getenv( 'WP_TESTS_LEAKS_SCOPE' ) ? CLASS_SCOPE : PER_TEST;

$missed  = array();
$checked = 0;
$skipped = 0;
foreach ( $expected as $title => $patterns ) {
	foreach ( $patterns as $pattern ) {
		if ( ( ! $multisite && in_array( $pattern, MULTISITE_ONLY, true ) ) || ( ! $runtime_cache && in_array( $pattern, RUNTIME_CACHE_ONLY, true ) ) ) {
			++$skipped;
			continue;
		}

		++$checked;
		if ( ! preg_grep( $pattern, $reported[ $title ] ?? array() ) ) {
			$missed[] = "$title: $pattern";
		}
	}
}

foreach ( $reported as $title => $keys ) {
	if ( ! str_ends_with( $title, CHILD_SUFFIX ) ) {
		continue;
	}

	foreach ( preg_grep( CHILD_AREAS, $keys, PREG_GREP_INVERT ) as $key ) {
		$missed[] = "$title: unexpected $key";
	}
}

if ( $missed ) {
	fwrite( STDERR, "Missed or unexpected leaks:\n  " . implode( "\n  ", $missed ) . "\n" );
	exit( 1 );
}

echo "$checked expected leaks reported, $skipped skipped for this setup.\n";
