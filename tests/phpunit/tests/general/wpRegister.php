<?php

/**
 * Tests for the `wp_register()` function.
 *
 * @group general
 * @group template
 * @group user
 */
class Tests_General_WpRegister extends WP_UnitTestCase {

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	/**
	 * ID of a user without any role on the site.
	 *
	 * @var int
	 */
	private static $no_role_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
		self::$no_role_id    = $factory->user->create( array( 'role' => '' ) );
	}

	/**
	 * Sets whether users can register.
	 *
	 * Short-circuits the option, because on multisite it is derived from the
	 * network's 'registration' option.
	 *
	 * @param bool $enabled Whether registration is enabled.
	 */
	private function set_users_can_register( $enabled ) {
		add_filter( 'pre_option_users_can_register', $enabled ? '__return_true' : '__return_zero' );
	}

	/**
	 * Tests the registration link for logged-out visitors.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_register
	 *
	 * @dataProvider data_register_link
	 *
	 * @param string $before Text before the link.
	 * @param string $after  Text after the link.
	 */
	public function test_register_link_when_registration_is_enabled( $before, $after ) {
		$this->set_users_can_register( true );

		$this->assertSame(
			$before . '<a href="http://' . WP_TESTS_DOMAIN . '/wp-login.php?action=register">Register</a>' . $after,
			wp_register( $before, $after, false )
		);
	}

	/**
	 * Data provider for test_register_link_when_registration_is_enabled().
	 *
	 * @return array[]
	 */
	public static function data_register_link() {
		return array(
			'custom wrapper' => array( '<p class="register">', '</p>' ),
			'no wrapper'     => array( '', '' ),
		);
	}

	/**
	 * Tests that the registration URL is escaped.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_register
	 */
	public function test_register_url_is_escaped() {
		$this->set_users_can_register( true );

		add_filter(
			'register_url',
			static function () {
				return 'https://example.com/register/?a=1&b=2';
			}
		);

		$this->assertSame(
			'<li><a href="https://example.com/register/?a=1&#038;b=2">Register</a></li>',
			wp_register( '<li>', '</li>', false )
		);
	}

	/**
	 * Tests the admin link for a logged-in user who can read.
	 *
	 * Registration is disabled to show the admin link does not depend on it.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_register
	 */
	public function test_admin_link_when_logged_in() {
		$this->set_users_can_register( false );
		wp_set_current_user( self::$subscriber_id );

		$this->assertSame(
			'<p class="admin"><a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/">Site Admin</a></p>',
			wp_register( '<p class="admin">', '</p>', false )
		);
	}

	/**
	 * Tests that nothing is returned for a logged-in user who cannot read.
	 *
	 * Registration is enabled to show the register link is not used for logged-in users.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_register
	 */
	public function test_empty_when_logged_in_user_cannot_read() {
		$this->set_users_can_register( true );
		wp_set_current_user( self::$no_role_id );

		$this->assertSame( '', wp_register( '<li>', '</li>', false ) );
	}

	/**
	 * Tests that the link is echoed by default.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_register
	 */
	public function test_echoes_link_by_default() {
		$this->set_users_can_register( true );

		$this->expectOutputString( '<li><a href="http://' . WP_TESTS_DOMAIN . '/wp-login.php?action=register">Register</a></li>' );

		$this->assertNull( wp_register() );
	}

	/**
	 * Tests that the 'register' filter receives the link and its result is used.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_register
	 *
	 * @dataProvider data_register_filter
	 *
	 * @param bool   $enabled  Whether registration is enabled.
	 * @param string $expected Link expected to reach the filter, after the host.
	 */
	public function test_register_filter( $enabled, $expected ) {
		$this->set_users_can_register( $enabled );

		$received = array();

		add_filter(
			'register',
			static function ( $link ) use ( &$received ) {
				$received[] = $link;

				return $link . '-filtered';
			}
		);

		$link = '' === $expected ? '' : '<li><a href="http://' . WP_TESTS_DOMAIN . $expected;

		$this->assertSame( $link . '-filtered', wp_register( '<li>', '</li>', false ) );
		$this->assertSame( array( $link ), $received, 'The filter should run once with the unfiltered link.' );
	}

	/**
	 * Data provider for test_register_filter().
	 *
	 * @return array[]
	 */
	public static function data_register_filter() {
		return array(
			'registration enabled'  => array( true, '/wp-login.php?action=register">Register</a></li>' ),
			'registration disabled' => array( false, '' ),
		);
	}
}
