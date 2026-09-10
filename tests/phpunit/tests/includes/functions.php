<?php

/**
 * Tests for the `includes/functions.php` file of the Unit Testing Framework.
 *
 * @group testsuite
 * @group phpunit
 *
 * @covers ::_delete_all_data
 */
class Tests_Includes_Functions extends WP_UnitTestCase {
	/**
	 * Verify that attachment files are deleted along with attachment posts.
	 *
	 * @ticket 41978
	 */
	public function test_delete_all_data_deletes_attachments() {
		// Create an attachment with an image.
		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/waffles.jpg'
		);

		// Retrieve the path to the image.
		$attachment_file = get_attached_file( $attachment_id );
		$this->assertFileExists( $attachment_file );

		_delete_all_data();

		// Verify that the image has been deleted along with the attachment.
		$this->assertFileDoesNotExist( $attachment_file );
		$this->assertNull( get_post( $attachment_id ) );
	}
}
