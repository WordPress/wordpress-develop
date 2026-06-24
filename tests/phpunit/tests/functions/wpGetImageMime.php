<?php

/**
 * @group functions
 * @group image
 *
 * @covers ::wp_get_image_mime
 */
class Tests_Functions_WpGetImageMime extends WP_UnitTestCase {

	/**
	 * @ticket 40017
	 * @dataProvider data_wp_get_image_mime
	 */
	public function test_wp_get_image_mime( $file, $expected ) {
		if ( ! is_callable( 'exif_imagetype' ) && ! function_exists( 'getimagesize' ) ) {
			$this->markTestSkipped( 'The exif PHP extension is not loaded.' );
		}

		if ( is_array( $expected ) ) {
			$this->assertContains( wp_get_image_mime( $file ), $expected );
		} else {
			$this->assertSame( $expected, wp_get_image_mime( $file ) );
		}
	}

	/**
	 * Data provider for test_wp_get_image_mime().
	 *
	 * @return array[]
	 */
	public function data_wp_get_image_mime() {
		$data = array(
			// Standard JPEG.
			array(
				DIR_TESTDATA . '/images/test-image.jpg',
				'image/jpeg',
			),
			// Standard GIF.
			array(
				DIR_TESTDATA . '/images/test-image.gif',
				'image/gif',
			),
			// Standard PNG.
			array(
				DIR_TESTDATA . '/images/test-image.png',
				'image/png',
			),
			// Image with wrong extension.
			array(
				DIR_TESTDATA . '/images/test-image-mime-jpg.png',
				'image/jpeg',
			),
			// Animated WebP.
			array(
				DIR_TESTDATA . '/images/webp-animated.webp',
				'image/webp',
			),
			// Lossless WebP.
			array(
				DIR_TESTDATA . '/images/webp-lossless.webp',
				'image/webp',
			),
			// Lossy WebP.
			array(
				DIR_TESTDATA . '/images/webp-lossy.webp',
				'image/webp',
			),
			// Transparent WebP.
			array(
				DIR_TESTDATA . '/images/webp-transparent.webp',
				'image/webp',
			),
			// Not an image.
			array(
				DIR_TESTDATA . '/uploads/dashicons.woff',
				false,
			),
			// Animated AVIF.
			array(
				DIR_TESTDATA . '/images/avif-animated.avif',
				'image/avif',
			),
			// Lossless AVIF.
			array(
				DIR_TESTDATA . '/images/avif-lossless.avif',
				'image/avif',
			),
			// Lossy AVIF.
			array(
				DIR_TESTDATA . '/images/avif-lossy.avif',
				'image/avif',
			),
			// Transparent AVIF.
			array(
				DIR_TESTDATA . '/images/avif-transparent.avif',
				'image/avif',
			),
			// HEIC.
			array(
				DIR_TESTDATA . '/images/test-image.heic',
				// In PHP 8.5, it returns 'image/heif'. Before that, it returns 'image/heic'.
				array( 'image/heic', 'image/heif' ),
			),
		);

		return $data;
	}
}
