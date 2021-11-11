<?php

/**
 * Test the WP_Image_Editor_Imagick class
 *
 * @group image
 * @group media
 * @group wp-image-editor-imagick
 */
require_once __DIR__ . '/base.php';

class Tests_Image_Editor_Imagick extends WP_Image_UnitTestCase {

	public $editor_engine = 'WP_Image_Editor_Imagick';

	public function set_up() {
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';
		require_once DIR_TESTROOT . '/includes/class-wp-test-stream.php';

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

	/**
	 * Check support for ImageMagick compatible mime types.
	 */
	public function test_supports_mime_type() {
		$imagick_image_editor = new WP_Image_Editor_Imagick( null );

		$this->assertTrue( $imagick_image_editor->supports_mime_type( 'image/jpeg' ), 'Does not support image/jpeg' );
		$this->assertTrue( $imagick_image_editor->supports_mime_type( 'image/png' ), 'Does not support image/png' );
		$this->assertTrue( $imagick_image_editor->supports_mime_type( 'image/gif' ), 'Does not support image/gif' );
	}

	/**
	 * Test resizing an image, not using crop
	 */
	public function test_resize() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$imagick_image_editor = new WP_Image_Editor_Imagick( $file );
		$imagick_image_editor->load();

		$imagick_image_editor->resize( 100, 50 );

		$this->assertSame(
			array(
				'width'  => 75,
				'height' => 50,
			),
			$imagick_image_editor->get_size()
		);
	}

	/**
	 * Test multi_resize with single image resize and no crop
	 */
	public function test_single_multi_resize() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$imagick_image_editor = new WP_Image_Editor_Imagick( $file );
		$imagick_image_editor->load();

		$sizes_array = array(
			array(
				'width'  => 50,
				'height' => 50,
			),
		);

		$resized = $imagick_image_editor->multi_resize( $sizes_array );

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

		$imagick_image_editor = new WP_Image_Editor_Imagick( $file );
		$imagick_image_editor->load();

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

		$resized = $imagick_image_editor->multi_resize( $sizes_array );

