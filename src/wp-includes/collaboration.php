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
 * @since 7.0.0
 *
 * @return bool True if real-time collaboration is enabled, false otherwise.
 */
function wp_is_collaboration_enabled() {
	return (bool) get_option( 'wp_collaboration_enabled' );
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
