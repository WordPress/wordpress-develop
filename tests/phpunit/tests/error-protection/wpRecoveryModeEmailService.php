<?php

/**
 * Test the WP_Recovery_Mode_Email_Service class.
 *
 * @group error-protection
 *
 * @covers WP_Recovery_Mode_Email_Service
 */
class Tests_Error_Protection_wpRecoveryModeEmailService extends WP_UnitTestCase {

	/**
	 * Mock link service instance.
	 *
	 * @var WP_Recovery_Mode_Link_Service|PHPUnit\Framework\MockObject\MockObject
	 */
	private $link_service;

	/**
	 * Email service instance under test.
	 *
	 * @var WP_Recovery_Mode_Email_Service
	 */
	private $service;

	/**
	 * Sets up each test method.
	 */
	public function set_up() {
		parent::set_up();

		reset_phpmailer_instance();

		$this->link_service = $this->createMock( WP_Recovery_Mode_Link_Service::class );
		$this->link_service->method( 'generate_url' )->willReturn( 'https://example.com/wp-login.php?action=enter_recovery_mode&rm_token=testtoken&rm_key=testkey' );

		$this->service = new WP_Recovery_Mode_Email_Service( $this->link_service );

		delete_option( WP_Recovery_Mode_Email_Service::RATE_LIMIT_OPTION );
	}

	/**
	 * Cleans up after each test method.
	 */
	public function tear_down() {
		delete_option( WP_Recovery_Mode_Email_Service::RATE_LIMIT_OPTION );
		reset_phpmailer_instance();

		parent::tear_down();
	}

	/**
	 * Tests that maybe_send_recovery_mode_email() successfully sends an email and updates rate limit.
	 *
	 * @ticket 65819
	 */
	public function test_maybe_send_recovery_mode_email_success() {
		$rate_limit = 3600;
		$error      = array(
			'type'    => E_ERROR,
			'message' => 'Call to undefined function test_broken_func()',
			'file'    => '/wp-content/plugins/test-plugin/test-plugin.php',
			'line'    => 42,
		);
		$extension  = array(
			'slug' => 'test-plugin',
			'type' => 'plugin',
		);

		$result = $this->service->maybe_send_recovery_mode_email( $rate_limit, $error, $extension );

		$this->assertTrue( $result, 'Expected maybe_send_recovery_mode_email() to return true on success.' );

		$last_sent = (int) get_option( WP_Recovery_Mode_Email_Service::RATE_LIMIT_OPTION );
		$this->assertGreaterThan( 0, $last_sent, 'Rate limit option should be updated with timestamp.' );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertNotEmpty( $mailer->mock_sent, 'Expected an email to be sent.' );

		$sent_email = end( $mailer->mock_sent );
		$this->assertSame( get_option( 'admin_email' ), $sent_email['to'][0][0] );
		$this->assertStringContainsString( 'Technical Issue', $sent_email['subject'] );
		$this->assertStringContainsString( 'https://example.com/wp-login.php?action=enter_recovery_mode&rm_token=testtoken&rm_key=testkey', $sent_email['body'] );
	}

	/**
	 * Tests that maybe_send_recovery_mode_email() returns WP_Error when already sent within rate limit.
	 *
	 * @ticket 65819
	 */
	public function test_maybe_send_recovery_mode_email_when_already_sent_within_rate_limit() {
		$rate_limit = 3600;
		$last_sent  = time() - 300;
		update_option( WP_Recovery_Mode_Email_Service::RATE_LIMIT_OPTION, $last_sent );

		$error     = array(
			'type'    => E_ERROR,
			'message' => 'Fatal error',
			'file'    => '/wp-content/plugins/test-plugin/test-plugin.php',
			'line'    => 10,
		);
		$extension = array(
			'slug' => 'test-plugin',
			'type' => 'plugin',
		);

		$result = $this->service->maybe_send_recovery_mode_email( $rate_limit, $error, $extension );

		$this->assertWPError( $result );
		$this->assertSame( 'email_sent_already', $result->get_error_code() );
		$this->assertStringContainsString( 'already sent', $result->get_error_message() );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertEmpty( $mailer->mock_sent, 'No email should be sent when within rate limit.' );
	}

	/**
	 * Tests that maybe_send_recovery_mode_email() sends email once the rate limit has expired.
	 *
	 * @ticket 65819
	 */
	public function test_maybe_send_recovery_mode_email_after_rate_limit_expires() {
		$rate_limit = 3600;
		$last_sent  = time() - 4000;
		update_option( WP_Recovery_Mode_Email_Service::RATE_LIMIT_OPTION, $last_sent );

		$error     = array(
			'type'    => E_ERROR,
			'message' => 'Fatal error',
			'file'    => '/wp-content/plugins/test-plugin/test-plugin.php',
			'line'    => 10,
		);
		$extension = array(
			'slug' => 'test-plugin',
			'type' => 'plugin',
		);

		$result = $this->service->maybe_send_recovery_mode_email( $rate_limit, $error, $extension );

		$this->assertTrue( $result, 'Expected email to send after rate limit expired.' );

		$updated_last_sent = (int) get_option( WP_Recovery_Mode_Email_Service::RATE_LIMIT_OPTION );
		$this->assertGreaterThan( $last_sent, $updated_last_sent, 'Rate limit timestamp should be updated.' );
	}

