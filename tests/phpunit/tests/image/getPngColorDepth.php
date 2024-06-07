<?php

/**
 * Tests_WP_Image_Editor_Imagick::test_get_png_color_depth()
 *
 * @group image
 * @group media
 *
 * @covers WP_Image_Editor_Imagick::get_png_color_depth
 */
class Tests_WP_Image_Editor_Imagick_get_png_color_depth extends WP_Image_UnitTestCase {

	public $editor_engine = 'WP_Image_Editor_Imagick';

	public function set_up() {
		require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';

		// This needs to come after the mock image editor class is loaded.
		parent::set_up();
	}

	/**
	 * @ticket 36477
	 *
	 * @dataProvider get_png_color_depth_with_valid_png_data
	 */
	public function test_get_png_color_depth_with_valid_png( $file, $expected_depth, $expected_encoded ) {

		$image = new WP_Image_Editor_Imagick( DIR_TESTDATA . $file );
		$image->load();
		$image->get_png_color_depth();

		$this->assertSame( $expected_depth, $image->indexed_pixel_depth );
		$this->assertSame( $expected_encoded, $image->indexed_color_encoded );
	}

	public function get_png_color_depth_with_valid_png_data() {
		return array(
			'4 bit'  => array(
				'file'             => '/images/png-tests/17-c3-duplicate-entries.png',
				'expected_depth'   => 4,
				'expected_encoded' => true,
			),
			'8 bit'  => array(
				'file'             => '/images/png-tests/test8.png',
				'expected_depth'   => 8,
				'expected_encoded' => true,
			),
			'16 bit' => array(
				'file'             => '/images/png-tests/basi0g16.png',
				'expected_depth'   => 16,
				'expected_encoded' => false,
			),
		);
	}

	/**
	 * @ticket 36477
	 */
	public function test_get_png_color_depth_with_invalid_png() {
		$image = new WP_Image_Editor_Imagick( DIR_TESTDATA . '/images/a2-small.jpg' );
		$image->load();
		$image->get_png_color_depth();

		$this->assertFalse( $image->indexed_pixel_depth );
	}
}
