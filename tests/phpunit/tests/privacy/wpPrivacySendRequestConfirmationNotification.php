<?php
/**
 * Test cases for the `_wp_privacy_send_request_confirmation_notification()` function.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.9.8
 */

/**
 * Tests_Privacy_wpPrivacySendRequestConfirmationNotification class.
 *
 * @group privacy
 * @group user
 * @covers ::_wp_privacy_send_request_confirmation_notification
 *
 * @since 4.9.8
 */
class Tests_Privacy_wpPrivacySendRequestConfirmationNotification extends WP_UnitTestCase {
	/**
	 * Reset the mocked PHPMailer instance before each test method.
	 *
	 * @since 4.9.8
	 */
	public function set_up() {
		parent::set_up();
		reset_phpmailer_instance();
	}

	/**
	 * Reset the mocked PHPMailer instance after each test method.
	 *
	 * @since 4.9.8
	 */
	public function tear_down() {
		reset_phpmailer_instance();
		parent::tear_down();
	}

	/**
	 * The function should not send emails when the request ID does not exist.
	 *
	 * @ticket 43967
	 */
	public function test_function_should_not_send_email_when_not_a_valid_request_id() {
		_wp_privacy_send_request_confirmation_notification( 1234567890 );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertEmpty( $mailer->mock_sent );
	}

