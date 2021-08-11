<?php
/**
 * Dependencies API: WP_Styles class
 *
 * @since 2.6.0
 *
 * @package WordPress
 * @subpackage Dependencies
 */

/**
 * Core class used to register styles.
 *
 * @since 2.6.0
 *
 * @see WP_Dependencies
 */
class WP_Webfonts extends WP_Styles {

	/**
	 * Register an item.
	 *
	 * Registers the item if no item of that name already exists.
	 *
	 * @since 2.1.0
	 * @since 2.6.0 Moved from `WP_Scripts`.
	 *
	 * @param string           $handle Name of the item. Should be unique.
	 * @param string|bool      $src    Full URL of the item, or path of the item relative
	 *                                 to the WordPress root directory. If source is set to false,
	 *                                 item is an alias of other items it depends on.
	 * @param array            $params Optional. An array of registered item handles this item depends on.
	 *                                 Default empty array.
	 * @param string|bool|null $ver    Optional. String specifying item version number, if it has one,
	 *                                 which is added to the URL as a query string for cache busting purposes.
	 *                                 If version is set to false, a version number is automatically added
	 *                                 equal to current installed WordPress version.
	 *                                 If set to null, no version is added.
	 * @param mixed            $args   Optional. Custom property of the item. NOT the class property $args.
	 *                                 Examples: $media, $in_footer.
	 * @return bool Whether the item has been registered. True on success, false on failure.
	 */
	public function add( $handle, $src, $params = array(), $ver = false, $args = null ) {
		if ( isset( $this->registered[ $handle ] ) ) {
			return false;
		}

		$params = wp_parse_args(
			$params,
			array(
				'local' => false,
			)
		);

		if ( true === $params['local'] ) {
			$src = $this->maybe_get_local_src( sanitize_title( $handle ), $src );
		}
		$this->registered[ $handle ] = new _WP_Dependency( $handle, $src, array(), $ver, $args );
		return true;
	}

	/**
	 * Get the local URL which contains the styles.
	 *
	 * Fallback to the remote URL if we were unable to write the file locally.
	 *
	 * @access public
	 * @since 1.1.0
	 * @return string
	 */
	public function maybe_get_local_src( $slug, $remote_url ) {
		$local_stylesheet_path = trailingslashit( WP_CONTENT_DIR ) . "/fonts/$slug/" . md5( content_url() . trailingslashit( WP_CONTENT_DIR ) . $remote_url ) . '.css';

		// Check if the local stylesheet exists.
		if ( ! file_exists( $local_stylesheet_path ) ) {

			// Attempt to update the stylesheet. Return the local URL on success.
			if ( $this->write_stylesheet( $slug, $remote_url ) ) {
				return str_replace( trailingslashit( WP_CONTENT_DIR ), content_url(), $local_stylesheet_path );
			}
		}

		// If the local file exists, return its URL, with a fallback to the remote URL.
		return file_exists( $local_stylesheet_path )
			? str_replace( trailingslashit( WP_CONTENT_DIR ), content_url(), $local_stylesheet_path )
			: $remote_url;
	}

	/**
	 * Get local stylesheet contents.
	 *
	 * @access public
	 * @since 1.1.0
	 * @return string|false Returns the remote URL contents.
	 */
	public function get_local_stylesheet_contents( $slug, $remote_url ) {
		$local_path = trailingslashit( WP_CONTENT_DIR ) . "/fonts/$slug/" . md5( content_url() . trailingslashit( WP_CONTENT_DIR ) . $remote_url ) . '.css';

		// If the local stylesheet does not exist, attempt to create the stylesheet.
		// Return false if the file does not exist and can't be created.
		if ( ! file_exists( $local_path ) && ! $this->write_stylesheet( $slug, $remote_url ) ) {
			return false;
		}

		return file_get_contents( $local_path );
	}

