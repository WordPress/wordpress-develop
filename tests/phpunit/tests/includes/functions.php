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

	/**
	 * @ticket 66512
	 *
	 * @covers ::_test_filter_build_unique_id
	 *
	 * @dataProvider data_test_filter_build_unique_id_matches_core
	 *
	 * @param callable $callback The callback to build an ID for.
	 */
	public function test_test_filter_build_unique_id_matches_core( $callback ) {
		$this->assertSame(
			_wp_filter_build_unique_id( 'test_hook', $callback, 10 ),
			_test_filter_build_unique_id( 'test_hook', $callback, 10 )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_test_filter_build_unique_id_matches_core() {
		return array(
			'function name'        => array( '__return_true' ),
			'static method string' => array( 'WP_Hook::build_preinitialized_hooks' ),
			'static method array'  => array( array( 'WP_Hook', 'build_preinitialized_hooks' ) ),
			'object method'        => array( array( new ArrayObject(), 'count' ) ),
			'closure'              => array( static function () {} ),
		);
	}
}
