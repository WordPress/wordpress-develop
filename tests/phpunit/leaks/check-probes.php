<?php
/**
 * Checks diff.php reports each leak the leak-probe suite plants, see tests/phpunit/README.md.
 *
 * php check-probes.php run.jsonl
 *
 * Exits with 1 on a missed leak, a key a probe must not report, or a failed diff.php.
 */

// Not site 1's.
const OTHER_SITE_ROW = '/^db\|\w+blogs\|(?!1$)\d+$/';

// Before PHP 8.1, a subclass has its own copy of an inherited method's static variables.
const PARENT_STATIC = '/^fstatic\|Leak_Probe_Counter::count\(\)\|\$calls$/';

const CHILD_STATIC = '/^fstatic\|Leak_Probe_Counter_Child::count\(\)\|\$calls$/';

const STATIC_TITLE = 'test Tests_Leak_Probe::test_leaks_function_static_var';

const PHP_SHARED_STATICS = 80100;

// Report title => patterns that must each match a reported key.
const PER_TEST = array(
	'test Tests_Leak_Probe::test_leaks_option_cache'       => array( '/^stale\|runtime\|1\|options\|alloptions\|blogname$/', '/^stale\|runtime\|1\|options\|leak_probe_not_autoloaded$/' ),
	'test Tests_Leak_Probe::test_leaks_cache_after_tear_down' => array( '/^cache\|leak_probe\|(1:)?after_tear_down$/', '/^stale\|runtime\|1\|options\|leak_probe_cached_only$/', '/^cache\|.*leak_probe:after_tear_down$/', '/^mc\|.*leak_probe:after_tear_down$/', '/^stale\|local\|1\|options\|leak_probe_cached_only$/', '/^stale\|memcached\|1\|options\|leak_probe_cached_only$/' ),
	'test Tests_Leak_Probe::test_leaks_composite_key_row'  => array( '/^db\|\w+term_relationships\|999999,1$/', '/^dbcount\|\w+term_relationships$/' ),
	'test Tests_Leak_Probe::test_leaks_notoptions_cache'   => array( '/^db\|\w+options\|leak_probe_missing$/', '/^dbcount\|\w+options$/', '/^stale\|runtime\|1\|options\|notoptions\|leak_probe_missing$/' ),
	'test Tests_Leak_Probe::test_leaks_network_option_cache' => array( '/^stale\|runtime\|1\|site-options\|1:site_admins$/' ),
	'test Tests_Leak_Probe::test_leaks_other_site'         => array( OTHER_SITE_ROW, '/^temptable\|\w+_\d+_options$/', '/^stale\|runtime\|(?!1\|)\d+\|options\|alloptions\|blogname$/', '/^stale\|runtime\|(?!1\|)\d+\|options\|leak_probe_cached_only$/', '/^stale\|local\|(?!1\|)\d+\|options\|leak_probe_cached_only$/', '/^stale\|memcached\|(?!1\|)\d+\|options\|leak_probe_cached_only$/' ),
	'test Tests_Leak_Probe::test_leaks_cache_global_group' => array( '/^global\|wp_object_cache\|global_groups$/' ),
	STATIC_TITLE                                           => array( '/^fstatic\|leak_probe_counter\(\)\|\$calls$/', '/^fstatic\|Tests_Leak_Probe::method_counter\(\)\|\$calls$/', PARENT_STATIC, CHILD_STATIC ),
	'test Tests_Leak_Probe::test_leaks_static_property'    => array( '/^static\|Tests_Leak_Probe::\$leaked$/' ),
	'test Tests_Leak_Probe::test_leaks_constant'           => array( '/^const\|LEAK_PROBE_CONSTANT$/' ),
	'test Tests_Leak_Probe::test_leaks_auto_increment'     => array( '/^autoinc\|\w+posts$/' ),
	'test Tests_Leak_Probe::test_leaks_deep_global_change' => array( '/^global\|leak_probe_deep\|l1$/' ),
	'test Tests_Leak_Probe::test_leaks_big_array_key'      => array( '/^global\|leak_probe_big\|2500$/' ),
	'test Tests_Leak_Probe::test_leaks_type_only_change'   => array( '/^global\|leak_probe_int$/' ),
	'test Tests_Leak_Probe::test_leaks_filtered_globals'   => array( '/^global\|leak_probe_filtered_kept$/' ),
	'test Tests_Leak_Probe::test_leaks_closure_swap'       => array( '/^global\|leak_probe_cb$/' ),
	'test Tests_Leak_Probe::test_leaks_wpdb_and_server'    => array( '/^global\|_SERVER\|LEAK_PROBE$/', '/^global\|wpdb\|show_errors$/' ),
	'test Tests_Leak_Probe::test_leaks_process_state'      => array( '/^env\|LEAK_PROBE$/', '/^ini\|precision$/', '/^ini\|error_reporting$/', '/^proc\|autoloaders$/', '/^proc\|error_handler$/', '/^proc\|error_reporting$/', '/^proc\|exception_handler$/', '/^proc\|libxml_errors$/', '/^proc\|locale$/', '/^proc\|mb_encoding$/', '/^proc\|timezone$/', '/^proc\|umask$/' ),
	'test Tests_Leak_Probe::test_leaks_files'              => array( '/^file\|.*\/wp-content\/leak-probe\.txt$/', '/^file\|(?!.*\/wp-content\/).*\/leak-probe\.txt$/', '/^file\|.*\/wp-content\/leak-probe-link$/', '/^file\|(?!.*\/wp-content\/).*\/leak-probe-link$/', '/^file\|.*\/wp-content\/leak-probe-dir$/', '/^file\|(?!.*\/wp-content\/).*\/leak-probe-dir$/' ),
	'test Tests_Leak_Probe::test_leaks_schema'             => array( '/^schema\|\w+options$/' ),
	'test Tests_Leak_Probe::test_leaks_temp_table'         => array( '/^temptable\|\w+leak_probe_tmp$/' ),
	'test Tests_Leak_Probe::test_leaks_permanent_table'    => array( '/^schema\|\w+leak_probe_permanent$/', '/^dbcount\|\w+leak_probe_permanent$/' ),
	'test Tests_Leak_Probe::test_leaks_in_child (isolated child)' => array( '/^db\|\w+links\|\d+$/' ),
	'class Tests_Leak_Probe_Class_Fixture'                 => array( '/^proc\|cwd$/', '/^proc\|ob_level$/', '/^cache\|leak_probe\|(1:)?after_class$/', '/^cache\|.*leak_probe:after_class$/', '/^mc\|.*leak_probe:after_class$/' ),
);