	/**
	 * Tests that maybe_send_recovery_mode_email() returns WP_Error when wp_mail() fails.
	 *
	 * @ticket 65819
	 */
	public function test_maybe_send_recovery_mode_email_when_mail_fails() {
		add_filter( 'pre_wp_mail', '__return_false' );

		$result = $this->service->maybe_send_recovery_mode_email(
			3600,
			array(
				'type'    => E_ERROR,
				'message' => 'Fatal error',
				'file'    => 'test.php',
				'line'    => 1,
			),
			array(
				'slug' => 'test-plugin',
				'type' => 'plugin',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'email_failed', $result->get_error_code() );
	}

	/**
	 * Tests that clear_rate_limit() deletes the rate limit option.
	 *
	 * @ticket 65819
	 */
	public function test_clear_rate_limit() {
		update_option( WP_Recovery_Mode_Email_Service::RATE_LIMIT_OPTION, time() );

		$cleared = $this->service->clear_rate_limit();

		$this->assertTrue( $cleared, 'clear_rate_limit() should return true.' );
		$this->assertFalse( get_option( WP_Recovery_Mode_Email_Service::RATE_LIMIT_OPTION ), 'Option should be deleted.' );
	}

	/**
	 * Tests email content when error is caused by a theme.
	 *
	 * @ticket 65819
	 */
	public function test_maybe_send_recovery_mode_email_with_theme_cause() {
		$error     = array(
			'type'    => E_ERROR,
			'message' => 'Call to undefined function in theme',
			'file'    => '/wp-content/themes/twentytwentyfour/functions.php',
			'line'    => 15,
		);
		$extension = array(
			'slug' => 'twentytwentyfour',
			'type' => 'theme',
		);

		$result = $this->service->maybe_send_recovery_mode_email( 3600, $error, $extension );

		$this->assertTrue( $result );

		$mailer     = tests_retrieve_phpmailer_instance();
		$sent_email = end( $mailer->mock_sent );

		$this->assertStringContainsString( 'error with your theme', $sent_email['body'] );
		$this->assertStringContainsString( 'Twenty Twenty-Four', $sent_email['body'] );
	}

	/**
	 * Tests email content when no extension is specified.
	 *
	 * @ticket 65819
	 */
	public function test_maybe_send_recovery_mode_email_without_extension() {
		$error  = array(
			'type'    => E_ERROR,
			'message' => 'General fatal error',
			'file'    => '/wp-includes/unknown.php',
			'line'    => 1,
		);
		$result = $this->service->maybe_send_recovery_mode_email( 3600, $error, array() );

		$this->assertTrue( $result );

		$mailer     = tests_retrieve_phpmailer_instance();
		$sent_email = end( $mailer->mock_sent );

		$this->assertStringNotContainsString( 'error with one of your plugins', $sent_email['body'] );
		$this->assertStringNotContainsString( 'error with your theme', $sent_email['body'] );
	}

	/**
	 * Tests that the recovery_email_support_info filter customizes support text.
	 *
	 * @ticket 65819
	 */
	public function test_recovery_email_support_info_filter() {
		$custom_support = 'Contact support@example.com for help with this site.';
		add_filter(
			'recovery_email_support_info',
			static function () use ( $custom_support ) {
				return $custom_support;
			}
		);

		$this->service->maybe_send_recovery_mode_email(
			3600,
			array(
				'type'    => E_ERROR,
				'message' => 'Fatal error',
				'file'    => 'test.php',
				'line'    => 1,
			),
			array()
		);

		$mailer     = tests_retrieve_phpmailer_instance();
		$sent_email = end( $mailer->mock_sent );

		$this->assertStringContainsString( $custom_support, $sent_email['body'] );
	}

	/**
	 * Tests that the recovery_email_debug_info filter customizes debug information.
	 *
	 * @ticket 65819
	 */
	public function test_recovery_email_debug_info_filter() {
		add_filter(
			'recovery_email_debug_info',
			static function ( $debug ) {
				$debug['custom_metric'] = 'Server: Custom Enterprise Host';
				return $debug;
			}
		);

		$this->service->maybe_send_recovery_mode_email(
			3600,
			array(
				'type'    => E_ERROR,
				'message' => 'Fatal error',
				'file'    => 'test.php',
				'line'    => 1,
			),
			array()
		);

		$mailer     = tests_retrieve_phpmailer_instance();
		$sent_email = end( $mailer->mock_sent );

		$this->assertStringContainsString( 'Server: Custom Enterprise Host', $sent_email['body'] );
	}

	/**
	 * Tests that the recovery_mode_email filter customizes the email attributes.
	 *
	 * @ticket 65819
	 */
	public function test_recovery_mode_email_filter() {
		add_filter(
			'recovery_mode_email',
			static function ( $email ) {
				$email['subject'] = 'Customized Urgent Alert';
				$email['to']      = 'security@example.com';
				return $email;
			}
		);

		$this->service->maybe_send_recovery_mode_email(
			3600,
			array(
				'type'    => E_ERROR,
				'message' => 'Fatal error',
				'file'    => 'test.php',
				'line'    => 1,
			),
			array()
		);

		$mailer     = tests_retrieve_phpmailer_instance();
		$sent_email = end( $mailer->mock_sent );

		$this->assertSame( 'security@example.com', $sent_email['to'][0][0] );
		$this->assertSame( 'Customized Urgent Alert', $sent_email['subject'] );
	}
}
