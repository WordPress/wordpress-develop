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
	$affix = SCRIPT_DEBUG ? '' : '.min';
	$path  = ABSPATH . "wp-admin/css/view-transitions{$affix}.css";
	return (string) file_get_contents( $path );
}

/**
 * Prints a render-blocking expectation so View Transitions capture a fully parsed admin page.
 *
 * A cross-document view transition snapshots the incoming page at its first render, which can
 * happen before the `<body>` has finished parsing. The snapshot would then capture a partially
 * built page — for example an incomplete admin menu — and the transition animates to or from
 * the wrong state.
 *
 * The `expect` link blocks the first render until an element near the end of the body is
 * parsed, so the snapshot is always taken of a complete page. The media query limits the
 * (small) first-render delay to when view transitions actually run, matching the
 * `@view-transition` rule in view-transitions.css.
 *
 * @since 7.0.1
 *
 * @link https://html.spec.whatwg.org/multipage/links.html#link-type-expect
 */
function wp_print_view_transitions_render_blocking_link(): void {
	echo '<link rel="expect" href="#wpfooter" blocking="render" media="(prefers-reduced-motion: no-preference)">' . "\n";
}
