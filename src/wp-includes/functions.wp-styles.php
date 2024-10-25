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
 * Initializes $wp_styles if it has not been set.
 *
 * @since 4.2.0
 *
 * @global WP_Styles $wp_styles
 *
 * @return WP_Styles WP_Styles instance.
 */
function wp_styles() {
	global $wp_styles;

	if ( ! ( $wp_styles instanceof WP_Styles ) ) {
		$wp_styles = new WP_Styles();
	}

	return $wp_styles;
}

/**
 * Displays styles that are in the $handles queue.
 *
 * Passing an empty array to $handles prints the queue,
 * passing an array with one string prints that style,
 * and passing an array of strings prints those styles.
 *
 * @since 2.6.0
 *
 * @global WP_Styles $wp_styles The WP_Styles object for printing styles.
 *
 * @param string|bool|array $handles Styles to be printed. Default 'false'.
 * @return string[] On success, an array of handles of processed WP_Dependencies items; otherwise, an empty array.
 */
function wp_print_styles( $handles = false ) {
	global $wp_styles;

	if ( '' === $handles ) { // For 'wp_head'.
		$handles = false;
	}

	if ( ! $handles ) {
		/**
		 * Fires before styles in the $handles queue are printed.
		 *
		 * @since 2.6.0
		 */
		do_action( 'wp_print_styles' );
	}

	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );

	if ( ! ( $wp_styles instanceof WP_Styles ) ) {
		if ( ! $handles ) {
			return array(); // No need to instantiate if nothing is there.
		}
	}

	return wp_styles()->do_items( $handles );
}

/**
 * Adds extra CSS styles to a registered stylesheet.
 *
 * Styles will only be added if the stylesheet is already in the queue.
 * Accepts a string $data containing the CSS. If two or more CSS code blocks
 * are added to the same stylesheet $handle, they will be printed in the order
 * they were added, i.e. the latter added styles can redeclare the previous.
 *
 * @see WP_Styles::add_inline_style()
 *
 * @since 3.3.0
 * @since 6.7.0 Refactor to use HTML API.
 *
 * @param string $handle Name of the stylesheet to add the extra styles to.
 * @param string $data   String containing the CSS styles to be added.
 * @return bool True on success, false on failure.
 */
function wp_add_inline_style( $handle, $data ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__, $handle );

	$processor = new WP_HTML_Tag_Processor( "<style>{$data}</style>" );
	$processor->next_tag( 'STYLE' );
	$contents = $processor->get_modifiable_text();

	/*
	 * If the processor did not entirely consume the input already, it means
	 * that the input contained a closing STYLE tag.
	 */
	if ( $processor->next_token() ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				/* translators: 1: <style>, 2: wp_add_inline_style() */
				__( 'Do not pass %1$s tags to %2$s.' ),
				'<code>&lt;style&gt;</code>',
				'<code>wp_add_inline_style()</code>'
			),
			'3.7.0'
		);

		// It's possible this was a benign case of sending `<style>...</style>`.
		$processor = new WP_HTML_Tag_Processor( $data );
		$processor->next_token();
		$is_style_token = 'STYLE' === $processor->get_token_name();
		$contents       = $processor->get_modifiable_text();
		$is_only_style  = false === $processor->next_token();

		if ( ! $is_style_token || ! $is_only_style ) {
			/*
			 * Otherwise it's unbalanced. It could be malicious.
			 *
			 *  - The input could be entirely rejected.
			 *  - This could take only the part before the closing STYLE tag.
			 *  - The closing STYLE tag could be escaped.
			 *
			 * Without any more compelling reasons, this code is escaping the tag
			 * and using the rest of the input so as to preserve as much of it as
			 * is possible. It's possible the intent was to set a name in a `content`
			 * property, such as `content: "Use a </style> to close a STYLE element."`.
			 */
			$processor = new WP_HTML_Tag_Processor( '<style></style>' );
			$processor->next_token();
			$processor->set_modifiable_text( $data );
			$contents = $processor->get_modifiable_text();
		}
	}

	return wp_styles()->add_inline_style( $handle, trim( $contents ) );
}

