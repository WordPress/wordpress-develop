<?php

/**
 * Tests for sampleImage downsampling logic in WP_Image_Editor_Imagick.
 *
 * @group image
 * @group media
 * @group wp-image-editor-imagick
 *
 * @ticket 66284
 */
class Tests_Image_ImagickSampleImage extends WP_UnitTestCase {

	public function set_up() {
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';

		parent::set_up();
	}

	/**
	 * Creates an instance of the mock image editor with custom size and mock Imagick image.
	 *
	 * @param int $width  Image width.
	 * @param int $height Image height.
	 * @return array Editor and Mock image.
	 */
	protected function get_mock_editor( $width, $height ) {
		$mock_image = new class() {
			public $calls = array();

			// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Mocking Imagick methods.
			public function sampleImage( $width, $height ) {
				$this->calls['sampleImage'][] = array(
					'width'  => $width,
					'height' => $height,
				);
				return true;
			}

			public function resizeImage( $width, $height, $filter = null, $blur = 1 ) {
				$this->calls['resizeImage'][] = array(
					'width'  => $width,
					'height' => $height,
				);
				return true;
			}

			public function scaleImage( $width, $height ) {
				$this->calls['scaleImage'][] = array(
					'width'  => $width,
					'height' => $height,
				);
				return true;
			}

			public function setOption( $key, $value ) {
				return true;
			}

			public function cropImage( $w, $h, $x, $y ) {
				$this->calls['cropImage'][] = array(
					'width'  => $w,
					'height' => $h,
					'x'      => $x,
					'y'      => $y,
				);
				return true;
			}

			public function setImagePage( $w, $h, $x, $y ) {
				return true;
			}

			public function getImageGeometry() {
				return array(
					'width'  => 500,
					'height' => 500,
				);
			}
			// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		};

		$editor = new class( null ) extends WP_Image_Editor_Imagick {
			public function set_test_size( $width, $height ) {
				$this->size = array(
					'width'  => (int) $width,
					'height' => (int) $height,
				);
			}

			public function set_test_image( $mock ) {
				$this->image = $mock;
			}

			public function call_thumbnail_image( $dst_w, $dst_h, $filter_name = 'FILTER_TRIANGLE', $strip_meta = false ) {
				return $this->thumbnail_image( $dst_w, $dst_h, $filter_name, $strip_meta );
			}
		};

		$editor->set_test_size( $width, $height );
		$editor->set_test_image( $mock_image );

		return array( $editor, $mock_image );
	}

	/**
	 * Tests that sampleImage is skipped when 5x target dimensions exceed original dimensions.
	 *
	 * This addresses Trac #66284 where scaling ultra-large images (e.g. 102MP) to 2560px
	 * resulted in attempting to upsample the image to 12800x9600 before downscaling.
	 */
	public function test_sample_image_skipped_when_sampled_dimensions_exceed_original() {
		// 102 Megapixel image: 11656 x 8742. Target scaled size: 2560 x 1920.
		list( $editor, $mock_image ) = $this->get_mock_editor( 11656, 8742 );

		// 2560 * 5 = 12800 (> 11656), 1920 * 5 = 9600 (> 8742).
		$editor->call_thumbnail_image( 2560, 1920 );

		$this->assertArrayNotHasKey(
			'sampleImage',
			$mock_image->calls,
			'sampleImage should not be called when 5x destination size exceeds original dimensions.'
		);
	}

	/**
	 * Tests that sampleImage is executed when 5x target dimensions are smaller than original.
	 */
	public function test_sample_image_executed_for_large_downscale() {
		// 102 Megapixel image: 11656 x 8742. Target thumbnail size: 150 x 112.
		list( $editor, $mock_image ) = $this->get_mock_editor( 11656, 8742 );

		// 150 * 5 = 750 (< 11656), 112 * 5 = 560 (< 8742).
		$editor->call_thumbnail_image( 150, 112 );

		$this->assertArrayHasKey(
			'sampleImage',
			$mock_image->calls,
			'sampleImage should be called when 5x destination size is smaller than original dimensions.'
		);
		$this->assertSame(
			array(
				array(
					'width'  => 750,
					'height' => 560,
				),
			),
			$mock_image->calls['sampleImage'],
			'sampleImage should be called with 5x destination dimensions.'
		);
	}

	/**
	 * Tests that sampleImage is executed for intermediate sizes like 1024px when original is large enough.
	 */
	public function test_sample_image_executed_when_both_dimensions_strictly_smaller() {
		// 102 Megapixel image: 11656 x 8742. Target size: 1024 x 768.
		list( $editor, $mock_image ) = $this->get_mock_editor( 11656, 8742 );

		// 1024 * 5 = 5120 (< 11656), 768 * 5 = 3840 (< 8742).
		$editor->call_thumbnail_image( 1024, 768 );

		$this->assertArrayHasKey(
			'sampleImage',
			$mock_image->calls,
			'sampleImage should be called when 5x destination size is strictly smaller than original.'
		);
		$this->assertSame(
			array(
				array(
					'width'  => 5120,
					'height' => 3840,
				),
			),
			$mock_image->calls['sampleImage']
		);
	}

	/**
	 * Tests that sampleImage is skipped when resize ratio is greater than 1/3^2 (~0.111).
	 */
	public function test_sample_image_skipped_when_resize_ratio_not_small_enough() {
		// 5000 x 5000 image. Target: 2500 x 2500.
		// resize_ratio = (2500 / 5000) * (2500 / 5000) = 0.25 > 0.111.
		list( $editor, $mock_image ) = $this->get_mock_editor( 5000, 5000 );

		$editor->call_thumbnail_image( 2500, 2500 );

		$this->assertArrayNotHasKey(
			'sampleImage',
			$mock_image->calls,
			'sampleImage should not be called when resize ratio is >= 0.111.'
		);
	}

	/**
	 * Tests that sampleImage is skipped when 5x destination dimensions are <= 128px.
	 */
	public function test_sample_image_skipped_when_resampled_dimensions_too_small() {
		// 1000 x 1000 image. Target: 20 x 20.
		// 20 * 5 = 100 <= 128.
		list( $editor, $mock_image ) = $this->get_mock_editor( 1000, 1000 );

		$editor->call_thumbnail_image( 20, 20 );

		$this->assertArrayNotHasKey(
			'sampleImage',
			$mock_image->calls,
			'sampleImage should not be called when sampled dimensions are <= 128.'
		);
	}

	/**
	 * Tests that sampleImage is skipped when one dimension exceeds original dimension (asymmetric aspect ratio).
	 */
	public function test_sample_image_skipped_when_one_dimension_exceeds_original() {
		// Wide panorama image: 10000 x 2000.
		// Target: 2200 x 440.
		// resize_ratio = (2200/10000) * (440/2000) = 0.22 * 0.22 = 0.0484 < 0.111.
		// 2200 * 5 = 11000 (> 10000 original width!).
		// 440 * 5 = 2200 (> 2000 original height!).
		list( $editor, $mock_image ) = $this->get_mock_editor( 10000, 2000 );

		$editor->call_thumbnail_image( 2200, 440 );

		$this->assertArrayNotHasKey(
			'sampleImage',
			$mock_image->calls,
			'sampleImage should not be called when sampled dimension exceeds original dimension.'
		);
	}

	/**
	 * Tests that crop with downscaling updates size so sampleImage respects cropped dimensions.
	 */
	public function test_crop_with_downscale_uses_cropped_dimensions_for_sample_image() {
		// Original is 10000 x 10000.
		list( $editor, $mock_image ) = $this->get_mock_editor( 10000, 10000 );

		// Crop to 500 x 500, with destination 200 x 200.
		// 200 * 5 = 1000, which exceeds 500 (the cropped dimension).
		// Without updating size after cropImage, 1000 would be compared to 10000 and erroneously upsample.
		$editor->crop( 0, 0, 500, 500, 200, 200 );

		$this->assertArrayNotHasKey(
			'sampleImage',
			$mock_image->calls,
			'sampleImage should not be called when 5x destination exceeds cropped dimensions.'
		);
	}

	/**
	 * Tests that real Imagick editor gracefully resizes an image if extension is available.
	 */
	public function test_real_imagick_resizes_correctly_when_extension_available() {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'WP_Image_Editor_Imagick' ) || ! WP_Image_Editor_Imagick::test() ) {
			$this->markTestSkipped( 'The imagick extension is not available.' );
		}

		$file   = DIR_TESTDATA . '/images/waffles.jpg';
		$editor = new WP_Image_Editor_Imagick( $file );
		$loaded = $editor->load();

		$this->assertNotWPError( $loaded );

		$resized = $editor->resize( 100, 50 );
		$this->assertTrue( $resized );
		$this->assertSame(
			array(
				'width'  => 75,
				'height' => 50,
			),
			$editor->get_size()
		);
	}
}
