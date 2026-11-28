<?php
/**
 * Checks diff.php reports each leak the leak-probe suite plants, see tests/phpunit/README.md.
 *
 * php check-probes.php run.jsonl
 *
 * Exits with 1 on a missed leak, a key a probe must not report, or a failed diff.php.
 */

// Not site 1's.
const OTHER_SITE_ROW = '#^db/\w+blogs/(?!1$)\d+$#';

// Before PHP 8.1, a subclass has its own copy of an inherited method's static variables.
const PARENT_STATIC = '#^fstatic/Leak_Probe_Counter::count\(\)/\$calls$#';

const CHILD_STATIC = '#^fstatic/Leak_Probe_Counter_Child::count\(\)/\$calls$#';

const STATIC_TITLE = 'test Tests_Leak_Probe::test_leaks_function_static_var';

const PHP_SHARED_STATICS = 80100;

// Report title => patterns that must each match a reported key.
const PER_TEST = array(
	'test Tests_Leak_Probe::test_leaks_option_cache'       => array( '#^stale/runtime/1/options/alloptions/blogname$#', '#^stale/runtime/1/options/leak_probe_not_autoloaded$#' ),
	'test Tests_Leak_Probe::test_leaks_cache_after_tear_down' => array( '#^cache/leak_probe/(1:)?after_tear_down$#', '#^stale/runtime/1/options/leak_probe_cached_only$#', '#^cache/.*leak_probe:after_tear_down$#', '#^mc/.*leak_probe:after_tear_down$#', '#^stale/local/1/options/leak_probe_cached_only$#', '#^stale/memcached/1/options/leak_probe_cached_only$#' ),
	'test Tests_Leak_Probe::test_leaks_composite_key_row'  => array( '#^db/\w+term_relationships/999999,1$#', '#^dbcount/\w+term_relationships$#' ),
	'test Tests_Leak_Probe::test_leaks_notoptions_cache'   => array( '#^db/\w+options/leak_probe_missing$#', '#^dbcount/\w+options$#', '#^stale/runtime/1/options/notoptions/leak_probe_missing$#' ),
	'test Tests_Leak_Probe::test_leaks_network_option_cache' => array( '#^stale/runtime/1/site-options/1:site_admins$#' ),
	'test Tests_Leak_Probe::test_leaks_other_site'         => array( OTHER_SITE_ROW, '#^temptable/\w+_\d+_options$#', '#^stale/runtime/(?!1/)\d+/options/alloptions/blogname$#', '#^stale/runtime/(?!1/)\d+/options/leak_probe_cached_only$#', '#^stale/local/(?!1/)\d+/options/leak_probe_cached_only$#', '#^stale/memcached/(?!1/)\d+/options/leak_probe_cached_only$#' ),
	'test Tests_Leak_Probe::test_leaks_cache_global_group' => array( '#^global/wp_object_cache/global_groups$#' ),
	STATIC_TITLE                                           => array( '#^fstatic/leak_probe_counter\(\)/\$calls$#', '#^fstatic/Tests_Leak_Probe::method_counter\(\)/\$calls$#', PARENT_STATIC, CHILD_STATIC ),
	'test Tests_Leak_Probe::test_leaks_static_property'    => array( '#^static/Tests_Leak_Probe::\$leaked$#' ),
	'test Tests_Leak_Probe::test_leaks_constant'           => array( '#^const/LEAK_PROBE_CONSTANT$#' ),
	'test Tests_Leak_Probe::test_leaks_auto_increment'     => array( '#^autoinc/\w+posts$#' ),
	'test Tests_Leak_Probe::test_leaks_deep_global_change' => array( '#^global/leak_probe_deep/l1$#' ),
	'test Tests_Leak_Probe::test_leaks_big_array_key'      => array( '#^global/leak_probe_big/2500$#' ),
	'test Tests_Leak_Probe::test_leaks_removed_global'     => array( '#^global/leak_probe_gone$#' ),
	'test Tests_Leak_Probe::test_leaks_type_only_change'   => array( '#^global/leak_probe_int$#' ),
	'test Tests_Leak_Probe::test_leaks_filtered_globals'   => array( '#^global/leak_probe_filtered_kept$#' ),
	'test Tests_Leak_Probe::test_leaks_closure_swap'       => array( '#^global/leak_probe_cb$#' ),
	'test Tests_Leak_Probe::test_leaks_wpdb_and_server'    => array( '#^global/_SERVER/LEAK_PROBE$#', '#^global/wpdb/show_errors$#' ),
	'test Tests_Leak_Probe::test_leaks_process_state'      => array( '#^env/LEAK_PROBE$#', '#^ini/precision$#', '#^ini/error_reporting$#', '#^proc/autoloaders$#', '#^proc/error_handler$#', '#^proc/error_reporting$#', '#^proc/exception_handler$#', '#^proc/libxml_errors$#', '#^proc/locale$#', '#^proc/mb_encoding$#', '#^proc/timezone$#', '#^proc/umask$#' ),
	'test Tests_Leak_Probe::test_leaks_files'              => array( '#^file/.*/wp-content/leak-probe\.txt$#', '#^file/(?!.*/wp-content/).*/leak-probe\.txt$#', '#^file/.*/wp-content/leak-probe-link$#', '#^file/(?!.*/wp-content/).*/leak-probe-link$#', '#^file/.*/wp-content/leak-probe-dir$#', '#^file/(?!.*/wp-content/).*/leak-probe-dir$#' ),
	'test Tests_Leak_Probe::test_leaks_schema'             => array( '#^schema/\w+options$#' ),
	'test Tests_Leak_Probe::test_leaks_temp_table'         => array( '#^temptable/\w+leak_probe_tmp$#' ),
	'test Tests_Leak_Probe::test_leaks_permanent_table'    => array( '#^schema/\w+leak_probe_permanent$#', '#^dbcount/\w+leak_probe_permanent$#' ),
	'test Tests_Leak_Probe::test_leaks_in_child'           => array( '#^db/\w+links/\d+$#' ),
	'class Tests_Leak_Probe_Class_Fixture'                 => array( '#^proc/cwd$#', '#^proc/ob_level$#', '#^cache/leak_probe/(1:)?after_class$#', '#^cache/.*leak_probe:after_class$#', '#^mc/.*leak_probe:after_class$#' ),
);

