<?php
/**
 * Bootstraps collaborative editing.
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Checks whether real-time collaboration is enabled.
 *
 * The feature requires both the site option and the database schema
 * introduced in db_version 61841.
 *
 * @since 7.0.0
 *
 * @return bool True if collaboration is enabled, false otherwise.
 */
function wp_is_collaboration_enabled() {
	return get_option( 'wp_enable_real_time_collaboration' )
		&& get_option( 'db_version' ) >= 61841;
}

/**
 * Injects the real-time collaboration setting into a global variable.
 *
 * @since 7.0.0
 *
 * @access private
 *
 * @global string $pagenow The filename of the current screen.
 */
function wp_collaboration_inject_setting() {
	global $pagenow;

	if ( ! wp_is_collaboration_enabled() ) {
		return;
	}

	// Disable real-time collaboration on the site editor.
	$enabled = true;
	if ( 'site-editor.php' === $pagenow ) {
		$enabled = false;
	}

	wp_add_inline_script(
		'wp-core-data',
		'window._wpCollaborationEnabled = ' . wp_json_encode( $enabled ) . ';',
		'after'
	);
}

/**
 * Deletes stale collaboration data from the collaboration table.
 *
 * Removes non-awareness rows older than 7 days and awareness rows older
 * than 60 seconds. Rows left behind by abandoned collaborative editing
 * sessions are cleaned up to prevent unbounded table growth.
 *
 * @since 7.0.0
 */
function wp_delete_old_collaboration_data() {
	global $wpdb;

	if ( ! wp_is_collaboration_enabled() ) {
		/*
		 * Collaboration was enabled in the past but has since been disabled.
		 * Clean up any remaining stale data and unschedule the cron job
		 * so this callback does not continue to run.
		 */
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->collaboration} WHERE type != 'awareness' AND date_gmt < %s",
				gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS )
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->collaboration} WHERE type = 'awareness' AND date_gmt < %s",
				gmdate( 'Y-m-d H:i:s', time() - 60 )
			)
		);

		wp_clear_scheduled_hook( 'wp_delete_old_collaboration_data' );
		return;
	}

	/*
	 * Clean up sync rows older than 7 days.
	 *
	 * The type != 'awareness' exclusion keeps awareness rows untouched —
	 * they are cleaned up separately below. Future persistent types
	 * (e.g. persisted_crdt_doc) may also need exclusion here.
	 */
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->collaboration} WHERE type != 'awareness' AND date_gmt < %s",
			gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS )
		)
	);

	// Clean up awareness rows older than 60 seconds.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->collaboration} WHERE type = 'awareness' AND date_gmt < %s",
			gmdate( 'Y-m-d H:i:s', time() - 60 )
		)
	);
}
