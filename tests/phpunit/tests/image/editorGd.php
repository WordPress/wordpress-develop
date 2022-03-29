<?php

/**
 * Test the WP_Image_Editor_GD class
 *
 * @group image
 * @group media
 * @group wp-image-editor-gd
 */
require_once __DIR__ . '/base.php';

class Tests_Image_Editor_GD extends WP_Image_UnitTestCase {

	public $editor_engine = 'WP_Image_Editor_GD';

	public function set_up() {
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';

		// This needs to come after the mock image editor class is loaded.
		parent::set_up();
	}

	public function tear_down() {
		$folder = DIR_TESTDATA . '/images/waffles-*.jpg';

		foreach ( glob( $folder ) as $file ) {
			unlink( $file );
		}

		$this->remove_added_uploads();

		parent::tear_down();
	}

	public function test_supports_mime_type_jpeg() {
		$gd_image_editor = new WP_Image_Editor_GD( null );
		$expected        = (bool) ( imagetypes() & IMG_JPG );
		$this->assertSame( $expected, $gd_image_editor->supports_mime_type( 'image/jpeg' ) );
	}

	public function test_supports_mime_type_png() {
		$gd_image_editor = new WP_Image_Editor_GD( null );
		$expected        = (bool) ( imagetypes() & IMG_PNG );
		$this->assertSame( $expected, $gd_image_editor->supports_mime_type( 'image/png' ) );
	}

	public function test_supports_mime_type_gif() {
		$gd_image_editor = new WP_Image_Editor_GD( null );
		$expected        = (bool) ( imagetypes() & IMG_GIF );
		$this->assertSame( $expected, $gd_image_editor->supports_mime_type( 'image/gif' ) );
	}

	/**
	 * Test resizing an image, not using crop
	 *
	 * @requires function imagejpeg
	 */
	public function test_resize() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$gd_image_editor->resize( 100, 50 );

