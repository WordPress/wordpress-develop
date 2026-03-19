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
 * Otherwise, falls back to the 'wp_collaboration_enabled' option.
 *
 * @since 7.0.0
 *
 * @return bool Whether real-time collaboration is enabled.
 */
function wp_is_collaboration_enabled() {
	if ( defined( 'WP_DISABLE_COLLABORATION' ) && WP_DISABLE_COLLABORATION ) {
		return false;
	}

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