/**
 * Registers a CSS stylesheet.
 *
 * @see WP_Dependencies::add()
 * @link https://www.w3.org/TR/CSS2/media.html#media-types List of CSS media types.
 *
 * @since 2.6.0
 * @since 4.3.0 A return value was added.
 *
 * @param string           $handle Name of the stylesheet. Should be unique.
 * @param string|false     $src    Full URL of the stylesheet, or path of the stylesheet relative to the WordPress root directory.
 *                                 If source is set to false, stylesheet is an alias of other stylesheets it depends on.
 * @param string[]         $deps   Optional. An array of registered stylesheet handles this stylesheet depends on. Default empty array.
 * @param string|bool|null $ver    Optional. String specifying stylesheet version number, if it has one, which is added to the URL
 *                                 as a query string for cache busting purposes. If version is set to false, a version
 *                                 number is automatically added equal to current installed WordPress version.
 *                                 If set to null, no version is added.
 * @param string           $media  Optional. The media for which this stylesheet has been defined.
 *                                 Default 'all'. Accepts media types like 'all', 'print' and 'screen', or media queries like
 *                                 '(orientation: portrait)' and '(max-width: 640px)'.
 * @return bool Whether the style has been registered. True on success, false on failure.
 */
function wp_register_style( $handle, $src, $deps = array(), $ver = false, $media = 'all' ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__, $handle );

	return wp_styles()->add( $handle, $src, $deps, $ver, $media );
}

/**
 * Removes a registered stylesheet.
 *
 * @see WP_Dependencies::remove()
 *
 * @since 2.1.0
 *
 * @param string $handle Name of the stylesheet to be removed.
 */
function wp_deregister_style( $handle ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__, $handle );

	wp_styles()->remove( $handle );
}

/**
 * Enqueues a CSS stylesheet.
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
 * @param string[]         $deps   Optional. An array of registered stylesheet handles this stylesheet depends on. Default empty array.
 * @param string|bool|null $ver    Optional. String specifying stylesheet version number, if it has one, which is added to the URL
 *                                 as a query string for cache busting purposes. If version is set to false, a version
 *                                 number is automatically added equal to current installed WordPress version.
 *                                 If set to null, no version is added.
 * @param string           $media  Optional. The media for which this stylesheet has been defined.
 *                                 Default 'all'. Accepts media types like 'all', 'print' and 'screen', or media queries like
 *                                 '(orientation: portrait)' and '(max-width: 640px)'.
 */
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__, $handle );

	$wp_styles = wp_styles();

	if ( $src ) {
		$_handle = explode( '?', $handle );
		$wp_styles->add( $_handle[0], $src, $deps, $ver, $media );
	}

	$wp_styles->enqueue( $handle );
}

/**
 * Removes a previously enqueued CSS stylesheet.
 *
 * @see WP_Dependencies::dequeue()
 *
 * @since 3.1.0
 *
 * @param string $handle Name of the stylesheet to be removed.
 */
function wp_dequeue_style( $handle ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__, $handle );

	wp_styles()->dequeue( $handle );
}

/**
 * Checks whether a CSS stylesheet has been added to the queue.
 *
 * @since 2.8.0
 *
 * @param string $handle Name of the stylesheet.
 * @param string $status Optional. Status of the stylesheet to check. Default 'enqueued'.
 *                       Accepts 'enqueued', 'registered', 'queue', 'to_do', and 'done'.
 * @return bool Whether style is queued.
 */
function wp_style_is( $handle, $status = 'enqueued' ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__, $handle );

	return (bool) wp_styles()->query( $handle, $status );
}

/**
 * Adds metadata to a CSS stylesheet.
 *
 * Works only if the stylesheet has already been registered.
 *
 * Possible values for $key and $value:
 * 'conditional' string      Comments for IE 6, lte IE 7 etc.
 * 'rtl'         bool|string To declare an RTL stylesheet.
 * 'suffix'      string      Optional suffix, used in combination with RTL.
 * 'alt'         bool        For rel="alternate stylesheet".
 * 'title'       string      For preferred/alternate stylesheets.
 * 'path'        string      The absolute path to a stylesheet. Stylesheet will
 *                           load inline when 'path' is set.
 *
 * @see WP_Dependencies::add_data()
 *
 * @since 3.6.0
 * @since 5.8.0 Added 'path' as an official value for $key.
 *              See {@see wp_maybe_inline_styles()}.
 *
 * @param string $handle Name of the stylesheet.
 * @param string $key    Name of data point for which we're storing a value.
 *                       Accepts 'conditional', 'rtl' and 'suffix', 'alt', 'title' and 'path'.
 * @param mixed  $value  String containing the CSS data to be added.
 * @return bool True on success, false on failure.
 */
function wp_style_add_data( $handle, $key, $value ) {
	return wp_styles()->add_data( $handle, $key, $value );
}
