<?php

/**
 * Tests for the `wp_generate_attachment_metadata()` function.
 *
 * @group media
 * @covers ::wp_generate_attachment_metadata
 */
class Tests_Media_wpGenerateAttachmentMetadata extends WP_UnitTestCase {

	public function tear_down() {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * Skips the current test when no image editor is available.
	 *
	 * @param string $file Image file path.
	 */
	private function skip_without_image_editor( $file ) {
		if ( is_wp_error( wp_get_image_editor( $file ) ) ) {
			$this->markTestSkipped( 'No image editor is available.' );
		}
	}

	/**
	 * Creates a JPEG file with trailing bytes in the uploads directory.
	 *
	 * @return string Image file path.
	 */
	private function create_jpeg_with_trailing_bytes() {
		$uploads  = wp_upload_dir();
		$filename = wp_unique_filename( $uploads['path'], 'canola.jpg' );
		$file     = trailingslashit( $uploads['path'] ) . $filename;

		wp_mkdir_p( $uploads['path'] );
		copy( DIR_TESTDATA . '/images/canola.jpg', $file );
		file_put_contents( $file, str_repeat( 'x', 300 * KB_IN_BYTES ), FILE_APPEND );

		return $file;
	}

	/**
	 * Creates attachment metadata for an image file.
	 *
	 * @param string $file Image file path.
	 * @return array {
	 *     Attachment data.
	 *
	 *     @type int   $0 Attachment ID.
	 *     @type array $1 Attachment metadata.
	 * }
	 */
	private function create_image_attachment_metadata( $file ) {
		$attachment = $this->factory->attachment->create_object(
			array(
				'post_mime_type' => 'image/jpeg',
				'file'           => $file,
			)
		);
		$metadata   = wp_generate_attachment_metadata( $attachment, $file );

		wp_update_attachment_metadata( $attachment, $metadata );

		return array( $attachment, $metadata );
	}

	/**
	 * Returns an unreachable recompression savings threshold.
	 *
	 * @return int
	 */
	public function filter_recompress_original_image_minimum_savings() {
		return 100;
	}

	/**
	 * Tests that filesize meta is generated for JPEGs.
	 *
	 * @ticket 49412
	 * @ticket 57003
	 *
	 * @covers ::wp_create_image_subsizes
	 */
	public function test_wp_generate_attachment_metadata_includes_filesize_in_jpg_meta() {
		$attachment = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		$metadata = wp_get_attachment_metadata( $attachment );
		$file     = get_attached_file( $attachment );

		$this->assertSame( wp_filesize( $file ), $metadata['filesize'] );
		$this->assertLessThan( 256 * KB_IN_BYTES, wp_filesize( DIR_TESTDATA . '/images/canola.jpg' ) );
		$this->assertStringNotContainsString( '-compressed.', wp_basename( $file ) );
		$this->assertArrayNotHasKey( 'original_image', $metadata );

		foreach ( $metadata['sizes'] as $intermediate_size ) {
			$this->assertArrayHasKey( 'filesize', $intermediate_size );
			$this->assertNotEmpty( $intermediate_size['filesize'] );
			$this->assertIsNumeric( $intermediate_size['filesize'] );
		}
	}

	/**
	 * Checks that filesize meta is generated for PNGs.
	 *
	 * @ticket 49412
	 *
	 * @covers ::wp_create_image_subsizes
	 */
	public function test_wp_generate_attachment_metadata_includes_filesize_in_png_meta() {
		$attachment = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );

		$metadata = wp_get_attachment_metadata( $attachment );

		$this->assertSame( wp_filesize( get_attached_file( $attachment ) ), $metadata['filesize'] );
	}

	/**
	 * Checks that filesize meta is generated for PDFs.
	 *
	 * @ticket 49412
	 */
	public function test_wp_generate_attachment_metadata_includes_filesize_in_pdf_meta() {
		$attachment = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/wordpress-gsoc-flyer.pdf' );

		$metadata = wp_get_attachment_metadata( $attachment );

		$this->assertSame( wp_filesize( get_attached_file( $attachment ) ), $metadata['filesize'] );
	}

	/**
	 * Checks that filesize meta is generated for PSDs.
	 *
	 * @ticket 49412
	 */
	public function test_wp_generate_attachment_metadata_includes_filesize_in_psd_meta() {
		if ( is_multisite() ) {
			// PSD mime type is not allowed by default on multisite.
			add_filter(
				'upload_mimes',
				static function ( $mimes ) {
					$mimes['psd'] = 'application/octet-stream';
					return $mimes;
				}
			);
		}

		$attachment = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.psd' );

		$metadata = wp_get_attachment_metadata( $attachment );

		$this->assertSame( wp_filesize( get_attached_file( $attachment ) ), $metadata['filesize'] );
	}

