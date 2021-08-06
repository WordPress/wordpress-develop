<?php
/**
 * Dependencies API: Webfonts functions
 *
 * @since 5.9.0
 *
 * @package WordPress
 * @subpackage Dependencies
 */

/**
 * Register a webfont's stylesheet and generate CSS rules for it.
 *
 * @see WP_Dependencies::add()
 * @link https://www.w3.org/TR/CSS2/media.html#media-types List of CSS media types.
 *
 * @since 5.9.0
 *
 * @param string           $handle Name of the webfont. Should be unique.
 * @param string|bool      $src    Full URL of the stylesheet, or path of the stylesheet relative to the WordPress root directory.
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
function wp_register_webfont( $handle = '', $src = '', $params = array(), $ver = null, $media = 'screen' ) {

	// Generate handle if not provided.
	if ( empty( $handle && ! empty( $params ) ) ) {
		$handle = md5( json_encode( $params ) );
	}

	// Early return if there is no handle.
	if ( empty( $handle ) ) {
		return;
	}

	// Register the stylesheet.
	$result = wp_register_style( "webfont-$handle", $src, array(), $ver, $media );

	// Add inline styles for generated @font-face styles.
	$inline_styles = wp_webfont_generate_styles( $params );
	if ( $inline_styles ) {
		wp_add_inline_style( "webfont-$handle", $inline_styles );
	}

	// Add preconnect links for external webfonts.
	_wp_webfont_add_preconnect_links( $params );

	return $result;
}

/**
 * Remove a registered webfont.
 *
 * @see WP_Dependencies::remove()
 *
 * @since 5.9.0
 *
 * @param string $handle Name of the webfont to be removed.
 */
function wp_deregister_webfont( $handle ) {
	wp_deregister_style( "webfont-$handle" );
}

/**
 * Enqueue a webfont's CSS stylesheet and generate CSS rules for it.
 *
 * Registers the style if source provided (does NOT overwrite) and enqueues.
 *
 * @see WP_Dependencies::add()
 * @see WP_Dependencies::enqueue()
 * @link https://www.w3.org/TR/CSS2/media.html#media-types List of CSS media types.
 *
 * @since 5.9.0
 *
 * @param string           $handle Name of the webfont. Should be unique.
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
function wp_enqueue_webfont( $handle = '', $src = '', $params = array(), $ver = null, $media = 'screen' ) {
	if ( $src || ! empty( $params ) ) {
		wp_register_webfont( $handle, $src, $params, $ver, $media );
	}

	// Generate handle if not provided.
	if ( empty( $handle && ! empty( $params ) ) ) {
		$handle = md5( json_encode( $params ) );
	}

	// Early return if there is no handle.
	if ( empty( $handle ) ) {
		return;
	}

	return wp_enqueue_style( "webfont-$handle" );
}

/**
 * Remove a previously enqueued webfont.
 *
 * @see WP_Dependencies::dequeue()
 *
 * @since 5.9.0
 *
 * @param string $handle Name of the webfont to be removed.
 */
function wp_dequeue_webfont( $handle ) {
	wp_dequeue_style( "webfont-$handle" );
}

/**
 * Check whether a webfont's CSS stylesheet has been added to the queue.
 *
 * @since 5.9.0
 *
 * @param string $handle Name of the webfont.
 * @param string $list   Optional. Status of the webfont to check. Default 'enqueued'.
 *                       Accepts 'enqueued', 'registered', 'queue', 'to_do', and 'done'.
 * @return bool Whether style is queued.
 */
function wp_webfont_is( $handle, $list = 'enqueued' ) {
	return wp_style_is( "webfont-$handle", $list );
}

/**
 * Add metadata to a CSS stylesheet.
 *
 * Works only if the stylesheet has already been added.
 *
 * Possible values for $key and $value:
 * 'conditional' string      Comments for IE 6, lte IE 7 etc.
 * 'rtl'         bool|string To declare an RTL stylesheet.
 * 'suffix'      string      Optional suffix, used in combination with RTL.
 * 'alt'         bool        For rel="alternate stylesheet".
 * 'title'       string      For preferred/alternate stylesheets.
 *
 * @see WP_Dependencies::add_data()
 *
 * @since 5.9.0
 *
 * @param string $handle Name of the stylesheet.
 * @param string $key    Name of data point for which we're storing a value.
 *                       Accepts 'conditional', 'rtl' and 'suffix', 'alt' and 'title'.
 * @param mixed  $value  String containing the CSS data to be added.
 * @return bool True on success, false on failure.
 */
function wp_webfont_add_data( $handle, $key, $value ) {
	return wp_style_add_data( "webfont-$handle", $key, $value );
}

/**
 * Generate styles for a webfont.
 *
 * @since 5.9.0
 *
 * @param array $params The webfont parameters.
 *
 * @return string The generated styles.
 */
function wp_webfont_generate_styles( $params ) {
	// Fallback to local provider if none is specified.
	$provider = isset( $params['provider'] ) ? $params['provider'] : new WP_Fonts_Provider_Local();
	// Set the $params to the object.
	$provider->set_params( $params );
	// Get the CSS.
	return $provider->get_css();
}

/**
 * Add preconnect links to <head> for enqueued webfonts.
 *
 * @since 5.9.0
 *
 * @param array $params The webfont parameters.
 *
 * @return void
 */
function _wp_webfont_add_preconnect_links( $params ) {

	$provider = isset( $params['provider'] ) ? $params['provider'] : new WP_Fonts_Provider_Local();
	$provider->set_params( $params );

	// Store a static var to avoid adding the same preconnect links multiple times.
	static $preconnect_urls_added_from_api = array();
	// Add preconnect links.
	add_action(
		'wp_head',
		function() use ( $provider, &$preconnect_urls_added_from_api ) {

			// Early exit if the provider has already added preconnect links.
			if ( in_array( $provider->get_id(), $preconnect_urls_added_from_api ) ) {
				return;
			}

			// Add the preconnect links.
			$preconnect_urls = $provider->get_preconnect_urls();
			foreach ( $preconnect_urls as $preconnection ) {
				echo '<link rel="preconnect"';
				foreach ( $preconnection as $key => $value ) {
					if ( 'href' === $key ) {
						echo ' href="' . esc_url( $value ) . '"';
					} elseif ( true === $value || false === $value ) {
						echo $value ? ' ' . esc_attr( $key ) : '';
					} else {
						echo ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
					}
				}
				echo '>' . "\n";
			}
			$preconnect_urls_added_from_api[] = $provider->get_id();
		}
	);
}
