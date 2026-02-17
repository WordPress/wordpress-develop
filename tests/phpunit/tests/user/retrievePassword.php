<?php
/**
 * Test cases for the `retrieve_password()` function.
 *
 * @package WordPress
 * @since 6.0.0
 *
 * @group user
 * @covers ::retrieve_password
 */
class Tests_User_RetrievePassword extends WP_UnitTestCase {
	/**
	 * Test user.
	 *
	 * @since 6.0.0
	 *
	 * @var WP_User $user
	 */
	protected $user;

	/**
	 * Create users for tests.
	 *
	 * @since 6.0.0
	 */
	public function set_up() {
		parent::set_up();

		// Create the user.
		$this->user = self::factory()->user->create_and_get(
			array(
				'user_login' => 'jane',
				'user_email' => 'r.jane@example.com',
			)
		);
	}

	/**
	 * The function should not error when the email was sent.
	 *
	 * @ticket 54690
	 */
	public function test_retrieve_password_reset_notification_email() {
		$this->assertNotWPError( retrieve_password( $this->user->user_login ), 'Sending password reset notification email failed.' );
	}

	/**
	 * The function should error when the email was not sent.
	 *
	 * @ticket 54690
	 */
	public function test_retrieve_password_should_return_wp_error_on_failed_email() {
		add_filter(
			'retrieve_password_notification_email',
			static function () {
				return array( 'message' => '' );
			}
		);

		$this->assertWPError( retrieve_password( $this->user->user_login ), 'Sending password reset notification email succeeded.' );
	}

	/**
	 * @ticket 53634
	 */
	public function test_retrieve_password_should_fetch_user_by_login_if_not_found_by_email() {
		self::factory()->user->create(
			array(
				'user_login' => 'foo@example.com',
				'user_email' => 'bar@example.com',
			)
		);

		$this->assertTrue( retrieve_password( 'foo@example.com' ), 'Fetching user by login failed.' );
		$this->assertTrue( retrieve_password( 'bar@example.com' ), 'Fetching user by email failed.' );
	}

	/**
	 * Tests that PHP 8.1 "passing null to non-nullable" deprecation notice
	 * is not thrown when the `$user_login` parameter is empty.
	 *
	 * The notice that we should not see:
	 * `Deprecated: trim(): Passing null to parameter #1 ($string) of type string is deprecated`.
	 *
	 * @ticket 62298
	 */
	public function test_retrieve_password_does_not_throw_deprecation_notice_with_default_parameters() {
		$this->assertWPError( retrieve_password() );
	}

	/**
	 * Tests that a fatal error is not thrown when the login passed via `$_POST`
	 * is an array instead of a string.
	 *
	 * The message that we should not see:
	 * `TypeError: trim(): Argument #1 ($string) must be of type string, array given`.
	 *
	 * @ticket 62794
	 */
	public function test_retrieve_password_does_not_throw_fatal_error_with_array_parameters() {
		$_POST['user_login'] = array( 'example' );

		$error = retrieve_password();
		$this->assertWPError( $error, 'The result should be an instance of WP_Error.' );

		$error_codes = $error->get_error_codes();
		$this->assertContains( 'empty_username', $error_codes, 'The "empty_username" error code should be present.' );
	}
}
