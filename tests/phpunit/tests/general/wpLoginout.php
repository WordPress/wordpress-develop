<?php

/**
 * Tests for the `wp_loginout()` function.
 *
 * @group general
 * @group template
 * @group user
 */
class Tests_General_WpLoginout extends WP_UnitTestCase {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	private static $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Tests the log in link for a logged-out visitor.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_loginout
	 *
	 * @dataProvider data_login_link
	 *
	 * @param string $redirect Redirect path passed to the function.
	 * @param string $expected Expected markup after the host.
	 */
	public function test_login_link_when_logged_out( $redirect, $expected ) {
		$this->assertSame( '<a href="http://' . WP_TESTS_DOMAIN . $expected, wp_loginout( $redirect, false ) );
	}

	/**
	 * Data provider for test_login_link_when_logged_out().
	 *
	 * @return array[]
	 */
	public static function data_login_link() {
		return array(
			'no redirect'   => array( '', '/wp-login.php">Log in</a>' ),
			'with redirect' => array( '/sample-page/', '/wp-login.php?redirect_to=%2Fsample-page%2F">Log in</a>' ),
		);
	}

	/**
	 * Tests the log out link for a logged-in user.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_loginout
	 *
	 * @dataProvider data_logout_link
	 *
	 * @param string $redirect Redirect path passed to the function.
	 * @param string $query    Expected query string before the nonce.
	 */
	public function test_logout_link_when_logged_in( $redirect, $query ) {
		wp_set_current_user( self::$user_id );

		$this->assertSame(
			'<a href="http://' . WP_TESTS_DOMAIN . '/wp-login.php?' . $query . '&#038;_wpnonce=' . wp_create_nonce( 'log-out' ) . '">Log out</a>',
			wp_loginout( $redirect, false )
		);
	}

	/**
	 * Data provider for test_logout_link_when_logged_in().
	 *
	 * @return array[]
	 */
	public static function data_logout_link() {
		return array(
			'no redirect'   => array( '', 'action=logout' ),
			'with redirect' => array( '/sample-page/', 'action=logout&#038;redirect_to=%2Fsample-page%2F' ),
		);
	}

	/**
	 * Tests that the link URL is escaped.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_loginout
	 */
	public function test_login_link_url_is_escaped() {
		add_filter(
			'login_url',
			static function () {
				return 'https://example.com/login/?a=1&b=2';
			}
		);

		$this->assertSame( '<a href="https://example.com/login/?a=1&#038;b=2">Log in</a>', wp_loginout( '', false ) );
	}

	/**
	 * Tests that the link is echoed by default.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_loginout
	 */
	public function test_echoes_link_by_default() {
		$this->expectOutputString( '<a href="http://' . WP_TESTS_DOMAIN . '/wp-login.php?redirect_to=%2Fsample-page%2F">Log in</a>' );

		$this->assertNull( wp_loginout( '/sample-page/' ) );
	}

	/**
	 * Tests that the 'loginout' filter receives the link and its result is used.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_loginout
	 *
	 * @dataProvider data_display
	 *
	 * @param bool $display Whether to echo the link.
	 */
	public function test_loginout_filter( $display ) {
		$received = array();

		add_filter(
			'loginout',
			static function ( $link ) use ( &$received ) {
				$received[] = $link;

				return $link . '-filtered';
			}
		);

		$link = '<a href="http://' . WP_TESTS_DOMAIN . '/wp-login.php">Log in</a>';

		if ( $display ) {
			$this->expectOutputString( $link . '-filtered' );
			wp_loginout();
		} else {
			$this->assertSame( $link . '-filtered', wp_loginout( '', false ) );
		}

		$this->assertSame( array( $link ), $received, 'The filter should run once with the unfiltered link.' );
	}

	/**
	 * Data provider for test_loginout_filter().
	 *
	 * @return array[]
	 */
	public static function data_display() {
		return array(
			'echo'   => array( true ),
			'return' => array( false ),
		);
	}
}
