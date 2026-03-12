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
		$inline_script = 'window._wpCollaborationEnabled = true;';

		wp_add_inline_script( 'wp-core-data', $inline_script, 'after' );
		wp_add_inline_script( 'inline-edit-post', $inline_script, 'before' );
	}
}
