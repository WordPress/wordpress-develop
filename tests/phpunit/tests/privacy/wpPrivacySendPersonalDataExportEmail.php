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
	 * Reset the mocked phpmailer instance before each test method.
	 *
	 * @since 4.9.6
	 */
	function setUp() {
		parent::setUp();
		reset_phpmailer_instance();
	}

	/**
	 * Reset the mocked phpmailer instance after each test method.
	 *
	 * @since 4.9.6
	 */
	function tearDown() {
		reset_phpmailer_instance();
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
		self::$request_id      = wp_create_user_request( self::$requester_email, 'export_personal_data' );

		_wp_privacy_account_request_confirmed( self::$request_id );
	}

	/**
	 * The function should send an export link to the requester when the user request is confirmed.
	 */
	public function test_function_should_send_export_link_to_requester() {
		$archive_url = wp_privacy_exports_url() . 'wp-personal-data-file-requester-at-example-com-Wv0RfMnGIkl4CFEDEEkSeIdfLmaUrLsl.zip';
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
		$this->assertSame( 'invalid', $email_sent->get_error_code() );

		$request_id = PHP_INT_MAX;
		$email_sent = wp_privacy_send_personal_data_export_email( $request_id );
		$this->assertWPError( $email_sent );
		$this->assertSame( 'invalid', $email_sent->get_error_code() );
	}

	/**
	 * The function should error when the email was not sent.
	 *
	 * @since 4.9.6
	 */
	public function test_return_wp_error_when_send_fails() {
		add_filter( 'wp_mail_from', '__return_empty_string' ); // Cause `wp_mail()` to return false.
		$email_sent = wp_privacy_send_personal_data_export_email( self::$request_id );
		remove_filter( 'wp_mail_from', '__return_empty_string' );

		$this->assertWPError( $email_sent );
		$this->assertSame( 'error', $email_sent->get_error_code() );
	}

	/**
	 * The export expiration should be filterable.
	 *
	 * @since 4.9.6
	 */
	public function test_export_expiration_should_be_filterable() {
		add_filter( 'wp_privacy_export_expiration', array( $this, 'modify_export_expiration' ) );
		wp_privacy_send_personal_data_export_email( self::$request_id );
		remove_filter( 'wp_privacy_export_expiration', array( $this, 'modify_export_expiration' ) );

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
	 * The email content should be filterable.
	 *
	 * @since 4.9.6
	 */
	public function test_email_content_should_be_filterable() {
		add_filter( 'wp_privacy_personal_data_email_content', array( $this, 'modify_email_content' ), 10, 2 );
		wp_privacy_send_personal_data_export_email( self::$request_id );
		remove_filter( 'wp_privacy_personal_data_email_content', array( $this, 'modify_email_content' ) );

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
}
