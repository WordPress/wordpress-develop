<?php

/**
 * Tests for the behavior of `wp_application_password_created_notification()`.
 *
 * @group functions
 * @group auth
 *
 * @covers ::wp_application_password_created_notification
 */
class Tests_Functions_wpApplicationPasswordCreatedNotification extends WP_UnitTestCase {

	/**
	 * Test user.
	 *
	 * @var WP_User
	 */
	protected static $user;

	/**
	 * Creates the user shared by the test methods.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user = $factory->user->create_and_get(
			array(
				'user_email'   => 'app-password-user@example.org',
				'display_name' => 'App Password User',
			)
		);
	}

	public function set_up() {
		parent::set_up();
		reset_phpmailer_instance();
	}

	public function tear_down() {
		reset_phpmailer_instance();
		parent::tear_down();
	}

	/**
	 * @ticket 63927
	 */
	public function test_sends_email_when_application_password_is_created() {
		$created = WP_Application_Passwords::create_new_application_password( self::$user->ID, array( 'name' => 'My App' ) );

		$this->assertNotWPError( $created, 'The application password should be created.' );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertCount( 1, $mailer->mock_sent, 'Exactly one email should be sent.' );
		$this->assertSame( self::$user->user_email, $mailer->get_recipient( 'to' )->address );
	}

	/**
	 * @ticket 63927
	 */
	public function test_email_replaces_all_placeholders() {
		update_option( 'blogname', 'Test Blog' );
		update_option( 'admin_email', 'site-admin@example.org' );
		// Changing the admin email sends its own notification.
		reset_phpmailer_instance();

		wp_application_password_created_notification( self::$user->ID, array( 'name' => 'My App' ) );

		$mailer = tests_retrieve_phpmailer_instance();
		$sent   = $mailer->get_sent();

		$this->assertSame( '[Test Blog] Application Password Created', $sent->subject );
		$this->assertStringNotContainsString( '###', $sent->body, 'All placeholders should be replaced.' );
		$this->assertStringContainsString( 'Hi App Password User,', $sent->body );
		$this->assertStringContainsString( 'Application password name: My App', $sent->body );
		$this->assertStringContainsString( 'site-admin@example.org', $sent->body );
		$this->assertStringContainsString( 'This email has been sent to app-password-user@example.org', $sent->body );
		$this->assertStringContainsString( 'All at Test Blog', $sent->body );
		$this->assertStringContainsString( home_url(), $sent->body );
	}

	/**
	 * @ticket 63927
	 */
	public function test_does_not_send_email_when_disabled_by_filter() {
		add_filter( 'wp_send_application_password_created_email', '__return_false' );

		wp_application_password_created_notification( self::$user->ID, array( 'name' => 'My App' ) );

		$this->assertEmpty( tests_retrieve_phpmailer_instance()->mock_sent );
	}

	/**
	 * @ticket 63927
	 */
	public function test_send_filter_receives_user_and_application_password() {
		$filter = new MockAction();
		add_filter( 'wp_send_application_password_created_email', array( $filter, 'filter' ), 10, 3 );

		$new_item = array( 'name' => 'My App' );
		wp_application_password_created_notification( self::$user->ID, $new_item );

		$args = $filter->get_args();
		$this->assertCount( 1, $args, 'The filter should run once.' );
		$this->assertTrue( $args[0][0], 'The email should be sent by default.' );
		$this->assertSame( self::$user->ID, $args[0][1]->ID );
		$this->assertSame( $new_item, $args[0][2] );
	}

	/**
	 * @ticket 63927
	 */
	public function test_email_can_be_changed_by_filter() {
		add_filter(
			'wp_application_password_created_email',
			static function ( $email ) {
				$email['to']      = 'someone-else@example.org';
				$email['subject'] = 'Custom subject for %s';
				$email['message'] = 'Custom message for ###APPLICATION_PASSWORD_NAME###';
				return $email;
			}
		);
		update_option( 'blogname', 'Test Blog' );

		wp_application_password_created_notification( self::$user->ID, array( 'name' => 'My App' ) );

		$mailer = tests_retrieve_phpmailer_instance();
		$sent   = $mailer->get_sent();

		$this->assertSame( 'someone-else@example.org', $mailer->get_recipient( 'to' )->address );
		$this->assertSame( 'Custom subject for Test Blog', $sent->subject );
		$this->assertStringContainsString( 'Custom message for My App', $sent->body, 'Placeholders in a filtered message should still be replaced.' );
	}

	/**
	 * @ticket 63927
	 * @dataProvider data_does_not_send_email_for_invalid_input
	 *
	 * @param callable $get_args Returns the user ID and application password details to pass.
	 */
	public function test_does_not_send_email_for_invalid_input( $get_args ) {
		list( $user_id, $new_item ) = $get_args( self::$user->ID );

		wp_application_password_created_notification( $user_id, $new_item );

		$this->assertEmpty( tests_retrieve_phpmailer_instance()->mock_sent );
	}

	/**
	 * Data provider for test_does_not_send_email_for_invalid_input().
	 *
	 * @return array<string, array{0: callable}>
	 */
	public static function data_does_not_send_email_for_invalid_input() {
		return array(
			'user does not exist'          => array(
				static function () {
					return array( PHP_INT_MAX, array( 'name' => 'My App' ) );
				},
			),
			'application password unnamed' => array(
				static function ( $user_id ) {
					return array( $user_id, array( 'name' => '' ) );
				},
			),
			'name missing'                 => array(
				static function ( $user_id ) {
					return array( $user_id, array() );
				},
			),
		);
	}

	/**
	 * @ticket 63927
	 */
	public function test_does_not_send_email_when_user_email_is_invalid() {
		global $wpdb;

		$user_id = self::factory()->user->create();
		$wpdb->update( $wpdb->users, array( 'user_email' => 'not-an-email' ), array( 'ID' => $user_id ) );
		clean_user_cache( $user_id );

		wp_application_password_created_notification( $user_id, array( 'name' => 'My App' ) );

		$this->assertEmpty( tests_retrieve_phpmailer_instance()->mock_sent );
	}
}
