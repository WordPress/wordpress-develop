<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_wp_updateMediaItem extends WP_XMLRPC_UnitTestCase {
	/**
	 * @var int
	 */
	protected static $post_id;

	/**
	 * @var int
	 */
	protected $attachment_id;

	/**
	 * Create test data before our tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();

		$filename = ( DIR_TESTDATA . '/images/waffles.jpg' );
		$contents = file_get_contents( $filename );
		$upload   = wp_upload_bits( wp_basename( $filename ), null, $contents );

		$this->attachment_id = $this->_make_attachment( $upload, self::$post_id );
		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', 'Initial alt text' );
	}

	/**
	 * Clean up test environment.
	 */
	public function tear_down() {
		$this->remove_added_uploads();
		parent::tear_down();
	}

	/**
	 * Test various error cases when updating media items.
	 *
	 * @ticket 59414
	 * @dataProvider data_error_cases
	 *
	 * @param string $role          User role to test with.
	 * @param string $username      Username to use.
	 * @param string $password      Password to use.
	 * @param int    $attachment_id Attachment ID to use.
	 * @param int    $expected_code Expected error code.
	 */
	public function test_error_cases( $role, $username, $password, $attachment_id, $expected_code ) {
		if ( $role ) {
			$this->make_user_by_role( $role );
		}

		$result = $this->myxmlrpcserver->wp_updateMediaItem(
			array(
				1,
				$username,
				$password,
				null === $attachment_id ? $this->attachment_id : $attachment_id,
				array(
					'title' => 'New Title',
				),
			)
		);

		$this->assertIXRError( $result );
		$this->assertSame( $expected_code, $result->code );
	}

	/**
	 * Data provider for testing error cases.
	 *
	 * @return array Test parameters {
	 *     @type string $test_name     Name of the test case
	 *     @type string $role          User role to test with
	 *     @type string $username      Username to use
	 *     @type string $password      Password to use
	 *     @type int    $attachment_id Attachment ID to use
	 *     @type int    $expected_code Expected error code
	 * }
	 */
	public static function data_error_cases() {
		return array(
			'invalid_credentials'      => array(
				'role'          => '',
				'username'      => 'username',
				'password'      => 'password',
				'attachment_id' => null,
				'expected_code' => 403,
			),
			'insufficient_permissions' => array(
				'role'          => 'subscriber',
				'username'      => 'subscriber',
				'password'      => 'subscriber',
				'attachment_id' => null,
				'expected_code' => 401,
			),
			'invalid_attachment_id'    => array(
				'role'          => 'editor',
				'username'      => 'editor',
				'password'      => 'editor',
				'attachment_id' => 99999,
				'expected_code' => 404,
			),
		);
	}

	/**
	 * Test updating all media item fields successfully.
	 *
	 * @ticket 59414
	 */
	public function test_valid_update_all_fields() {
		$this->make_user_by_role( 'editor' );

		$new_title       = 'New Test Title';
		$new_alt         = 'New Alt Text';
		$new_caption     = 'New Caption';
		$new_description = 'New Description';

		$result = $this->myxmlrpcserver->wp_updateMediaItem(
			array(
				1,
				'editor',
				'editor',
				$this->attachment_id,
				array(
					'title'       => $new_title,
					'alt'         => $new_alt,
					'caption'     => $new_caption,
					'description' => $new_description,
				),
			)
		);

		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );

		$attachment = get_post( $this->attachment_id );
		$this->assertSame( $new_title, $attachment->post_title );
		$this->assertSame( $new_caption, $attachment->post_excerpt );
		$this->assertSame( $new_description, $attachment->post_content );
		$this->assertSame( $new_alt, get_post_meta( $this->attachment_id, '_wp_attachment_image_alt', true ) );
	}

	/**
	 * Test that the action hook fires when media item is updated.
	 *
	 * @ticket 59414
	 */
	public function test_action_hook_fires() {
		$this->make_user_by_role( 'editor' );

		$action_fired = false;
		add_action(
			'xmlrpc_call_success_wp_updateMediaItem',
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		$result = $this->myxmlrpcserver->wp_updateMediaItem(
			array(
				1,
				'editor',
				'editor',
				$this->attachment_id,
				array(
					'title' => 'Hook Test Title',
				),
			)
		);

		$this->assertTrue( $action_fired, 'Action hook did not fire' );
	}
}