	/**
	 * The function should not send emails when the ID passed is not a WP_User_Request.
	 *
	 * @ticket 43967
	 */
	public function test_function_should_not_send_email_when_not_a_wp_user_request() {
		$post_id = self::factory()->post->create(
			array(
				'post_type' => 'post',
			)
		);

		_wp_privacy_send_request_confirmation_notification( $post_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertEmpty( $mailer->mock_sent );
	}

	/**
	 * The function should send an email to the site admin when a user request is confirmed.
	 *
	 * @ticket 43967
	 */
	public function test_function_should_send_email_to_site_admin_when_user_request_confirmed() {
		$email      = 'export.request.from.unregistered.user@example.com';
		$request_id = wp_create_user_request( $email, 'export_personal_data' );

		_wp_privacy_account_request_confirmed( $request_id );

		_wp_privacy_send_request_confirmation_notification( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertSame( 'request-confirmed', get_post_status( $request_id ) );
		$this->assertTrue( (bool) get_post_meta( $request_id, '_wp_user_request_confirmed_timestamp', true ) );
		$this->assertTrue( (bool) get_post_meta( $request_id, '_wp_admin_notified', true ) );
		$this->assertSame( get_site_option( 'admin_email' ), $mailer->get_recipient( 'to' )->address );
		$this->assertStringContainsString( 'Action Confirmed', $mailer->get_sent()->subject );
		$this->assertStringContainsString( 'Request: Export Personal Data', $mailer->get_sent()->body );
		$this->assertStringContainsString( 'A user data privacy request has been confirmed', $mailer->get_sent()->body );
	}

	/**
	 * The function should only send an email to the site admin when a user request is confirmed.
	 *
	 * @ticket 43967
	 */
	public function test_function_should_only_send_email_to_site_admin_when_user_request_is_confirmed() {
		$email      = 'export.request.from.unregistered.user@example.com';
		$request_id = wp_create_user_request( $email, 'export_personal_data' );

		_wp_privacy_send_request_confirmation_notification( $request_id );

		// Non-confirmed request.
		$this->assertSame( 'request-pending', get_post_status( $request_id ) );

		$mailer = tests_retrieve_phpmailer_instance();
		// Email not sent.
		$this->assertEmpty( $mailer->mock_sent );
		$this->assertEmpty( get_post_meta( $request_id, '_wp_admin_notified', true ) );
	}

	/**
	 * The function should only send an email once to the site admin when a user request is confirmed.
	 *
	 * @ticket 43967
	 */
	public function test_function_should_only_send_email_once_to_admin_when_user_request_is_confirmed() {
		$email      = 'export.request.from.unregistered.user@example.com';
		$request_id = wp_create_user_request( $email, 'export_personal_data' );

		_wp_privacy_account_request_confirmed( $request_id );

		_wp_privacy_send_request_confirmation_notification( $request_id );
		$first_mailer = tests_retrieve_phpmailer_instance();
		reset_phpmailer_instance();
		_wp_privacy_send_request_confirmation_notification( $request_id );
		$second_mailer = tests_retrieve_phpmailer_instance();

		$this->assertSame( 'request-confirmed', get_post_status( $request_id ) );
		// First email sent.
		$this->assertNotEmpty( $first_mailer->mock_sent );
		// Second email not sent.
		$this->assertEmpty( $second_mailer->mock_sent );
	}

	/**
	 * The email address should be filterable.
	 *
	 * @ticket 43967
	 */
	public function test_email_address_should_be_filterable() {
		$email      = 'export.request.from.unregistered.user@example.com';
		$request_id = wp_create_user_request( $email, 'export_personal_data' );

		_wp_privacy_account_request_confirmed( $request_id );

		add_filter( 'user_request_confirmed_email_to', array( $this, 'modify_email_address' ), 10, 2 );
		_wp_privacy_send_request_confirmation_notification( $request_id );
		remove_all_filters( 'user_request_confirmed_email_to' );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertSame( $email, $mailer->get_recipient( 'to' )->address );
	}

	/**
	 * Filter callback that modifies the recipient of the user request confirmation notification.
	 *
	 * @since 4.9.8
	 *
	 * @param string          $admin_email The email address of the notification recipient.
	 * @param WP_User_Request $request     The request that is initiating the notification.
	 * @return string Admin email address.
	 */
	public function modify_email_address( $admin_email, $request ) {
		$admin_email = $request->email;
		return $admin_email;
	}

	/**
	 * The email content should be filterable.
	 *
	 * @ticket 43967
	 */
	public function test_email_content_should_be_filterable() {
		$email      = 'export.request.from.unregistered.user@example.com';
		$request_id = wp_create_user_request( $email, 'export_personal_data' );

		_wp_privacy_account_request_confirmed( $request_id );

		add_filter( 'user_request_confirmed_email_content', array( $this, 'modify_email_content' ), 10, 2 );
		_wp_privacy_send_request_confirmation_notification( $request_id );
		remove_filter( 'user_request_confirmed_email_content', array( $this, 'modify_email_content' ), 10 );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertStringContainsString( 'Custom content containing email address:' . $email, $mailer->get_sent()->body );
	}

	/**
	 * Filter callback that modifies the body of the user request confirmation email.
	 *
	 * @since 4.9.8
	 *
	 * @param string $email_text Email text.
	 * @param array  $email_data {
	 *     Data relating to the account action email.
	 *
	 *     @type WP_User_Request $request     User request object.
	 *     @type string          $user_email  The email address confirming a request
	 *     @type string          $description Description of the action being performed so the user knows what the email is for.
	 *     @type string          $manage_url  The link to click manage privacy requests of this type.
	 *     @type string          $sitename    The site name sending the mail.
	 *     @type string          $siteurl     The site URL sending the mail.
	 * }
	 * @return string Email text.
	 */
	public function modify_email_content( $email_text, $email_data ) {
		$email_text = 'Custom content containing email address:' . $email_data['user_email'];
		return $email_text;
	}

	/**
	 * The email headers should be filterable.
	 *
	 * @since 5.4.0
	 *
	 * @ticket 44501
	 */
	public function test_email_headers_should_be_filterable() {
		$email      = 'export.request.from.unregistered.user@example.com';
		$request_id = wp_create_user_request( $email, 'export_personal_data' );

		_wp_privacy_account_request_confirmed( $request_id );

		add_filter( 'user_request_confirmed_email_headers', array( $this, 'modify_email_headers' ) );
		_wp_privacy_send_request_confirmation_notification( $request_id );
		remove_filter( 'user_request_confirmed_email_headers', array( $this, 'modify_email_headers' ) );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertStringContainsString( 'From: Tester <tester@example.com>', $mailer->get_sent()->header );
	}

	/**
	 * Filter callback that modifies the headers of the user request confirmation email.
	 *
	 * @since 5.4.0
	 *
	 * @param string|array $headers The email headers.
	 * @return array The new email headers.
	 */
	public function modify_email_headers( $headers ) {
		$headers = array(
			'From: Tester <tester@example.com>',
		);

		return $headers;
	}

}
