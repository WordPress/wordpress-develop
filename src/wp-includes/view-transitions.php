<?php
/**
 * View Transitions API.
 *
 * @package WordPress
 * @subpackage View Transitions
 * @since 7.0.0
 */

/**
 * Enqueues View Transitions CSS for the admin.
 *
 * @since 7.0.0
 */
function wp_enqueue_view_transitions_admin_css(): void {
	wp_enqueue_style( 'wp-view-transitions-admin' );
}

/**
 * Gets the CSS for View Transitions in the admin.
 *
 * @since 7.0.0
 *
 * @return string The CSS.
 */
function wp_get_view_transitions_admin_css(): string {
	$css = <<<CSS
@view-transition { navigation: auto; }
#adminmenu > .menu-top { view-transition-name: attr(id type(<custom-ident>), none); }
CSS;

	return $css;
}
