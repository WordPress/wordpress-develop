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
		$upload_dir = wp_get_upload_dir();

		static::$dir_defaults = array(
			'path'    => untrailingslashit( $upload_dir['basedir'] ) . '/fonts',
			'url'     => untrailingslashit( $upload_dir['baseurl'] ) . '/fonts',
			'subdir'  => '',
			'basedir' => untrailingslashit( $upload_dir['basedir'] ) . '/fonts',
			'baseurl' => untrailingslashit( $upload_dir['baseurl'] ) . '/fonts',
			'error'   => false,
		);
	}

	/**
	 * Ensure the font directory is correct.
	 */
	public function test_fonts_dir() {
		$font_dir = wp_get_font_dir();

		$this->assertSame( $font_dir, static::$dir_defaults );
	}

	/**
	 * Ensure that the fonts directory is correct for a multisite installation.
	 *
	 * The main site will use the default location and others will follow a pattern of  `/sites/{$blog_id}/fonts`
	 *
	 * @group multisite
	 * @group ms-required
	 */
	public function test_fonts_dir_for_multisite() {
		$blog_id              = self::factory()->blog->create();
		$main_site_upload_dir = wp_get_upload_dir();
		switch_to_blog( $blog_id );

		$actual   = wp_get_font_dir();
		$expected = array(
			'path'    => untrailingslashit( $main_site_upload_dir['basedir'] ) . "/sites/{$blog_id}/fonts",
			'url'     => untrailingslashit( $main_site_upload_dir['baseurl'] ) . "/sites/{$blog_id}/fonts",
			'subdir'  => '',
			'basedir' => untrailingslashit( $main_site_upload_dir['basedir'] ) . "/sites/{$blog_id}/fonts",
			'baseurl' => untrailingslashit( $main_site_upload_dir['baseurl'] ) . "/sites/{$blog_id}/fonts",
			'error'   => false,
		);

		// Restore blog prior to assertions.
		restore_current_blog();
		$this->assertSameSets( $expected, $actual );
	}

	/**
	 * Ensure modifying the font directory via the 'font_dir' filter works.
	 */
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

	/**
	 * Ensure infinite loops are not triggered when filtering the font uploads directory.
	 *
	 * @ticket 60652
	 */
	public function test_fonts_dir_filters_do_not_trigger_infinite_loop() {
		/*
		 * Naive filtering of uploads directory to return font directory.
		 *
		 * This emulates the approach a plugin developer may take to
		 * add the filter when extending the font library functionality.
		 */
		add_filter( 'upload_dir', '_wp_filter_font_directory' );

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
}
