<?php

require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 * Tests for the `wp_get_missing_image_subsizes()` function.
 *
 * @group media
 * @covers ::wp_get_missing_image_subsizes
 */
class Tests_Media_wpGetMissingImageSubsizes extends WP_UnitTestCase {

	/**
	 * Creates an attachment with the given metadata, without uploading a real file.
	 *
	 * @param string $file      Attached file name.
	 * @param string $mime_type Attachment mime type.
	 * @param array  $meta      Attachment metadata.
	 * @return int Attachment ID.
	 */
	private function create_attachment_with_meta( $file, $mime_type, $meta ) {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => $file,
				'post_mime_type' => $mime_type,
			)
		);

		wp_update_attachment_metadata( $attachment_id, $meta );

		return $attachment_id;
	}

	/**
	 * Tests that missing width or height in the image meta does not trigger
	 * an "Undefined array key" warning, and that no sub-sizes are reported
	 * as missing when the full-size dimensions are unknown.
	 *
	 * @ticket 57813
	 *
	 * @dataProvider data_meta_without_dimensions
	 *
	 * @param string $file      Attached file name.
	 * @param string $mime_type Attachment mime type.
	 * @param array  $meta      Attachment metadata missing width and/or height.
	 */
	public function test_should_not_warn_when_dimensions_are_missing( $file, $mime_type, $meta ) {
		$attachment_id = $this->create_attachment_with_meta( $file, $mime_type, $meta );

		$this->assertSame( array(), wp_get_missing_image_subsizes( $attachment_id ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_meta_without_dimensions() {
		return array(
			'svg with no dimensions'  => array(
				'file'      => 'image.svg',
				'mime_type' => 'image/svg+xml',
				'meta'      => array(
					'file'     => 'image.svg',
					'filesize' => 1024,
				),
			),
			'jpeg with no dimensions' => array(
				'file'      => 'image.jpg',
				'mime_type' => 'image/jpeg',
				'meta'      => array(
					'file'  => 'image.jpg',
					'sizes' => array(),
				),
			),
			'jpeg with no width'      => array(
				'file'      => 'image.jpg',
				'mime_type' => 'image/jpeg',
				'meta'      => array(
					'file'   => 'image.jpg',
					'height' => 900,
					'sizes'  => array(),
				),
			),
			'jpeg with no height'     => array(
				'file'      => 'image.jpg',
				'mime_type' => 'image/jpeg',
				'meta'      => array(
					'file'  => 'image.jpg',
					'width' => 1200,
					'sizes' => array(),
				),
			),
		);
	}

	/**
	 * Tests that the `wp_get_missing_image_subsizes` filter still runs
	 * when the image meta lacks width and height.
	 *
	 * @ticket 57813
	 */
	public function test_should_apply_filter_when_dimensions_are_missing() {
		$attachment_id = $this->create_attachment_with_meta(
			'image.jpg',
			'image/jpeg',
			array(
				'file'  => 'image.jpg',
				'sizes' => array(),
			)
		);

		$filter = new MockAction();
		add_filter( 'wp_get_missing_image_subsizes', array( $filter, 'filter' ), 10, 3 );

		wp_get_missing_image_subsizes( $attachment_id );

		$this->assertSame( 1, $filter->get_call_count(), 'The filter should be called once.' );

		$args = $filter->get_args();
		$this->assertSame( array(), $args[0][0], 'The filter should receive an empty array of missing sizes.' );
		$this->assertSame( $attachment_id, $args[0][2], 'The filter should receive the attachment ID.' );
	}

	/**
	 * Tests that sub-sizes are still reported as missing when the meta
	 * has valid dimensions, so the guard for missing dimensions does not
	 * short-circuit the normal path.
	 *
	 * @ticket 57813
	 */
	public function test_should_return_missing_sizes_when_dimensions_are_present() {
		$attachment_id = $this->create_attachment_with_meta(
			'image.jpg',
			'image/jpeg',
			array(
				'file'   => 'image.jpg',
				'width'  => 1200,
				'height' => 900,
				'sizes'  => array(),
			)
		);

		$missing_sizes = wp_get_missing_image_subsizes( $attachment_id );

		$this->assertNotEmpty( $missing_sizes, 'Sub-sizes should be reported as missing for an image with valid dimensions.' );
		$this->assertArrayHasKey( 'thumbnail', $missing_sizes, 'The thumbnail size should be reported as missing.' );
	}
}
