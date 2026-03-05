<?php
/**
 * Performance benchmark for WP_Sync_Table_Storage at scale.
 *
 * Measures idle poll, catch-up poll, and compaction at 100, 1,000, 10,000,
 * and 100,000 rows to verify that queries hold up under load.
 *
 * Usage:
 *   npm run test:performance:sync
 *   npm run test:performance:sync -- --format=json
 *
 * @package WordPress
 */

global $wpdb;

require_once __DIR__ . '/utils.php';

// ============================================================
// Configuration
// ============================================================

$scales                  = array( 100, 1000, 10000, 100000 );
$rooms_per_scale         = 10;
$measured_iterations     = 50;
$warmup_iterations       = 5;
$compaction_iterations   = 10;
$compaction_delete_ratio = 0.8;
$target_room             = 'postType/post:1';

// Parse --format flag from WP-CLI args.
$format = 'table';
if ( ! empty( $args ) ) {
	foreach ( $args as $arg ) {
		if ( 0 === strpos( $arg, '--format=' ) ) {
			$format = substr( $arg, 9 );
		}
	}
}

$config = array(
	'measured_iterations'     => $measured_iterations,
	'warmup_iterations'       => $warmup_iterations,
	'compaction_iterations'   => $compaction_iterations,
	'compaction_delete_ratio' => $compaction_delete_ratio,
	'rooms'                   => $rooms_per_scale,
);

// ============================================================
// Preflight check
// ============================================================

$table_name = $wpdb->prefix . 'sync_updates';
$has_table  = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

if ( ! $has_table ) {
	WP_CLI::error( "{$table_name} table not found. This script must run on the feature branch." );
}

// ============================================================
// Benchmark runner
// ============================================================

$results = array();

WP_CLI::log( '' );
WP_CLI::log( WP_CLI::colorize( '%_Sync Storage Performance Benchmark%n' ) );
WP_CLI::log( "Backend:      WP_Sync_Table_Storage" );
WP_CLI::log( "Iterations:   {$measured_iterations} measured + {$warmup_iterations} warm-up" );
WP_CLI::log( "Compaction:   {$compaction_iterations} measured (re-seed each)" );
WP_CLI::log( '' );

foreach ( $scales as $scale ) {
	$per_room = (int) ceil( $scale / $rooms_per_scale );
	$label    = number_format( $scale );
	WP_CLI::log( "Scale: {$label} total rows ({$per_room} per room)" );

	WP_CLI::log( '  Seeding table...' );
	sync_perf_seed_table( $scale, $rooms_per_scale );

	$primer            = new WP_Sync_Table_Storage();
	$primer->get_updates_after_cursor( $target_room, 0 );
	$table_idle_cursor = $primer->get_cursor( $target_room );

	// Idle poll.
	WP_CLI::log( '  Idle poll...' );
	$results['idle_poll'][ $scale ] = sync_perf_stats(
		function () use ( $target_room, $table_idle_cursor ) {
			$s = new WP_Sync_Table_Storage();
			$s->get_updates_after_cursor( $target_room, $table_idle_cursor );
		},
		$measured_iterations,
		$warmup_iterations
	);

	// Catch-up poll.
	WP_CLI::log( '  Catch-up poll...' );
	$results['catchup_poll'][ $scale ] = sync_perf_stats(
		function () use ( $target_room ) {
			$s = new WP_Sync_Table_Storage();
			$s->get_updates_after_cursor( $target_room, 0 );
		},
		$measured_iterations,
		$warmup_iterations
	);

	// Compaction.
	WP_CLI::log( '  Compaction...' );
	$compaction_times = array();

	for ( $ci = 0; $ci < $compaction_iterations; $ci++ ) {
		sync_perf_seed_table( $scale, $rooms_per_scale );

		$compaction_cursor_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->sync_updates} WHERE room = %s ORDER BY id ASC LIMIT 1 OFFSET %d",
			$target_room,
			max( 0, (int) floor( $per_room * $compaction_delete_ratio ) )
		) );

		$s     = new WP_Sync_Table_Storage();
		$start = microtime( true );
		$s->remove_updates_before_cursor( $target_room, $compaction_cursor_id );
		$compaction_times[] = ( microtime( true ) - $start ) * 1000;
	}

	$results['compaction'][ $scale ] = sync_perf_compute_stats( $compaction_times );
}

// ============================================================
// EXPLAIN analysis at largest scale
// ============================================================

WP_CLI::log( 'Collecting EXPLAIN analysis...' );
$explain_data = sync_perf_collect_explains( $target_room, end( $scales ), $rooms_per_scale );

// ============================================================
// Cleanup
// ============================================================

$wpdb->query( "TRUNCATE TABLE {$wpdb->sync_updates}" );

// ============================================================
// Output
// ============================================================

sync_perf_print_output( $results, $explain_data, $config, $scales, $format );
