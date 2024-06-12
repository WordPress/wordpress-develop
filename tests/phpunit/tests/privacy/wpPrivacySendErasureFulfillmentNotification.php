<?php
/**
 * Test cases for the `_wp_privacy_send_erasure_fulfillment_notification()` function.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.1.0
 */

/**
 * Tests_Privacy_wpPrivacySendErasureFulfillmentNotification class.
 *
 * @group privacy
 * @covers ::_wp_privacy_send_erasure_fulfillment_notification
 *
 * @since 5.1.0
 */
class Tests_Privacy_wpPrivacySendErasureFulfillmentNotification extends WP_UnitTestCase {
	/**
	 * Request ID.
	 *
	 * @since 5.1.0
	 *
	 * @var int $request_id
	 */
	protected static $request_id;

	/**
	 * Requester Email.
	 *
	 * @since 5.1.0
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
	 * Create user request fixtures shared by test methods.
	 *
	 * @since 5.1.0
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$requester_email = 'erase-my-data@local.test';
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

		self::$request_id = wp_create_user_request( self::$requester_email, 'remove_personal_data' );
		wp_update_post(
			array(
				'ID'          => self::$request_id,
				'post_status' => 'request-completed',
			)
		);
	}

	/**
	 * Reset the mocked PHPMailer instance before each test method.
	 *
	 * @since 5.1.0
	 */
	public function set_up() {
		parent::set_up();
		reset_phpmailer_instance();
	}

	/**
	 * Reset the mocked PHPMailer instance after each test method.
	 *
	 * @since 5.1.0
	 */
	public function tear_down() {
		reset_phpmailer_instance();
		restore_previous_locale();
		parent::tear_down();
	}

	/**
	 * The function should send an email when a valid request ID is passed.
	 *
	 * @ticket 44234
	 */
	public function test_should_send_email_no_privacy_policy() {

		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertStringContainsString( self::$requester_email, $mailer->get_recipient( 'to' )->address );
		$this->assertStringContainsString( 'Erasure Request Fulfilled', $mailer->get_sent()->subject );
		$this->assertStringContainsString( 'Your request to erase your personal data', $mailer->get_sent()->body );
		$this->assertStringContainsString( 'has been completed.', $mailer->get_sent()->body );
		$this->assertStringContainsString( wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), $mailer->get_sent()->body );
		$this->assertStringContainsString( home_url(), $mailer->get_sent()->body );