	/**
	 * Tests that the original image is recompressed when the new file is smaller.
	 *
	 * @ticket 57003
	 * @covers ::wp_create_image_subsizes
	 */
	public function test_wp_generate_attachment_metadata_recompresses_original_image_when_smaller() {
		$this->skip_without_image_editor( DIR_TESTDATA . '/images/canola.jpg' );

		list( $attachment, $metadata ) = $this->create_image_attachment_metadata( $this->create_jpeg_with_trailing_bytes() );
		$file                          = get_attached_file( $attachment );
		$original                      = wp_get_original_image_path( $attachment );

		$this->assertStringContainsString( '-compressed.', wp_basename( $file ) );
		$this->assertArrayHasKey( 'original_image', $metadata );
		$this->assertSame( wp_basename( $original ), $metadata['original_image'] );
		$this->assertFileExists( $file );
		$this->assertFileExists( $original );
		$this->assertLessThan( wp_filesize( $original ), wp_filesize( $file ) );
		$this->assertSame( wp_filesize( $file ), $metadata['filesize'] );
	}

	/**
	 * Tests that the original image is kept when savings are not large enough.
	 *
	 * @ticket 57003
	 * @covers ::wp_create_image_subsizes
	 */
	public function test_wp_generate_attachment_metadata_keeps_original_image_when_recompression_savings_are_too_small() {
		$this->skip_without_image_editor( DIR_TESTDATA . '/images/canola.jpg' );

		add_filter( 'wp_recompress_original_image_minimum_savings', array( $this, 'filter_recompress_original_image_minimum_savings' ) );

		$file      = $this->create_jpeg_with_trailing_bytes();
		$collision = preg_replace( '/(\.[^.]+)$/', '-compressed$1', $file );

		file_put_contents( $collision, 'collision' );

		try {
			list( $attachment, $metadata ) = $this->create_image_attachment_metadata( $file );
			$attached_file                 = get_attached_file( $attachment );
			$compressed                    = preg_replace( '/(\.[^.]+)$/', '-compressed-1$1', $attached_file );

			$this->assertStringNotContainsString( '-compressed.', wp_basename( $attached_file ) );
			$this->assertArrayNotHasKey( 'original_image', $metadata );
			$this->assertFileExists( $attached_file );
			$this->assertFileExists( $collision );
			$this->assertFileDoesNotExist( $compressed );
			$this->assertSame( wp_filesize( $attached_file ), $metadata['filesize'] );
		} finally {
			remove_filter( 'wp_recompress_original_image_minimum_savings', array( $this, 'filter_recompress_original_image_minimum_savings' ) );
		}
	}

	/**
	 * Tests that ineligible images are not recompressed.
	 *
	 * @ticket 57003
	 * @covers ::wp_create_image_subsizes
	 */
	public function test_wp_generate_attachment_metadata_does_not_recompress_ineligible_images() {
		$this->skip_without_image_editor( DIR_TESTDATA . '/images/canola.jpg' );

		$file = $this->create_jpeg_with_trailing_bytes();

		add_filter( 'wp_recompress_original_image', '__return_false' );

		try {
			list( $attachment, $metadata ) = $this->create_image_attachment_metadata( $file );
			$attached_file                 = get_attached_file( $attachment );

			$this->assertStringNotContainsString( '-compressed.', wp_basename( $attached_file ) );
			$this->assertArrayNotHasKey( 'original_image', $metadata );
			$this->assertSame( wp_filesize( $attached_file ), $metadata['filesize'] );
		} finally {
			remove_filter( 'wp_recompress_original_image', '__return_false' );
		}

		$attachment = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );
		$metadata   = wp_get_attachment_metadata( $attachment );
		$file       = get_attached_file( $attachment );

		$this->assertStringNotContainsString( '-compressed.', wp_basename( $file ) );
		$this->assertArrayNotHasKey( 'original_image', $metadata );
		$this->assertSame( wp_filesize( $file ), $metadata['filesize'] );

		$attachment = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image-large.jpg' );
		$metadata   = wp_get_attachment_metadata( $attachment );
		$file       = get_attached_file( $attachment );

		$this->assertStringContainsString( '-scaled.', wp_basename( $file ) );
		$this->assertStringNotContainsString( '-compressed.', wp_basename( $file ) );
		$this->assertArrayHasKey( 'original_image', $metadata );

		$file       = DIR_TESTDATA . '/images/test-image-rotated-90ccw.jpg';
		$metadata   = wp_read_image_metadata( $file );
		$can_rotate = wp_image_editor_supports(
			array(
				'mime_type' => 'image/jpeg',
				'methods'   => array( 'rotate' ),
			)
		);

		if ( empty( $metadata['orientation'] ) || 1 === (int) $metadata['orientation'] || ! $can_rotate ) {
			return;
		}

		$attachment          = $this->factory->attachment->create_upload_object( $file );
		$attachment_metadata = wp_get_attachment_metadata( $attachment );
		$attached_file       = get_attached_file( $attachment );

		$this->assertStringContainsString( '-rotated.', wp_basename( $attached_file ) );
		$this->assertStringNotContainsString( '-compressed.', wp_basename( $attached_file ) );
		$this->assertArrayHasKey( 'original_image', $attachment_metadata );
	}

	/**
	 * Checks that large PNG uploads generate PNG `-scaled` thumbnails.
	 *
	 * @ticket 62900
	 */
	public function test_wp_generate_attachment_metadata_png_thumbnail_smaller_than_original() {
		// Use the test-image-large.png test file.
		$attachment = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/png-tests/test-image-large.png' );

		$metadata = wp_get_attachment_metadata( $attachment );

		// Check that the full sized image with `-scaled` is created for the PNG.
		$this->assertStringContainsString( '-scaled.png', basename( $metadata['file'] ) );
	}
}
