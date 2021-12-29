<?php
/**
 * Test retrieve_password, in wp-includes/user.php
 *
 * @group user
 */
class Tests_User_RetrievePassword extends WP_UnitTestCase {

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

	public function retrieve_password_reset_notification_email() {
		$message = 'Sending password reset notification email failed.';
		$this->assertNotWPError( retrieve_password( $this->user->user_login ), $message );
	}
}
