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
 *                                 Default 'screen'. Accepts media types like 'all', 'print' and 'screen', or media queries like
 *                                 '(orientation: portrait)' and '(max-width: 640px)'.
 */
function wp_enqueue_webfont( $handle, $src = '', $params = array(), $ver = false, $media = 'screen' ) {
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
 * @param string $remote_url The remote URL.
 *
 * @return string Returns the local URL if it exists, otherwise the remote URL.
 */
function wp_maybe_get_local_webfont_url( $remote_url = '' ) {

	// If $remote_url is empty, get ehe value from a transient and loop the array.
	if ( empty( $remote_url ) ) {
		$font_faces_to_download = (array) get_site_transient( 'webfonts_to_download' );
		foreach ( $font_faces_to_download as $font_face ) {
			wp_maybe_get_local_webfont_url( $font_face );
		}
		return;
	}

	$folder_path           = trailingslashit( WP_CONTENT_DIR ) . 'fonts';
	$local_stylesheet_path = "$folder_path/" . md5( content_url() . WP_CONTENT_DIR . $remote_url ) . '.css';
	$local_stylesheet_url  = str_replace( trailingslashit( WP_CONTENT_DIR ), content_url(), $local_stylesheet_path );

	// Return the local URL if the file exists.
	if ( file_exists( $local_stylesheet_path ) ) {
		return $local_stylesheet_url;
	}

	// In order to avoid affecting performance during page-load, we'll only
	// run the downloader on `shutdown`, after the page has finished loading.
	// To do that, we're adding a `webfonts_to_download` transient to store the URLs we need to download.
	if ( ! doing_action( 'shutdown' ) ) {
		$font_faces_to_download   = get_site_transient( 'webfonts_to_download' );
		$font_faces_to_download   = $font_faces_to_download ? $font_faces_to_download : array();
		$font_faces_to_download[] = $remote_url;
		set_site_transient( 'webfonts_to_download', $font_faces_to_download, DAY_IN_SECONDS );
		add_action( 'shutdown', 'wp_maybe_get_local_webfont_url' );
		return $remote_url;
	}

	// Get the filesystem.
	// This will be needed to perform all the file operations required to create the local stylesheet.
	global $wp_filesystem;
	if ( ! $wp_filesystem ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once wp_normalize_path( ABSPATH . '/wp-admin/includes/file.php' );
		}
		WP_Filesystem();
	}
	if ( ! defined( 'FS_CHMOD_DIR' ) ) {
		define( 'FS_CHMOD_DIR', ( 0755 & ~ umask() ) );
	}

	// If the "fonts" folder doesn't exist, create it.
	// Early exit if the folder can not be created.
	if ( ! file_exists( $folder_path ) && ! $wp_filesystem->mkdir( $folder_path, FS_CHMOD_DIR ) ) {
		return $remote_url;
	}

	// If the file doesn't exist and can not be created, return early.
	if ( ! $wp_filesystem->exists( $local_stylesheet_path ) && ! $wp_filesystem->touch( $local_stylesheet_path ) ) {
		return $remote_url;
	}

	// Get the remote URL contents.
	$response = wp_remote_get(
		$remote_url,
		array(
			// Use a modern user-agent, to get woff2 files.
			'user-agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0',
		)
	);

	// Early exit if there was an error.
	if ( is_wp_error( $response ) ) {
		return $remote_url;
	}

	// Get the CSS from the response.
	$remote_styles = wp_remote_retrieve_body( $response );

	if ( 'text/css' !== wp_remote_retrieve_header( $response, 'content-type' ) ) {
		return $remote_url;
	}

	// Get an array of all files from the CSS.
	$font_faces = explode( '@font-face', $remote_styles );

	$font_files = array();

	// Loop all font-face declarations.
	foreach ( $font_faces as $font_face ) {

		// Make sure to only process styles inside this declaration.
		$style = explode( '}', $font_face )[0];

		// Sanity check.
		if ( false === strpos( $style, 'font-family' ) ) {
			continue;
		}

		// Get an array of font-families.
		preg_match_all( '/font-family.*?\;/', $style, $matched_font_families );

		// Get an array of font-files.
		preg_match_all( '/url\(.*?\)/i', $style, $matched_font_files );

		// Get the font-family name.
		$font_family = 'unknown';
		if ( isset( $matched_font_families[0] ) && isset( $matched_font_families[0][0] ) ) {
			$font_family = rtrim( ltrim( $matched_font_families[0][0], 'font-family:' ), ';' );
			$font_family = trim( str_replace( array( '"', "'", ';' ), '', $font_family ) );
			$font_family = sanitize_key( strtolower( str_replace( ' ', '-', $font_family ) ) );
		}

		// Make sure the font-family is set in the array.
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

		// Make sure items are unique.
		// Use array_flip( array_flip() ) here instead of array_unique() for improved performance.
		$font_files[ $font_family ] = array_flip( array_flip( $font_files[ $font_family ] ) );
	}

	// Downloaded font-files are stored in an option to improve performance and reduce lookups.
	$cached_files = get_site_transient( 'downloaded_font_files_' . md5( $remote_url ) );
	$cached_files = $cached_files ? $cached_files : array();

	// If in the end $change is true, the cache option will need to be updated.
	$change = false;

	// Loop all font-files.
	foreach ( $font_files as $font_family => $files ) {

		// The folder path for this font-family.
		$folder_path = trailingslashit( WP_CONTENT_DIR ) . "fonts/$font_family";

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
				if ( isset( $cached_files[ $url ] ) ) {
					continue;
				}

				// Add file to the cache and change the $changed var to indicate we need to update the option.
				$cached_files[ $url ] = "$folder_path/$filename";
				$change               = true;

				// Since the file exists we don't need to proceed with downloading it.
				continue;
			}

			/**
			 * If we got this far, download the file.
			 */

			// Download file to temporary location.
			$tmp_path = download_url( $url );

			// Make sure there were no errors.
			if ( is_wp_error( $tmp_path ) ) {
				continue;
			}

			// Move temp file to final destination.
			$success = $wp_filesystem->move( $tmp_path, "$folder_path/$filename", true );
			if ( $success ) {
				$cached_files[ $url ] = "$folder_path/$filename";
				$change               = true;
			}
		}
	}

	// If there were changes, update the option.
	if ( $change ) {

		// Cleanup the option and then save it.
		foreach ( $cached_files as $url => $path ) {
			if ( ! file_exists( $path ) ) {
				unset( $cached_files[ $url ] );
			}
		}
		set_site_transient( 'downloaded_font_files_' . md5( $remote_url ), $cached_files, MONTH_IN_SECONDS );
	}

	// Convert paths to URLs.
	foreach ( $cached_files as $remote => $local ) {
		$cached_files[ $remote ] = str_replace( trailingslashit( WP_CONTENT_DIR ), content_url(), $local );
	}

	$styles = str_replace( array_keys( $cached_files ), array_values( $cached_files ), $remote_styles );

	// Put the contents in the file. Return false if that fails.
	if ( ! $wp_filesystem->put_contents( $local_stylesheet_path, $styles ) ) {
		return $local_stylesheet_url;
	}

	// Fallback to the remote URL.
	return $remote_url;
}
