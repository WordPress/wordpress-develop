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
function wp_register_webfont( $handle, $src, $params = array(), $ver = null, $media = 'screen' ) {

	if ( is_array( $src ) ) {
		$media  = $ver;
		$ver    = $params;
		$params = $src;
		$src    = '';
	}
	$params = _wp_webfont_parse_params( $params );
	$result = wp_register_style( "webfont-$handle", $src, array(), $ver, $media );
	_wp_maybe_preload_webfont( $params );
	wp_add_inline_style( "webfont-$handle", _wp_webfont_generate_styles( $params ) );
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
function wp_enqueue_webfont( $handle, $src = '', $params = array(), $ver = null, $media = 'screen' ) {
	if ( $src || ! empty( $params ) ) {
		wp_register_webfont( $handle, $src, $params, $ver, $media );
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
 * Generates styles for a webfont.
 *
 * @since 5.9.0
 *
 * @param array $params The webfont parameters.
 * @return string The styles.
 */
function _wp_webfont_generate_styles( $params ) {
	if ( empty( $params['font-family'] ) ) {
		return '';
	}

	$css = '@font-face{';
	foreach ( $params as $key => $value ) {
		if ( 'preload' === $key ) {
			continue;
		}
		if ( 'src' === $key ) {
			$src = "local({$params['font-family']})";
			foreach ( $value as $item ) {
				$src .= ", url('{$item['url']}') format('{$item['format']}')";
			}
			$value = $src;
		}

		if ( 'variation' === $key && is_array( $value ) ) {
			$variations = array();
			foreach ( $value as $key => $val ) {
				$variations[] = "$key $val";
			}
			$value = implode( ', ', $variations );
		}

		if ( ! empty( $value ) ) {
			$css .= "$key:$value;";
		}
	}
	$css .= '}';

	return $css;
}

/**
 * Pre-loads the webfont if needed.
 *
 * @since 5.9.0
 *
 * @param string $src    The webfont URL.
 * @param array  $params The webfont parameters.
 * @return void
 */
function _wp_maybe_preload_webfont( $params ) {
	if ( empty( $params['preload'] ) || true !== $params['preload'] || empty( $params['src'] ) ) {
		return;
	}

	add_action(
		'wp_head',
		function() use ( $params ) {
			if ( 0 === strpos( $params['src'][0]['format'], 'data' ) ) {
				return;
			}
			$link = sprintf(
				'<link rel="preload" href="%1$s" as="font" type="%2$s" crossorigin>',
				esc_url( $params['src'][0]['url'] ),
				wp_get_mime_types()[ pathinfo( $params['src'][0]['url'], PATHINFO_EXTENSION ) ]
			);
			/**
			 * Filters the preload link for a webfont.
			 * This filter is only applied if the webfont is preloaded.
			 *
			 * @since 5.9.0
			 *
			 * @param string $link   The preload link.
			 * @param array  $params The webfont parameters.
			 *
			 * @return string The preload link.
			 */
			echo apply_filters( 'wp_preload_webfont', $link, $params );
		}
	);
}

/**
 * Parse a webfont's parameters.
 *
 * @since 5.9.0
 *
 * @param array $params The webfont parameters.
 * @return array The parsed parameters.
 */
function _wp_webfont_parse_params( $params ) {
	$defaults = array(
		'font-weight'  => '400',
		'font-style'   => 'normal',
		'font-display' => 'fallback',
		'src'          => array(),
		'preload'      => true,
	);
	$params   = wp_parse_args( $params, $defaults );

	$whitelist = array(
		// Valid CSS properties.
		'ascend-override',
		'descend-override',
		'font-display',
		'font-family',
		'font-stretch',
		'font-style',
		'font-weight',
		'font-variant',
		'font-feature-settings',
		'font-variation-settings',
		'line-gap-override',
		'size-adjust',
		'src',
		'unicode-range',

		// Extras.
		'preload',
	);

	// Only allow whitelisted properties.
	foreach ( $params as $key => $value ) {
		if ( ! in_array( $key, $whitelist, true ) ) {
			unset( $params[ $key ] );
		}
	}

	// Order $src items to optimize for browser support.
	if ( ! empty( $params['src'] ) ) {
		$params['src'] = (array) $params['src'];
		$src           = array();
		$src_ordered   = array();

		foreach ( $params['src'] as $url ) {
			// Add data URIs first.
			if ( 0 === strpos( trim( $url ), 'data:' ) ) {
				$src_ordered[] = array(
					'url'    => $url,
					'format' => 'data',
				);
				continue;
			}
			$format         = pathinfo( $url, PATHINFO_EXTENSION );
			$src[ $format ] = $url;
		}
		if ( ! empty( $src['woff2'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['woff2'],
				'format' => 'woff2',
			);
		}
		if ( ! empty( $src['woff'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['woff'],
				'format' => 'woff',
			);
		}
		if ( ! empty( $src['ttf'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['ttf'],
				'format' => 'truetype',
			);
		}
		if ( ! empty( $src['eot'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['eot'],
				'format' => 'embedded-opentype',
			);
		}
		if ( ! empty( $src['otf'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['otf'],
				'format' => 'opentype',
			);
		}
		$params['src'] = $src_ordered;
	}

	// Only allow valid font-display values.
	if (
		! empty( $params['font-display'] ) &&
		! in_array( $params['font-display'], array( 'auto', 'block', 'swap', 'fallback' ), true )
	) {
		$params['font-display'] = 'fallback';
	}

	// Only allow valid font-style values.
	if (
		! empty( $params['font-style'] ) &&
		! in_array( $params['font-style'], array( 'normal', 'italic', 'oblique' ), true ) &&
		! preg_match( '/^oblique\s+(\d+)%/', $params['font-style'], $matches )
	) {
		$params['font-style'] = 'normal';
	}

	// Only allow valid font-weight values.
	if (
		! empty( $params['font-weight'] ) &&
		! in_array( $params['font-weight'], array( 'normal', 'bold', 'bolder', 'lighter', 'inherit' ), true ) &&
		! preg_match( '/^(\d+)$/', $params['font-weight'], $matches ) &&
		! preg_match( '/^(\d+)\s+(\d+)$/', $params['font-weight'], $matches )
	) {
		$params['font-weight'] = 'normal';
	}

	return $params;
}
