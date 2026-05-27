<?php
/**
 * Bootstraps collaborative editing.
 *
 * @package WordPress
 * @since 7.1.0
 */

/**
 * Determines whether real-time collaboration is enabled.
 *
 * If the WP_ALLOW_COLLABORATION constant is false,
 * collaboration is always disabled regardless of the database option.
 * Otherwise, the feature requires both the 'wp_collaboration_enabled'
 * option and the database schema introduced in db_version 61841.
 *
 * @since 7.1.0
 *
 * @return bool Whether real-time collaboration is enabled.
 */
function wp_is_collaboration_enabled() {
	return (
		wp_is_collaboration_allowed() &&
		get_option( 'wp_collaboration_enabled' ) &&
		get_option( 'db_version' ) >= 61843
	);
}

/**
 * Determines whether real-time collaboration is allowed.
 *
 * If the WP_ALLOW_COLLABORATION constant is false,
 * collaboration is not allowed and cannot be enabled.
 * The constant defaults to true, unless the WP_ALLOW_COLLABORATION
 * environment variable is set to string "false".
 *
 * @since 7.1.0
 *
 * @return bool Whether real-time collaboration is allowed.
 */
function wp_is_collaboration_allowed() {
	if ( ! defined( 'WP_ALLOW_COLLABORATION' ) ) {
		$env_value = getenv( 'WP_ALLOW_COLLABORATION' );
		if ( false === $env_value ) {
			// Environment variable is not defined, default to allowing collaboration.
			define( 'WP_ALLOW_COLLABORATION', true );
		} else {
			/*
			 * Environment variable is defined, let's confirm it is actually set to
			 * "true" as it may still have a string value "false" – the preceeding
			 * `if` branch only tests for the boolean `false`.
			 */
			define( 'WP_ALLOW_COLLABORATION', 'true' === $env_value );
		}
	}

	return WP_ALLOW_COLLABORATION;
}

/**
 * Injects the real-time collaboration setting into a global variable.
 *
 * @since 7.1.0
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
 * Removes update rows older than 7 days. Presence data is cleaned
 * separately via wp_delete_expired_presence_data().
 *
 * @since 7.1.0
 */
function wp_delete_old_collaboration_data() {
	global $wpdb;

	if ( ! wp_is_collaboration_enabled() ) {
		/*
		 * Collaboration was enabled in the past but has since been disabled.
		 * Unschedule the cron job prior to clean up so this callback does not
		 * continue to run.
		 */
		wp_clear_scheduled_hook( 'wp_delete_old_collaboration_data' );
	}

	/* Clean up update rows older than 7 days. */
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->collaboration} WHERE date_gmt < %s",
			gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS )
		)
	);

	/* Clean up expired presence entries. */
	wp_delete_expired_presence_data();
}
