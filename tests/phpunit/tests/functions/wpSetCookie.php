<?php

/**
 * @group functions
 * @group cookies
 *
 * @covers ::wp_set_cookie
 * @covers ::wp_unset_cookie
 */
class Tests_Functions_WpSetCookie extends WP_UnitTestCase {

	/**
	 * Cookies captured via the `send_cookie` filter.
	 *
	 * @var array<int, array>
	 */
	private $sent_cookies = array();

	public function set_up() {
		parent::set_up();

		$this->sent_cookies = array();
	}

	/**
	 * Records a cookie sent via wp_set_cookie() and prevents it from actually
	 * being sent, so the value and options can be asserted.
	 *
	 * @param bool   $send    Whether to send the cookie.
	 * @param string $name    The name of the cookie.
	 * @param string $value   The value of the cookie.
	 * @param array  $options The options passed to setcookie().
	 * @return false Always false, to prevent the cookie from being sent.
	 */
	public function filter_capture_cookie( $send, $name, $value, $options ) {
		$this->sent_cookies[] = array(
			'name'    => $name,
			'value'   => $value,
			'options' => $options,
		);

		return false;
	}

	/**
	 * Forces the SameSite and Secure options, used to test the options filter.
	 *
	 * @param array $options The cookie options.
	 * @return array The modified options.
	 */
	public function filter_force_strict_samesite( $options ) {
		$options['samesite'] = 'Strict';
		$options['secure']   = true;

		return $options;
	}

	/**
	 * Tests that the `wp_set_cookie_options` filter can modify the options that
	 * are ultimately passed to setcookie().
	 *
	 * @ticket 37000
	 */
	public function test_wp_set_cookie_options_filter_can_modify_options() {
		add_filter( 'wp_set_cookie_options', array( $this, 'filter_force_strict_samesite' ) );
		add_filter( 'send_cookie', array( $this, 'filter_capture_cookie' ), 10, 4 );

		wp_set_cookie(
			'test_cookie',
			'test_value',
			array(
				'path'     => '/',
				'samesite' => 'Lax',
			)
		);

		$options = $this->sent_cookies[0]['options'];

		$this->assertSame( 'Strict', $options['samesite'] );
		$this->assertTrue( $options['secure'] );
		$this->assertSame( '/', $options['path'] );
	}
}
