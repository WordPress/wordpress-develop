<?php
/**
 * Test cases for the `retrieve_password()` function.
 *
 * @package WordPress
 * @since 6.0.0
 */

/**
 * Test retrieve_password(), in wp-includes/user.php.
 *
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
		$message = 'Sending password reset notification email failed.';
		$this->assertNotWPError( retrieve_password( $this->user->user_login ), $message );
	}

	/**
	 * The function should error when the email was not sent.
	 *
	 * @ticket 54690
	 */
	public function test_retrieve_password_should_return_wp_error_on_failed_email() {
		add_filter(
			'retrieve_password_notification_email',
			static function() {
				return array( 'message' => '' );
			}
		);

		$message = 'Sending password reset notification email succeeded.';
		$this->assertWPError( retrieve_password( $this->user->user_login ), $message );
	}
}
