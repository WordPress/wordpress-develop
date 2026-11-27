<?php
/**
 * Test cases for the `_wp_personal_data_handle_actions()` function.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group privacy
 * @group admin
 * @covers ::_wp_personal_data_handle_actions
 */
class Tests_Privacy_wpPersonalDataHandleActions extends WP_UnitTestCase {

	/**
	 * Reset the mocked phpmailer instance before each test.
	 */
	public function set_up() {
		parent::set_up();

		reset_phpmailer_instance();
	}

	/**
	 * Clean up superglobals and settings errors after each test.
	 */
	public function tear_down() {
		unset(
			$_POST['action'],
			$_POST['_wpnonce'],
			$_POST['type_of_action'],
			$_POST['username_or_email_for_privacy_request'],
			$_POST['send_confirmation_email'],
			$_REQUEST['_wpnonce']
		);

		$GLOBALS['wp_settings_errors'] = array();

		reset_phpmailer_instance();

		parent::tear_down();
	}

	/**
	 * Populates $_POST with a valid "add personal data request" submission.
	 *
	 * @param string $email Email address for the request.
	 */
	private function set_up_add_request_post_data( $email ) {
		$_POST['action']                                = 'add_export_personal_data_request';
		$_POST['_wpnonce']                               = wp_create_nonce( 'personal-data-request' );
		$_POST['type_of_action']                         = 'export_personal_data';
		$_POST['username_or_email_for_privacy_request']  = $email;
		$_POST['send_confirmation_email']                = '1';

		// check_admin_referer() reads the nonce from $_REQUEST, which the test bootstrap resets per test.
		$_REQUEST['_wpnonce'] = $_POST['_wpnonce'];
	}

	/**
	 * A confirmation email failing to send should surface as an error, not a false success message.
	 *
	 * @ticket 54442
	 */
	public function test_should_add_error_when_confirmation_email_fails_to_send() {
		$this->set_up_add_request_post_data( 'requester@example.com' );

		// Cause `wp_mail()` to return false.
		add_filter( 'wp_mail_from', '__return_empty_string' );

		_wp_personal_data_handle_actions();

		$errors = get_settings_errors( 'username_or_email_for_privacy_request' );

		$this->assertNotEmpty( $errors, 'An error should be recorded when the confirmation email fails to send.' );
		$this->assertSame( 'error', $errors[0]['type'] );
		$this->assertSame( 'Unable to send personal data export confirmation email.', $errors[0]['message'] );
	}

	/**
	 * A successfully sent confirmation email should still report success.
	 *
	 * @ticket 54442
	 */
	public function test_should_add_success_message_when_confirmation_email_sends() {
		$this->set_up_add_request_post_data( 'requester@example.com' );

		_wp_personal_data_handle_actions();

		$errors = get_settings_errors( 'username_or_email_for_privacy_request' );

		$this->assertNotEmpty( $errors );
		$this->assertSame( 'success', $errors[0]['type'] );
		$this->assertSame( 'Confirmation request initiated successfully.', $errors[0]['message'] );
	}
}
