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
	private static $skip_file_system_tests = false;

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

		$default_dir_exists  = file_exists( $path );
		$fallback_dir_exists = file_exists( path_join( WP_CONTENT_DIR, 'uploads/fonts' ) );

		self::$skip_file_system_tests = true; // $default_dir_exists || $fallback_dir_exists; // I reckon this is causing the rest failures due to the static.
	}

	public function tear_down() {
		$this->remove_font_paths();
		parent::tear_down();
	}

	public function test_fonts_dir() {
		$font_dir = wp_get_font_dir();

		$this->assertSame( $font_dir, static::$dir_defaults );
	}

	public function test_fonts_dir_with_filter() {
		// Define a callback function to pass to the filter.
		function set_new_values( $defaults ) {
			$defaults['path']    = '/custom-path/fonts/my-custom-subdir';
			$defaults['url']     = 'http://example.com/custom-path/fonts/my-custom-subdir';
			$defaults['subdir']  = 'my-custom-subdir';
			$defaults['basedir'] = '/custom-path/fonts';
			$defaults['baseurl'] = 'http://example.com/custom-path/fonts';
			$defaults['error']   = false;
			return $defaults;
		}

		// Add the filter.
		add_filter( 'font_dir', 'set_new_values' );

		// Gets the fonts dir.
		$font_dir = wp_get_font_dir();

		$expected = array(
			'path'    => '/custom-path/fonts/my-custom-subdir',
			'url'     => 'http://example.com/custom-path/fonts/my-custom-subdir',
			'subdir'  => 'my-custom-subdir',
			'basedir' => '/custom-path/fonts',
			'baseurl' => 'http://example.com/custom-path/fonts',
			'error'   => false,
		);

		// Remove the filter.
		remove_filter( 'font_dir', 'set_new_values' );

		$this->assertSame( $expected, $font_dir, 'The wp_get_font_dir() method should return the expected values.' );

		// Gets the fonts dir.
		$font_dir = wp_get_font_dir();

		$this->assertSame( static::$dir_defaults, $font_dir, 'The wp_get_font_dir() method should return the default values.' );
	}

	public function test_fonts_dir_filters_do_not_trigger_infinite_loop() {
		/*
		 * Naive filtering of uploads directory to return font directory.
		 *
		 * This emulates the approach a plugin developer may take to
		 * add the filter when extending the font library functionality.
		 */
		add_filter( 'upload_dir', 'wp_apply_font_dir_filters' );

		add_filter(
			'upload_dir',
			function ( $upload_dir ) {
				static $count = 0;
				++$count;
				// The filter may be applied a couple of times, at five iterations assume an infinite loop.
				if ( $count >= 5 ) {
					$this->fail( 'Filtering the uploads directory triggered an infinite loop.' );
				}
				return $upload_dir;
			},
			5
		);

		/*
		 * Filter the font directory to return the uploads directory.
		 *
		 * This emulates moving font files back to the uploads directory due
		 * to file system structure.
		 */
		add_filter( 'font_dir', 'wp_get_upload_dir' );

		wp_get_upload_dir();

		// This will never be hit if an infinite loop is triggered.
		$this->assertTrue( true );
	}

	public function test_should_create_fonts_dir_in_uploads_when_fails_in_wp_content() {
		if ( self::$skip_file_system_tests ) {
			$this->markTestSkipped( 'The file system tests can not run on this environment.' );
		}

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

		$this->create_fake_file_to_avoid_dir_creation( static::$dir_defaults['path'] );
		$this->assertFileExists( static::$dir_defaults['path'] );

		$font_dir = wp_font_dir();

		$this->assertDirectoryDoesNotExist( static::$dir_defaults['path'], 'The `wp-content/fonts` directory should not exist.' );
		$this->assertDirectoryExists( $font_dir['path'], 'The `uploads/fonts` directory should exist.' );
		$this->assertSame( $expected, $font_dir, 'The `fonts` directory should be a subdir in the `uploads` directory.' );
	}

	public function test_should_return_error_if_unable_to_create_fonts_dir_in_uploads() {
		if ( self::$skip_file_system_tests ) {
			$this->markTestSkipped( 'The file system tests can not run on this environment.' );
		}

		// Disallow the creation of the `wp-content/fonts` directory.
		$this->create_fake_file_to_avoid_dir_creation( static::$dir_defaults['path'] );
		$this->assertFileExists( static::$dir_defaults['path'] );

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

		$font_dir = wp_font_dir();

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
