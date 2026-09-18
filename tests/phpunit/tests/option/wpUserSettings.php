<?php
/**
 * Test wp_user_settings().
 *
 * @group option
 * @group user
 * @covers ::wp_user_settings
 */
class Tests_Option_wpUserSettings extends WP_UnitTestCase {

	/**
	 * Cookies captured via the `send_cookie` filter, keyed by cookie name.
	 *
	 * @var array<string, array>
	 */
	private $sent_cookies = array();

	public function set_up() {
		parent::set_up();

		$this->sent_cookies = array();
	}

	/**
	 * Records a cookie sent via wp_set_cookie() and prevents it from actually
	 * being sent, so the value and options can be asserted without setcookie()
	 * attempting to modify headers that have already been sent under PHPUnit.
	 *
	 * @param bool   $send    Whether to send the cookie.
	 * @param string $name    The name of the cookie.
	 * @param string $value   The value of the cookie.
	 * @param array  $options The options passed to setcookie().
	 * @return false Always false, to prevent the cookie from being sent.
	 */
	public function filter_send_cookie( $send, $name, $value, $options ) {
		$this->sent_cookies[ $name ] = array(
			'value'   => $value,
			'options' => $options,
		);

		return false;
	}

	/**
	 * Tests that the user settings cookies are sent with the stored settings value
	 * and the expected cookie options.
	 *
	 * @ticket 54914
	 */
	public function test_wp_user_settings_sends_the_settings_cookies() {
		set_current_screen( 'edit.php' );
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		// Verify that the function's starting conditions are satisfied.
		$this->assertTrue( is_admin() );
		$this->assertGreaterThan( 0, get_current_user_id() );

		update_user_option( $user_id, 'user-settings', 'foo=bar' );

		add_filter( 'send_cookie', array( $this, 'filter_send_cookie' ), 10, 4 );

		wp_user_settings();

		// The settings cookie is sent with the stored settings value.
		$this->assertArrayHasKey( 'wp-settings-' . $user_id, $this->sent_cookies );
		$this->assertSame( 'foo=bar', $this->sent_cookies[ 'wp-settings-' . $user_id ]['value'] );

		// The companion timestamp cookie is also sent.
		$this->assertArrayHasKey( 'wp-settings-time-' . $user_id, $this->sent_cookies );
	}
}
