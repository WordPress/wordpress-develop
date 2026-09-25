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
		require_once DIR_TESTROOT . '/includes/class-wp-test-stream.php';

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

		// First, check to see if returned array is as expected.
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

		// Now, verify real dimensions are as expected.
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
			array(
				'height' => 0,
			),
		);

		$resized = $vips_image_editor->multi_resize( $sizes_array );

		// If no images are generated, the returned array is empty.
		$this->assertEmpty( $resized );
	}

	/**
	 * Tests multi_resize() with multiple sizes.
	 *
	 * @ticket 26823
	 */
	public function test_multi_resize() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$sizes_array = array(

			/*
			 * #0 - 10x10 resize, no cropping.
			 * By aspect, should be 10x6 output.
			 */
			array(
				'width'  => 10,
				'height' => 10,
				'crop'   => false,
			),

			/*
			 * #1 - 75x50 resize, with cropping.
			 * Output dimensions should be 75x50
			 */
			array(
				'width'  => 75,
				'height' => 50,
				'crop'   => true,
			),

			/*
			 * #2 - 20 pixel max height, no cropping.
			 * By aspect, should be 30x20 output.
			 */
			array(
				'width'  => 9999, // Arbitrary high value.
				'height' => 20,
				'crop'   => false,
			),

			/*
			 * #3 - 45 pixel max height, with cropping.
			 * By aspect, should be 45x400 output.
			 */
			array(
				'width'  => 45,
				'height' => 9999, // Arbitrary high value.
				'crop'   => true,
			),

			/*
			 * #4 - 50 pixel max width, no cropping.
			 * By aspect, should be 50x33 output.
			 */
			array(
				'width' => 50,
			),

			/*
			 * #5 - 55 pixel max width, no cropping, null height
			 * By aspect, should be 55x36 output.
			 */
			array(
				'width'  => 55,
				'height' => null,
			),

			/*
			 * #6 - 55 pixel max height, no cropping, no width specified.
			 * By aspect, should be 82x55 output.
			 */
			array(
				'height' => 55,
			),

			/*
			 * #7 - 60 pixel max height, no cropping, null width.
			 * By aspect, should be 90x60 output.
			 */
			array(
				'width'  => null,
				'height' => 60,
			),

			/*
			 * #8 - 70 pixel max height, no cropping, negative width.
			 * By aspect, should be 105x70 output.
			 */
			array(
				'width'  => -9999, // Arbitrary negative value.
				'height' => 70,
			),

			/*
			 * #9 - 200 pixel max width, no cropping, negative height.
			 * By aspect, should be 200x133 output.
			 */
			array(
				'width'  => 200,
				'height' => -9999, // Arbitrary negative value.
			),
		);

		$resized = $vips_image_editor->multi_resize( $sizes_array );

		$expected_array = array(

			// #0
			array(
				'file'      => 'waffles-10x7.jpg',
				'width'     => 10,
				'height'    => 7,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-10x7.jpg' ),
			),

			// #1
			array(
				'file'      => 'waffles-75x50.jpg',
				'width'     => 75,
				'height'    => 50,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-75x50.jpg' ),
			),

			// #2
			array(
				'file'      => 'waffles-30x20.jpg',
				'width'     => 30,
				'height'    => 20,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-30x20.jpg' ),
			),

			// #3
			array(
				'file'      => 'waffles-45x400.jpg',
				'width'     => 45,
				'height'    => 400,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-45x400.jpg' ),
			),

			// #4
			array(
				'file'      => 'waffles-50x33.jpg',
				'width'     => 50,
				'height'    => 33,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-50x33.jpg' ),
			),

			// #5
			array(
				'file'      => 'waffles-55x37.jpg',
				'width'     => 55,
				'height'    => 37,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-55x37.jpg' ),
			),

			// #6
			array(
				'file'      => 'waffles-83x55.jpg',
				'width'     => 83,
				'height'    => 55,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-83x55.jpg' ),
			),

			// #7
			array(
				'file'      => 'waffles-90x60.jpg',
				'width'     => 90,
				'height'    => 60,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-90x60.jpg' ),
			),

			// #8
			array(
				'file'      => 'waffles-105x70.jpg',
				'width'     => 105,
				'height'    => 70,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-105x70.jpg' ),
			),

			// #9
			array(
				'file'      => 'waffles-200x133.jpg',
				'width'     => 200,
				'height'    => 133,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-200x133.jpg' ),
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
	 * Tests resizing an image with cropping.
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
	 * Tests cropping an image.
	 *
	 * @ticket 51937
	 *
	 * @dataProvider data_crop
	 */
	public function test_crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {
		$file = DIR_TESTDATA . '/images/gradient-square.jpg';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$vips_image_editor->crop( $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h, $src_abs );

		$this->assertSame(
			array(
				'width'  => (int) $src_w,
				'height' => (int) $src_h,
			),
			$vips_image_editor->get_size()
		);
	}

	/**
	 * Data provider for valid crop dimensions.
	 *
	 * @return array<string,array>
	 */
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
	 * Tests that crop() returns WP_Error for invalid dimensions.
	 *
	 * @ticket 51937
	 *
	 * @dataProvider data_crop_invalid_dimensions
	 */
	public function test_crop_invalid_dimensions( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {
		$file = DIR_TESTDATA . '/images/gradient-square.jpg';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$actual = $vips_image_editor->crop( $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h, $src_abs );

		$this->assertInstanceOf( 'WP_Error', $actual );
		$this->assertSame( 'image_crop_error', $actual->get_error_code() );
	}

	/**
	 * Data provider for invalid crop dimensions.
	 *
	 * @return array<string,array>
	 */
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
	 * Tests rotating an image 180 deg.
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
	 * Tests that flipping along the horizontal axis mirrors the image vertically.
	 */
	public function test_flip() {
		$file = DIR_TESTDATA . '/images/gradient-square.jpg';

		// Save a lossless copy of the unmodified image to sample the source pixels from.
		$original_editor = new WP_Image_Editor_Vips( $file );
		$original_editor->load();

		$size          = $original_editor->get_size();
		$original_tmp  = tempnam( get_temp_dir(), 'vips_flip_' );
		$original_file = $original_tmp . '.png';
		$original_editor->save( $original_file );

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$flipped = $vips_image_editor->flip( true, false );
		$this->assertTrue( $flipped );

		$flipped_tmp  = tempnam( get_temp_dir(), 'vips_flip_' );
		$flipped_file = $flipped_tmp . '.png';
		$vips_image_editor->save( $flipped_file );

		$original_image = imagecreatefrompng( $original_file );
		$flipped_image  = imagecreatefrompng( $flipped_file );

		// Flipping along the horizontal axis moves the bottom-left pixel to the top-left.
		$expected = imagecolorsforindex( $original_image, imagecolorat( $original_image, 0, $size['height'] - 1 ) );
		$actual   = imagecolorsforindex( $flipped_image, imagecolorat( $flipped_image, 0, 0 ) );

		imagedestroy( $original_image );
		imagedestroy( $flipped_image );

		unlink( $original_tmp );
		unlink( $original_file );
		unlink( $flipped_tmp );
		unlink( $flipped_file );

		$this->assertSame( $expected['red'], $actual['red'] );
		$this->assertSame( $expected['green'], $actual['green'] );
		$this->assertSame( $expected['blue'], $actual['blue'] );
	}

	/**
	 * Tests that WP_Image_Editor_Vips handles extensionless images.
	 *
	 * @ticket 39195
	 */
	public function test_image_non_existent_extension() {
		$vips_image_editor = new WP_Image_Editor_Vips( DIR_TESTDATA . '/images/test-image-no-extension' );

		$loaded = $vips_image_editor->load();

		$this->assertTrue( $loaded );
	}

	/**
	 * Tests that images can be loaded and written over streams.
	 */
	public function test_streams() {
		if ( ! in_array( 'wptest', stream_get_wrappers(), true ) ) {
			stream_wrapper_register( 'wptest', 'WP_Test_Stream' );
		}
		WP_Test_Stream::$data = array(
			'Tests_Image_Editor_Vips' => array(
				'/read.jpg' => file_get_contents( DIR_TESTDATA . '/images/waffles.jpg' ),
			),
		);

		$file              = 'wptest://Tests_Image_Editor_Vips/read.jpg';
		$vips_image_editor = new WP_Image_Editor_Vips( $file );

		$loaded = $vips_image_editor->load();
		$this->assertNotWPError( $loaded );

		$temp_file = 'wptest://Tests_Image_Editor_Vips/write.jpg';
		$saved     = $vips_image_editor->save( $temp_file );

		if ( $temp_file !== $saved['path'] ) {
			unlink( $saved['path'] );
		}

		unlink( $temp_file );

		$this->assertNotWPError( $saved );
		$this->assertSame( $temp_file, $saved['path'] );
	}

	/**
	 * Tests that an image created with WP_Image_Editor_Vips preserves alpha.
	 */
	public function test_image_preserves_alpha() {
		$file = DIR_TESTDATA . '/images/transparent.png';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$save_to_file = tempnam( get_temp_dir(), '' ) . '.png';

		$vips_image_editor->save( $save_to_file );

		// Use GD to check the alpha channel since VIPS doesn't have a direct PHP API
		$this->assertImageAlphaAtPointGD( $save_to_file, array( 0, 0 ), 127 );

		unlink( $save_to_file );
	}

	/**
	 * Tests that an image created with WP_Image_Editor_Vips preserves alpha when resizing.
	 *
	 * @ticket 23039
	 */
	public function test_image_preserves_alpha_on_resize() {
		$file = DIR_TESTDATA . '/images/transparent.png';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$vips_image_editor->resize( 5, 5 );
		$save_to_file = tempnam( get_temp_dir(), '' ) . '.png';

		$vips_image_editor->save( $save_to_file );

		$this->assertImageAlphaAtPointGD( $save_to_file, array( 0, 0 ), 127 );

		unlink( $save_to_file );
	}

	/**
	 * Tests that an image created with WP_Image_Editor_Vips preserves alpha when rotating.
	 *
	 * @ticket 30596
	 */
	public function test_image_preserves_alpha_on_rotate() {
		$file = DIR_TESTDATA . '/images/transparent.png';

		// Get expected alpha from original image
		$image    = imagecreatefrompng( $file );
		$rgb      = imagecolorat( $image, 0, 0 );
		$expected = imagecolorsforindex( $image, $rgb );

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$vips_image_editor->rotate( 180 );
		$save_to_file = tempnam( get_temp_dir(), '' ) . '.png';

		$vips_image_editor->save( $save_to_file );

		$this->assertImageAlphaAtPointGD( $save_to_file, array( 0, 0 ), $expected['alpha'] );

		unlink( $save_to_file );
	}

	/**
	 * Tests that the image_strip_meta filter controls whether metadata is stripped
	 * when the image is resized.
	 *
	 * @requires extension exif
	 */
	public function test_image_strip_meta_filter() {
		$file = DIR_TESTDATA . '/images/test-image-iptc.jpg';

		$strip_meta = static function () {
			return false;
		};

		// Metadata is stripped by default.
		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$this->assertNotWPError( $vips_image_editor->resize( 25, 25 ) );

		$stripped_file = tempnam( get_temp_dir(), 'vips_meta_' ) . '.jpg';
		$vips_image_editor->save( $stripped_file );

		$stripped = wp_read_image_metadata( $stripped_file );
		unlink( $stripped_file );

		$this->assertEmpty( $stripped['caption'], 'Metadata should be stripped by default.' );

		// The filter can prevent stripping.
		add_filter( 'image_strip_meta', $strip_meta );

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$this->assertNotWPError( $vips_image_editor->resize( 25, 25 ) );

		$preserved_file = tempnam( get_temp_dir(), 'vips_meta_' ) . '.jpg';
		$vips_image_editor->save( $preserved_file );

		remove_filter( 'image_strip_meta', $strip_meta );

		$preserved = wp_read_image_metadata( $preserved_file );
		unlink( $preserved_file );

		$this->assertNotEmpty( $preserved['caption'], 'Metadata should be preserved when the filter returns false.' );
	}

	/**
	 * Tests that metadata survives a save that does not resize the image.
	 *
	 * Metadata is only stripped when the image is resized or cropped, matching the
	 * Imagick editor, so the filter returning true is not enough on its own.
	 *
	 * @requires extension exif
	 */
	public function test_metadata_is_preserved_when_saving_without_resizing() {
		$file = DIR_TESTDATA . '/images/test-image-iptc.jpg';

		add_filter( 'image_strip_meta', '__return_true' );

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$saved_file = tempnam( get_temp_dir(), 'vips_meta_' ) . '.jpg';
		$vips_image_editor->save( $saved_file );

		remove_filter( 'image_strip_meta', '__return_true' );

		$saved = wp_read_image_metadata( $saved_file );
		unlink( $saved_file );

		$this->assertNotEmpty( $saved['caption'], 'Metadata should be preserved when the image is not resized.' );
	}

	/**
	 * Tests that a single loaded image can be read more than once.
	 *
	 * The source must not be opened with sequential access, which permits only one pass
	 * and fails the second read with a "VipsJpeg: out of order read" error.
	 */
	public function test_repeated_reads_from_one_instance() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$size = $vips_image_editor->get_size();

		$first_file  = tempnam( get_temp_dir(), 'vips_reads_' ) . '.jpg';
		$second_file = tempnam( get_temp_dir(), 'vips_reads_' ) . '.jpg';

		$first  = $vips_image_editor->save( $first_file );
		$second = $vips_image_editor->save( $second_file );

		$this->assertNotWPError( $first );
		$this->assertNotWPError( $second );

		$this->assertImageDimensions( $first_file, $size['width'], $size['height'] );
		$this->assertImageDimensions( $second_file, $size['width'], $size['height'] );

		unlink( $first_file );
		unlink( $second_file );
	}
}
