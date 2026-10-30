<?php

/**
 * Tests for the `wp_image_src_get_dimensions()` function.
 *
 * @group media
 * @covers ::wp_image_src_get_dimensions
 */
class Tests_Media_wpImageSrcGetDimensions extends WP_UnitTestCase {

	protected static $attachment_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$attachment_id = $factory->attachment->create_object(
			array(
				'file'           => '2024/10/image.svg',
				'post_mime_type' => 'image/svg+xml',
			)
		);
	}

	/**
	 * Tests that `wp_image_src_get_dimensions()` returns the full size dimensions from the image meta.
	 *
	 * @ticket 62312
	 */
	public function test_should_return_full_size_dimensions() {
		wp_update_attachment_metadata(
			self::$attachment_id,
			array(
				'file'   => '2024/10/image.svg',
				'width'  => 640,
				'height' => 480,
			)
		);

		$this->assertSame(
			array( 640, 480 ),
			wp_image_src_get_dimensions(
				'http://example.org/wp-content/uploads/2024/10/image.svg',
				wp_get_attachment_metadata( self::$attachment_id ),
				self::$attachment_id
			)
		);
	}

	/**
	 * Tests that `wp_image_src_get_dimensions()` returns false without raising warnings
	 * when the full size image meta is missing width or height.
	 *
	 * @ticket 62312
	 *
	 * @dataProvider data_should_return_false_when_full_size_dimensions_are_missing
	 *
	 * @param array $image_meta The image meta data.
	 */
	public function test_should_return_false_when_full_size_dimensions_are_missing( $image_meta ) {
		wp_update_attachment_metadata( self::$attachment_id, $image_meta );

		$this->assertFalse(
			wp_image_src_get_dimensions(
				'http://example.org/wp-content/uploads/2024/10/image.svg',
				wp_get_attachment_metadata( self::$attachment_id ),
				self::$attachment_id
			)
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_return_false_when_full_size_dimensions_are_missing() {
		return array(
			'width and height missing' => array(
				'image_meta' => array(
					'file' => '2024/10/image.svg',
				),
			),
			'width missing'            => array(
				'image_meta' => array(
					'file'   => '2024/10/image.svg',
					'height' => 480,
				),
			),
			'height missing'           => array(
				'image_meta' => array(
					'file'  => '2024/10/image.svg',
					'width' => 640,
				),
			),
		);
	}
}
