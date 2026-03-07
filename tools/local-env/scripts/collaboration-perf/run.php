<?php
/**
 * Performance benchmark for WP_Collaboration_Table_Storage at scale.
 *
 * Measures idle poll, catch-up poll, and compaction at 100, 1,000, and
 * 10,000 rows to verify that queries hold up under load.
 *
 * Usage:
 *   npm run test:performance:collaboration
 *
 * @package WordPress
 */

global $wpdb;

require_once __DIR__ . '/utils.php';

// ============================================================
// Configuration
// ============================================================

$scales                  = array( 100, 1000, 10000 );
$rooms_per_scale         = 10;
$measured_iterations     = 50;
$warmup_iterations       = 5;
$compaction_iterations   = 10;
$compaction_delete_ratio = 0.8;
$target_room             = 'postType/post:1';

$config = array(
	'measured_iterations'     => $measured_iterations,
	'warmup_iterations'       => $warmup_iterations,
	'compaction_iterations'   => $compaction_iterations,
	'rooms'                   => $rooms_per_scale,
);

// ============================================================
// Preflight check
// ============================================================

$table_name = $wpdb->prefix . 'collaboration';
$has_table  = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

if ( ! $has_table ) {
	WP_CLI::error( "{$table_name} table not found. This script must run on the feature branch." );
}

// ============================================================
// Benchmark runner
// ============================================================

$results = array();

WP_CLI::log( '' );
WP_CLI::log( WP_CLI::colorize( '%_Collaboration Storage Benchmark%n' ) );
WP_CLI::log( 'Measures database speed for real-time collaborative editing.' );
WP_CLI::log( "Each row = one edit stored for a post being co-edited." );
WP_CLI::log( "{$measured_iterations} iterations ({$warmup_iterations} warm-up), {$compaction_iterations} compaction (re-seeded)" );
WP_CLI::log( '' );

$total_steps = count( $scales ) * 4; // seed + idle + catch-up + compaction per scale.
$progress    = \WP_CLI\Utils\make_progress_bar( 'Benchmarking', $total_steps );

foreach ( $scales as $scale ) {
	$per_room = (int) ceil( $scale / $rooms_per_scale );

	collaboration_perf_seed_table( $scale, $rooms_per_scale );
	$progress->tick();

	$primer            = new WP_Collaboration_Table_Storage();
	$primer->get_updates_after_cursor( $target_room, 0 );
	$table_idle_cursor = $primer->get_cursor( $target_room );

	// Idle poll.
	$results['idle_poll'][ $scale ] = collaboration_perf_stats(
		function () use ( $target_room, $table_idle_cursor ) {
			$s = new WP_Collaboration_Table_Storage();
			$s->get_updates_after_cursor( $target_room, $table_idle_cursor );
		},
		$measured_iterations,
		$warmup_iterations
	);
	$progress->tick();

	// Catch-up poll.
	$results['catchup_poll'][ $scale ] = collaboration_perf_stats(
		function () use ( $target_room ) {
			$s = new WP_Collaboration_Table_Storage();
			$s->get_updates_after_cursor( $target_room, 0 );
		},
		$measured_iterations,
		$warmup_iterations
	);
	$progress->tick();

	// Compaction.
	$compaction_times = array();

	for ( $ci = 0; $ci < $compaction_iterations; $ci++ ) {
		collaboration_perf_seed_table( $scale, $rooms_per_scale );

		// Prime the buffer pool after re-seed so the DELETE measures
		// query speed, not cold-cache warming.
		$primer = new WP_Collaboration_Table_Storage();
		$primer->get_updates_after_cursor( $target_room, 0 );

		$compaction_cursor_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->collaboration} WHERE room = %s ORDER BY id ASC LIMIT 1 OFFSET %d",
			$target_room,
			max( 0, (int) floor( $per_room * $compaction_delete_ratio ) )
		) );

		$s     = new WP_Collaboration_Table_Storage();
		$start = microtime( true );
		$s->remove_updates_before_cursor( $target_room, $compaction_cursor_id );
		$compaction_times[] = ( microtime( true ) - $start ) * 1000;
	}

	$results['compaction'][ $scale ] = collaboration_perf_compute_stats( $compaction_times );
	$progress->tick();
}

$progress->finish();

// ============================================================
// EXPLAIN analysis at largest scale
// ============================================================

$explain_data = collaboration_perf_collect_explains( $target_room, end( $scales ), $rooms_per_scale );

// ============================================================
// Cleanup
// ============================================================

$wpdb->query( "TRUNCATE TABLE {$wpdb->collaboration}" );

// ============================================================
// Output
// ============================================================

collaboration_perf_print_output( $results, $explain_data, $config, $scales );