const CLASS_SCOPE = array(
	// The global: rules.php adds it to the class scope's areas for the file.
	'class Tests_Leak_Probe'               => array( '#^db/\w+links/\d+$#', '#^schema/\w+options$#', OTHER_SITE_ROW, '#^global/leak_probe_filtered_kept$#' ),
	'class Tests_Leak_Probe_Class_Fixture' => array( '#^stale/runtime/1/options/leak_probe_class_cached_only$#', '#^mc/.*leak_probe:after_class$#', '#^stale/memcached/1/options/leak_probe_class_cached_only$#' ),
);

// Planted in the in-memory object cache, which a persistent cache's tear_down() flush clears.
const RUNTIME_CACHE_ONLY = array(
	'#^stale/runtime/1/options/alloptions/blogname$#',
	'#^stale/runtime/1/options/leak_probe_not_autoloaded$#',
	'#^stale/runtime/1/options/notoptions/leak_probe_missing$#',
	'#^stale/runtime/1/site-options/1:site_admins$#',
	'#^cache/leak_probe/(1:)?after_tear_down$#',
	'#^stale/runtime/1/options/leak_probe_cached_only$#',
	'#^cache/leak_probe/(1:)?after_class$#',
	'#^stale/runtime/1/options/leak_probe_class_cached_only$#',
	'#^stale/runtime/(?!1/)\d+/options/alloptions/blogname$#',
	'#^stale/runtime/(?!1/)\d+/options/leak_probe_cached_only$#',
);

// Only a persistent cache has these: the drop-in's derived keys, the servers' keys, their stale checks.
const PERSISTENT_CACHE_ONLY = array(
	'#^cache/.*leak_probe:after_tear_down$#',
	'#^mc/.*leak_probe:after_tear_down$#',
	'#^stale/local/1/options/leak_probe_cached_only$#',
	'#^stale/memcached/1/options/leak_probe_cached_only$#',
	'#^cache/.*leak_probe:after_class$#',
	'#^mc/.*leak_probe:after_class$#',
	'#^stale/memcached/1/options/leak_probe_class_cached_only$#',
	'#^stale/local/(?!1/)\d+/options/leak_probe_cached_only$#',
	'#^stale/memcached/(?!1/)\d+/options/leak_probe_cached_only$#',
);

const MULTISITE_ONLY = array(
	'#^stale/runtime/1/site-options/1:site_admins$#',
	OTHER_SITE_ROW,
	'#^temptable/\w+_\d+_options$#',
	'#^stale/runtime/(?!1/)\d+/options/alloptions/blogname$#',
	'#^stale/runtime/(?!1/)\d+/options/leak_probe_cached_only$#',
	'#^stale/local/(?!1/)\d+/options/leak_probe_cached_only$#',
	'#^stale/memcached/(?!1/)\d+/options/leak_probe_cached_only$#',
);

// Report title => pattern no reported key may match: the class plants no cache leak, and its flushes drop the install's entries.
const UNEXPECTED = array(
	'class Tests_Leak_Probe'                             => '#^(cache|mc)/#',
	// Gone with the connection the test replaced.
	'test Tests_Leak_Probe::test_reconnect_drops_temp_table' => '#^temptable/\w+leak_probe_gone$#',
	// Its start is the previous test's end, reused: a wrong one reports more keys.
	'test Tests_Leak_Probe::test_leaks_constant'         => '#^(?!const/LEAK_PROBE_CONSTANT$)#',
	// Its start follows its class's fixture, not the previous class's last test_end.
	'test Tests_Leak_Probe_Class_Fixture::test_fixture_user_exists' => '#^db/\w+users/#',
	// The previous probe's, which this test's set_up() flush drops.
	'test Tests_Leak_Probe::test_leaks_cache_after_tear_down' => '#^stale/runtime/1/options/(alloptions/blogname|leak_probe_not_autoloaded)$#',
	// Dies with its child process.
	'test Tests_Leak_Probe::test_leaks_in_child'         => '#^const/LEAK_PROBE_CHILD_CONSTANT$#',
	// Dropped by a rules.php pattern.
	'test Tests_Leak_Probe::test_leaks_filtered_globals' => '#^global/leak_probe_filtered_dropped$#',
);

