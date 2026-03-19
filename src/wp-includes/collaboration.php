<?php
/**
 * Bootstraps collaborative editing.
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Determines whether real-time collaboration is enabled.
 *
 * If the WP_DISABLE_COLLABORATION constant is defined and true,
 * collaboration is always disabled regardless of the database option.
 * Otherwise, falls back to the 'enable_real_time_collaboration' option.
 *
 * @since 7.0.0
 *
 * @return bool Whether real-time collaboration is enabled.
 */
function wp_is_collaboration_enabled() {
	if ( defined( 'WP_DISABLE_COLLABORATION' ) && WP_DISABLE_COLLABORATION ) {
		return false;
	}

	return (bool) get_option( 'enable_real_time_collaboration' );
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
