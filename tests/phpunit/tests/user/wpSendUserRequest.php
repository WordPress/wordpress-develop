<?php
/**
 * Test cases for the `wp_send_user_request()` function.
 *
 * @package WordPress
 * @since 4.9.9
 */

/**
 * Tests_User_WpSendUserRequest class.
 *
 * @since 4.9.9
 *
 * @group privacy
 * @group user
 * @covers ::wp_send_user_request
 */
class Tests_User_WpSendUserRequest extends WP_UnitTestCase {

	/**
	 * Test administrator user.
	 *
	 * @since 4.9.9
	 *
	 * @var WP_User $admin_user
	 */
	protected static $admin_user;

	/**
	 * Test subscriber user.
	 *
	 * @since 4.9.9
	 *
	 * @var WP_User $test_user
	 */
	protected static $test_user;

	/**
	 * Create users for tests.
	 *
	 * @since 4.9.9
	 *
	 * @param WP_UnitTest_Factory $factory Test fixture factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user = $factory->user->create_and_get(
			array(
				'user_email' => 'admin@local.dev',
				'role'       => 'administrator',
			)
		);

		self::$test_user = $factory->user->create_and_get(
			array(
				'user_email' => 'export-user@local.dev',
				'role'       => 'subscriber',
			)
		);
	}

	/**
	 * Reset the mocked phpmailer instance before each test method.
	 *
	 * @since 4.9.9
	 */
	public function setUp() {
		parent::setUp();

		set_current_screen( 'dashboard' );
		reset_phpmailer_instance();
	}

	/**
	 * Reset the mocked phpmailer instance after each test method.
	 *
	 * @since 4.9.9
	 */
	public function tearDown() {
		reset_phpmailer_instance();

		unset( $GLOBALS['locale'] );

		restore_previous_locale();
		parent::tearDown();
	}

	/**
	 * The function should error when the request ID is invalid.
	 *
	 * @ticket 43985
	 */
	public function test_should_error_when_invalid_request_id() {
		$result = wp_send_user_request( null );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	/**
	 * The function should send a user request export email when the requester is a registered user.
	 *
	 * @ticket 43985
	 */
	public function test_should_send_user_request_export_email_when_requester_registered_user() {
		$request_id = wp_create_user_request( self::$test_user->user_email, 'export_personal_data' );

		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $result );
		$this->assertSame( self::$test_user->user_email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'Confirm Action: Export Personal Data', $mailer->get_sent()->subject );
		$this->assertContains( 'action=confirmaction&request_id=', $mailer->get_sent()->body );
		$this->assertContains( 'Export Personal Data', $mailer->get_sent()->body );
	}

