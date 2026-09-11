<?php
/**
 * Test cases for the `wp_create_user_request()` function.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.2.0
 *
 * @group privacy
 * @covers ::wp_create_user_request
 */
class Tests_Privacy_wpCreateUserRequest extends WP_UnitTestCase {
	/**
	 * Request ID.
	 *
	 * @since 5.2.0
	 *
	 * @var int $request_id
	 */
	protected static $request_id;

	/**
	 * Request email for a registered user.
	 *
	 * @since 5.2.0
	 *
	 * @var string $registered_user_email
	 */
	protected static $registered_user_email;

	/**
	 * Request email for a non-registered user.
	 *
	 * @since 5.2.0
	 *
	 * @var string $non_registered_user_email
	 */
	protected static $non_registered_user_email;

	/**
	 * Test user ID.
	 *
	 * @since 5.2.0
	 *
	 * @var string $user_id
	 */
	protected static $user_id;

	/**
	 * Create fixtures.
	 *
	 * @since 5.2.0
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$registered_user_email     = 'export@local.test';
		self::$non_registered_user_email = 'non-registered-user@local.test';

		self::$user_id = $factory->user->create(
			array(
				'user_email' => self::$registered_user_email,
			)
		);

		self::$request_id = $factory->post->create(
			array(
				'post_type'   => 'user_request',
				'post_author' => self::$user_id,
				'post_name'   => 'export_personal_data',
				'post_status' => 'request-pending',
				'post_title'  => self::$registered_user_email,
			)
		);
	}

	/**
	 * Ensure a WP_Error is returned when an invalid email is passed.
	 *
	 * @ticket 44707
	 */
	public function test_invalid_email() {
		$actual = wp_create_user_request( 'not-a-valid-email', 'export_personal_data' );

		$this->assertWPError( $actual );
		$this->assertSame( 'invalid_email', $actual->get_error_code() );
	}

	/**
	 * Ensure a WP_Error is returned when no action is passed.
	 *
	 * @ticket 46536
	 */
	public function test_missing_action() {
		$actual = wp_create_user_request( self::$registered_user_email, false );

		$this->assertWPError( $actual );
		$this->assertSame( 'invalid_action', $actual->get_error_code() );
	}

	/**
	 * Ensure a WP_Error is returned when an invalid action is passed.
	 *
	 * @ticket 44707
	 * @ticket 46536
	 */
	public function test_invalid_action() {
		$actual = wp_create_user_request( self::$registered_user_email, 'invalid_action_name' );

		$this->assertWPError( $actual );
		$this->assertSame( 'invalid_action', $actual->get_error_code() );
	}

	/**
	 * When there are incomplete requests for a registered user, a WP_Error should be returned.
	 *
	 * @ticket 44707
	 */
	public function test_failure_due_to_incomplete_registered_user() {
		// Second request (duplicated).
		$actual = wp_create_user_request( self::$registered_user_email, 'export_personal_data' );

		$this->assertWPError( $actual );
		$this->assertSame( 'duplicate_request', $actual->get_error_code() );
	}

	/**
	 * When there are incomplete requests for an non-registered user, a WP_Error should be returned.
	 *
	 * @ticket 44707
	 */
	public function test_failure_due_to_incomplete_unregistered_user() {
		// Update first request.
		wp_update_post(
			array(
				'ID'          => self::$request_id,
				'post_author' => 0,
				'post_title'  => self::$non_registered_user_email,
			)
		);

		// Second request (duplicated).
		$actual = wp_create_user_request( self::$non_registered_user_email, 'export_personal_data' );

		$this->assertWPError( $actual );
		$this->assertSame( 'duplicate_request', $actual->get_error_code() );
	}

	/**
	 * Ensure emails are properly sanitized.
	 *
	 * @ticket 44707
	 */
	public function test_sanitized_email() {
		$actual = wp_create_user_request( 'some(email<withinvalid\characters@local.test', 'export_personal_data' );

		$this->assertNotWPError( $actual );

		$post = get_post( $actual );

		$this->assertSame( 'export_personal_data', $post->post_name );
		$this->assertSame( 'someemailwithinvalidcharacters@local.test', $post->post_title );
	}

	/**
	 * Ensure action names are properly sanitized.
	 *
	 * @ticket 44707
	 */
	public function test_sanitized_action_name() {
		$actual = wp_create_user_request( self::$non_registered_user_email, 'export[_person*al_\data' );

		$this->assertNotWPError( $actual );

		$post = get_post( $actual );

		$this->assertSame( 'export_personal_data', $post->post_name );
		$this->assertSame( self::$non_registered_user_email, $post->post_title );
	}

	/**
	 * Test a user request is created successfully for a registered user.
	 *
	 * @ticket 44707
	 */
	public function test_create_request_registered_user() {
		wp_delete_post( self::$request_id, true );

		$test_data = array(
			'test-data'  => 'test value here',
			'test index' => 'more privacy data',
		);

		$actual = wp_create_user_request( self::$registered_user_email, 'export_personal_data', $test_data );

		$this->assertNotWPError( $actual );

		$post = get_post( $actual );

		$this->assertSame( self::$user_id, (int) $post->post_author );
		$this->assertSame( 'export_personal_data', $post->post_name );
		$this->assertSame( self::$registered_user_email, $post->post_title );
		$this->assertSame( 'request-pending', $post->post_status );
		$this->assertSame( 'user_request', $post->post_type );
		$this->assertSame( wp_json_encode( $test_data ), $post->post_content );
	}

