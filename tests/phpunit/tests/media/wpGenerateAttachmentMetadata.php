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
	 * Tests that filesize meta is generated for JPEGs.
	 *
	 * @ticket 49412
	 *
	 * @covers ::wp_create_image_subsizes
	 */
	public function test_wp_generate_attachment_metadata_includes_filesize_in_jpg_meta() {
		$attachment = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		$metadata = wp_get_attachment_metadata( $attachment );

		$this->assertSame( wp_filesize( get_attached_file( $attachment ) ), $metadata['filesize'] );

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
				static function( $mimes ) {
					$mimes['psd'] = 'application/octet-stream';
					return $mimes;
				}
			);
		}

		$attachment = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.psd' );

		$metadata = wp_get_attachment_metadata( $attachment );

		$this->assertSame( wp_filesize( get_attached_file( $attachment ) ), $metadata['filesize'] );
	}
}