	/**
	 * Download files mentioned in our CSS locally.
	 *
	 * @access public
	 * @since 1.0.0
	 * @return array Returns an array of remote URLs and their local counterparts.
	 */
	public function get_local_files_from_css( $css ) {
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
		$stored     = get_site_option( 'downloaded_font_files', array() );
		$change     = false; // If in the end this is true, we need to update the cache option.
		$filesystem = $this->get_filesystem();

		if ( ! defined( 'FS_CHMOD_DIR' ) ) {
			define( 'FS_CHMOD_DIR', ( 0755 & ~ umask() ) );
		}

		// If the fonts folder don't exist, create it.
		if ( ! file_exists( trailingslashit( WP_CONTENT_DIR ) . '/fonts' ) ) {
			$filesystem->mkdir( trailingslashit( WP_CONTENT_DIR ) . '/fonts', FS_CHMOD_DIR );
		}

		foreach ( $font_files as $font_family => $files ) {

			// The folder path for this font-family.
			$folder_path = trailingslashit( WP_CONTENT_DIR ) . "/fonts/$font_family";

			// If the folder doesn't exist, create it.
			if ( ! file_exists( $folder_path ) ) {
				$filesystem->mkdir( $folder_path, FS_CHMOD_DIR );
			}

			foreach ( $files as $url ) {

				// Get the filename.
				$filename = basename( wp_parse_url( $url, PHP_URL_PATH ) );

				// Check if the file already exists.
				if ( file_exists( "$folder_path/$filename" ) ) {

					// Skip if already cached.
					if ( isset( $stored[ $url ] ) ) {
						continue;
					}

					// Add file to the cache and change the $changed var to indicate we need to update the option.
					$stored[ $url ] = "$folder_path/$filename";
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
				$success = $filesystem->move( $tmp_path, "$folder_path/$filename", true );
				if ( $success ) {
					$stored[ $url ] = "$folder_path/$filename";
					$change         = true;
				}
			}
		}

		// If there were changes, update the option.
		if ( $change ) {

			// Cleanup the option and then save it.
			foreach ( $stored as $url => $path ) {
				if ( ! file_exists( $path ) ) {
					unset( $stored[ $url ] );
				}
			}
			update_site_option( 'downloaded_font_files', $stored );
		}

		return $stored;
	}

	/**
	 * Write the CSS to the filesystem.
	 *
	 * @access protected
	 * @since 1.1.0
	 * @return string|false Returns the absolute path of the file on success, or false on fail.
	 */
	protected function write_stylesheet( $slug, $remote_url ) {
		$folder_path = trailingslashit( WP_CONTENT_DIR ) . '/fonts';
		$file_path   = trailingslashit( WP_CONTENT_DIR ) . "/fonts/$slug/" . md5( content_url() . trailingslashit( WP_CONTENT_DIR ) . $remote_url ) . '.css';
		$filesystem  = $this->get_filesystem();

		if ( ! defined( 'FS_CHMOD_DIR' ) ) {
			define( 'FS_CHMOD_DIR', ( 0755 & ~ umask() ) );
		}

		// If the folder doesn't exist, create it. Return false on fail.
		if ( ! file_exists( $folder_path ) && ! $filesystem->mkdir( $folder_path, FS_CHMOD_DIR ) ) {
			return false;
		}

		// If the subfolder doesn't exist, create it. Return false on fail.
		if ( ! file_exists( "$folder_path/$slug" ) && ! $filesystem->mkdir( "$folder_path/$slug", FS_CHMOD_DIR ) ) {
			return false;
		}

		// If the file doesn't exist and can not be created, return early with false.
		if ( ! $filesystem->exists( $file_path ) && ! $filesystem->touch( $file_path ) ) {
			return false;
		}

		// Get the remote URL contents.
		$response = wp_remote_get( $remote_url, array( 'user-agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0' ) );

		// Early exit if there was an error.
		if ( is_wp_error( $response ) ) {
			return '';
		}

		// Get the CSS from our response.
		$remote_styles = wp_remote_retrieve_body( $response );

		// Get an array of locally-hosted files.
		$files = $this->get_local_files_from_css( $remote_styles );

		// Convert paths to URLs.
		foreach ( $files as $remote => $local ) {
			$files[ $remote ] = str_replace( trailingslashit( WP_CONTENT_DIR ), content_url(), $local );
		}

		$styles = str_replace( array_keys( $files ), array_values( $files ), $remote_styles );

		// Put the contents in the file. Return false if that fails.
		if ( ! $filesystem->put_contents( $file_path, $styles ) ) {
			return false;
		}

		return $file_path;
	}

	/**
	 * Get the filesystem.
	 *
	 * @access protected
	 * @since 1.0.0
	 * @return \WP_Filesystem_Base
	 */
	protected function get_filesystem() {
		global $wp_filesystem;

		// If the filesystem has not been instantiated yet, do it here.
		if ( ! $wp_filesystem ) {
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once wp_normalize_path( ABSPATH . '/wp-admin/includes/file.php' );
			}
			WP_Filesystem();
		}
		return $wp_filesystem;
	}
}
