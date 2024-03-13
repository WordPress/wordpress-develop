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
	private static $fake_fonts_file;

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

		static::$fake_fonts_file = $path;
	}

	public function tear_down() {
		$this->remove_fonts_directory();
		$this->remove_no_new_directories_in_wp_content_fake();

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

	public function test_should_created_fonts_dir_in_uploads_when_fails_in_wp_content() {
		// Set the expected results.
		$upload_dir = wp_upload_dir();
		$path       = path_join( $upload_dir['basedir'], 'fonts' );
		$expected   = array(
			'path'    => $path,
			'url'     => $upload_dir['baseurl'] . '/fonts',
			'subdir'  => '',
			'basedir' => $path,
			'baseurl' => $upload_dir['baseurl'] . '/fonts',
			'error'   => false,
		);

		$this->fake_no_new_directories_in_wp_content();
		$this->assertFileExists( self::$fake_fonts_file );

		$font_dir = wp_get_font_dir();

		$this->assertDirectoryDoesNotExist( path_join( WP_CONTENT_DIR, 'fonts' ), 'The `wp-content/fonts` directory should not exist' );
		$this->assertDirectoryExists( $font_dir['path'], 'The `uploads/fonts` directory should exist' );
		$this->assertSame( $expected, $font_dir, 'The font directory should be a subdir in the uploads directory.' );
	}

	private function remove_fonts_directory() {
		$directories = array(
			path_join( WP_CONTENT_DIR, 'fonts' ),
			path_join( WP_CONTENT_DIR, 'uploads/fonts' ),
			'/custom-path/fonts/my-custom-subdir',
		);

		foreach ( $directories as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			$this->rmdir( $dir );
			@rmdir( $dir );
		}
	}

	private function fake_no_new_directories_in_wp_content() {
		file_put_contents(
			self::$fake_fonts_file,
			'fake file'
		);
	}

	private function remove_no_new_directories_in_wp_content_fake() {
		if ( file_exists( self::$fake_fonts_file ) ) {
			@unlink( self::$fake_fonts_file );
		}
	}
}