const CLASS_SCOPE = array(
	// The global: rules.php adds it to the class scope's areas for the file.
	'class Tests_Leak_Probe'               => array( '/^db\|\w+links\|\d+$/', '/^schema\|\w+options$/', OTHER_SITE_ROW, '/^global\|leak_probe_filtered_kept$/' ),
	'class Tests_Leak_Probe_Class_Fixture' => array( '/^stale\|runtime\|1\|options\|leak_probe_class_cached_only$/', '/^mc\|.*leak_probe:after_class$/', '/^stale\|memcached\|1\|options\|leak_probe_class_cached_only$/' ),
);

// Planted in the in-memory object cache, which a persistent cache's tear_down() flush clears.
const RUNTIME_CACHE_ONLY = array(
	'/^stale\|runtime\|1\|options\|alloptions\|blogname$/',
	'/^stale\|runtime\|1\|options\|leak_probe_not_autoloaded$/',
	'/^stale\|runtime\|1\|options\|notoptions\|leak_probe_missing$/',
	'/^stale\|runtime\|1\|site-options\|1:site_admins$/',
	'/^cache\|leak_probe\|(1:)?after_tear_down$/',
	'/^stale\|runtime\|1\|options\|leak_probe_cached_only$/',
	'/^cache\|leak_probe\|(1:)?after_class$/',
	'/^stale\|runtime\|1\|options\|leak_probe_class_cached_only$/',
	'/^stale\|runtime\|(?!1\|)\d+\|options\|alloptions\|blogname$/',
	'/^stale\|runtime\|(?!1\|)\d+\|options\|leak_probe_cached_only$/',
);

