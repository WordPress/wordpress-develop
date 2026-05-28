<?php

/**
 * Tests for the `wp_delete_attachment_heic_companion_file()` function.
 *
 * @group media
 * @covers ::wp_delete_attachment_heic_companion_file
 */
class Tests_Media_wpDeleteAttachmentHeicCompanionFile extends WP_UnitTestCase {

	public function tear_down(): void {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * @ticket 64915
	 */
	public function test_deletes_heic_file_recorded_in_metadata_original(): void {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		$attached_file = get_attached_file( $attachment_id, true );
		$dir           = dirname( $attached_file );
		$heic_name     = 'companion-' . wp_generate_password( 6, false ) . '.heic';
		$heic_path     = $dir . '/' . $heic_name;

		// Create a dummy companion file on disk.
		file_put_contents( $heic_path, 'test' );
		$this->assertFileExists( $heic_path, 'Test fixture should be on disk.' );

		// Record the companion under metadata['original'] as the sideload route does.
		$metadata             = wp_get_attachment_metadata( $attachment_id, true );
		$metadata['original'] = $heic_name;
		wp_update_attachment_metadata( $attachment_id, $metadata );

		wp_delete_attachment( $attachment_id, true );

		$this->assertFileDoesNotExist( $heic_path, 'Companion HEIC file should be deleted alongside the attachment.' );
	}

	/**
	 * @ticket 64915
	 */
	public function test_noop_when_metadata_original_is_missing(): void {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		// Sanity: no 'original' key on freshly-created metadata.
		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		$this->assertArrayNotHasKey( 'original', $metadata );

		// Should not raise even though the hook fires.
		wp_delete_attachment( $attachment_id, true );

		$this->assertNull( get_post( $attachment_id ) );
	}

	/**
	 * Guards against $metadata['original'] holding a non-string value (e.g.
	 * the array form some flows write). Regression coverage for GB #78128.
	 *
	 * @ticket 64915
	 */
	public function test_noop_when_metadata_original_is_not_a_string(): void {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$attached_file = get_attached_file( $attachment_id, true );

		$metadata             = wp_get_attachment_metadata( $attachment_id, true );
		$metadata['original'] = array( 'file' => 'should-not-delete.heic' );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Should not raise (no path_join() / file_exists() on an array).
		wp_delete_attachment_heic_companion_file( $attachment_id );

		$this->assertFileExists( $attached_file, 'Attached file should still be on disk; the hook must bail on non-string original.' );
	}
}
