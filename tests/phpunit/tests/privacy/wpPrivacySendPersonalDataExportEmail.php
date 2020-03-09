<?php
/**
 * Test cases for the `wp_privacy_send_personal_data_export_email()` function.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.9.6
 */

/**
 * Tests_Privacy_WpPrivacySendPersonalDataExportEmail class.
 *
 * @group privacy
 * @covers wp_privacy_send_personal_data_export_email
 *
 * @since 4.9.6
 */
class Tests_Privacy_WpPrivacySendPersonalDataExportEmail extends WP_UnitTestCase {
	/**
	 * Request ID.
	 *
	 * @since 4.9.6
	 *
	 * @var int $request_id
	 */
	protected static $request_id;

	/**
	 * Requester Email.
	 *
	 * @since 4.9.6
	 *
	 * @var string $requester_email
	 */
	protected static $requester_email;

	/**
	 * Request user.
	 *
	 * @since 5.2.0
	 *
	 * @var WP_User $request_user
	 */
	protected static $request_user;

	/**
	 * Test administrator user.
	 *
	 * @since 5.2.0
	 *
	 * @var WP_User $admin_user
	 */
	protected static $admin_user;

	/**
	 * Reset the mocked phpmailer instance before each test method.
	 *
	 * @since 4.9.6
	 */
	public function setUp() {
		parent::setUp();
		reset_phpmailer_instance();
	}

	/**
	 * Reset the mocked phpmailer instance after each test method.
	 *
	 * @since 4.9.6
	 */
	public function tearDown() {
		reset_phpmailer_instance();
		restore_previous_locale();
		parent::tearDown();
	}

	/**
	 * Create user request fixtures shared by test methods.
	 *
	 * @since 4.9.6
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$requester_email = 'requester@example.com';
		self::$request_user    = $factory->user->create_and_get(
			array(
				'user_email' => self::$requester_email,
				'role'       => 'subscriber',
			)
		);
		self::$admin_user      = $factory->user->create_and_get(
			array(
				'user_email' => 'admin@local.dev',
				'role'       => 'administrator',
			)
		);

		self::$request_id = wp_create_user_request( self::$requester_email, 'export_personal_data' );

		_wp_privacy_account_request_confirmed( self::$request_id );
	}

	/**
	 * The function should send an export link to the requester when the user request is confirmed.
	 */
	public function test_function_should_send_export_link_to_requester() {
		$archive_url = wp_privacy_exports_url() . 'wp-personal-data-file-Wv0RfMnGIkl4CFEDEEkSeIdfLmaUrLsl.zip';
		update_post_meta( self::$request_id, '_export_file_url', $archive_url );

		$email_sent = wp_privacy_send_personal_data_export_email( self::$request_id );
		$mailer     = tests_retrieve_phpmailer_instance();

		$this->assertSame( 'request-confirmed', get_post_status( self::$request_id ) );
		$this->assertSame( self::$requester_email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'Personal Data Export', $mailer->get_sent()->subject );
		$this->assertContains( $archive_url, $mailer->get_sent()->body );
		$this->assertContains( 'please download it', $mailer->get_sent()->body );
		$this->assertTrue( $email_sent );
	}

	/**
	 * The function should error when the request ID is invalid.
	 *
	 * @since 4.9.6
	 */
	public function test_function_should_error_when_request_id_invalid() {
		$request_id = 0;
		$email_sent = wp_privacy_send_personal_data_export_email( $request_id );
		$this->assertWPError( $email_sent );
		$this->assertSame( 'invalid_request', $email_sent->get_error_code() );

		$request_id = PHP_INT_MAX;
		$email_sent = wp_privacy_send_personal_data_export_email( $request_id );
		$this->assertWPError( $email_sent );
		$this->assertSame( 'invalid_request', $email_sent->get_error_code() );
	}

	/**
	 * The function should error when the email was not sent.
	 *
	 * @since 4.9.6
	 */
	public function test_return_wp_error_when_send_fails() {
		add_filter( 'wp_mail_from', '__return_empty_string' ); // Cause `wp_mail()` to return false.
		$email_sent = wp_privacy_send_personal_data_export_email( self::$request_id );

		$this->assertWPError( $email_sent );
		$this->assertSame( 'privacy_email_error', $email_sent->get_error_code() );
	}

