<?php

/**
 * Tests that wp_delete_attachment_files() removes the 'source_image' companion file.
 *
 * @group media
 * @covers ::wp_delete_attachment_files
 */
class Tests_Media_wpDeleteAttachmentSourceImage extends WP_UnitTestCase {

	public function tear_down(): void {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * @ticket 64915
	 */
	public function test_deletes_companion_file_recorded_in_metadata_source_image(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->assertIsInt( $attachment_id );

		$attached_file = get_attached_file( $attachment_id, true );
		$this->assertIsString( $attached_file );
		$dir       = dirname( $attached_file );
		$heic_name = 'companion-' . wp_generate_password( 6, false ) . '.heic';
		$heic_path = $dir . '/' . $heic_name;

		// Create a dummy companion file on disk.
		file_put_contents( $heic_path, 'test' );
		$this->assertFileExists( $heic_path, 'Test fixture should be on disk.' );

		// Record the companion under metadata['source_image'] as the sideload route does.
		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		$this->assertIsArray( $metadata );
		$metadata['source_image'] = $heic_name;
		wp_update_attachment_metadata( $attachment_id, $metadata );

		wp_delete_attachment( $attachment_id, true );

		$this->assertNull( get_post( $attachment_id ) );
		$this->assertFileDoesNotExist( $heic_path, 'Companion file should be deleted alongside the attachment.' );
	}

	/**
	 * @ticket 64915
	 */
	public function test_noop_when_metadata_source_image_is_missing(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->assertIsInt( $attachment_id );

		// Sanity: no 'source_image' key on freshly-created metadata.
		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		$this->assertIsArray( $metadata );
		$this->assertArrayNotHasKey( 'source_image', $metadata );

		// Deletion should complete cleanly even though no companion file is recorded.
		wp_delete_attachment( $attachment_id, true );

		$this->assertNull( get_post( $attachment_id ) );
	}

	/**
	 * Guards against $metadata['source_image'] holding a non-string value (e.g.
	 * the array form some flows write). Regression coverage for GB #78128.
	 *
	 * @ticket 64915
	 */
	public function test_noop_when_metadata_source_image_is_not_a_string(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->assertIsInt( $attachment_id );
		$attached_file = get_attached_file( $attachment_id, true );
		$this->assertIsString( $attached_file );

		/*
		 * Place a real file that a buggy, guard-less implementation could try to
		 * delete after running wp_basename() over the array value below.
		 */
		$bystander_path = dirname( $attached_file ) . '/should-not-delete.heic';
		file_put_contents( $bystander_path, 'test' );
		$this->assertFileExists( $bystander_path, 'Test fixture should be on disk.' );

		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		$this->assertIsArray( $metadata );
		$metadata['source_image'] = array( 'file' => 'should-not-delete.heic' );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Deletion should not raise (no str_replace() / file deletion on an array).
		wp_delete_attachment( $attachment_id, true );

		$this->assertNull( get_post( $attachment_id ) );
		$this->assertFileExists( $bystander_path, 'The non-string guard must prevent any file deletion.' );
	}
}