// Only a persistent cache has these: the drop-in's derived keys, the servers' keys, their stale checks.
const PERSISTENT_CACHE_ONLY = array(
	'/^cache\|.*leak_probe:after_tear_down$/',
	'/^mc\|.*leak_probe:after_tear_down$/',
	'/^stale\|local\|1\|options\|leak_probe_cached_only$/',
	'/^stale\|memcached\|1\|options\|leak_probe_cached_only$/',
	'/^cache\|.*leak_probe:after_class$/',
	'/^mc\|.*leak_probe:after_class$/',
	'/^stale\|memcached\|1\|options\|leak_probe_class_cached_only$/',
	'/^stale\|local\|(?!1\|)\d+\|options\|leak_probe_cached_only$/',
	'/^stale\|memcached\|(?!1\|)\d+\|options\|leak_probe_cached_only$/',
);

const MULTISITE_ONLY = array(
	'/^stale\|runtime\|1\|site-options\|1:site_admins$/',
	OTHER_SITE_ROW,
	'/^temptable\|\w+_\d+_options$/',
	'/^stale\|runtime\|(?!1\|)\d+\|options\|alloptions\|blogname$/',
	'/^stale\|runtime\|(?!1\|)\d+\|options\|leak_probe_cached_only$/',
	'/^stale\|local\|(?!1\|)\d+\|options\|leak_probe_cached_only$/',
	'/^stale\|memcached\|(?!1\|)\d+\|options\|leak_probe_cached_only$/',
);

// An isolated child's report holds only these, see OUTLIVES_PROCESS in the listener.
const CHILD_AREAS = '/^(db|schema|mc|file|stale\|memcached)\|/';

const CHILD_SUFFIX = ' (isolated child)';

// Report title => pattern no reported key may match: the class plants no cache leak, and its flushes drop the install's entries.
const UNEXPECTED = array(
	'class Tests_Leak_Probe'                             => '/^(cache|mc)\|/',
	// Gone with the connection the test replaced.
	'test Tests_Leak_Probe::test_reconnect_drops_temp_table' => '/^temptable\|\w+leak_probe_gone$/',
	// Its start is the previous test's end, reused: a wrong one reports other constants.
	'test Tests_Leak_Probe::test_leaks_constant'         => '/^const\|(?!LEAK_PROBE_CONSTANT$)/',
	// The previous probe's, which this test's set_up() flush drops.
	'test Tests_Leak_Probe::test_leaks_cache_after_tear_down' => '/^stale\|runtime\|1\|options\|(alloptions\|blogname|leak_probe_not_autoloaded)$/',
	// Dropped by a rules.php pattern.
	'test Tests_Leak_Probe::test_leaks_filtered_globals' => '/^global\|leak_probe_filtered_dropped$/',
);

// rules.php skips its file: no snapshot line names it, and no report blames its leak on another.
const SKIPPED_CLASS = 'Tests_Leak_Probe_Skipped';

const SKIPPED_LEAK = '/leak_probe_skipped/';

// diff.php exits with 2 when it reports a leak, as every probe run must.
const DIFF_LEAKS = 2;

// Report header and changed key lines, see report() in diff.php.
const HEADER = '/^== (.+) leaves(?:, unlike the install)? \(\d+ changed\)$/';

const KEY_LINE = '/^  (\S.*)$/';

const SCOPE_CLASS = 'class';

