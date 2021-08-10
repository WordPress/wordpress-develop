<?php
/**
 * Dependencies API: Styles functions
 *
 * @since 2.6.0
 *
 * @package WordPress
 * @subpackage Dependencies
 */

/**
 * Initialize $wp_webfonts if it has not been set.
 *
 * @global WP_Webfonts $wp_webfonts
 *
 * @since 4.2.0
 *
 * @return WP_Webfonts WP_Webfonts instance.
 */
function wp_webfonts() {
	global $wp_webfonts;

	if ( ! ( $wp_webfonts instanceof WP_Webfonts ) ) {
		$wp_webfonts = new WP_Webfonts();
	}

	return $wp_webfonts;
}

/**
 * Register a CSS stylesheet.
 *
 * @see WP_Dependencies::add()
 * @link https://www.w3.org/TR/CSS2/media.html#media-types List of CSS media types.
 *
 * @since 2.6.0
 * @since 4.3.0 A return value was added.
 *
 * @param string           $handle Name of the stylesheet. Should be unique.
 * @param string|bool      $src    Full URL of the stylesheet, or path of the stylesheet relative to the WordPress root directory.
 *                                 If source is set to false, stylesheet is an alias of other stylesheets it depends on.
 * @param array            $params Optional. An array of parameters. Default empty array.
 * @param string|bool|null $ver    Optional. String specifying stylesheet version number, if it has one, which is added to the URL
 *                                 as a query string for cache busting purposes. If version is set to false, a version
 *                                 number is automatically added equal to current installed WordPress version.
 *                                 If set to null, no version is added.
 * @param string           $media  Optional. The media for which this stylesheet has been defined.
 *                                 Default 'screen'. Accepts media types like 'all', 'print' and 'screen', or media queries like
 *                                 '(orientation: portrait)' and '(max-width: 640px)'.
 * @return bool Whether the style has been registered. True on success, false on failure.
 */
function wp_register_webfont( $handle, $src, $deps = array(), $ver = false, $media = 'screen' ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__, $handle );

	return wp_webfonts()->add( $handle, $src, $deps, $ver, $media );
}

/**
 * Remove a registered stylesheet.
 *
 * @see WP_Dependencies::remove()
 *
 * @since 2.1.0
 *
 * @param string $handle Name of the stylesheet to be removed.
 */
function wp_deregister_webfont( $handle ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__, $handle );

	wp_webfonts()->remove( $handle );
}

/**
 * Enqueue a CSS stylesheet.
 *
 * Registers the style if source provided (does NOT overwrite) and enqueues.
 *
 * @see WP_Dependencies::add()
 * @see WP_Dependencies::enqueue()
 * @link https://www.w3.org/TR/CSS2/media.html#media-types List of CSS media types.
 *
 * @since 2.6.0
 *
 * @param string           $handle Name of the stylesheet. Should be unique.
 * @param string           $src    Full URL of the stylesheet, or path of the stylesheet relative to the WordPress root directory.
 *                                 Default empty.
 * @param array            $params Optional. An array of parameters. Default empty array.
 * @param string|bool|null $ver    Optional. String specifying stylesheet version number, if it has one, which is added to the URL
 *                                 as a query string for cache busting purposes. If version is set to false, a version
 *                                 number is automatically added equal to current installed WordPress version.
 *                                 If set to null, no version is added.
 * @param string           $media  Optional. The media for which this stylesheet has been defined.
 *                                 Default 'screen'. Accepts media types like 'all', 'print' and 'screen', or media queries like
 *                                 '(orientation: portrait)' and '(max-width: 640px)'.
 */
function wp_enqueue_webfont( $handle, $src = '', $params = array(), $ver = false, $media = 'screen' ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__, $handle );

	$wp_webfonts = wp_webfonts();

	if ( $src ) {
		$_handle = explode( '?', $handle );
		$wp_webfonts->add( $_handle[0], $src, $params, $ver, $media );
	}

	$wp_webfonts->enqueue( $handle );
}

/**
 * Remove a previously enqueued CSS stylesheet.
 *
 * @see WP_Dependencies::dequeue()
 *
 * @since 3.1.0
 *
 * @param string $handle Name of the stylesheet to be removed.
 */
function wp_dequeue_webfont( $handle ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__, $handle );

	wp_webfonts()->dequeue( $handle );
}
