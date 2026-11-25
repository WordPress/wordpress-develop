<?php

/**
 * Test the WP_Image_Editor_GD class
 *
 * @group image
 * @group media
 * @group wp-image-editor-gd
 */
require_once __DIR__ . '/base.php';
require_once DIR_TESTROOT . '/includes/class-wp-test-stream.php';

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
	 * Tests resizing an image, not using crop.
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
	 * Tests multi_resize() with single image resize and no crop.
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
	 * Tests that multi_resize() does not create an image when
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
	 * Tests multi_resize() with multiple sizes.
	 *
	 * @ticket 26823
	 * @requires function imagejpeg
	 */
	public function test_multi_resize() {
		$file = DIR_TESTDATA . '/images/waffles.jpg';

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

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

		$resized = $gd_image_editor->multi_resize( $sizes_array );

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
	 * Tests cropping an image.
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

	public static function data_crop() {
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
	 * Tests that crop() returns WP_Error when dimensions are not integer or are <= 0.
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

		$this->assertWPError( $actual );
		$this->assertSame( 'image_crop_error', $actual->get_error_code() );
	}

	public static function data_crop_invalid_dimensions() {
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

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$color_top_left = imagecolorat( $this->get_property_value( $gd_image_editor ), 0, 0 );

		$gd_image_editor->rotate( 180 );

		$this->assertSame( $color_top_left, imagecolorat( $this->get_property_value( $gd_image_editor ), 99, 99 ) );
	}

	/**
	 * Tests flipping an image.
	 */
	public function test_flip() {
		$file = DIR_TESTDATA . '/images/gradient-square.jpg';

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$color_top_left = imagecolorat( $this->get_property_value( $gd_image_editor ), 0, 0 );

		$gd_image_editor->flip( true, false );

		$this->assertSame( $color_top_left, imagecolorat( $this->get_property_value( $gd_image_editor ), 0, 99 ) );
	}

	/**
	 * Tests that flip() moves every pixel to its mirrored position.
	 *
	 * A small non-square image with a distinct color per pixel is used so that
	 * mixed up axes, off-by-one shifts and duplicated edges are all detected.
	 *
	 * @ticket 66113
	 *
	 * @dataProvider data_flip_axes
	 *
	 * @covers WP_Image_Editor_GD::flip
	 *
	 * @param bool $horz Whether to flip along the horizontal axis.
	 * @param bool $vert Whether to flip along the vertical axis.
	 */
	public function test_flip_moves_every_pixel( $horz, $vert ) {
		$w     = 3;
		$h     = 2;
		$color = static function ( $x, $y ) {
			return ( ( $x * 40 + 10 ) << 16 ) | ( ( $y * 90 + 20 ) << 8 ) | 0x77;
		};

		$image = imagecreatetruecolor( $w, $h );
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				imagesetpixel( $image, $x, $y, $color( $x, $y ) );
			}
		}

		$gd_image_editor = new WP_Image_Editor_GD( DIR_TESTDATA . '/images/gradient-square.jpg' );
		$gd_image_editor->load();

		$this->set_property_value( $gd_image_editor, 'image', $image );

		// Keep the stored size in sync with the replaced image.
		$this->set_property_value(
			$gd_image_editor,
			'size',
			array(
				'width'  => $w,
				'height' => $h,
			)
		);

		$this->assertTrue( $gd_image_editor->flip( $horz, $vert ) );

		$flipped = $this->get_property_value( $gd_image_editor );

		$this->assertSame( $w, imagesx( $flipped ), 'The width should not change.' );
		$this->assertSame( $h, imagesy( $flipped ), 'The height should not change.' );

		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$source_x = $vert ? $w - 1 - $x : $x;
				$source_y = $horz ? $h - 1 - $y : $y;

				$this->assertSame(
					$color( $source_x, $source_y ),
					imagecolorat( $flipped, $x, $y ),
					"Pixel ($source_x,$source_y) did not move to ($x,$y)."
				);
			}
		}
	}

	/**
	 * Data provider with every combination of flip axes.
	 *
	 * @return array[]
	 */
	public static function data_flip_axes() {
		return array(
			'no flip'         => array( false, false ),
			'vertical flip'   => array( true, false ),
			'horizontal flip' => array( false, true ),
			'both'            => array( true, true ),
		);
	}

	/**
	 * Tests that flip() returns a WP_Error when no valid GD image is loaded.
	 *
	 * @ticket 66113
	 *
	 * @dataProvider data_flip_axes
	 *
	 * @covers WP_Image_Editor_GD::flip
	 *
	 * @param bool $horz Whether to flip along the horizontal axis.
	 * @param bool $vert Whether to flip along the vertical axis.
	 */
	public function test_flip_returns_error_for_invalid_image( $horz, $vert ) {
		$gd_image_editor = new WP_Image_Editor_GD( DIR_TESTDATA . '/images/gradient-square.jpg' );
		$gd_image_editor->load();

		$this->set_property_value( $gd_image_editor, 'image', false );

		$result = $gd_image_editor->flip( $horz, $vert );

		$this->assertWPError( $result, 'flip() should return a WP_Error when no valid image is loaded.' );
		$this->assertSame( 'image_flip_error', $result->get_error_code(), 'The error code should be image_flip_error.' );
	}

	/**
	 * Tests that maybe_exif_rotate() flips images whose EXIF orientation requires a flip.
	 *
	 * This is the most common way to reach flip(): uploading a mirrored or
	 * upside-down JPEG. The image is only flipped, so its size does not change
	 * and every pixel must end up at its mirrored position.
	 *
	 * @ticket 66113
	 *
	 * @dataProvider data_maybe_exif_rotate_flips_image
	 *
	 * @covers WP_Image_Editor::maybe_exif_rotate
	 * @covers WP_Image_Editor_GD::flip
	 *
	 * @param int  $orientation EXIF orientation value.
	 * @param bool $mirror_x    Whether the image should be mirrored left to right.
	 * @param bool $mirror_y    Whether the image should be mirrored top to bottom.
	 */
	public function test_maybe_exif_rotate_flips_image( $orientation, $mirror_x, $mirror_y ) {
		$file = DIR_TESTDATA . '/images/test-image-upside-down.jpg';

		// The fixture has orientation 3. Override it to cover the other flip-only orientations.
		$filter = static function () use ( $orientation ) {
			return $orientation;
		};
		add_filter( 'wp_image_maybe_exif_rotate', $filter );

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$original = $this->get_property_value( $gd_image_editor );
		$w        = imagesx( $original );
		$h        = imagesy( $original );

		// Snapshot a grid of pixels, since flip() modifies the image in place.
		$points = array();
		foreach ( array( 0, (int) ( $w / 3 ), (int) ( $w / 2 ), $w - 1 ) as $x ) {
			foreach ( array( 0, (int) ( $h / 3 ), (int) ( $h / 2 ), $h - 1 ) as $y ) {
				$points[] = array( $x, $y, imagecolorat( $original, $x, $y ) );
			}
		}

		$result = $gd_image_editor->maybe_exif_rotate();

		remove_filter( 'wp_image_maybe_exif_rotate', $filter );

		$this->assertTrue( $result, 'maybe_exif_rotate() should return true.' );
		$this->assertSame(
			array(
				'width'  => $w,
				'height' => $h,
			),
			$gd_image_editor->get_size(),
			'Flipping should not change the size.'
		);

		$flipped = $this->get_property_value( $gd_image_editor );

		foreach ( $points as list( $x, $y, $color ) ) {
			$target_x = $mirror_x ? $w - 1 - $x : $x;
			$target_y = $mirror_y ? $h - 1 - $y : $y;

			$this->assertSame(
				$color,
				imagecolorat( $flipped, $target_x, $target_y ),
				"Pixel ($x,$y) did not move to ($target_x,$target_y)."
			);
		}
	}

	/**
	 * Data provider for test_maybe_exif_rotate_flips_image().
	 *
	 * @return array[]
	 */
	public static function data_maybe_exif_rotate_flips_image() {
		return array(
			'orientation 2: mirrored horizontally' => array( 2, true, false ),
			'orientation 3: upside down'           => array( 3, true, true ),
			'orientation 4: mirrored vertically'   => array( 4, false, true ),
		);
	}

	/**
	 * Tests that flip() converts palette images to truecolor, like the previous implementation.
	 *
	 * The previous implementation always copied into a new truecolor image.
	 * Code that saves or streams the flipped image as WebP relies on that,
	 * since imagewebp() rejects palette images.
	 *
	 * @ticket 66113
	 *
	 * @dataProvider data_flip_axes
	 *
	 * @covers WP_Image_Editor_GD::flip
	 *
	 * @param bool $horz Whether to flip along the horizontal axis.
	 * @param bool $vert Whether to flip along the vertical axis.
	 */
	public function test_flip_converts_palette_image_to_truecolor( $horz, $vert ) {
		$gd_image_editor = new WP_Image_Editor_GD( DIR_TESTDATA . '/images/test-image.gif' );
		$gd_image_editor->load();

		$image = $this->get_property_value( $gd_image_editor );
		$w     = imagesx( $image );
		$h     = imagesy( $image );

		$this->assertFalse( imageistruecolor( $image ), 'The fixture should be a palette image.' );

		// Snapshot every pixel as a truecolor value, since flip() modifies the image in place.
		$colors = array();
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$rgba = imagecolorsforindex( $image, imagecolorat( $image, $x, $y ) );

				$colors[ $y ][ $x ] = ( $rgba['alpha'] << 24 ) | ( $rgba['red'] << 16 ) | ( $rgba['green'] << 8 ) | $rgba['blue'];
			}
		}

		$this->assertTrue( $gd_image_editor->flip( $horz, $vert ) );

		$flipped = $this->get_property_value( $gd_image_editor );

		$this->assertTrue( imageistruecolor( $flipped ), 'The flipped image should be a truecolor image.' );

		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$source_x = $vert ? $w - 1 - $x : $x;
				$source_y = $horz ? $h - 1 - $y : $y;

				$this->assertSame(
					$colors[ $source_y ][ $source_x ],
					imagecolorat( $flipped, $x, $y ),
					"Pixel ($source_x,$source_y) did not move to ($x,$y)."
				);
			}
		}
	}

	/**
	 * Tests that a flipped palette image can be saved as WebP.
	 *
	 * imagewebp() rejects palette images with a warning and writes an empty file,
	 * so this fails unless flip() returns a truecolor image.
	 *
	 * @ticket 66113
	 *
	 * @covers WP_Image_Editor_GD::flip
	 * @covers WP_Image_Editor_GD::save
	 */
	public function test_flipped_palette_image_can_be_saved_as_webp() {
		if ( ! WP_Image_Editor_GD::supports_mime_type( 'image/webp' ) ) {
			$this->markTestSkipped( 'This GD build does not support WebP.' );
		}

		$gd_image_editor = new WP_Image_Editor_GD( DIR_TESTDATA . '/images/test-image.gif' );
		$gd_image_editor->load();

		$this->assertTrue( $gd_image_editor->flip( true, false ) );

		$save_to_file = get_temp_dir() . wp_unique_filename( get_temp_dir(), 'flipped-palette.webp' );
		$saved        = $gd_image_editor->save( $save_to_file, 'image/webp' );

		$this->assertNotWPError( $saved, 'Saving the flipped image as WebP should not fail.' );

		$size     = wp_getimagesize( $saved['path'] );
		$filesize = filesize( $saved['path'] );

		unlink( $saved['path'] );

		$this->assertGreaterThan( 0, $filesize, 'The saved WebP file should not be empty.' );
		$this->assertIsArray( $size, 'The saved WebP file should be a valid image.' );
		$this->assertSame( 'image/webp', $size['mime'], 'The saved file should be a WebP image.' );
		$this->assertSame( array( 50, 50 ), array( $size[0], $size[1] ), 'The saved image size should not change.' );
	}

	/**
	 * Tests that flip() keeps the alpha channel of every pixel, in memory and after saving as PNG.
	 *
	 * Fully transparent pixels are left out on purpose: their hidden RGB value
	 * is not visible and is not guaranteed to survive saving.
	 *
	 * @ticket 66113
	 *
	 * @dataProvider data_flip_axes
	 *
	 * @covers WP_Image_Editor_GD::flip
	 *
	 * @param bool $horz Whether to flip along the horizontal axis.
	 * @param bool $vert Whether to flip along the vertical axis.
	 */
	public function test_flip_keeps_alpha_channel( $horz, $vert ) {
		$w     = 3;
		$h     = 2;
		$color = static function ( $x, $y ) {
			// Alpha values from 0 (opaque) to 125, all distinct.
			$alpha = ( $y * 3 + $x ) * 25;

			return ( $alpha << 24 ) | ( ( $x * 40 + 10 ) << 16 ) | ( ( $y * 90 + 20 ) << 8 ) | 0x77;
		};

		$image = imagecreatetruecolor( $w, $h );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				imagesetpixel( $image, $x, $y, $color( $x, $y ) );
			}
		}

		$source_file = get_temp_dir() . wp_unique_filename( get_temp_dir(), 'flip-alpha.png' );
		imagepng( $image, $source_file );

		$gd_image_editor = new WP_Image_Editor_GD( $source_file );
		$loaded          = $gd_image_editor->load();

		unlink( $source_file );

		$this->assertTrue( $loaded, 'The test image should load.' );
		$this->assertTrue( $gd_image_editor->flip( $horz, $vert ) );

		$flipped = $this->get_property_value( $gd_image_editor );

		$save_to_file = get_temp_dir() . wp_unique_filename( get_temp_dir(), 'flipped-alpha.png' );
		$saved        = $gd_image_editor->save( $save_to_file, 'image/png' );

		$this->assertNotWPError( $saved, 'Saving the flipped image as PNG should not fail.' );

		$reloaded = imagecreatefrompng( $saved['path'] );
		unlink( $saved['path'] );

		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$source_x = $vert ? $w - 1 - $x : $x;
				$source_y = $horz ? $h - 1 - $y : $y;
				$expected = $color( $source_x, $source_y );

				$this->assertSame(
					$expected,
					imagecolorat( $flipped, $x, $y ),
					"Pixel ($source_x,$source_y) did not move to ($x,$y) with its alpha in memory."
				);
				$this->assertSame(
					$expected,
					imagecolorat( $reloaded, $x, $y ),
					"Pixel ($source_x,$source_y) did not move to ($x,$y) with its alpha after saving."
				);
			}
		}
	}

	/**
	 * Tests that flipping along both axes gives the same image as rotating by 180 degrees.
	 *
	 * @ticket 66113
	 *
	 * @covers WP_Image_Editor_GD::flip
	 */
	public function test_flip_both_axes_matches_rotate_180() {
		$file = DIR_TESTDATA . '/images/canola.jpg';

		$flipped_editor = new WP_Image_Editor_GD( $file );
		$flipped_editor->load();
		$this->assertTrue( $flipped_editor->flip( true, true ) );

		$rotated_editor = new WP_Image_Editor_GD( $file );
		$rotated_editor->load();
		$this->assertTrue( $rotated_editor->rotate( 180 ) );

		$this->assertSame( $rotated_editor->get_size(), $flipped_editor->get_size(), 'The sizes should match.' );

		$flipped = $this->get_property_value( $flipped_editor );
		$rotated = $this->get_property_value( $rotated_editor );

		$w = imagesx( $flipped );
		$h = imagesy( $flipped );

		// Count mismatches instead of asserting each pixel, since the image is large.
		$mismatches = 0;
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				if ( imagecolorat( $flipped, $x, $y ) !== imagecolorat( $rotated, $x, $y ) ) {
					++$mismatches;
				}
			}
		}

		$this->assertSame( 0, $mismatches, 'Every pixel should match the image rotated by 180 degrees.' );
	}

	/**
	 * Tests that flip() disables alpha blending, including after rotate() enabled it.
	 *
	 * Alpha blending only affects drawing onto the image, so it is detected by
	 * drawing a fully transparent pixel: with blending disabled, it replaces the
	 * existing pixel instead of being blended into it.
	 *
	 * @ticket 66113
	 *
	 * @dataProvider data_flip_axes
	 *
	 * @covers WP_Image_Editor_GD::flip
	 *
	 * @param bool $horz Whether to flip along the horizontal axis.
	 * @param bool $vert Whether to flip along the vertical axis.
	 */
	public function test_flip_disables_alpha_blending_after_rotate( $horz, $vert ) {
		$gd_image_editor = new WP_Image_Editor_GD( DIR_TESTDATA . '/images/gradient-square.jpg' );
		$gd_image_editor->load();

		$this->assertTrue( $gd_image_editor->rotate( 90 ) );
		$this->assertTrue( $gd_image_editor->flip( $horz, $vert ) );

		$image = $this->get_property_value( $gd_image_editor );

		imagesetpixel( $image, 0, 0, imagecolorallocatealpha( $image, 0, 0, 0, 127 ) );

		$this->assertSame( 127, ( imagecolorat( $image, 0, 0 ) >> 24 ) & 0x7F, 'Alpha blending should be disabled after flipping.' );
	}

	/**
	 * Tests that flip() resets the resolution and interlace setting to GD's defaults.
	 *
	 * The previous implementation always copied into a new image, which starts
	 * at 96 DPI with interlacing disabled, like rotate(), crop() and resize().
	 *
	 * @ticket 66113
	 *
	 * @dataProvider data_flip_axes
	 *
	 * @covers WP_Image_Editor_GD::flip
	 *
	 * @param bool $horz Whether to flip along the horizontal axis.
	 * @param bool $vert Whether to flip along the vertical axis.
	 */
	public function test_flip_resets_resolution_and_interlace( $horz, $vert ) {
		$gd_image_editor = new WP_Image_Editor_GD( DIR_TESTDATA . '/images/gradient-square.jpg' );
		$gd_image_editor->load();

		$image = $this->get_property_value( $gd_image_editor );

		imageresolution( $image, 300, 150 );
		imageinterlace( $image, true );

		$this->assertTrue( $gd_image_editor->flip( $horz, $vert ) );

		$flipped = $this->get_property_value( $gd_image_editor );

		$this->assertSame( array( 96, 96 ), imageresolution( $flipped ), 'The resolution should be reset to 96 DPI.' );
		$this->assertFalse( (bool) imageinterlace( $flipped ), 'Interlacing should be disabled.' );
	}

	/**
	 * Tests that an image created with WP_Image_Editor_GD preserves alpha with no resizing.
	 *
	 * @ticket 23039
	 */
	public function test_image_preserves_alpha() {
		if ( ! ( imagetypes() & IMG_PNG ) ) {
			$this->fail( 'This test requires PHP to be compiled with PNG support.' );
		}

		$file = DIR_TESTDATA . '/images/transparent.png';

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$save_to_file = tempnam( get_temp_dir(), '' ) . '.png';

		$gd_image_editor->save( $save_to_file );

		$this->assertImageAlphaAtPointGD( $save_to_file, array( 0, 0 ), 127 );

		unlink( $save_to_file );
	}

	/**
	 * Tests that an image created with WP_Image_Editor_GD preserves alpha when resizing.
	 *
	 * @ticket 23039
	 */
	public function test_image_preserves_alpha_on_resize() {
		if ( ! ( imagetypes() & IMG_PNG ) ) {
			$this->fail( 'This test requires PHP to be compiled with PNG support.' );
		}

		$file = DIR_TESTDATA . '/images/transparent.png';

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$gd_image_editor->resize( 5, 5 );
		$save_to_file = tempnam( get_temp_dir(), '' ) . '.png';

		$gd_image_editor->save( $save_to_file );

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

		$gd_image_editor = new WP_Image_Editor_GD( $file );
		$gd_image_editor->load();

		$gd_image_editor->rotate( 180 );
		$save_to_file = tempnam( get_temp_dir(), '' ) . '.png';

		$gd_image_editor->save( $save_to_file );

		$this->assertImageAlphaAtPointGD( $save_to_file, array( 0, 0 ), $expected['alpha'] );

		unlink( $save_to_file );
	}

	/**
	 * Tests that WP_Image_Editor_GD handles extensionless images.
	 *
	 * @ticket 39195
	 */
	public function test_image_non_existent_extension() {
		$gd_image_editor = new WP_Image_Editor_GD( DIR_TESTDATA . '/images/test-image-no-extension' );

		$loaded = $gd_image_editor->load();

		$this->assertTrue( $loaded );
	}

	/**
	 * Tests that images can be saved to a stream.
	 *
	 * @covers WP_Image_Editor::make_image
	 */
	public function test_save_to_stream() {
		$saved = $this->save_to_stream( 'wptestgd', 'WP_Test_Stream' );

		$this->assertNotWPError( $saved );
		$this->assertSame( 'wptestgd://Tests_Image_Editor_GD/write.jpg', $saved['path'] );
		$this->assertNotEmpty( WP_Test_Stream::$data['Tests_Image_Editor_GD']['/write.jpg'], 'The image should have been written to the stream.' );
	}

	/**
	 * Tests that saving fails when the image cannot be fully written to the stream.
	 *
	 * @covers WP_Image_Editor::make_image
	 */
	public function test_save_to_stream_fails_when_write_fails() {
		// file_put_contents() warns about the incomplete write. $this->expectWarning() is deprecated in PHPUnit 10.
		$warnings = array();
		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$warnings ) {
				$warnings[] = $errstr;
				return true;
			},
			E_WARNING
		);

		try {
			$saved = $this->save_to_stream( 'wptestgdwritefail', 'WP_Test_Stream_GD_Write_Failure' );
		} finally {
			restore_error_handler();
		}

		$this->assertCount( 1, $warnings, 'Expected one warning about the incomplete write.' );
		$this->assertStringContainsString( 'bytes written', $warnings[0] );
		$this->assertWPError( $saved );
		$this->assertSame( 'image_save_error', $saved->get_error_code() );
	}

	/**
	 * Tests that saving fails when the stream cannot be flushed.
	 *
	 * PHP 8.6 is the first version where fclose() reports flush and close failures.
	 *
	 * @requires PHP >= 8.6
	 *
	 * @covers WP_Image_Editor::make_image
	 */
	public function test_save_to_stream_fails_when_flush_fails() {
		$saved = $this->save_to_stream( 'wptestgdflushfail', 'WP_Test_Stream_GD_Flush_Failure' );

		$this->assertWPError( $saved );
		$this->assertSame( 'image_save_error', $saved->get_error_code() );
	}

	/**
	 * Loads a JPEG image with the GD editor and saves it to a stream.
	 *
	 * @param string $protocol      The stream protocol to register.
	 * @param string $wrapper_class The stream wrapper class to register for the protocol.
	 * @return array|WP_Error The result of WP_Image_Editor_GD::save().
	 */
	private function save_to_stream( $protocol, $wrapper_class ) {
		stream_wrapper_register( $protocol, $wrapper_class );
		WP_Test_Stream::$data = array();

		$gd_image_editor = new WP_Image_Editor_GD( DIR_TESTDATA . '/images/waffles.jpg' );
		$loaded          = $gd_image_editor->load();

		$saved = $gd_image_editor->save( $protocol . '://Tests_Image_Editor_GD/write.jpg', 'image/jpeg' );

		stream_wrapper_unregister( $protocol );

		$this->assertTrue( $loaded );

		return $saved;
	}
}

/**
 * A stream wrapper that fails to write any data.
 */
class WP_Test_Stream_GD_Write_Failure extends WP_Test_Stream {
	public function stream_write( $data ) {
		return 0;
	}
}

/**
 * A stream wrapper that fails to flush, like a remote storage wrapper whose upload fails.
 */
class WP_Test_Stream_GD_Flush_Failure extends WP_Test_Stream {
	public function stream_flush() {
		return false;
	}
}
