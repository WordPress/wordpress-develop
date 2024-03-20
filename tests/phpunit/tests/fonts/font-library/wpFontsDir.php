<?php
/**
 * Test wp_get_font_dir().
 *
 * @package WordPress
 * @subpackage Font Library
 *
 * @group fonts
 * @group font-library
 *
 * @covers ::wp_get_font_dir
 */
class Tests_Fonts_WpFontDir extends WP_UnitTestCase {
	private static $dir_defaults;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		$path               = path_join( WP_CONTENT_DIR, 'fonts' );
		$url                = content_url( 'fonts' );
		self::$dir_defaults = array(
			'path'    => $path,
			'url'     => $url,
			'subdir'  => '',
			'basedir' => $path,
			'baseurl' => $url,
			'error'   => false,
		);
	}

	public function tear_down() {
		$this->remove_font_paths();
		parent::tear_down();
	}

	public function test_fonts_dir() {
		$font_dir = wp_get_font_dir();

		$this->assertSame( $font_dir, static::$dir_defaults );
	}

	public function test_font_dir_filter() {
		// Define a callback function to pass to the filter.
		function set_new_values( $defaults ) {
			$defaults['path']    = path_join( WP_CONTENT_DIR, 'custom_dir' );
			$defaults['url']     = 'http://example.com/custom-path/fonts/my-custom-dir';
			$defaults['subdir']  = '';
			$defaults['basedir'] = path_join( WP_CONTENT_DIR, 'custom_dir' );
			$defaults['baseurl'] = 'http://example.com/custom-path/fonts/my-custom-dir';
			$defaults['error']   = false;
			return $defaults;
		}

		// Add the filter.
		add_filter( 'font_dir', 'set_new_values' );

		// Gets the fonts dir.
		$font_dir = wp_get_font_dir( 'create' );

		$expected = array(
			'path'    => path_join( WP_CONTENT_DIR, 'custom_dir' ),
			'url'     => 'http://example.com/custom-path/fonts/my-custom-dir',
			'subdir'  => '',
			'basedir' => path_join( WP_CONTENT_DIR, 'custom_dir' ),
			'baseurl' => 'http://example.com/custom-path/fonts/my-custom-dir',
			'error'   => false,
		);

		// Remove the filter.
		remove_filter( 'font_dir', 'set_new_values' );

		$this->assertSame( $expected, $font_dir, 'The wp_get_font_dir() method should return the expected values.' );

		// Gets the fonts dir.
		$font_dir = wp_get_font_dir( 'create' );

		$this->assertSame( static::$dir_defaults, $font_dir, 'The wp_get_font_dir() method should return the default values.' );
	}

	public function test_non_writable_filtered_dir() {
		// Define a callback function to pass to the filter.
		function set_custom_dir( $defaults ) {
			$defaults['path']    = path_join( WP_CONTENT_DIR, 'custom_dir' );
			$defaults['url']     = 'http://example.com/custom-path/fonts/my-custom-dir';
			$defaults['basedir'] = path_join( WP_CONTENT_DIR, 'custom_dir' );
			$defaults['baseurl'] = 'http://example.com/custom-path/fonts/my-custom-dir';
			return $defaults;
		}

		// Add the filter.
		add_filter( 'font_dir', 'set_custom_dir' );

		// make 'wp-content/custom_dir' non-writable
		$this->create_fake_file_to_avoid_dir_creation( path_join( WP_CONTENT_DIR, 'custom_dir' ) );

		// Gets the fonts dir.
		$font_dir = wp_get_font_dir();

		$expected = array(
			'path'    => path_join( WP_CONTENT_DIR, 'custom_dir' ),
			'url'     => 'http://example.com/custom-path/fonts/my-custom-dir',
			'subdir'  => '',
			'basedir' => path_join( WP_CONTENT_DIR, 'custom_dir' ),
			'baseurl' => 'http://example.com/custom-path/fonts/my-custom-dir',
			'error'   => 'Unable to create directory wp-content/custom_dir. Is its parent directory writable by the server?',
		);

		$this->assertSame( $expected, $font_dir, 'The wp_get_font_dir() method should return the filtered values with an error message.' );
	}

	public function test_should_create_fonts_dir_in_uploads_when_fails_in_wp_content() {
		// Set the expected results.
		$upload_dir = wp_upload_dir();
		$expected   = array(
			'path'    => path_join( $upload_dir['basedir'], 'fonts' ),
			'url'     => $upload_dir['baseurl'] . '/fonts',
			'subdir'  => '',
			'basedir' => path_join( $upload_dir['basedir'], 'fonts' ),
			'baseurl' => $upload_dir['baseurl'] . '/fonts',
			'error'   => false,
		);

		add_filter( 'font_dir__wp_content_is_writable', '__return_false' );
		$font_dir = wp_get_font_dir( 'create' );
		remove_filter( 'font_dir__wp_content_is_writable', '__return_false' );

		$this->assertDirectoryDoesNotExist( static::$dir_defaults['path'], 'The `wp-content/fonts` directory should not exist.' );
		$this->assertDirectoryExists( $font_dir['path'], 'The `uploads/fonts` directory should exist.' );
		$this->assertSame( $expected, $font_dir, 'The `fonts` directory should be a subdir in the `uploads` directory.' );
	}

	public function test_should_return_error_if_unable_to_create_fonts_dir_in_uploads() {
		// Disallow the creation of the `uploads/fonts` directory.
		$upload_dir       = wp_upload_dir();
		$font_upload_path = path_join( $upload_dir['basedir'], 'fonts' );
		$this->create_fake_file_to_avoid_dir_creation( $font_upload_path );
		$this->assertFileExists( $font_upload_path );

		$expected = array(
			'path'    => $font_upload_path,
			'url'     => $upload_dir['baseurl'] . '/fonts',
			'subdir'  => '',
			'basedir' => $font_upload_path,
			'baseurl' => $upload_dir['baseurl'] . '/fonts',
			'error'   => 'Unable to create directory wp-content/uploads/fonts. Is its parent directory writable by the server?',
		);

		add_filter( 'font_dir__wp_content_is_writable', '__return_false' );
		$font_dir = wp_get_font_dir( 'create' );
		remove_filter( 'font_dir__wp_content_is_writable', '__return_false' );

		$this->assertDirectoryDoesNotExist( $font_upload_path, 'The `uploads/fonts` directory should not exist.' );
		$this->assertSame( $expected, $font_dir, 'As /wp-content/uplods/fonts is not writable the error key should be populated with an error message.' );
	}

	private function remove_font_paths() {
		$paths = array(
			path_join( WP_CONTENT_DIR, 'fonts' ),
			path_join( WP_CONTENT_DIR, 'custom_dir' ),
			path_join( WP_CONTENT_DIR, 'uploads/fonts' ),
		);

		foreach ( $paths as $path ) {
			if ( ! is_dir( $path ) ) {
				if ( file_exists( $path ) ) {
					unlink( $path );
				}
			} else {
				$this->rmdir( $path );
				rmdir( $path );
			}
		}
	}

	/**
	 * A placeholder "fake" file at $path triggers `wp_mkdir_p()` to fail into the `file_exists()` bail out, causing `is_dir()` to  return `false`.
	 * This effectively makes $path unwritable.
	 */
	private function create_fake_file_to_avoid_dir_creation( $path ) {
		file_put_contents(
			$path,
			'fake file'
		);
	}
}
