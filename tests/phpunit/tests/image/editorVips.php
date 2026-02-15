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
			array(
				'width'  => 10,
				'height' => 10,
				'crop'   => false,
			),
			array(
				'width'  => 75,
				'height' => 50,
				'crop'   => true,
			),
			array(
				'width'  => 9999,
				'height' => 20,
				'crop'   => false,
			),
			array(
				'width'  => 45,
				'height' => 9999,
				'crop'   => true,
			),
			array(
				'width' => 50,
			),
			array(
				'width'  => 55,
				'height' => null,
			),
			array(
				'height' => 55,
			),
			array(
				'width'  => null,
				'height' => 60,
			),
			array(
				'width'  => -9999,
				'height' => 70,
			),
			array(
				'width'  => 200,
				'height' => -9999,
			),
		);

		$resized = $vips_image_editor->multi_resize( $sizes_array );

		$expected_array = array(
			array(
				'file'      => 'waffles-10x7.jpg',
				'width'     => 10,
				'height'    => 7,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-10x7.jpg' ),
			),
			array(
				'file'      => 'waffles-75x50.jpg',
				'width'     => 75,
				'height'    => 50,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-75x50.jpg' ),
			),
			array(
				'file'      => 'waffles-30x20.jpg',
				'width'     => 30,
				'height'    => 20,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-30x20.jpg' ),
			),
			array(
				'file'      => 'waffles-45x400.jpg',
				'width'     => 45,
				'height'    => 400,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-45x400.jpg' ),
			),
			array(
				'file'      => 'waffles-50x33.jpg',
				'width'     => 50,
				'height'    => 33,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-50x33.jpg' ),
			),
			array(
				'file'      => 'waffles-55x37.jpg',
				'width'     => 55,
				'height'    => 37,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-55x37.jpg' ),
			),
			array(
				'file'      => 'waffles-83x55.jpg',
				'width'     => 83,
				'height'    => 55,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-83x55.jpg' ),
			),
			array(
				'file'      => 'waffles-90x60.jpg',
				'width'     => 90,
				'height'    => 60,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-90x60.jpg' ),
			),
			array(
				'file'      => 'waffles-105x70.jpg',
				'width'     => 105,
				'height'    => 70,
				'mime-type' => 'image/jpeg',
				'filesize'  => wp_filesize( dirname( $file ) . '/waffles-105x70.jpg' ),
			),
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

			$this->assertImageDimensions(
				$image_path,
				$expected_array[ $key ]['width'],
				$expected_array[ $key ]['height']
			);
		}
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
	 * Tests crop behavior.
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
			'src dimensions as ints'            => array(
				'src_x' => 0,
				'src_y' => 0,
				'src_w' => 50,
				'src_h' => 50,
			),
			'src dimensions as numeric strings' => array(
				'src_x' => 10,
				'src_y' => '10',
				'src_w' => '50',
				'src_h' => '50',
			),
			'dst dimensions as ints'            => array(
				'src_x' => 10,
				'src_y' => 10,
				'src_w' => 150,
				'src_h' => 150,
				'dst_w' => 150,
				'dst_h' => 150,
			),
			'dst dimensions as numeric strings' => array(
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
				'src_y' => 10,
				'src_w' => 0,
				'src_h' => 100,
			),
			'src height must be numeric and greater than 0' => array(
				'src_x' => 10,
				'src_y' => 10,
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
			'src and dst dimensions must be greater than 0' => array(
				'src_x' => 0,
				'src_y' => 0,
				'src_w' => 0,
				'src_h' => 0,
				'dst_w' => 0,
				'dst_h' => 0,
			),
			'src and dst dimensions as strings must be greater than 0' => array(
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
		$file = DIR_TESTDATA . '/images/one-blue-pixel-100x100.png';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );
		$vips_image_editor->load();

		$save_to_file = tempnam( get_temp_dir(), '' ) . '.png';

		$result = $vips_image_editor->flip( true, false );
		$vips_image_editor->save( $save_to_file );

		$this->assertTrue( $result );

		$verify = new WP_Image_Editor_Vips( $save_to_file );
		$verify->load();
		$property = new ReflectionProperty( $verify, 'image' );

		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		$image = $property->getValue( $verify );
		if ( ! is_callable( array( $image, 'getpoint' ) ) ) {
			unlink( $save_to_file );
			$this->markTestSkipped( 'The image editor does not support getpoint().' );
		}

		$pixel = $image->getpoint( 0, 99 );
		$this->assertSame( 255, (int) $pixel[2] );

		unlink( $save_to_file );
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
		stream_wrapper_register( 'wptest', 'WP_Test_Stream' );
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
	 * @ticket 51665
	 */
	public function test_directory_creation() {
		$file      = realpath( DIR_TESTDATA ) . '/images/a2-small.jpg';
		$directory = realpath( DIR_TESTDATA ) . '/images/nonexistent-directory';

		$vips_image_editor = new WP_Image_Editor_Vips( $file );

		$this->assertFileDoesNotExist( $directory );

		$loaded = $vips_image_editor->load();
		$this->assertNotWPError( $loaded );

		$resized = $vips_image_editor->resize( 100, 100, true );
		$this->assertNotWPError( $resized );

		$saved = $vips_image_editor->save( $directory . '/a2-small-cropped.jpg' );

		unlink( $directory . '/a2-small-cropped.jpg' );
		rmdir( $directory );

		$this->assertNotWPError( $saved );
	}
}
