<?php

/**
 * @group media
 * @group media_filesize
 */
class Tests_Image_Filesize extends WP_UnitTestCase {
	function tearDown() {
		$this->remove_added_uploads();

		parent::tearDown();
	}

	/**
	 * Check that filesize meta is generated for jpegs.
	 *
	 * @ticket 49412
	 */
	function test_filesize_in_jpg_meta() {
		$attachment = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/33772.jpg' );

		$metadata = wp_get_attachment_metadata( $attachment );

		$this->assertEquals( round_to_nearest_thousand( $metadata['filesize'] ), 177000 );

		foreach ( $metadata['sizes'] as $intermediate_size ) {
			$this->assertTrue( ! empty( $intermediate_size['filesize'] ) && is_numeric( $intermediate_size['filesize'] ) );
		}
	}

	/**
	 * Check that filesize meta is generated for pngs.
	 *
	 * @ticket 49412
	 */
	function test_filesize_in_png_meta() {
		$attachment = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );

		$metadata = wp_get_attachment_metadata( $attachment );

		$this->assertEquals( round_to_nearest_thousand( $metadata['filesize'] ), 1000 );

		foreach ( $metadata['sizes'] as $intermediate_size ) {
			$this->assertTrue( ! empty( $intermediate_size['filesize'] ) && is_numeric( $intermediate_size['filesize'] ) );
		}
	}

	/**
	 * Check that filesize meta is generated for pdfs.
	 *
	 * @ticket 49412
	 */
	function test_filesize_in_pdf_meta() {
		$attachment = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/wordpress-gsoc-flyer.pdf' );

		$metadata = wp_get_attachment_metadata( $attachment );

		$this->assertEquals( round_to_nearest_thousand( $metadata['filesize'] ), 13000 );
	}

	/**
	 * Check that filesize meta is generated for psds.
	 *
	 * @ticket 49412
	 */
	function test_filesize_in_psd_meta() {
		$attachment = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.psd' );

		$metadata = wp_get_attachment_metadata( $attachment );

		$this->assertEquals( round_to_nearest_thousand( $metadata['filesize'] ), 41000 );
	}
}
