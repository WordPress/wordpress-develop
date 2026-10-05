<?php

/**
 * Tests that wp_delete_attachment_files() removes the transcoded video companion.
 *
 * @group media
 * @covers ::wp_delete_attachment_files
 */
class Tests_Media_wpDeleteAttachmentOptimizedVideo extends WP_UnitTestCase {

	public function tear_down(): void {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * @ticket 65998
	 */
	public function test_deletes_companion_recorded_in_metadata(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/uploads/small-video.mov' );
		$this->assertIsInt( $attachment_id );

		$attached_file = get_attached_file( $attachment_id, true );
		$this->assertIsString( $attached_file );
		$dir        = dirname( $attached_file );
		$video_name = 'optimized-' . wp_generate_password( 6, false ) . '.mp4';
		$video_path = $dir . '/' . $video_name;

		// Create a dummy companion file on disk.
		file_put_contents( $video_path, 'test' );
		$this->assertFileExists( $video_path, 'Video fixture should be on disk.' );

		// Record the companion as the finalize route does.
		$metadata                    = (array) wp_get_attachment_metadata( $attachment_id, true );
		$metadata['optimized_video'] = $video_name;
		wp_update_attachment_metadata( $attachment_id, $metadata );

		wp_delete_attachment( $attachment_id, true );

		$this->assertNull( get_post( $attachment_id ) );
		$this->assertFileDoesNotExist( $video_path, 'Video companion should be deleted alongside the attachment.' );
		$this->assertFileDoesNotExist( $attached_file, 'The original video should be deleted as before.' );
	}

	/**
	 * @ticket 65998
	 */
	public function test_noop_when_no_companion_metadata(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/uploads/small-video.mov' );
		$this->assertIsInt( $attachment_id );

		$metadata = (array) wp_get_attachment_metadata( $attachment_id, true );
		$this->assertArrayNotHasKey( 'optimized_video', $metadata );

		// Deletion should complete cleanly even though no companion file is recorded.
		wp_delete_attachment( $attachment_id, true );

		$this->assertNull( get_post( $attachment_id ) );
	}

	/**
	 * A path-traversal value in the metadata only ever resolves inside the
	 * attachment's own directory, so a file outside it is never touched.
	 *
	 * @ticket 65998
	 */
	public function test_traversal_value_does_not_delete_outside_attachment_dir(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/uploads/small-video.mov' );
		$this->assertIsInt( $attachment_id );

		$attached_file = get_attached_file( $attachment_id, true );
		$this->assertIsString( $attached_file );

		// A file one level above the attachment's directory.
		$outside_path = dirname( $attached_file, 2 ) . '/outside-' . wp_generate_password( 6, false ) . '.mp4';
		file_put_contents( $outside_path, 'test' );
		$this->assertFileExists( $outside_path, 'Test fixture should be on disk.' );

		$metadata                    = (array) wp_get_attachment_metadata( $attachment_id, true );
		$metadata['optimized_video'] = '../' . wp_basename( $outside_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		wp_delete_attachment( $attachment_id, true );

		$this->assertNull( get_post( $attachment_id ) );
		$this->assertFileExists( $outside_path, 'A file outside the attachment directory must not be deleted.' );

		wp_delete_file( $outside_path );
	}

	/**
	 * Guards against the companion key holding a non-string value.
	 *
	 * @ticket 65998
	 */
	public function test_noop_when_companion_metadata_is_not_a_string(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/uploads/small-video.mov' );
		$this->assertIsInt( $attachment_id );
		$attached_file = get_attached_file( $attachment_id, true );
		$this->assertIsString( $attached_file );

		$bystander_path = dirname( $attached_file ) . '/should-not-delete.mp4';
		file_put_contents( $bystander_path, 'test' );
		$this->assertFileExists( $bystander_path, 'Test fixture should be on disk.' );

		$metadata                    = (array) wp_get_attachment_metadata( $attachment_id, true );
		$metadata['optimized_video'] = array( 'file' => 'should-not-delete.mp4' );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		wp_delete_attachment( $attachment_id, true );

		$this->assertNull( get_post( $attachment_id ) );
		$this->assertFileExists( $bystander_path, 'The non-string guard must prevent any file deletion.' );

		wp_delete_file( $bystander_path );
	}
}