// The run's setup, from its first line.
function setup( $file ) {
	$handle = fopen( $file, 'r' );
	if ( false === $handle ) {
		fwrite( STDERR, "Cannot read $file\n" );
		exit( 1 );
	}

	$snap = json_decode( (string) fgets( $handle ), true );
	fclose( $handle );

	if ( ! isset( $snap['setup']['multisite'], $snap['setup']['php'], $snap['setup']['persistent_cache'] ) || ! array_key_exists( 'scope', $snap['setup'] ) ) {
		fwrite( STDERR, "No run setup in $file\n" );
		exit( 1 );
	}

	return $snap['setup'];
}

// Whether any snapshot line's id starts with the class name.
function snapshotted( $file, $class_name ) {
	$handle = fopen( $file, 'r' );
	if ( false === $handle ) {
		fwrite( STDERR, "Cannot read $file\n" );
		exit( 1 );
	}

	$needle = '"id":"' . $class_name;
	$found  = false;
	$line   = fgets( $handle );
	while ( false !== $line && ! $found ) {
		$found = false !== strpos( $line, $needle );
		$line  = fgets( $handle );
	}
	fclose( $handle );

	return $found;
}

if ( 2 !== $argc ) {
	fwrite( STDERR, "Usage: php check-probes.php run.jsonl\n" );
	exit( 1 );
}

exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/diff.php' ) . ' ' . escapeshellarg( $argv[1] ), $output, $code );
if ( DIFF_LEAKS !== $code ) {
	fwrite( STDERR, "diff.php exited with $code, not " . DIFF_LEAKS . ".\n" );
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

$setup    = setup( $argv[1] );
$expected = SCOPE_CLASS === $setup['scope'] ? CLASS_SCOPE : PER_TEST;

// The copy this PHP version does not have: reporting it would mean the listener reads the wrong one.
$other_static = $setup['php'] >= PHP_SHARED_STATICS ? CHILD_STATIC : PARENT_STATIC;
$unexpected   = UNEXPECTED + array( STATIC_TITLE => $other_static );

// Patterns this setup cannot report: the other cache's, multisite's on single site, the other PHP's.
$skip = array_merge(
	$setup['persistent_cache'] ? RUNTIME_CACHE_ONLY : PERSISTENT_CACHE_ONLY,
	$setup['multisite'] ? array() : MULTISITE_ONLY,
	array( $other_static )
);

$missed  = array();
$checked = 0;
$skipped = 0;
foreach ( $expected as $title => $patterns ) {
	foreach ( $patterns as $pattern ) {
		if ( in_array( $pattern, $skip, true ) ) {
			++$skipped;
			continue;
		}

		++$checked;
		if ( ! preg_grep( $pattern, $reported[ $title ] ?? array() ) ) {
			$missed[] = "$title: $pattern";
		}
	}
}

foreach ( $unexpected as $title => $pattern ) {
	foreach ( preg_grep( $pattern, $reported[ $title ] ?? array() ) as $key ) {
		$missed[] = "$title: unexpected $key";
	}
}

foreach ( $reported as $title => $keys ) {
	// No str_ends_with() before PHP 8.
	if ( substr( $title, -strlen( CHILD_SUFFIX ) ) !== CHILD_SUFFIX ) {
		continue;
	}

	foreach ( preg_grep( CHILD_AREAS, $keys, PREG_GREP_INVERT ) as $key ) {
		$missed[] = "$title: unexpected $key";
	}
}

foreach ( $reported as $title => $keys ) {
	if ( false !== strpos( $title, SKIPPED_CLASS ) ) {
		$missed[] = "$title: reported, but rules.php skips its file";
	}

	foreach ( preg_grep( SKIPPED_LEAK, $keys ) as $key ) {
		$missed[] = "$title: unexpected $key";
	}
}

if ( snapshotted( $argv[1], SKIPPED_CLASS ) ) {
	$missed[] = SKIPPED_CLASS . ': snapshotted, but rules.php skips its file';
}

if ( $missed ) {
	fwrite( STDERR, "Missed or unexpected leaks:\n  " . implode( "\n  ", $missed ) . "\n" );
	exit( 1 );
}

echo "$checked expected leaks reported, $skipped skipped for this setup.\n";
