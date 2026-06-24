<?php

/**
 * @group functions
 * @group media
 *
 * @covers ::wp_getimagesize
 */
class Tests_Functions_WpGetImageSize extends WP_UnitTestCase {
	/**
	 * @ticket 35725
	 * @dataProvider data_wp_getimagesize
	 */
	public function test_wp_getimagesize( $file, $expected ) {
		if ( ! is_callable( 'exif_imagetype' ) && ! function_exists( 'getimagesize' ) ) {
			$this->markTestSkipped( 'The exif PHP extension is not loaded.' );
		}

		$result = wp_getimagesize( $file );

		// The getimagesize() function varies in its response, so
		// let's restrict comparison to expected keys only.
		if ( is_array( $expected ) ) {
			foreach ( $expected as $k => $v ) {
				$this->assertArrayHasKey( $k, $result );
				$this->assertSame( $expected[ $k ], $result[ $k ] );
			}
		} else {
			$this->assertSame( $expected, $result );
		}
	}

	/**
	 * Data provider for test_wp_getimagesize().
	 */
	public function data_wp_getimagesize() {
		$data = array(
			// Standard JPEG.
			array(
				DIR_TESTDATA . '/images/test-image.jpg',
				array(
					50,
					50,
					IMAGETYPE_JPEG,
					'width="50" height="50"',
					'mime' => 'image/jpeg',
				),
			),
			// Standard GIF.
			array(
				DIR_TESTDATA . '/images/test-image.gif',
				array(
					50,
					50,
					IMAGETYPE_GIF,
					'width="50" height="50"',
					'mime' => 'image/gif',
				),
			),
			// Standard PNG.
			array(
				DIR_TESTDATA . '/images/test-image.png',
				array(
					50,
					50,
					IMAGETYPE_PNG,
					'width="50" height="50"',
					'mime' => 'image/png',
				),
			),
			// Image with wrong extension.
			array(
				DIR_TESTDATA . '/images/test-image-mime-jpg.png',
				array(
					50,
					50,
					IMAGETYPE_JPEG,
					'width="50" height="50"',
					'mime' => 'image/jpeg',
				),
			),
			// Animated WebP.
			array(
				DIR_TESTDATA . '/images/webp-animated.webp',
				array(
					100,
					100,
					IMAGETYPE_WEBP,
					'width="100" height="100"',
					'mime' => 'image/webp',
				),
			),
			// Lossless WebP.
			array(
				DIR_TESTDATA . '/images/webp-lossless.webp',
				array(
					1200,
					675,
					IMAGETYPE_WEBP,
					'width="1200" height="675"',
					'mime' => 'image/webp',
				),
			),
			// Lossy WebP.
			array(
				DIR_TESTDATA . '/images/webp-lossy.webp',
				array(
					1200,
					675,
					IMAGETYPE_WEBP,
					'width="1200" height="675"',
					'mime' => 'image/webp',
				),
			),
			// Transparent WebP.
			array(
				DIR_TESTDATA . '/images/webp-transparent.webp',
				array(
					1200,
					675,
					IMAGETYPE_WEBP,
					'width="1200" height="675"',
					'mime' => 'image/webp',
				),
			),
			// Not an image.
			array(
				DIR_TESTDATA . '/uploads/dashicons.woff',
				false,
			),
			// Animated AVIF.
			array(
				DIR_TESTDATA . '/images/avif-animated.avif',
				array(
					150,
					150,
					IMAGETYPE_AVIF,
					'width="150" height="150"',
					'mime' => 'image/avif',
				),
			),
			// Lossless AVIF.
			array(
				DIR_TESTDATA . '/images/avif-lossless.avif',
				array(
					400,
					400,
					IMAGETYPE_AVIF,
					'width="400" height="400"',
					'mime' => 'image/avif',
				),
			),
			// Lossy AVIF.
			array(
				DIR_TESTDATA . '/images/avif-lossy.avif',
				array(
					400,
					400,
					IMAGETYPE_AVIF,
					'width="400" height="400"',
					'mime' => 'image/avif',
				),
			),
			// Transparent AVIF.
			array(
				DIR_TESTDATA . '/images/avif-transparent.avif',
				array(
					128,
					128,
					IMAGETYPE_AVIF,
					'width="128" height="128"',
					'mime' => 'image/avif',
				),
			),
			// Grid AVIF.
			array(
				DIR_TESTDATA . '/images/avif-alpha-grid2x1.avif',
				array(
					199,
					200,
					IMAGETYPE_AVIF,
					'width="199" height="200"',
					'mime' => 'image/avif',
				),
			),
		);

		return $data;
	}

	/**
	 * Tests that wp_getimagesize() correctly handles HEIC image files.
	 *
	 * @ticket 53645
	 */
	public function test_wp_getimagesize_heic() {
		if ( ! is_callable( 'exif_imagetype' ) && ! function_exists( 'getimagesize' ) ) {
			$this->markTestSkipped( 'The exif PHP extension is not loaded.' );
		}

		$file = DIR_TESTDATA . '/images/test-image.heic';

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) || ! $editor->supports_mime_type( 'image/heic' ) ) {
			$this->markTestSkipped( 'No HEIC support in the editor engine on this system.' );
		}

		$expected = array(
			1180,
			1180,
			IMAGETYPE_HEIF,
			'width="1180" height="1180"',
		);

		// As of PHP 8.5.0, getimagesize() supports HEIF/HEIC files.
		if ( PHP_VERSION_ID >= 80500 ) {
			$expected = array_merge(
				$expected,
				array(
					'bits'        => 8,
					'channels'    => 3,
					'mime'        => 'image/heif',
					'width_unit'  => 'px',
					'height_unit' => 'px',
				)
			);
		} else {
			$expected['mime'] = 'image/heic';
		}

		$result = wp_getimagesize( $file );
		$this->assertSame( $expected, $result );
	}
}