		// If no images are generated, the returned array is empty.
		$this->assertEmpty( $resized );
	}

	/**
	 * Test multi_resize with multiple sizes
	 *
	 * @ticket 26823
	 */
	public function test_multi_resize() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$imagick_image_editor = new WP_Image_Editor_Imagick( $file );
		$imagick_image_editor->load();

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

		$resized = $imagick_image_editor->multi_resize( $sizes_array );

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

		$imagick_image_editor = new WP_Image_Editor_Imagick( $file );
		$imagick_image_editor->load();

		$imagick_image_editor->resize( 100, 50, true );

		$this->assertSame(
			array(
				'width'  => 100,
				'height' => 50,
			),
			$imagick_image_editor->get_size()
		);
	}

	/**
	 * Test cropping an image
	 */
	public function test_crop() {
		$file = DIR_TESTDATA . '/images/gradient-square.jpg';

		$imagick_image_editor = new WP_Image_Editor_Imagick( $file );
		$imagick_image_editor->load();

		$imagick_image_editor->crop( 0, 0, 50, 50 );

		$this->assertSame(
			array(
				'width'  => 50,
				'height' => 50,
			),
			$imagick_image_editor->get_size()
		);
	}

	/**
	 * Test rotating an image 180 deg
	 */
	public function test_rotate() {
		$file = DIR_TESTDATA . '/images/one-blue-pixel-100x100.png';

		$imagick_image_editor = new WP_Image_Editor_Imagick( $file );
		$imagick_image_editor->load();

		$property = new ReflectionProperty( $imagick_image_editor, 'image' );
		$property->setAccessible( true );

		$color_top_left = $property->getValue( $imagick_image_editor )->getImagePixelColor( 0, 0 )->getColor();

		$imagick_image_editor->rotate( 180 );

		$this->assertSame( $color_top_left, $property->getValue( $imagick_image_editor )->getImagePixelColor( 99, 99 )->getColor() );
	}

	/**
	 * Test flipping an image
	 */
	public function test_flip() {
		$file = DIR_TESTDATA . '/images/one-blue-pixel-100x100.png';

		$imagick_image_editor = new WP_Image_Editor_Imagick( $file );
		$imagick_image_editor->load();

		$property = new ReflectionProperty( $imagick_image_editor, 'image' );
		$property->setAccessible( true );

		$color_top_left = $property->getValue( $imagick_image_editor )->getImagePixelColor( 0, 0 )->getColor();

		$imagick_image_editor->flip( true, false );

		$this->assertSame( $color_top_left, $property->getValue( $imagick_image_editor )->getImagePixelColor( 0, 99 )->getColor() );
	}

	/**
	 * Test the image created with WP_Image_Editor_Imagick preserves alpha when resizing
	 *
	 * @ticket 24871
	 */
	public function test_image_preserves_alpha_on_resize() {
		$file = DIR_TESTDATA . '/images/transparent.png';

		$editor = new WP_Image_Editor_Imagick( $file );
		$editor->load();
		$editor->resize( 5, 5 );
		$save_to_file = tempnam( get_temp_dir(), '' ) . '.png';

		$editor->save( $save_to_file );

		$im       = new Imagick( $save_to_file );
		$pixel    = $im->getImagePixelColor( 0, 0 );
		$expected = $pixel->getColorValue( imagick::COLOR_ALPHA );

		$this->assertImageAlphaAtPointImagick( $save_to_file, array( 0, 0 ), $expected );

		unlink( $save_to_file );
	}

	/**
	 * Test the image created with WP_Image_Editor_Imagick preserves alpha with no resizing etc
	 *
	 * @ticket 24871
	 */
	public function test_image_preserves_alpha() {
		$file = DIR_TESTDATA . '/images/transparent.png';

		$editor = new WP_Image_Editor_Imagick( $file );
		$editor->load();

		$save_to_file = tempnam( get_temp_dir(), '' ) . '.png';

		$editor->save( $save_to_file );

		$im       = new Imagick( $save_to_file );
		$pixel    = $im->getImagePixelColor( 0, 0 );
		$expected = $pixel->getColorValue( imagick::COLOR_ALPHA );

		$this->assertImageAlphaAtPointImagick( $save_to_file, array( 0, 0 ), $expected );

		unlink( $save_to_file );
	}

	/**
	 * @ticket 30596
	 */
	public function test_image_preserves_alpha_on_rotate() {
		$file = DIR_TESTDATA . '/images/transparent.png';

		$pre_rotate_editor = new Imagick( $file );
		$pre_rotate_pixel  = $pre_rotate_editor->getImagePixelColor( 0, 0 );
		$pre_rotate_alpha  = $pre_rotate_pixel->getColorValue( imagick::COLOR_ALPHA );
		$save_to_file      = tempnam( get_temp_dir(), '' ) . '.png';
		$pre_rotate_editor->writeImage( $save_to_file );
		$pre_rotate_editor->destroy();

		$image_editor = new WP_Image_Editor_Imagick( $save_to_file );
		$image_editor->load();
		$image_editor->rotate( 180 );
		$image_editor->save( $save_to_file );

		$this->assertImageAlphaAtPointImagick( $save_to_file, array( 0, 0 ), $pre_rotate_alpha );
		unlink( $save_to_file );
	}

	/**
	 * Test WP_Image_Editor_Imagick handles extension-less images
	 *
	 * @ticket 39195
	 */
	public function test_image_non_existent_extension() {
		$image_editor = new WP_Image_Editor_Imagick( DIR_TESTDATA . '/images/test-image-no-extension' );
		$result       = $image_editor->load();

		$this->assertTrue( $result );
	}

	/**
	 * Test resetting Exif orientation data on rotate
	 *
	 * @ticket 37140
	 * @requires function exif_read_data
	 */
	public function test_remove_orientation_data_on_rotate() {
		$file = DIR_TESTDATA . '/images/test-image-upside-down.jpg';
		$data = wp_read_image_metadata( $file );

		// The orientation value 3 is equivalent to rotated upside down (180 degrees).
		$this->assertSame( 3, (int) $data['orientation'], 'Orientation value read from does not match image file Exif data: ' . $file );

		$temp_file = wp_tempnam( $file );
		$image     = wp_get_image_editor( $file );

		// Test a value that would not lead back to 1, as WP is resetting the value to 1 manually.
		$image->rotate( 90 );
		$ret = $image->save( $temp_file, 'image/jpeg' );

		$data = wp_read_image_metadata( $ret['path'] );

		// Make sure the image is no longer in The Upside Down Exif orientation.
		$this->assertSame( 1, (int) $data['orientation'], 'Orientation Exif data was not updated after rotating image: ' . $file );

		// Remove both the generated file ending in .tmp and tmp.jpg due to wp_tempnam().
		unlink( $temp_file );
		unlink( $ret['path'] );
	}

	/**
	 * Test that images can be loaded and written over streams
	 */
	public function test_streams() {
		stream_wrapper_register( 'wptest', 'WP_Test_Stream' );
		WP_Test_Stream::$data = array(
			'Tests_Image_Editor_Imagick' => array(
				'/read.jpg' => file_get_contents( DIR_TESTDATA . '/images/waffles.jpg' ),
			),
		);

		$file                 = 'wptest://Tests_Image_Editor_Imagick/read.jpg';
		$imagick_image_editor = new WP_Image_Editor_Imagick( $file );

		$ret = $imagick_image_editor->load();
		$this->assertNotWPError( $ret );

		$temp_file = 'wptest://Tests_Image_Editor_Imagick/write.jpg';

		$ret = $imagick_image_editor->save( $temp_file );
		$this->assertNotWPError( $ret );

		$this->assertSame( $temp_file, $ret['path'] );

		if ( $temp_file !== $ret['path'] ) {
			unlink( $ret['path'] );
		}
		unlink( $temp_file );
	}

	/**
	 * @ticket 51665
	 */
	public function test_directory_creation() {
		$file      = realpath( DIR_TESTDATA ) . '/images/a2-small.jpg';
		$directory = realpath( DIR_TESTDATA ) . '/images/nonexistent-directory';
		$editor    = new WP_Image_Editor_Imagick( $file );

		$this->assertFileDoesNotExist( $directory );

		$loaded = $editor->load();
		$this->assertNotWPError( $loaded );

		$resized = $editor->resize( 100, 100, true );
		$this->assertNotWPError( $resized );

		$saved = $editor->save( $directory . '/a2-small-cropped.jpg' );
		$this->assertNotWPError( $saved );

		unlink( $directory . '/a2-small-cropped.jpg' );
		rmdir( $directory );
	}
}
