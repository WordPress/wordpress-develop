<?php

/**
 * Test the WP_Image_Editor_Vips class.
 *
 * @group image
 * @group media
 * @group wp-image-editor-vips
 */
require_once __DIR__ . '/base.php';

class Tests_Image_Editor_Vips extends WP_Image_UnitTestCase {

	public $editor_engine = 'WP_Image_Editor_Vips';

	/**
	 * Sets up test dependencies.
	 */
	public function set_up() {
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-vips.php';

		parent::set_up();
	}

	/**
	 * Cleans generated images after each test.
	 */
	public function tear_down() {
		$folder = DIR_TESTDATA . '/images/waffles-*.jpg';

		foreach ( glob( $folder ) as $file ) {
			unlink( $file );
		}

		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * Tests support for commonly used mime types.
	 */
	public function test_supports_mime_type() {
		$vips_image_editor = new WP_Image_Editor_Vips( null );

		$this->assertTrue( $vips_image_editor->supports_mime_type( 'image/jpeg' ), 'Does not support image/jpeg' );
		$this->assertTrue( $vips_image_editor->supports_mime_type( 'image/png' ), 'Does not support image/png' );
		$this->assertTrue( $vips_image_editor->supports_mime_type( 'image/gif' ), 'Does not support image/gif' );
	}

	/**
	 * Tests resizing an image without cropping.
	 */
	public function test_resize() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$vips_image_editor->resize( 100, 50 );

		$this->assertSame(
			array(
				'width'  => 75,
				'height' => 50,
			),
			$vips_image_editor->get_size()
		);
	}

	/**
	 * Tests multi_resize() with a single generated sub-size.
	 */
	public function test_single_multi_resize() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$sizes_array = array(
			array(
				'width'  => 50,
				'height' => 50,
			),
		);

		$resized = $vips_image_editor->multi_resize( $sizes_array );

		$expected_array = array(
			array(
				'file'      => 'waffles-50x33.jpg',
				'width'     => 50,
				'height'    => 33,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-50x33.jpg' ),
			),
		);

		$this->assertSame( $expected_array, $resized );

		$image_path = DIR_TESTDATA . '/images/' . $resized[0]['file'];
		$this->assertImageDimensions(
			$image_path,
			$expected_array[0]['width'],
			$expected_array[0]['height']
		);
	}

	/**
	 * Tests that multi_resize() does not create an image when dimensions are missing.
	 *
	 * @ticket 26823
	 */
	public function test_multi_resize_does_not_create() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$sizes_array = array(
			array(
				'width'  => 0,
				'height' => 0,
			),
			array(
				'width'  => null,
				'height' => null,
			),
			array(
				'width' => 0,
			),
			array(
				'height' => 0,
			),
		);

		$resized = $vips_image_editor->multi_resize( $sizes_array );

		$this->assertEmpty( $resized );
	}

	/**
	 * Tests resizing with crop enabled.
	 */
	public function test_resize_and_crop() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$vips_image_editor->resize( 100, 50, true );

		$this->assertSame(
			array(
				'width'  => 100,
				'height' => 50,
			),
			$vips_image_editor->get_size()
		);
	}

	/**
	 * Tests basic crop behavior.
	 */
	public function test_crop() {
		$file = DIR_TESTDATA . '/images/gradient-square.jpg';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$vips_image_editor->crop( 0, 0, 50, 50 );

		$this->assertSame(
			array(
				'width'  => 50,
				'height' => 50,
			),
			$vips_image_editor->get_size()
		);
	}

	/**
	 * Tests rotation behavior.
	 */
	public function test_rotate() {
		$file = DIR_TESTDATA . '/images/gradient-square.jpg';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$result = $vips_image_editor->rotate( 180 );

		$this->assertTrue( $result );
		$this->assertSame(
			array(
				'width'  => 100,
				'height' => 100,
			),
			$vips_image_editor->get_size()
		);
	}

	/**
	 * Tests horizontal flip behavior.
	 */
	public function test_flip() {
		$file = DIR_TESTDATA . '/images/gradient-square.jpg';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$result = $vips_image_editor->flip( true, false );

		$this->assertTrue( $result );
		$this->assertSame(
			array(
				'width'  => 100,
				'height' => 100,
			),
			$vips_image_editor->get_size()
		);
	}
}