		$this->assertStringNotContainsString( 'you can also read our privacy policy', $mailer->get_sent()->body );
		$this->assertTrue( (bool) get_post_meta( self::$request_id, '_wp_user_notified', true ) );
	}

	/**
	 * The email should include a link to the site's privacy policy when set.
	 *
	 * @ticket 44234
	 */
	public function test_should_send_email_with_privacy_policy() {
		$privacy_policy = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'title'       => 'Site Privacy Policy',
				'post_status' => 'publish',
			)
		);
		update_option( 'wp_page_for_privacy_policy', $privacy_policy );

		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertStringContainsString( self::$requester_email, $mailer->get_recipient( 'to' )->address );
		$this->assertStringContainsString( 'you can also read our privacy policy', $mailer->get_sent()->body );
		$this->assertStringContainsString( get_privacy_policy_url(), $mailer->get_sent()->body );
		$this->assertTrue( (bool) get_post_meta( self::$request_id, '_wp_user_notified', true ) );
	}

	/**
	 * The function should send a fulfillment email only once.
	 *
	 * @ticket 44234
	 */
	public function test_should_send_email_only_once() {
		// First function call.
		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		// Should send an email.
		$this->assertStringContainsString( self::$requester_email, $mailer->get_recipient( 'to' )->address );
		$this->assertStringContainsString( 'Erasure Request Fulfilled', $mailer->get_sent()->subject );
		$this->assertTrue( (bool) get_post_meta( self::$request_id, '_wp_user_notified', true ) );

		reset_phpmailer_instance();

		// Second function call.
		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		// Should not send an email.
		$this->assertEmpty( $mailer->mock_sent );
		$this->assertTrue( metadata_exists( 'post', self::$request_id, '_wp_user_notified' ) );
	}

	/**
	 * The email address of the recipient of the fulfillment notification should be filterable.
	 *
	 * @ticket 44234
	 */
	public function test_email_address_of_recipient_should_be_filterable() {
		add_filter( 'user_erasure_fulfillment_email_to', array( $this, 'filter_email_address' ) );
		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertSame( 'modified-' . self::$requester_email, $mailer->get_recipient( 'to' )->address );
	}

	/**
	 * Filter callback that modifies the email address of the recipient of the fulfillment notification.
	 *
	 * @since 5.1.0
	 *
	 * @param string $user_email The email address of the notification recipient.
	 * @return string The email address of the notification recipient.
	 */
	public function filter_email_address( $user_email ) {
		return 'modified-' . $user_email;
	}

	/**
	 * The email subject of the fulfillment notification should be filterable.
	 *
	 * @ticket 44234
	 */
	public function test_email_subject_should_be_filterable() {
		add_filter( 'user_erasure_fulfillment_email_subject', array( $this, 'filter_email_subject' ) );
		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertSame( 'Modified subject', $mailer->get_sent()->subject );
	}

	/**
	 * Filter callback that modifies the email subject of the data erasure fulfillment notification.
	 *
	 * @since 5.1.0
	 *
	 * @param string $subject The email subject.
	 * @return string The email subject.
	 */
	public function filter_email_subject( $subject ) {
		return 'Modified subject';
	}

	/**
	 * The email body text of the fulfillment notification should be filterable.
	 *
	 * @ticket 44234
	 */
	public function test_email_body_text_should_be_filterable() {
		add_filter( 'user_erasure_fulfillment_email_content', array( $this, 'filter_email_body_text' ) );
		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertSame( 'Modified text', trim( $mailer->get_sent()->body ) );
	}

	/**
	 * Filter callback that modifies the email body text of the data erasure fulfillment notification.
	 *
	 * @since 5.1.0
	 *
	 * @param string $email_text Text in the email.
	 * @return string Text in the email.
	 */
	public function filter_email_body_text( $email_text ) {
		return 'Modified text';
	}

	/**
	 * The email headers of the fulfillment notification should be filterable.
	 *
	 * @since 5.4.0
	 *
	 * @ticket 44501
	 */
	public function test_email_headers_should_be_filterable() {
		add_filter( 'user_erasure_fulfillment_email_headers', array( $this, 'modify_email_headers' ) );
		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertStringContainsString( 'From: Tester <tester@example.com>', $mailer->get_sent()->header );
	}

	/**
	 * Filter callback that modifies the email headers of the data erasure fulfillment notification.
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
	 * The function should not send an email when the request ID does not exist.
	 *
	 * @ticket 44234
	 */
	public function test_should_not_send_email_when_passed_invalid_request_id() {
		_wp_privacy_send_erasure_fulfillment_notification( 1234567890 );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertEmpty( $mailer->mock_sent );
	}

	/**
	 * The function should not send an email when the ID passed does not correspond to a user request.
	 *
	 * @ticket 44234
	 */
	public function test_should_not_send_email_when_not_user_request() {
		$post_id = self::factory()->post->create(
			array(
				'post_type' => 'post', // Should be 'user_request'.
			)
		);

		_wp_privacy_send_erasure_fulfillment_notification( $post_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertEmpty( $mailer->mock_sent );
	}

	/**
	 * The function should not send an email when the request is not completed.
	 *
	 * @ticket 44234
	 */
	public function test_should_not_send_email_when_request_not_completed() {
		wp_update_post(
			array(
				'ID'          => self::$request_id,
				'post_status' => 'request-confirmed', // Should be 'request-completed'.
			)
		);

		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertEmpty( $mailer->mock_sent );
		$this->assertFalse( metadata_exists( 'post', self::$request_id, '_wp_user_notified' ) );
	}

	/**
	 * The function should respect the user locale settings when the site uses the default locale.
	 *
	 * @since 5.2.0
	 * @ticket 44721
	 * @group l10n
	 */
	public function test_should_send_fulfillment_email_in_user_locale() {
		update_user_meta( self::$request_user->ID, 'locale', 'es_ES' );

		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertStringContainsString( 'Solicitud de borrado completada', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the user locale settings when the site does not use en_US, the administrator
	 * uses the site's default locale, and the user has a different locale.
	 *
	 * @since 5.2.0
	 * @ticket 44721
	 * @group l10n
	 */
	public function test_should_send_fulfillment_email_in_user_locale_when_site_is_not_en_us() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		update_user_meta( self::$request_user->ID, 'locale', 'de_DE' );
		wp_set_current_user( self::$admin_user->ID );

		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertStringContainsString( 'Löschauftrag ausgeführt', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the user locale settings when the site is not en_US, the administrator
	 * has a different selected locale, and the user uses the site's default locale.
	 *
	 * @since 5.2.0
	 * @ticket 44721
	 * @group l10n
	 */
	public function test_should_send_fulfillment_email_in_user_locale_when_admin_and_site_have_different_locales() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		update_user_meta( self::$admin_user->ID, 'locale', 'de_DE' );
		wp_set_current_user( self::$admin_user->ID );

		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertStringContainsString( 'Solicitud de borrado completada', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the user locale settings when the site is not en_US and both the
	 * administrator and the user use different locales.
	 *
	 * @since 5.2.0
	 * @ticket 44721
	 * @group l10n
	 */
	public function test_should_send_fulfillment_email_in_user_locale_when_both_have_different_locales_than_site() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		update_user_meta( self::$admin_user->ID, 'locale', 'en_US' );
		update_user_meta( self::$request_user->ID, 'locale', 'de_DE' );

		wp_set_current_user( self::$admin_user->ID );

		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertStringContainsString( 'Löschauftrag ausgeführt', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the site's locale when the request is for an unregistered user and the
	 * administrator does not use the site's locale.
	 *
	 * @since 5.2.0
	 * @ticket 44721
	 * @group l10n
	 */
	public function test_should_send_fulfillment_email_in_site_locale() {
		update_user_meta( self::$admin_user->ID, 'locale', 'es_ES' );
		wp_set_current_user( self::$admin_user->ID );

		$request_id = wp_create_user_request( 'erase-user-not-registered@example.com', 'remove_personal_data' );
		wp_update_post(
			array(
				'ID'          => $request_id,
				'post_status' => 'request-completed',
			)
		);

		_wp_privacy_send_erasure_fulfillment_notification( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertStringContainsString( 'Erasure Request Fulfilled', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the site's locale when it is not en_US, the request is for an
	 * unregistered user, and the administrator does not use the site's default locale.
	 *
	 * @since 5.2.0
	 * @ticket 44721
	 * @group l10n
	 */
	public function test_should_send_fulfillment_email_in_site_locale_when_not_en_us_and_admin_has_different_locale() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		update_user_meta( self::$admin_user->ID, 'locale', 'de_DE' );
		wp_set_current_user( self::$admin_user->ID );

		$request_id = wp_create_user_request( 'erase-user-not-registered@example.com', 'remove_personal_data' );
		wp_update_post(
			array(
				'ID'          => $request_id,
				'post_status' => 'request-completed',
			)
		);

		_wp_privacy_send_erasure_fulfillment_notification( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertStringContainsString( 'Solicitud de borrado completada', $mailer->get_sent()->subject );
	}
}
