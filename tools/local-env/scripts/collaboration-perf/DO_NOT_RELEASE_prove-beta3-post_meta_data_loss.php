<?php
/**
 * Proves that post meta compaction loses sync data.
 *
 * Compaction triggers at 50 updates, keeping the newest 20% (10) and
 * discarding the oldest 80% (40).
 *
 * Table compaction does this:
 *   1. DELETE WHERE id < cutoff — removes only the 40 oldest rows.
 *
 * The 10 newest rows are never deleted, never absent. There is no step 2.
 *
 * Post meta compaction (beta 1–3) does this:
 *   1. delete_post_meta() — removes ALL 50 updates in one call.
 *   2. add_post_meta() — re-inserts the 10 newest updates one at a time.
 *
 * Between step 1 and step 2, every update is gone from the database.
 * Any read during this window returns nothing — even though 10 updates
 * should still exist.
 *
 * Usage:
 *   npm run env:cli -- eval-file tools/local-env/scripts/collaboration-perf/DO_NOT_RELEASE_prove-beta3-post_meta_data_loss.php
 *
 * @package WordPress
 */

global $wpdb;

$room    = 'postType/post:proof';
$total   = 50;
$discard = (int) ( $total * 0.8 ); // 80% — matches production compaction ratio.
$keep    = $total - $discard;       // 20% — the newest updates that should remain.

// =====================================================================
// Setup: 50 sync updates exist in both backends.
// =====================================================================

// Table backend.
$wpdb->query( "TRUNCATE TABLE {$wpdb->collaboration}" );
for ( $i = 0; $i < $total; $i++ ) {
	$s = new WP_Collaboration_Table_Storage();
	$s->add_update( $room, array( 'edit' => $i ) );
}

// Post meta backend needs a storage post.
if ( ! post_type_exists( 'wp_sync_storage' ) ) {
	register_post_type( 'wp_sync_storage', array( 'public' => false ) );
}
$post_id = wp_insert_post( array(
	'post_type'   => 'wp_sync_storage',
	'post_status' => 'publish',
	'post_name'   => md5( $room ),
) );
for ( $i = 0; $i < $total; $i++ ) {
	add_post_meta( $post_id, 'wp_sync_update', array(
		'timestamp' => 1000 + $i,
		'value'     => array( 'edit' => $i ),
	) );
}

// =====================================================================
// Table compaction — the correct behavior.
//
// This is the exact code path from:
// WP_Collaboration_Table_Storage::remove_updates_before_cursor()
//
//   DELETE FROM wp_collaboration WHERE room = %s AND id < %d
//
// One query. Only the 40 oldest rows are removed. The 10 newest rows
// are never deleted, never absent, always readable.
// There is no step 2.
// =====================================================================

// Cursor that keeps the $keep newest rows.
$cursor = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT id FROM {$wpdb->collaboration} WHERE room = %s ORDER BY id DESC LIMIT 1 OFFSET %d",
	$room,
	$keep - 1
) );
$table = new WP_Collaboration_Table_Storage();
$table->remove_updates_before_cursor( $room, $cursor );

// *** Read immediately after compaction. ***
$reader        = new WP_Collaboration_Table_Storage();
$table_visible = $reader->get_updates_after_cursor( $room, 0 );
$table_count   = count( $table_visible );

// =====================================================================
// Post Meta compaction — the bug.
//
// This is the exact code path from:
// WP_Sync_Post_Meta_Storage::remove_updates_before_cursor()
//
//   $all_updates = $this->get_all_updates( $room );
//   delete_post_meta( $post_id, self::SYNC_UPDATE_META_KEY );  ← ALL rows gone
//   foreach ( $all_updates as $envelope ) {
//       if ( $envelope['timestamp'] >= $cursor ) {
//           add_post_meta( ... );                               ← re-inserted one by one
//       }
//   }
//
// Between the delete and the first re-insert, the sync history is empty.
// =====================================================================

// Step 1 of 2: delete ALL updates (this is the production code path).
delete_post_meta( $post_id, 'wp_sync_update' );

// *** Read between step 1 and step 2. ***
wp_cache_delete( $post_id, 'post_meta' );
$meta_visible = get_post_meta( $post_id, 'wp_sync_update', false );
$meta_count   = count( array_filter( $meta_visible, 'is_array' ) );

// Step 2 of 2 (re-insert kept updates) would happen here, but the gap already occurred.

// =====================================================================
// Compaction at scale.
//
// Post meta: the data loss window is the time between
// delete_post_meta() and the last add_post_meta() — more updates
// to keep = more add_post_meta() calls = wider window.
//
// Table: rows are never absent — verify by reading immediately
// after compaction at each scale.
// =====================================================================

$gap_scales  = array( 50, 200, 500, 1000 );
$gap_results = array();

WP_CLI::log( '' );
WP_CLI::log( 'Measuring data loss window at scale...' );