		$this->assertSame(
			array(
				'width'  => 75,
				'height' => 50,
			),
			$gd_image_editor->get_size()
		);
	}

	/**
	 * Test multi_resize with single image resize and no crop
	 *
	 * @requires function imagejpeg
	 */
	public function test_single_multi_resize() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$sizes_array = array(
			array(
				'width'  => 50,
				'height' => 50,
			),
		);

		$resized = $gd_image_editor->multi_resize( $sizes_array );

		// First, check to see if returned array is as expected.
		$expected_array = array(
			array(
				'file'      => 'waffles-50x33.jpg',
				'width'     => 50,
				'height'    => 33,
				'mime-type' => 'image/jpeg',
			),
		);

		$this->assertSame( $expected_array, $resized );

		// Now, verify real dimensions are as expected.
		$image_path = DIR_TESTDATA . '/images/' . $resized[0]['file'];
		$this->assertImageDimensions(
			$image_path,
			$expected_array[0]['width'],
			$expected_array[0]['height']
		);
	}

	/**
	 * Ensure multi_resize doesn't create an image when
	 * both height and weight are missing, null, or 0.
	 *
	 * @ticket 26823
	 */
	public function test_multi_resize_does_not_create() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$sizes_array = array(
			array(
				'width'  => 0,
				'height' => 0,
			),
			array(
				'width'  => 0,
				'height' => 0,
				'crop'   => true,
			),
			array(
				'width'  => null,
				'height' => null,
			),
			array(
				'width'  => null,
				'height' => null,
				'crop'   => true,
			),
			array(
				'width'  => '',
				'height' => '',
			),
			array(
				'width'  => '',
				'height' => '',
				'crop'   => true,
			),
			array(
				'width' => 0,
			),
			array(
				'width' => 0,
				'crop'  => true,
			),
			array(
				'width' => null,
			),
			array(
				'width' => null,
				'crop'  => true,
			),
			array(
				'width' => '',
			),
			array(
				'width' => '',
				'crop'  => true,
			),
		);

		$resized = $gd_image_editor->multi_resize( $sizes_array );

		// If no images are generated, the returned array is empty.
		$this->assertEmpty( $resized );
	}

	/**
	 * Test multi_resize with multiple sizes
	 *
	 * @ticket 26823
	 * @requires function imagejpeg
	 */
	public function test_multi_resize() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$sizes_array = array(

			/**
			 * #0 - 10x10 resize, no cropping.
			 * By aspect, should be 10x6 output.
			 */
			array(
				'width'  => 10,
				'height' => 10,
				'crop'   => false,
			),

			/**
			 * #1 - 75x50 resize, with cropping.
			 * Output dimensions should be 75x50
			 */
			array(
				'width'  => 75,
				'height' => 50,
				'crop'   => true,
			),

			/**
			 * #2 - 20 pixel max height, no cropping.
			 * By aspect, should be 30x20 output.
			 */
			array(
				'width'  => 9999, // Arbitrary high value.
				'height' => 20,
				'crop'   => false,
			),

			/**
			 * #3 - 45 pixel max height, with cropping.
			 * By aspect, should be 45x400 output.
			 */
			array(
				'width'  => 45,
				'height' => 9999, // Arbitrary high value.
				'crop'   => true,
			),

			/**
			 * #4 - 50 pixel max width, no cropping.
			 * By aspect, should be 50x33 output.
			 */
			array(
				'width' => 50,
			),

			/**
			 * #5 - 55 pixel max width, no cropping, null height
			 * By aspect, should be 55x36 output.
			 */
			array(
				'width'  => 55,
				'height' => null,
			),

			/**
			 * #6 - 55 pixel max height, no cropping, no width specified.
			 * By aspect, should be 82x55 output.
			 */
			array(
				'height' => 55,
			),

			/**
			 * #7 - 60 pixel max height, no cropping, null width.
			 * By aspect, should be 90x60 output.
			 */
			array(
				'width'  => null,
				'height' => 60,
			),

			/**
			 * #8 - 70 pixel max height, no cropping, negative width.
			 * By aspect, should be 105x70 output.
			 */
			array(
				'width'  => -9999, // Arbitrary negative value.
				'height' => 70,
			),

			/**
			 * #9 - 200 pixel max width, no cropping, negative height.
			 * By aspect, should be 200x133 output.
			 */
			array(
				'width'  => 200,
				'height' => -9999, // Arbitrary negative value.
			),
		);

		$resized = $gd_image_editor->multi_resize( $sizes_array );

		$expected_array = array(

			// #0
			array(
				'file'      => 'waffles-10x7.jpg',
				'width'     => 10,
				'height'    => 7,
				'mime-type' => 'image/jpeg',
			),

			// #1
			array(
				'file'      => 'waffles-75x50.jpg',
				'width'     => 75,
				'height'    => 50,
				'mime-type' => 'image/jpeg',
			),

			// #2
			array(
				'file'      => 'waffles-30x20.jpg',
				'width'     => 30,
				'height'    => 20,
				'mime-type' => 'image/jpeg',
			),

			// #3
			array(
				'file'      => 'waffles-45x400.jpg',
				'width'     => 45,
				'height'    => 400,
				'mime-type' => 'image/jpeg',
			),

			// #4
			array(
				'file'      => 'waffles-50x33.jpg',
				'width'     => 50,
				'height'    => 33,
				'mime-type' => 'image/jpeg',
			),

			// #5
			array(
				'file'      => 'waffles-55x37.jpg',
				'width'     => 55,
				'height'    => 37,
				'mime-type' => 'image/jpeg',
			),

			// #6
			array(
				'file'      => 'waffles-83x55.jpg',
				'width'     => 83,
				'height'    => 55,
				'mime-type' => 'image/jpeg',
			),

			// #7
			array(
				'file'      => 'waffles-90x60.jpg',
				'width'     => 90,
				'height'    => 60,
				'mime-type' => 'image/jpeg',
			),

			// #8
			array(
				'file'      => 'waffles-105x70.jpg',
				'width'     => 105,
				'height'    => 70,
				'mime-type' => 'image/jpeg',
			),

			// #9
			array(
				'file'      => 'waffles-200x133.jpg',
				'width'     => 200,
				'height'    => 133,
				'mime-type' => 'image/jpeg',
			),
		);

		$this->assertNotNull( $resized );
		$this->assertSame( $expected_array, $resized );

		foreach ( $resized as $key => $image_data ) {
			$image_path = DIR_TESTDATA . '/images/' . $image_data['file'];

			// Now, verify real dimensions are as expected.
			$this->assertImageDimensions(
				$image_path,
				$expected_array[ $key ]['width'],
				$expected_array[ $key ]['height']
			);
		}
	}

	/**
	 * Test resizing an image with cropping
	 */
	public function test_resize_and_crop() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$gd_image_editor->resize( 100, 50, true );

		$this->assertSame(
			array(
				'width'  => 100,
				'height' => 50,
			),
			$gd_image_editor->get_size()
		);
	}

	/**
	 * Test cropping an image.
	 *
	 * @ticket 51937
	 *
	 * @dataProvider data_crop
	 */
	public function test_crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {
		$file = DIR_TESTDATA . '/images/gradient-square.jpg';

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$gd_image_editor->crop( $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h, $src_abs );

		$this->assertSame(
			array(
				'width'  => (int) $src_w,
				'height' => (int) $src_h,
			),
			$gd_image_editor->get_size()
		);
	}

	public function data_crop() {
		return array(
			'src height and width must be greater than 0' => array(
				'src_x' => 0,
				'src_y' => 0,
				'src_w' => 50,
				'src_h' => 50,
			),
			'src height and width can be string but must be greater than 0' => array(
				'src_x' => 10,
				'src_y' => '10',
				'src_w' => '50',
				'src_h' => '50',
			),
			'dst height and width must be greater than 0' => array(
				'src_x' => 10,
				'src_y' => '10',
				'src_w' => 150,
				'src_h' => 150,
				'dst_w' => 150,
				'dst_h' => 150,
			),
			'dst height and width can be string but must be greater than 0' => array(
				'src_x' => 10,
				'src_y' => '10',
				'src_w' => 150,
				'src_h' => 150,
				'dst_w' => '150',
				'dst_h' => '150',
			),
		);
	}

	/**
	 * Test should return WP_Error when dimensions are not integer or are <= 0.
	 *
	 * @ticket 51937
	 *
	 * @dataProvider data_crop_invalid_dimensions
	 */
	public function test_crop_invalid_dimensions( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {
		$file = DIR_TESTDATA . '/images/gradient-square.jpg';

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$actual = $gd_image_editor->crop( $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h, $src_abs );

		$this->assertInstanceOf( 'WP_Error', $actual );
		$this->assertSame( 'image_crop_error', $actual->get_error_code() );
	}

	public function data_crop_invalid_dimensions() {
		return array(
			'src height must be greater than 0' => array(
				'src_x' => 0,
				'src_y' => 0,
				'src_w' => 100,
				'src_h' => 0,
			),
			'src width must be greater than 0'  => array(
				'src_x' => 10,
				'src_y' => '10',
				'src_w' => 0,
				'src_h' => 100,
			),
			'src height must be numeric and greater than 0' => array(
				'src_x' => 10,
				'src_y' => '10',
				'src_w' => 100,
				'src_h' => 'NaN',
			),
			'dst height must be numeric and greater than 0' => array(
				'src_x' => 0,
				'src_y' => 0,
				'src_w' => 100,
				'src_h' => 50,
				'dst_w' => '100',
				'dst_h' => 'NaN',
			),
			'src and dst height and width must be greater than 0' => array(
				'src_x' => 0,
				'src_y' => 0,
				'src_w' => 0,
				'src_h' => 0,
				'dst_w' => 0,
				'dst_h' => 0,
			),
			'src and dst height and width can be string but must be greater than 0' => array(
				'src_x' => 0,
				'src_y' => 0,
				'src_w' => '0',
				'src_h' => '0',
				'dst_w' => '0',
				'dst_h' => '0',
			),
		);
	}

	/**
	 * Test rotating an image 180 deg
	 */
	public function test_rotate() {
		$file = DIR_TESTDATA . '/images/gradient-square.jpg';

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$property = new ReflectionProperty( $gd_image_editor, 'image' );
		$property->setAccessible( true );

		$color_top_left = imagecolorat( $property->getValue( $gd_image_editor ), 0, 0 );

		$gd_image_editor->rotate( 180 );

		$this->assertSame( $color_top_left, imagecolorat( $property->getValue( $gd_image_editor ), 99, 99 ) );
	}

	/**
	 * Test flipping an image
	 */
	public function test_flip() {
		$file = DIR_TESTDATA . '/images/gradient-square.jpg';

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$property = new ReflectionProperty( $gd_image_editor, 'image' );
		$property->setAccessible( true );

		$color_top_left = imagecolorat( $property->getValue( $gd_image_editor ), 0, 0 );

		$gd_image_editor->flip( true, false );

		$this->assertSame( $color_top_left, imagecolorat( $property->getValue( $gd_image_editor ), 0, 99 ) );
	}

	/**
	 * Test the image created with WP_Image_Editor_GD preserves alpha when resizing
	 *
	 * @ticket 23039
	 */
	public function test_image_preserves_alpha_on_resize() {
		if ( ! ( imagetypes() & IMG_PNG ) ) {
			$this->fail( 'This test requires PHP to be compiled with PNG support.' );
		}

		$file = DIR_TESTDATA . '/images/transparent.png';

		$editor = wp_get_image_editor( $file );

		$this->assertNotWPError( $editor );

		$editor->load();
		$editor->resize( 5, 5 );
		$save_to_file = tempnam( get_temp_dir(), '' ) . '.png';

		$editor->save( $save_to_file );

		$this->assertImageAlphaAtPointGD( $save_to_file, array( 0, 0 ), 127 );

		unlink( $save_to_file );
	}

	/**
	 * Test the image created with WP_Image_Editor_GD preserves alpha with no resizing etc
	 *
	 * @ticket 23039
	 */
	public function test_image_preserves_alpha() {
		if ( ! ( imagetypes() & IMG_PNG ) ) {
			$this->fail( 'This test requires PHP to be compiled with PNG support.' );
		}

		$file = DIR_TESTDATA . '/images/transparent.png';

		$editor = wp_get_image_editor( $file );

		$this->assertNotWPError( $editor );

		$editor->load();

		$save_to_file = tempnam( get_temp_dir(), '' ) . '.png';

		$editor->save( $save_to_file );

		$this->assertImageAlphaAtPointGD( $save_to_file, array( 0, 0 ), 127 );

		unlink( $save_to_file );
	}

	/**
	 * @ticket 30596
	 */
	public function test_image_preserves_alpha_on_rotate() {
		if ( ! ( imagetypes() & IMG_PNG ) ) {
			$this->fail( 'This test requires PHP to be compiled with PNG support.' );
		}

		$file = DIR_TESTDATA . '/images/transparent.png';

		$image    = imagecreatefrompng( $file );
		$rgb      = imagecolorat( $image, 0, 0 );
		$expected = imagecolorsforindex( $image, $rgb );

		$editor = new WP_Image_Editor_GD( $file );
				$editor->load();
				$editor->rotate( 180 );
				$save_to_file = tempnam( get_temp_dir(), '' ) . '.png';

				$editor->save( $save_to_file );

				$this->assertImageAlphaAtPointGD( $save_to_file, array( 0, 0 ), $expected['alpha'] );
				unlink( $save_to_file );

	}

	/**
	 * Test WP_Image_Editor_GD handles extension-less images
	 *
	 * @ticket 39195
	 */
	public function test_image_non_existent_extension() {
		$image_editor = new WP_Image_Editor_GD( DIR_TESTDATA . '/images/test-image-no-extension' );
		$result       = $image_editor->load();

		$this->assertTrue( $result );
	}
}