// rules.php skips its file: no snapshot line names it.
const SKIPPED_CLASS = 'Tests_Leak_Probe_Skipped';

// diff.php exits with 2 when it reports a leak, as every probe run must.
const DIFF_LEAKS = 2;

// Comparison mode refuses the run's first class: nothing ran before it.
const FIRST_CLASS = 'Tests_Leak_Probe';

const DIFF_REFUSED = 1;

const DIFF_INCOMPLETE = 1;

// Comparison mode: a key both first classes differ on is run noise, one only the class differs on is reported.
const NOISE_KEY = 'global/leak_probe_noise';

const INHERITED_KEY = 'global/leak_probe_inherited';

// Report header and changed key lines, see report() in diff.php.
const HEADER = '#^== (.+) leaves(?:, unlike the install)? \(\d+ changed\)$#';

const KEY_LINE = '#^  (\S.*)$#';

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

// diff.php's exit code and output lines, STDERR included.
function diff( array $files ) {
	$args = implode( ' ', array_map( 'escapeshellarg', $files ) );
	exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/diff.php' ) . " $args 2>&1", $output, $code );

	return array( $code, $output );
}

function temp_file( $content ) {
	$file = tempnam( sys_get_temp_dir(), 'leak-probe-' );
	if ( false === $file || false === file_put_contents( $file, $content ) ) {
		fwrite( STDERR, "Cannot write a temporary file\n" );
		exit( 1 );
	}

	return $file;
}

function class_start( $id, $noise, $inherited ) {
	$state = array(
		NOISE_KEY     => array( $noise, $noise ),
		INHERITED_KEY => array( $inherited, $inherited ),
	);

	return json_encode(
		array(
			'point' => 'class_start',
			'id'    => $id,
			'state' => $state,
		)
	) . "\n";
}

// The run without its last line, run_end.
function cut( $file ) {
	$lines = file( $file );
	if ( false === $lines ) {
		fwrite( STDERR, "Cannot read $file\n" );
		exit( 1 );
	}

	return temp_file( implode( '', array_slice( $lines, 0, -1 ) ) );
}

if ( 2 !== $argc ) {
	fwrite( STDERR, "Usage: php check-probes.php run.jsonl\n" );
	exit( 1 );
}

list( $code, $output ) = diff( array( $argv[1] ) );
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
	if ( false !== strpos( $title, SKIPPED_CLASS ) ) {
		$missed[] = "$title: reported, but rules.php skips its file";
	}
}

if ( snapshotted( $argv[1], SKIPPED_CLASS ) ) {
	$missed[] = SKIPPED_CLASS . ': snapshotted, but rules.php skips its file';
}

// The class scope writes no class_start.
if ( SCOPE_CLASS !== $setup['scope'] ) {
	list( $code, $refusal ) = diff( array( $argv[1], $argv[1], FIRST_CLASS ) );
	if ( DIFF_REFUSED !== $code || ! preg_grep( '/no snapshotted class ran before it/', $refusal ) ) {
		$missed[] = FIRST_CLASS . ": comparison mode exited with $code, not refused as the first class";
	}
}

$alone = temp_file( class_start( 'First', 'a', 'same' ) . class_start( 'Target', 'a', 'same' ) );
$seq   = temp_file( class_start( 'First', 'b', 'same' ) . class_start( 'Target', 'b', 'inherited' ) );

list( $code, $compared ) = diff( array( $alone, $seq, 'Target' ) );
if ( DIFF_LEAKS !== $code || ! in_array( '  ' . INHERITED_KEY, $compared, true ) || in_array( '  ' . NOISE_KEY, $compared, true ) ) {
	$missed[] = "comparison mode: exited with $code, not reporting " . INHERITED_KEY . ' only';
}

$cut = cut( $argv[1] );

list( $code, $incomplete ) = diff( array( $cut ) );
if ( DIFF_INCOMPLETE !== $code || ! preg_grep( '#^Incomplete run#', $incomplete ) ) {
	$missed[] = "run cut before run_end: diff.php exited with $code, not reported incomplete";
}

unlink( $alone );
unlink( $seq );
unlink( $cut );

if ( $missed ) {
	fwrite( STDERR, "Missed or unexpected leaks:\n  " . implode( "\n  ", $missed ) . "\n" );
	exit( 1 );
}

echo "$checked expected leaks reported, $skipped skipped for this setup.\n";
