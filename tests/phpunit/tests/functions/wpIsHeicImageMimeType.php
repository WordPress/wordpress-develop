<?php

/**
 * Tests for the `wp_is_heic_image_mime_type()` function.
 *
 * @group functions
 * @group media
 *
 * @covers ::wp_is_heic_image_mime_type
 */
class Tests_Functions_WpIsHeicImageMimeType extends WP_UnitTestCase {

	/**
	 * Tests that valid HEIC and HEIF mime types return true.
	 *
	 * @ticket 66624
	 *
	 * @dataProvider data_valid_heic_mime_types
	 *
	 * @param string $mime_type The MIME type to check.
	 */
	public function test_wp_is_heic_image_mime_type_true( $mime_type ) {
		$this->assertTrue( wp_is_heic_image_mime_type( $mime_type ) );
	}

	/**
	 * Data provider of valid HEIC/HEIF MIME types.
	 *
	 * @return array[]
	 */
	public static function data_valid_heic_mime_types() {
		return array(
			'image/heic'          => array( 'image/heic' ),
			'image/heif'          => array( 'image/heif' ),
			'image/heic-sequence' => array( 'image/heic-sequence' ),
			'image/heif-sequence' => array( 'image/heif-sequence' ),
		);
	}

	/**
	 * Tests that non-HEIC mime types return false.
	 *
	 * @ticket 66624
	 *
	 * @dataProvider data_non_heic_mime_types
	 *
	 * @param string $mime_type The MIME type to check.
	 */
	public function test_wp_is_heic_image_mime_type_false( $mime_type ) {
		$this->assertFalse( wp_is_heic_image_mime_type( $mime_type ) );
	}

	/**
	 * Data provider of non-HEIC MIME types.
	 *
	 * @return array[]
	 */
	public static function data_non_heic_mime_types() {
		return array(
			'image/jpeg'      => array( 'image/jpeg' ),
			'image/png'       => array( 'image/png' ),
			'image/webp'      => array( 'image/webp' ),
			'image/avif'      => array( 'image/avif' ),
			'image/gif'       => array( 'image/gif' ),
			'application/pdf' => array( 'application/pdf' ),
			'empty string'    => array( '' ),
		);
	}
}
