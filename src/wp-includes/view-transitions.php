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
 * Prints a render-blocking expectation so View Transitions capture a fully parsed admin menu.
 *
 * A cross-document view transition snapshots the incoming page at its first render, which can
 * happen before the `<body>` has finished parsing. The snapshot would then capture a partially
 * built page — for example an incomplete admin menu — and the transition animates to or from
 * the wrong state.
 *
 * The `expect` link blocks the first render until `#wpcontent` is parsed. Rendering is unblocked
 * as soon as the target's start tag is parsed, not its children, so the target is the element
 * right after the admin menu rather than the menu itself: once `#wpcontent` starts, the whole
 * menu has been parsed. Targeting an element near the end of the body would also work, but it
 * would delay the first render by the time it takes to parse all of the page content. The media
 * query limits the first-render delay to when view transitions actually run, matching the
 * `@view-transition` rule in view-transitions.css.
 *
 * Nothing is printed when the admin View Transitions style is not enqueued, since there is then
 * no transition to wait for.
 *
 * @since 7.2.0
 *
 * @link https://html.spec.whatwg.org/multipage/links.html#link-type-expect
 */
function wp_print_view_transitions_render_blocking_link(): void {
	if ( ! wp_style_is( 'wp-view-transitions-admin' ) ) {
		return;
	}

	echo '<link rel="expect" href="#wpcontent" blocking="render" media="(prefers-reduced-motion: no-preference)">' . "\n";
}
