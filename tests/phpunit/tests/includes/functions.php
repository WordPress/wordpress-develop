<?php
/**
 * Tests for the `includes/functions.php` file of the Unit Testing Framework.
 *
 * @group testsuite
 */

class Test_Includes extends WP_UnitTestCase {
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

		_delete_all_data();

		$posts = new WP_Query( array(
			'post_type'   => 'any',
			'post_status' => 'any',
		) );

		// Verify that the image has been deleted along with the attachment.
		$this->assertSame( $posts->posts, array() );
		$this->assertFalse( file_exists( $attachment_file ) );
	}
}
