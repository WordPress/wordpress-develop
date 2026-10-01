<?php

/**
 * Tests that wp_delete_attachment_files() removes the animated-GIF video companions.
 *
 * @group media
 * @covers ::wp_delete_attachment_files
 */
class Tests_Media_wpDeleteAttachmentAnimatedGifVideo extends WP_UnitTestCase {

	public function tear_down(): void {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * @ticket 65549
	 */
	public function test_deletes_video_and_poster_companions(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->assertIsInt( $attachment_id );

		$attached_file = get_attached_file( $attachment_id, true );
		$this->assertIsString( $attached_file );
		$dir         = dirname( $attached_file );
		$video_name  = 'companion-' . wp_generate_password( 6, false ) . '.mp4';
		$poster_name = 'companion-' . wp_generate_password( 6, false ) . '.jpg';
		$video_path  = $dir . '/' . $video_name;
		$poster_path = $dir . '/' . $poster_name;

		// Create dummy companion files on disk.
		file_put_contents( $video_path, 'test' );
		file_put_contents( $poster_path, 'test' );
		$this->assertFileExists( $video_path, 'Video fixture should be on disk.' );
		$this->assertFileExists( $poster_path, 'Poster fixture should be on disk.' );

		// Record the companions as the sideload route does.
		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		$this->assertIsArray( $metadata );
		$metadata['animated_video']        = $video_name;
		$metadata['animated_video_poster'] = $poster_name;
		wp_update_attachment_metadata( $attachment_id, $metadata );

		wp_delete_attachment( $attachment_id, true );

		$this->assertNull( get_post( $attachment_id ) );
		$this->assertFileDoesNotExist( $video_path, 'Video companion should be deleted alongside the attachment.' );
		$this->assertFileDoesNotExist( $poster_path, 'Poster companion should be deleted alongside the attachment.' );
	}

	/**
	 * @ticket 65549
	 */
	public function test_deletes_only_video_when_no_poster_recorded(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->assertIsInt( $attachment_id );

		$attached_file = get_attached_file( $attachment_id, true );
		$this->assertIsString( $attached_file );
		$dir        = dirname( $attached_file );
		$video_name = 'companion-' . wp_generate_password( 6, false ) . '.mp4';
		$video_path = $dir . '/' . $video_name;

		file_put_contents( $video_path, 'test' );
		$this->assertFileExists( $video_path, 'Video fixture should be on disk.' );

		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		$this->assertIsArray( $metadata );
		$metadata['animated_video'] = $video_name;
		wp_update_attachment_metadata( $attachment_id, $metadata );

		wp_delete_attachment( $attachment_id, true );

		$this->assertNull( get_post( $attachment_id ) );
		$this->assertFileDoesNotExist( $video_path, 'Video companion should be deleted alongside the attachment.' );
	}

	/**
	 * @ticket 65549
	 */
	public function test_noop_when_no_companion_metadata(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->assertIsInt( $attachment_id );

		// Sanity: no companion keys on freshly-created metadata.
		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		$this->assertIsArray( $metadata );
		$this->assertArrayNotHasKey( 'animated_video', $metadata );
		$this->assertArrayNotHasKey( 'animated_video_poster', $metadata );

		// Deletion should complete cleanly even though no companion file is recorded.
		wp_delete_attachment( $attachment_id, true );

		$this->assertNull( get_post( $attachment_id ) );
	}

	/**
	 * Guards against a companion key holding a non-string value (e.g. the array
	 * form some metadata flows write).
	 *
	 * @ticket 65549
	 */
	public function test_noop_when_companion_metadata_is_not_a_string(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->assertIsInt( $attachment_id );
		$attached_file = get_attached_file( $attachment_id, true );
		$this->assertIsString( $attached_file );

		/*
		 * Place a real file that a buggy, guard-less implementation could try to
		 * delete after running wp_basename() over the array value below.
		 */
		$bystander_path = dirname( $attached_file ) . '/should-not-delete.mp4';
		file_put_contents( $bystander_path, 'test' );
		$this->assertFileExists( $bystander_path, 'Test fixture should be on disk.' );

		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		$this->assertIsArray( $metadata );
		$metadata['animated_video'] = array( 'file' => 'should-not-delete.mp4' );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Deletion should not raise (no str_replace() / file deletion on an array).
		wp_delete_attachment( $attachment_id, true );

		$this->assertNull( get_post( $attachment_id ) );
		$this->assertFileExists( $bystander_path, 'The non-string guard must prevent any file deletion.' );
	}
}