foreach ( $gap_scales as $gap_total ) {
	$gap_discard = (int) ( $gap_total * 0.8 );
	$gap_keep    = $gap_total - $gap_discard;
	$gap_room    = "postType/post:gap-{$gap_total}";

	WP_CLI::log( "  Scale: {$gap_total} updates (keep {$gap_keep})" );

	// --- Post meta: measure the data loss window. ---

	$gap_post_id = wp_insert_post( array(
		'post_type'   => 'wp_sync_storage',
		'post_status' => 'publish',
		'post_name'   => md5( $gap_room ),
	) );
	for ( $i = 0; $i < $gap_total; $i++ ) {
		add_post_meta( $gap_post_id, 'wp_sync_update', array(
			'timestamp' => 1000 + $i,
			'value'     => array( 'edit' => $i ),
		) );
	}

	$gap_cursor  = 1000 + $gap_discard;
	$all_updates = get_post_meta( $gap_post_id, 'wp_sync_update', false );

	$gap_start = microtime( true );
	delete_post_meta( $gap_post_id, 'wp_sync_update' );
	foreach ( $all_updates as $envelope ) {
		if ( is_array( $envelope ) && $envelope['timestamp'] >= $gap_cursor ) {
			add_post_meta( $gap_post_id, 'wp_sync_update', $envelope );
		}
	}
	$meta_gap_ms = ( microtime( true ) - $gap_start ) * 1000;

	wp_delete_post( $gap_post_id, true );

	// --- Table: verify rows are never absent. ---

	$wpdb->query( "TRUNCATE TABLE {$wpdb->collaboration}" );
	$storage = new WP_Collaboration_Table_Storage();
	for ( $i = 0; $i < $gap_total; $i++ ) {
		$storage->add_update( $gap_room, array( 'edit' => $i ) );
	}

	$table_cursor = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$wpdb->collaboration} WHERE room = %s ORDER BY id DESC LIMIT 1 OFFSET %d",
		$gap_room,
		$gap_keep - 1
	) );

	$storage->remove_updates_before_cursor( $gap_room, $table_cursor );

	$reader              = new WP_Collaboration_Table_Storage();
	$table_after         = $reader->get_updates_after_cursor( $gap_room, 0 );
	$table_visible_count = count( $table_after );

	$gap_results[] = array(
		'total'         => $gap_total,
		'keep'          => $gap_keep,
		'meta_gap_ms'   => $meta_gap_ms,
		'table_visible' => $table_visible_count,
	);
}

// =====================================================================
// Results.
// =====================================================================

$separator = str_repeat( '─', 60 );

WP_CLI::log( '' );
WP_CLI::log( WP_CLI::colorize( '%_Compaction Data Integrity Test%n' ) );
WP_CLI::log( 'Run: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC' );
WP_CLI::log( '' );
WP_CLI::log( "Compaction triggers at {$total} updates. Keeps {$keep} newest, discards {$discard} oldest." );
WP_CLI::log( WP_CLI::colorize( "%_Expected: {$keep} newest updates remain visible after compaction.%n" ) );

WP_CLI::log( '' );
WP_CLI::log( $separator );
WP_CLI::log( WP_CLI::colorize( '%_Table (this PR)%n' ) );
WP_CLI::log( $separator );
WP_CLI::log( "  DELETE WHERE id < cutoff — only the {$discard} oldest removed." );
WP_CLI::log( '' );
WP_CLI::log( '  Read immediately after compaction:' );
$table_verdict = $table_count >= $keep
	? WP_CLI::colorize( "  %G→ {$table_count} of {$keep} visible — OK%n" )
	: WP_CLI::colorize( "  %R→ {$table_count} of {$keep} visible — UNEXPECTED%n" );
WP_CLI::log( $table_verdict );

WP_CLI::log( '' );
WP_CLI::log( $separator );
WP_CLI::log( WP_CLI::colorize( '%_Post Meta (beta 1–3)%n' ) );
WP_CLI::log( $separator );
WP_CLI::log( "  delete_post_meta() removes all {$total}, then add_post_meta() re-inserts {$keep}." );
WP_CLI::log( '' );
WP_CLI::log( '  Read between delete and re-insert:' );
$meta_verdict = 0 === $meta_count
	? WP_CLI::colorize( "  %R→ {$meta_count} of {$keep} visible — DATA LOSS%n" )
	: WP_CLI::colorize( "  %G→ {$meta_count} of {$keep} visible — OK%n" );
WP_CLI::log( $meta_verdict );

WP_CLI::log( '' );
WP_CLI::log( $separator );
WP_CLI::log( WP_CLI::colorize( '%_Compaction at scale%n' ) );
WP_CLI::log( $separator );
WP_CLI::log( '' );

$scale_fields = array( 'Updates (keep 20%)', 'Post meta data loss window', 'Table rows visible' );
$scale_items  = array();
foreach ( $gap_results as $gap ) {
	$table_label   = $gap['table_visible'] >= $gap['keep']
		? "{$gap['table_visible']} of {$gap['keep']} — OK"
		: "{$gap['table_visible']} of {$gap['keep']} — UNEXPECTED";
	$scale_items[] = array(
		'Updates (keep 20%)'         => sprintf( '%d (keep %d)', $gap['total'], $gap['keep'] ),
		'Post meta data loss window' => sprintf( '%.1f ms', $gap['meta_gap_ms'] ),
		'Table rows visible'         => $table_label,
	);
}
WP_CLI\Utils\format_items( 'table', $scale_items, $scale_fields );

// Cleanup.
wp_delete_post( $post_id, true );
$wpdb->query( "TRUNCATE TABLE {$wpdb->collaboration}" );
