<?php

/**
 * Tests for the `wpmu_signup_blog_notification()` function.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ms-required
 * @group multisite
 *
 * @covers ::wpmu_signup_blog_notification
 */
class Tests_Multisite_wpmuSignupBlogNotification extends WP_UnitTestCase {

	protected static $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'user_login' => 'testbloguser',
				'user_email' => 'testbloguser@example.com',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		wpmu_delete_user( self::$user_id );
	}

	public function set_up() {
		parent::set_up();
		reset_phpmailer_instance();
	}

	public function tear_down() {
		reset_phpmailer_instance();
		unset( $_SERVER['HTTPS'] );
		parent::tear_down();
	}

	/**
	 * @ticket 65521
	 */
	public function test_returns_false_when_filter_bypasses_notification() {
		add_filter( 'wpmu_signup_blog_notification', '__return_false' );

		$result = wpmu_signup_blog_notification(
			'example.com',
			'/',
			'Test Site',
			'testbloguser',
			'testbloguser@example.com',
			'activationkey123'
		);

		remove_filter( 'wpmu_signup_blog_notification', '__return_false' );

		$this->assertFalse( $result );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	/**
	 * @ticket 65521
	 */
	public function test_returns_true_and_sends_email() {
		$result = wpmu_signup_blog_notification(
			'example.com',
			'/',
			'Test Site',
			'testbloguser',
			'testbloguser@example.com',
			'activationkey123'
		);

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $result );
		$this->assertSame( 'testbloguser@example.com', $mailer->get_recipient( 'to' )->address );
		$this->assertStringContainsString( 'wp-activate.php', $mailer->get_sent()->body );
		$this->assertStringContainsString( 'activationkey123', $mailer->get_sent()->body );
	}

	/**
	 * The activation URL in the email body must use HTTPS when the site is
	 * running over SSL, so that users on HTTPS multisite installs do not receive
	 * mixed-content activation links.
	 *
	 * @ticket 65521
	 */
	public function test_activation_url_uses_https_scheme_when_ssl_is_on() {
		if ( ! is_subdomain_install() ) {
			$this->markTestSkipped( 'This test requires a subdomain install.' );
		}

		$_SERVER['HTTPS'] = 'on';

		wpmu_signup_blog_notification(
			'newsite.example.com',
			'/',
			'New Site',
			'testbloguser',
			'testbloguser@example.com',
			'securekey456'
		);

		$body = tests_retrieve_phpmailer_instance()->get_sent()->body;

		$this->assertStringContainsString( 'https://newsite.example.com/', $body );
		$this->assertStringNotContainsString( 'http://newsite.example.com/', $body );
	}
}