	/**
	 * Test a user request is created successfully for an non-registered user.
	 *
	 * @ticket 44707
	 */
	public function test_create_request_unregistered_user() {
		wp_delete_post( self::$request_id, true );

		$test_data = array(
			'test-data'  => 'test value here',
			'test index' => 'more privacy data',
		);

		$actual = wp_create_user_request( self::$non_registered_user_email, 'export_personal_data', $test_data );

		$this->assertNotWPError( $actual );

		$post = get_post( $actual );

		$this->assertSame( 0, (int) $post->post_author );
		$this->assertSame( 'export_personal_data', $post->post_name );
		$this->assertSame( self::$non_registered_user_email, $post->post_title );
		$this->assertSame( 'request-pending', $post->post_status );
		$this->assertSame( 'user_request', $post->post_type );
		$this->assertSame( wp_json_encode( $test_data ), $post->post_content );
	}

	/**
	 * Test that a pre-existing request for the same registered user that is not pending or confirmed status does not
	 * block a new request.
	 *
	 * @ticket 44707
	 */
	public function test_completed_request_does_not_block_new_request() {
		// Update first request.
		wp_update_post(
			array(
				'ID'          => self::$request_id,
				'post_status' => 'request-completed', // Not 'request-pending' or 'request-confirmed'.
			)
		);

		// Second request.
		$actual = wp_create_user_request( self::$registered_user_email, 'export_personal_data' );

		$this->assertNotWPError( $actual );

		$post = get_post( $actual );

		$this->assertSame( self::$registered_user_email, $post->post_title );
		$this->assertSame( 'request-pending', $post->post_status );
		$this->assertSame( 'user_request', $post->post_type );
	}

	/**
	 * Test that a pre-existing request for the same non-registered user that is not pending or confirmed status does not
	 * block a new request.
	 *
	 * @ticket 44707
	 */
	public function test_completed_request_does_not_block_new_request_for_unregistered_user() {
		wp_update_post(
			array(
				'ID'          => self::$request_id,
				'post_author' => 0,
				'post_title'  => self::$non_registered_user_email,
				'post_status' => 'request-failed', // Not 'request-pending' or 'request-confirmed'.
			)
		);

		$actual = wp_create_user_request( self::$non_registered_user_email, 'export_personal_data' );

		$this->assertNotWPError( $actual );

		$post = get_post( $actual );

		$this->assertSame( 0, (int) $post->post_author );
		$this->assertSame( 'export_personal_data', $post->post_name );
		$this->assertSame( self::$non_registered_user_email, $post->post_title );
		$this->assertSame( 'request-pending', $post->post_status );
		$this->assertSame( 'user_request', $post->post_type );
	}

	/**
	 * Test that an error from `wp_insert_post()` is returned.
	 *
	 * @ticket 44707
	 */
	public function test_wp_error_returned_from_wp_insert_post() {
		wp_delete_post( self::$request_id, true );

		add_filter( 'wp_insert_post_empty_content', '__return_true' );
		$actual = wp_create_user_request( self::$registered_user_email, 'export_personal_data' );

		$this->assertWPError( $actual );
		$this->assertSame( 'empty_content', $actual->get_error_code() );
	}

	/**
	 * Test that the request has a Pending status by default.
	 *
	 * @ticket 43890
	 */
	public function test_wp_create_user_request_default_pending_status() {
		$actual = wp_create_user_request( self::$non_registered_user_email, 'export_personal_data' );
		$post   = get_post( $actual );

		$this->assertSame( 'request-pending', $post->post_status );
	}

	/**
	 * Test that the request has a Pending status if the $status param is 'pending'.
	 *
	 * @ticket 43890
	 */
	public function test_wp_create_user_request_pending_status() {
		$actual = wp_create_user_request( self::$non_registered_user_email, 'export_personal_data', array(), 'pending' );
		$post   = get_post( $actual );

		$this->assertSame( 'request-pending', $post->post_status );
	}

	/**
	 * Test that the request has a Confirmed status if the $status param is 'confirmed'.
	 *
	 * @ticket 43890
	 */
	public function test_wp_create_user_request_confirmed_status() {
		$actual = wp_create_user_request( self::$non_registered_user_email, 'export_personal_data', array(), 'confirmed' );
		$post   = get_post( $actual );

		$this->assertSame( 'request-confirmed', $post->post_status );
	}

	/**
	 * Test that the request returns a WP_Error if $status isn't 'pending' or 'confirmed'.
	 *
	 * @ticket 43890
	 */
	public function test_wp_create_user_request_wp_error_status() {
		$actual = wp_create_user_request( self::$non_registered_user_email, 'export_personal_data', array(), 'wrong-status' );

		$this->assertWPError( $actual );
	}
}
