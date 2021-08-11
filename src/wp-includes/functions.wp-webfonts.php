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
 * Register a webfont's stylesheet.
 *
 * @see WP_Dependencies::add()
 * @link https://www.w3.org/TR/CSS2/media.html#media-types List of CSS media types.
 *
 * @since 5.9.0
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
function wp_register_webfont( $handle, $src, $params = array(), $ver = false, $media = 'screen' ) {

	$params = wp_parse_args(
		$params,
		array(
			'local' => false,
		)
	);

	if ( true === $params['local'] ) {
		$src = wp_maybe_get_local_webfont_url( $src );
	}

	return wp_register_style( "webfont-$handle", $src, array(), $ver, $media );
}

/**
 * Remove a registered stylesheet.
 *
 * @see WP_Dependencies::remove()
 *
 * @since 5.9.0
 *
 * @param string $handle Name of the stylesheet to be removed.
 */
function wp_deregister_webfont( $handle ) {
	wp_deregister_style( "webfont-$handle" );
}

/**
 * Enqueue a webfont's CSS stylesheet.
 *
 * Registers the style if source provided (does NOT overwrite) and enqueues.
 *
 * @see WP_Dependencies::add()
 * @see WP_Dependencies::enqueue()
 * @link https://www.w3.org/TR/CSS2/media.html#media-types List of CSS media types.
 *
 * @since 5.9.0
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
 *                                 Default 'all'. Accepts media types like 'all', 'print' and 'screen', or media queries like
 *                                 '(orientation: portrait)' and '(max-width: 640px)'.
 */
function wp_enqueue_style( $handle, $src = '', $params = array(), $ver = false, $media = 'all' ) {
	$params = wp_parse_args(
		$params,
		array(
			'local' => false,
		)
	);

	if ( true === $params['local'] ) {
		$src = wp_maybe_get_local_webfont_url( $src );
	}
	wp_enqueue_style( "webfont-$handle", $src, array(), $ver, $media );
}

/**
 * Remove a previously enqueued CSS stylesheet.
 *
 * @see WP_Dependencies::dequeue()
 *
 * @since 5.9.0
 *
 * @param string $handle Name of the stylesheet to be removed.
 */
function wp_dequeue_webfont( $handle ) {
	wp_dequeue_style( "webfont-$handle" );
}

/**
 * Check whether a webfont's CSS stylesheet has been added to the queue.
 *
 * @since 5.9.0
 *
 * @param string $handle Name of the stylesheet.
 * @param string $list   Optional. Status of the stylesheet to check. Default 'enqueued'.
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
 * Get the local URL which contains the styles.
 *
 * Fallback to the remote URL if we were unable to write the file locally.
 *
 * @since 5.9.0
 *
 * @param string $slug       The stylesheet slug.
 * @param string $remote_url The remote URL.
 *
 * @return string Returns the local URL if it exists, otherwise the remote URL.
 */
