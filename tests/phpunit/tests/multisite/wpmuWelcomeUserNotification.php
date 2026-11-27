<?php

/**
 * @group ms-required
 * @group multisite
 *
 * @covers ::wpmu_welcome_user_notification
 */
class Tests_Multisite_wpmuWelcomeUserNotification extends WP_UnitTestCase {

	protected static $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'user_email' => 'welcome-user-notification@example.com',
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
	 * @ticket 49564
	 */
	public function test_email_headers_should_be_filterable() {
		add_filter( 'update_welcome_user_headers', array( $this, 'filter_email_headers' ) );
		wpmu_welcome_user_notification( self::$user_id, 'password' );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertStringContainsString( 'From: Tester <tester@example.com>', $mailer->get_sent()->header );
	}

	/**
	 * @ticket 49564
	 */
	public function test_email_headers_filter_should_receive_the_default_headers_and_call_args() {
		$received = null;

		add_filter(
			'update_welcome_user_headers',
			function ( $headers, $user_id, $password, $meta ) use ( &$received ) {
				$received = array( $headers, $user_id, $password, $meta );

				return $headers;
			},
			10,
			4
		);

		wpmu_welcome_user_notification( self::$user_id, 'password', array( 'foo' => 'bar' ) );

		$this->assertIsArray( $received[0] );
		$this->assertNotEmpty( $received[0] );
		$this->assertSame( self::$user_id, $received[1] );
		$this->assertSame( 'password', $received[2] );
		$this->assertSame( array( 'foo' => 'bar' ), $received[3] );
	}

	/**
	 * @ticket 49564
	 */
	public function test_default_headers_should_still_be_sent_without_the_filter() {
		update_site_option( 'site_name', 'Test Network' );

		wpmu_welcome_user_notification( self::$user_id, 'password' );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertStringContainsString( 'From: Test Network <', $mailer->get_sent()->header );
	}

	public function filter_email_headers( $headers ) {
		return array( 'From: Tester <tester@example.com>' );
	}
}
