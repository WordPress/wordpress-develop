<?php
/**
 * Bootstraps collaborative editing.
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Injects the real-time collaboration setting into a global variable.
 *
 * @since 7.0.0
 *
 * @access private
 */
function wp_collaboration_inject_setting() {
	if ( get_option( 'wp_enable_real_time_collaboration' ) ) {
		wp_add_inline_script(
			'wp-core-data',
			'window._wpCollaborationEnabled = true;',
			'after'
		);
	}
}

/**
 * Deletes sync updates older than 1 day from the wp_sync_updates table.
 *
 * Rows left behind by abandoned collaborative editing sessions are cleaned up
 * to prevent unbounded table growth.
 *
 * @since 7.0.0
 */
function wp_delete_old_sync_updates() {
	global $wpdb;

	/**
	 * Filters the lifetime, in seconds, of a sync update row.
	 *
	 * By default, the lifetime is 1 day. Once a row reaches that age, it will
	 * automatically be deleted by a cron job.
	 *
	 * @since 7.0.0
	 *
	 * @param int $expiration The expiration age of a sync update row, in seconds.
	 */
	$expiration = apply_filters( 'wp_sync_updates_expiration', DAY_IN_SECONDS );

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sync_updates} WHERE created_at < %s",
			gmdate( 'Y-m-d H:i:s', time() - $expiration )
		)
	);
}