function wp_maybe_get_local_webfont_url( $slug, $remote_url ) {
	$slug                  = sanitize_title_with_dashes( $slug );
	$local_stylesheet_path = trailingslashit( WP_CONTENT_DIR ) . "/fonts/$slug/" . md5( content_url() . trailingslashit( WP_CONTENT_DIR ) . $remote_url ) . '.css';

	if ( file_exists( $local_stylesheet_path ) ) {
		return str_replace( trailingslashit( WP_CONTENT_DIR ), content_url(), $local_stylesheet_path );
	}

	global $wp_filesystem;
	// If the filesystem has not been instantiated yet, do it here.
	if ( ! $wp_filesystem ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once wp_normalize_path( ABSPATH . '/wp-admin/includes/file.php' );
		}
		WP_Filesystem();
	}

	$folder_path = trailingslashit( WP_CONTENT_DIR ) . '/fonts';

	if ( ! defined( 'FS_CHMOD_DIR' ) ) {
		define( 'FS_CHMOD_DIR', ( 0755 & ~ umask() ) );
	}

	// If the folder doesn't exist, create it. Return false on fail.
	if ( ! file_exists( $folder_path ) && ! $wp_filesystem->mkdir( $folder_path, FS_CHMOD_DIR ) ) {
		return false;
	}

	// If the subfolder doesn't exist, create it. Return false on fail.
	if ( ! file_exists( "$folder_path/$slug" ) && ! $wp_filesystem->mkdir( "$folder_path/$slug", FS_CHMOD_DIR ) ) {
		return false;
	}

	// If the file doesn't exist and can not be created, return early with false.
	if ( ! $wp_filesystem->exists( $local_stylesheet_path ) && ! $wp_filesystem->touch( $local_stylesheet_path ) ) {
		return false;
	}

	// Get the remote URL contents.
	$response = wp_remote_get( $remote_url, array( 'user-agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0' ) );

	// Early exit if there was an error.
	if ( is_wp_error( $response ) ) {
		return;
	}

	// Get the CSS from our response.
	$remote_styles = wp_remote_retrieve_body( $response );

	// Get an array of locally-hosted files.
	$font_faces = explode( '@font-face', $css );

	$font_files = array();

	// Loop all our font-face declarations.
	foreach ( $font_faces as $font_face ) {

		// Make sure we only process styles inside this declaration.
		$style = explode( '}', $font_face )[0];

		// Sanity check.
		if ( false === strpos( $style, 'font-family' ) ) {
			continue;
		}

		// Get an array of our font-families.
		preg_match_all( '/font-family.*?\;/', $style, $matched_font_families );

		// Get an array of our font-files.
		preg_match_all( '/url\(.*?\)/i', $style, $matched_font_files );

		// Get the font-family name.
		$font_family = 'unknown';
		if ( isset( $matched_font_families[0] ) && isset( $matched_font_families[0][0] ) ) {
			$font_family = rtrim( ltrim( $matched_font_families[0][0], 'font-family:' ), ';' );
			$font_family = trim( str_replace( array( "'", ';' ), '', $font_family ) );
			$font_family = sanitize_key( strtolower( str_replace( ' ', '-', $font_family ) ) );
		}

		// Make sure the font-family is set in our array.
		if ( ! isset( $font_files[ $font_family ] ) ) {
			$font_files[ $font_family ] = array();
		}

		// Get files for this font-family and add them to the array.
		foreach ( $matched_font_files as $match ) {

			// Sanity check.
			if ( ! isset( $match[0] ) ) {
				continue;
			}

			// Add the file URL.
			$font_files[ $font_family ][] = rtrim( ltrim( $match[0], 'url(' ), ')' );
		}

		// Make sure we have unique items.
		// We're using array_flip here instead of array_unique for improved performance.
		$font_files[ $font_family ] = array_flip( array_flip( $font_files[ $font_family ] ) );
	}
	$files  = get_site_option( 'downloaded_font_files', array() );
	$change = false; // If in the end this is true, we need to update the cache option.

	if ( ! defined( 'FS_CHMOD_DIR' ) ) {
		define( 'FS_CHMOD_DIR', ( 0755 & ~ umask() ) );
	}

	// If the fonts folder don't exist, create it.
	if ( ! file_exists( trailingslashit( WP_CONTENT_DIR ) . '/fonts' ) ) {
		$wp_filesystem->mkdir( trailingslashit( WP_CONTENT_DIR ) . '/fonts', FS_CHMOD_DIR );
	}

	foreach ( $font_files as $font_family => $files ) {

		// The folder path for this font-family.
		$folder_path = trailingslashit( WP_CONTENT_DIR ) . "/fonts/$font_family";

		// If the folder doesn't exist, create it.
		if ( ! file_exists( $folder_path ) ) {
			$wp_filesystem->mkdir( $folder_path, FS_CHMOD_DIR );
		}

		foreach ( $files as $url ) {

			// Get the filename.
			$filename = basename( wp_parse_url( $url, PHP_URL_PATH ) );

			// Check if the file already exists.
			if ( file_exists( "$folder_path/$filename" ) ) {

				// Skip if already cached.
				if ( isset( $files[ $url ] ) ) {
					continue;
				}

				// Add file to the cache and change the $changed var to indicate we need to update the option.
				$files[ $url ] = "$folder_path/$filename";
				$change         = true;

				// Since the file exists we don't need to proceed with downloading it.
				continue;
			}

			/**
			 * If we got this far, we need to download the file.
			 */

			// require file.php if the download_url function doesn't exist.
			if ( ! function_exists( 'download_url' ) ) {
				require_once wp_normalize_path( ABSPATH . '/wp-admin/includes/file.php' );
			}

			// Download file to temporary location.
			$tmp_path = download_url( $url );

			// Make sure there were no errors.
			if ( is_wp_error( $tmp_path ) ) {
				continue;
			}

			// Move temp file to final destination.
			$success = $wp_filesystem->move( $tmp_path, "$folder_path/$filename", true );
			if ( $success ) {
				$files[ $url ] = "$folder_path/$filename";
				$change         = true;
			}
		}
	}

	// If there were changes, update the option.
	if ( $change ) {

		// Cleanup the option and then save it.
		foreach ( $files as $url => $path ) {
			if ( ! file_exists( $path ) ) {
				unset( $files[ $url ] );
			}
		}
		update_site_option( 'downloaded_font_files', $files );
	}

	// Convert paths to URLs.
	foreach ( $files as $remote => $local ) {
		$files[ $remote ] = str_replace( trailingslashit( WP_CONTENT_DIR ), content_url(), $local );
	}

	$styles = str_replace( array_keys( $files ), array_values( $files ), $remote_styles );

	// Put the contents in the file. Return false if that fails.
	if ( ! $wp_filesystem->put_contents( $local_stylesheet_path, $styles ) ) {
		return str_replace( trailingslashit( WP_CONTENT_DIR ), content_url(), $local_stylesheet_path );
	}
}
