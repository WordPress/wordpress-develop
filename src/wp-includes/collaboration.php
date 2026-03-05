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
	global $pagenow;

	if ( ! get_option( 'wp_enable_real_time_collaboration' ) ) {
		return;
	}

	// Disable real-time collaboration on the site editor.
	$enabled = true;
	if (
		'site-editor.php' === $pagenow ||
		( 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'site-editor-v2' === $_GET['page'] )
	) {
		$enabled = false;
	}

	wp_add_inline_script(
		'wp-core-data',
		'window._wpCollaborationEnabled = ' . ( $enabled ? 'true' : 'false' ) . ';',
		'after'
	);
}
