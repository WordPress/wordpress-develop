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
 * introduced in db_version 61699.
 *
 * @since 7.0.0
 *
 * @return bool True if collaboration is enabled, false otherwise.
 */
function wp_is_collaboration_enabled() {
	return get_option( 'wp_enable_real_time_collaboration' )
		&& get_option( 'db_version' ) >= 61699;
}

/**
 * Injects the real-time collaboration setting into a global variable.
 *
 * @since 7.0.0
 *
 * @access private
 */
function wp_collaboration_inject_setting() {
	if ( wp_is_collaboration_enabled() ) {
		wp_add_inline_script(
			'wp-core-data',
			'window._wpCollaborationEnabled = true;',
			'after'
		);
	}
}

/**
 * Deletes stale collaboration data from the collaboration table.
 *
 * Removes sync-update rows older than 7 days and awareness rows older than
 * 60 seconds. Rows left behind by abandoned collaborative editing sessions
 * are cleaned up to prevent unbounded table growth.
 *
 * @since 7.0.0
 */
function wp_delete_old_collaboration_data() {
	if ( ! wp_is_collaboration_enabled() ) {
		return;
	}

	global $wpdb;

	// Clean up sync update rows older than 7 days.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->collaboration} WHERE created_at < %s",
			gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS )
		)
	);

	// Clean up awareness rows older than 60 seconds (2× the 30-second awareness timeout as a buffer).
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->awareness} WHERE created_at < %s",
			gmdate( 'Y-m-d H:i:s', time() - 60 )
		)
	);
}