	/**
	 * The function should send a user request erase email when the requester is a registered user.
	 *
	 * @ticket 43985
	 */
	public function test_should_send_user_request_erase_email_when_requester_registered_user() {
		$request_id = wp_create_user_request( self::$test_user->user_email, 'remove_personal_data' );

		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $result );
		$this->assertSame( self::$test_user->user_email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'Confirm Action: Erase Personal Data', $mailer->get_sent()->subject );
		$this->assertContains( 'action=confirmaction&request_id=', $mailer->get_sent()->body );
		$this->assertContains( 'Erase Personal Data', $mailer->get_sent()->body );
	}

	/**
	 * The function should send a user request export email when the requester is an un-registered user.
	 *
	 * @ticket 43985
	 */
	public function test_should_send_user_request_export_email_when_user_not_registered() {
		$request_id = wp_create_user_request( self::$test_user->user_email, 'export_personal_data' );

		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $result );
		$this->assertSame( self::$test_user->user_email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'Confirm Action: Export Personal Data', $mailer->get_sent()->subject );
		$this->assertContains( 'action=confirmaction&request_id=', $mailer->get_sent()->body );
		$this->assertContains( 'Export Personal Data', $mailer->get_sent()->body );
	}

	/**
	 * The function should send a user request erase email when the requester is an un-registered user.
	 *
	 * @ticket 43985
	 */
	public function test_should_send_user_request_erase_email_when_user_not_registered() {
		$request_id = wp_create_user_request( self::$test_user->user_email, 'remove_personal_data' );

		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $result );
		$this->assertSame( self::$test_user->user_email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'Confirm Action: Erase Personal Data', $mailer->get_sent()->subject );
		$this->assertContains( 'action=confirmaction&request_id=', $mailer->get_sent()->body );
		$this->assertContains( 'Erase Personal Data', $mailer->get_sent()->body );
	}

	/**
	 * The email subject should be filterable.
	 *
	 * @ticket 43985
	 */
	public function test_email_subject_should_be_filterable() {
		$request_id = wp_create_user_request( self::$test_user->user_email, 'remove_personal_data' );

		add_filter( 'user_request_action_email_subject', array( $this, 'modify_email_subject' ) );
		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $result );
		$this->assertSame( 'Custom Email Subject', $mailer->get_sent()->subject );
	}

	/**
	 * Filter callback to modify the subject of the email sent when an account action is attempted.
	 *
	 * @since 4.9.9
	 *
	 * @param string $subject The email subject.
	 * @return string Filtered email subject.
	 */
	public function modify_email_subject( $subject ) {
		return 'Custom Email Subject';
	}

	/**
	 * The email content should be filterable.
	 *
	 * @ticket 43985
	 */
	public function test_email_content_should_be_filterable() {
		$request_id = wp_create_user_request( self::$test_user->user_email, 'remove_personal_data' );

		add_filter( 'user_request_action_email_content', array( $this, 'modify_email_content' ), 10, 2 );
		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $result );
		$this->assertContains( 'Custom Email Content.', $mailer->get_sent()->body );
	}

	/**
	 * Filter callback to modify the content of the email sent when an account action is attempted.
	 *
	 * @since 4.9.9
	 *
	 * @param string $email_text Confirmation email text.
	 * @return string Filtered email text.
	 */
	public function modify_email_content( $email_text ) {
		return 'Custom Email Content.';
	}

	/**
	 * The email headers should be filterable.
	 *
	 * @since 5.4.0
	 *
	 * @ticket 44501
	 */
	public function test_email_headers_should_be_filterable() {
		$request_id = wp_create_user_request( self::$test_user->user_email, 'remove_personal_data' );

		add_filter( 'user_request_action_email_headers', array( $this, 'modify_email_headers' ) );
		$result = wp_send_user_request( $request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'From: Tester <tester@example.com>', $mailer->get_sent()->header );
	}

	/**
	 * Filter callback to modify the headers of the email sent when an account action is attempted.
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

	/**
	 * The function should error when the email was not sent.
	 *
	 * @ticket 43985
	 */
	public function test_return_wp_error_when_sending_fails() {
		$request_id = wp_create_user_request( 'erase.request.from.unregistered.user@example.com', 'remove_personal_data' );

		add_filter( 'wp_mail_from', '__return_empty_string' ); // Cause `wp_mail()` to return false.
		$result = wp_send_user_request( $request_id );

		$this->assertWPError( $result );
		$this->assertSame( 'privacy_email_error', $result->get_error_code() );
	}

	/**
	 * The function should respect the user locale settings when the site uses the default locale.
	 *
	 * @ticket 43985
	 * @group l10n
	 */
	public function test_should_send_user_request_email_in_user_locale() {
		update_user_meta( self::$test_user->ID, 'locale', 'es_ES' );

		wp_set_current_user( self::$admin_user->ID );
		$request_id = wp_create_user_request( self::$test_user->user_email, 'export_personal_data' );

		wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Confirmar la', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the user locale settings when the site does not use en_US, the administrator
	 * uses the site's default locale, and the user has a different locale.
	 *
	 * @ticket 43985
	 * @group l10n
	 */
	public function test_should_send_user_request_email_in_user_locale_when_site_is_not_en_us() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		update_user_meta( self::$test_user->ID, 'locale', 'de_DE' );

		wp_set_current_user( self::$admin_user->ID );
		$request_id = wp_create_user_request( self::$test_user->user_email, 'remove_personal_data' );

		wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Aktion bestätigen', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the user locale settings when the site is not en_US, the administrator
	 * has a different selected locale, and the user uses the site's default locale.
	 *
	 * @ticket 43985
	 * @group l10n
	 */
	public function test_should_send_user_request_email_in_user_locale_when_admin_and_site_have_different_locales() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		update_user_meta( self::$admin_user->ID, 'locale', 'de_DE' );
		wp_set_current_user( self::$admin_user->ID );

		$request_id = wp_create_user_request( self::$test_user->user_email, 'export_personal_data' );

		wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Confirmar la', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the user locale settings when the site is not en_US and both the
	 * administrator and the user use different locales.
	 *
	 * @ticket 43985
	 * @group l10n
	 */
	public function test_should_send_user_request_email_in_user_locale_when_both_have_different_locales_than_site() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		update_user_meta( self::$admin_user->ID, 'locale', 'de_DE' );
		update_user_meta( self::$test_user->ID, 'locale', 'en_US' );

		wp_set_current_user( self::$admin_user->ID );

		$request_id = wp_create_user_request( self::$test_user->user_email, 'export_personal_data' );

		wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Confirm Action', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the site's locale when the request is for an unregistered user and the
	 * administrator does not use the site's locale.
	 *
	 * @ticket 43985
	 * @group l10n
	 */
	public function test_should_send_user_request_email_in_site_locale() {
		update_user_meta( self::$admin_user->ID, 'locale', 'es_ES' );
		wp_set_current_user( self::$admin_user->ID );

		$request_id = wp_create_user_request( 'erase-user-not-registered@example.com', 'remove_personal_data' );

		wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Confirm Action', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the site's locale when it is not en_US, the request is for an
	 * unregistered user, and the administrator does not use the site's default locale.
	 *
	 * @ticket 43985
	 * @group l10n
	 */
	public function test_should_send_user_request_email_in_site_locale_when_not_en_us_and_admin_has_different_locale() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		update_user_meta( self::$admin_user->ID, 'locale', 'de_DE' );
		wp_set_current_user( self::$admin_user->ID );

		$request_id = wp_create_user_request( 'export-user-not-registered@example.com', 'remove_personal_data' );

		wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Confirmar la', $mailer->get_sent()->subject );
	}
}
