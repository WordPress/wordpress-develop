<?php
/**
 * Test cases for the `_wp_privacy_send_erasure_fulfillment_notification()` function.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.1.0
 */

/**
 * Tests_Privacy_WpPrivacySendErasureFulfillmentNotification class.
 *
 * @group privacy
 * @covers ::_wp_privacy_send_erasure_fulfillment_notification
 *
 * @since 5.1.0
 */
class Tests_Privacy_WpPrivacySendErasureFulfillmentNotification extends WP_UnitTestCase {
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
	 * Create user request fixtures shared by test methods.
	 *
	 * @since 5.1.0
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$requester_email = 'erase-my-data@local.test';
		self::$request_id      = wp_create_user_request( self::$requester_email, 'erase_personal_data' );
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
	public function setUp() {
		parent::setUp();
		reset_phpmailer_instance();
	}

	/**
	 * Reset the mocked PHPMailer instance after each test method.
	 *
	 * @since 5.1.0
	 */
	public function tearDown() {
		reset_phpmailer_instance();
		parent::tearDown();
	}

	/**
	 * The function should send an email when a valid request ID is passed.
	 *
	 * @ticket 44234
	 */
	public function test_should_send_email_no_privacy_policy() {

		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertContains( self::$requester_email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'Erasure Request Fulfilled', $mailer->get_sent()->subject );
		$this->assertContains( 'Your request to erase your personal data', $mailer->get_sent()->body );
		$this->assertContains( 'has been completed.', $mailer->get_sent()->body );
		$this->assertContains( wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), $mailer->get_sent()->body );
		$this->assertContains( home_url(), $mailer->get_sent()->body );

		$this->assertNotContains( 'you can also read our privacy policy', $mailer->get_sent()->body );
		$this->assertTrue( (bool) get_post_meta( self::$request_id, '_wp_user_notified', true ) );
	}

	/**
	 * The email should include a link to the site's privacy policy when set.
	 *
	 * @ticket 44234
	 */
	public function test_should_send_email_with_privacy_policy() {
		$privacy_policy = $this->factory->post->create(
			array(
				'post_type'   => 'page',
				'title'       => 'Site Privacy Policy',
				'post_status' => 'publish',
			)
		);
		update_option( 'wp_page_for_privacy_policy', $privacy_policy );

		_wp_privacy_send_erasure_fulfillment_notification( self::$request_id );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( self::$requester_email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'you can also read our privacy policy', $mailer->get_sent()->body );
		$this->assertContains( get_privacy_policy_url(), $mailer->get_sent()->body );
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
		$this->assertContains( self::$requester_email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'Erasure Request Fulfilled', $mailer->get_sent()->subject );
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
	 * @return string $user_email The email address of the notification recipient.
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
		add_filter( 'user_erasure_complete_email_subject', array( $this, 'filter_email_subject' ) );
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
	 * @return string $subject The email subject.
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
		add_filter( 'user_confirmed_action_email_content', array( $this, 'filter_email_body_text' ) );
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
	 * @return string $email_text Text in the email.
	 */
	public function filter_email_body_text( $email_text ) {
		return 'Modified text';
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
		$post_id = $this->factory->post->create(
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

}