	/**
	 * The export expiration should be filterable.
	 *
	 * @since 4.9.6
	 */
	public function test_export_expiration_should_be_filterable() {
		add_filter( 'wp_privacy_export_expiration', array( $this, 'modify_export_expiration' ) );
		wp_privacy_send_personal_data_export_email( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertContains( 'we will automatically delete the file on December 18, 2017,', $mailer->get_sent()->body );
	}

	/**
	 * Filter callback that modifies the lifetime, in seconds, of a personal data export file.
	 *
	 * @since 4.9.6
	 *
	 * @param int $expiration The expiration age of the export, in seconds.
	 * @return int $expiration The expiration age of the export, in seconds.
	 */
	public function modify_export_expiration( $expiration ) {
		// Set date to always be "Mon, 18 Dec 2017 21:30:00 GMT", so can assert a fixed date.
		return 1513632600 - time();
	}

	/**
	 * The email address of the recipient of the personal data export notification should be filterable.
	 *
	 * @ticket 46303
	 */
	public function test_email_address_of_recipient_should_be_filterable() {
		add_filter( 'wp_privacy_personal_data_email_to', array( $this, 'filter_email_address' ) );
		wp_privacy_send_personal_data_export_email( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertSame( 'modified-' . self::$requester_email, $mailer->get_recipient( 'to' )->address );
	}

	/**
	 * Filter callback that modifies the email address of the recipient of the personal data export notification.
	 *
	 * @since 5.3.0
	 *
	 * @param  string $user_email The email address of the notification recipient.
	 * @return string $user_email The modified email address of the notification recipient.
	 */
	public function filter_email_address( $user_email ) {
		return 'modified-' . $user_email;
	}

	/**
	 * The email subject of the personal data export notification should be filterable.
	 *
	 * @ticket 46303
	 */
	public function test_email_subject_should_be_filterable() {
		add_filter( 'wp_privacy_personal_data_email_subject', array( $this, 'filter_email_subject' ) );
		wp_privacy_send_personal_data_export_email( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertSame( 'Modified subject', $mailer->get_sent()->subject );
	}

	/**
	 * Filter callback that modifies the email subject of the data erasure fulfillment notification.
	 *
	 * @since 5.3.0
	 *
	 * @param string $subject The email subject.
	 * @return string $subject The email subject.
	 */
	public function filter_email_subject( $subject ) {
		return 'Modified subject';
	}

	/**
	 * The email content should be filterable.
	 *
	 * @since 4.9.6
	 */
	public function test_email_content_should_be_filterable() {
		add_filter( 'wp_privacy_personal_data_email_content', array( $this, 'modify_email_content' ), 10, 2 );
		wp_privacy_send_personal_data_export_email( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertContains( 'Custom content for request ID: ' . self::$request_id, $mailer->get_sent()->body );
	}

	/**
	 * Filter callback that modifies the text of the email sent with a personal data export file.
	 *
	 * @since 4.9.6
	 *
	 * @param string $email_text Text in the email.
	 * @param int    $request_id The request ID for this personal data export.
	 * @return string $email_text Text in the email.
	 */
	public function modify_email_content( $email_text, $request_id ) {
		return 'Custom content for request ID: ' . $request_id;
	}

	/**
	 * The email headers should be filterable.
	 *
	 * @since 5.4.0
	 *
	 * @ticket 44501
	 */
	public function test_email_headers_should_be_filterable() {
		add_filter( 'wp_privacy_personal_data_email_headers', array( $this, 'modify_email_headers' ) );
		wp_privacy_send_personal_data_export_email( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'From: Tester <tester@example.com>', $mailer->get_sent()->header );
	}

	/**
	 * Filter callback to modify the headers of the email sent with a personal data export file.
	 *
	 * @since 5.4.0
	 *
	 * @param string|array $headers The email headers.
	 * @return array       $headers The new email headers.
	 */
	public function modify_email_headers( $headers ) {
		$headers = array(
			'From: Tester <tester@example.com>',
		);

		return $headers;
	}

	/**
	 * The email content should be filterable using the $email_data
	 *
	 * @ticket 46303
	 */
	public function test_email_content_should_be_filterable_using_email_data() {
		add_filter( 'wp_privacy_personal_data_email_content', array( $this, 'modify_email_content_with_email_data' ), 10, 3 );
		wp_privacy_send_personal_data_export_email( self::$request_id );

		$site_url = home_url();
		$mailer   = tests_retrieve_phpmailer_instance();
		$this->assertContains( 'Custom content using the $site_url of $email_data: ' . $site_url, $mailer->get_sent()->body );
	}

	/**
	 * Filter callback that modifies the text of the email by using the $email_data sent with a personal data export file.
	 *
	 * @since 5.3.0
	 *
	 * @param string $email_text Text in the email.
	 * @param int    $request_id The request ID for this personal data export.
	 * @param array  $email_data {
	 *     Data relating to the account action email.
	 *
	 *     @type WP_User_Request $request           User request object.
	 *     @type int             $expiration        The time in seconds until the export file expires.
	 *     @type string          $expiration_date   The localized date and time when the export file expires.
	 *     @type string          $message_recipient The address that the email will be sent to. Defaults
	 *                                              to the value of `$request->email`, but can be changed
	 *                                              by the `wp_privacy_personal_data_email_to` filter.
	 *     @type string          $export_file_url   The export file URL.
	 *     @type string          $sitename          The site name sending the mail.
	 *     @type string          $siteurl           The site URL sending the mail.
	 * }
	 *
	 * @return string $email_text Text in the email.
	 */
	public function modify_email_content_with_email_data( $email_text, $request_id, $email_data ) {
		return 'Custom content using the $site_url of $email_data: ' . $email_data['siteurl'];
	}

	/**
	 * The function should respect the user locale settings when the site uses the default locale.
	 *
	 * @since 5.2.0
	 * @ticket 46056
	 * @group l10n
	 */
	public function test_should_send_personal_data_export_email_in_user_locale() {
		update_user_meta( self::$request_user->ID, 'locale', 'es_ES' );

		wp_privacy_send_personal_data_export_email( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Exportación de datos personales', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the user locale settings when the site does not use en_US, the administrator
	 * uses the site's default locale, and the user has a different locale.
	 *
	 * @since 5.2.0
	 * @ticket 46056
	 * @group l10n
	 */
	public function test_should_send_personal_data_export_email_in_user_locale_when_site_is_not_en_us() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		update_user_meta( self::$request_user->ID, 'locale', 'de_DE' );
		wp_set_current_user( self::$admin_user->ID );

		wp_privacy_send_personal_data_export_email( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Export personenbezogener Daten', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the user locale settings when the site is not en_US, the administrator
	 * has a different selected locale, and the user uses the site's default locale.
	 *
	 * @since 5.2.0
	 * @ticket 46056
	 * @group l10n
	 */
	public function test_should_send_personal_data_export_email_in_user_locale_when_admin_and_site_have_different_locales() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		update_user_meta( self::$admin_user->ID, 'locale', 'de_DE' );
		wp_set_current_user( self::$admin_user->ID );

		wp_privacy_send_personal_data_export_email( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Exportación de datos personales', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the user locale settings when the site is not en_US and both the
	 * administrator and the user use different locales.
	 *
	 * @since 5.2.0
	 * @ticket 46056
	 * @group l10n
	 */
	public function test_should_send_personal_data_export_email_in_user_locale_when_both_have_different_locales_than_site() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		update_user_meta( self::$admin_user->ID, 'locale', 'en_US' );
		update_user_meta( self::$request_user->ID, 'locale', 'de_DE' );

		wp_set_current_user( self::$admin_user->ID );

		wp_privacy_send_personal_data_export_email( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Export personenbezogener Daten', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the site's locale when the request is for an unregistered user and the
	 * administrator does not use the site's locale.
	 *
	 * @since 5.2.0
	 * @ticket 46056
	 * @group l10n
	 */
	public function test_should_send_personal_data_export_email_in_site_locale() {
		update_user_meta( self::$admin_user->ID, 'locale', 'es_ES' );
		wp_set_current_user( self::$admin_user->ID );

		$request_id = wp_create_user_request( 'export-user-not-registered@example.com', 'export_personal_data' );

		_wp_privacy_account_request_confirmed( self::$request_id );
		wp_privacy_send_personal_data_export_email( $request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Personal Data Export', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the site's locale when it is not en_US, the request is for an
	 * unregistered user, and the administrator does not use the site's default locale.
	 *
	 * @since 5.2.0
	 * @ticket 46056
	 * @group l10n
	 */
	public function test_should_send_personal_data_export_email_in_site_locale_when_not_en_us_and_admin_has_different_locale() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		update_user_meta( self::$admin_user->ID, 'locale', 'de_DE' );
		wp_set_current_user( self::$admin_user->ID );

		$request_id = wp_create_user_request( 'export-user-not-registered@example.com', 'export_personal_data' );

		_wp_privacy_account_request_confirmed( self::$request_id );
		wp_privacy_send_personal_data_export_email( $request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Exportación de datos personales', $mailer->get_sent()->subject );
	}
}
