<?php

/**
 * Tests for wp_login_url().
 *
 * @group general
 * @group login
 *
 * @covers ::wp_login_url
 */
class Tests_General_WpLoginUrl extends WP_UnitTestCase {

	/**
	 * Tests that wp_login_url() returns the default login URL.
	 *
	 * @ticket 65819
	 */
	public function test_wp_login_url_default() {
		$this->assertSame( site_url( 'wp-login.php', 'login' ), wp_login_url() );
	}

	/**
	 * Tests that wp_login_url() appends the redirect_to parameter when a redirect is provided.
	 *
	 * @ticket 65819
	 */
	public function test_wp_login_url_with_redirect() {
		$redirect  = home_url( '/custom-dashboard/' );
		$login_url = wp_login_url( $redirect );

		$query = array();
		wp_parse_str( wp_parse_url( $login_url, PHP_URL_QUERY ), $query );

		$this->assertSame( $redirect, $query['redirect_to'] );
		$this->assertArrayNotHasKey( 'reauth', $query );
	}

	/**
	 * Tests that wp_login_url() appends reauth=1 when $force_reauth is true.
	 *
	 * @ticket 65819
	 */
	public function test_wp_login_url_with_force_reauth() {
		$login_url = wp_login_url( '', true );

		$query = array();
		wp_parse_str( wp_parse_url( $login_url, PHP_URL_QUERY ), $query );

		$this->assertSame( '1', $query['reauth'] );
		$this->assertArrayNotHasKey( 'redirect_to', $query );
	}

	/**
	 * Tests that wp_login_url() appends both redirect_to and reauth when both parameters are set.
	 *
	 * @ticket 65819
	 */
	public function test_wp_login_url_with_redirect_and_force_reauth() {
		$redirect  = home_url( '/profile/' );
		$login_url = wp_login_url( $redirect, true );

		$query = array();
		wp_parse_str( wp_parse_url( $login_url, PHP_URL_QUERY ), $query );

		$this->assertSame( $redirect, $query['redirect_to'] );
		$this->assertSame( '1', $query['reauth'] );
	}

	/**
	 * Tests that the 'login_url' filter receives the expected arguments and modifies the URL.
	 *
	 * @ticket 65819
	 */
	public function test_login_url_filter() {
		$filter = new MockAction();
		add_filter( 'login_url', array( $filter, 'filter' ), 10, 3 );

		$redirect = home_url( '/landing/' );
		$url      = wp_login_url( $redirect, true );

		$this->assertSame( 1, $filter->get_call_count() );

		$filter_args = $filter->get_args();
		$this->assertSame( $url, $filter_args[0][0] );
		$this->assertSame( $redirect, $filter_args[0][1] );
		$this->assertTrue( $filter_args[0][2] );

		$custom_login_callback = static function ( $login_url, $redirect, $force_reauth ) {
			return 'https://sso.example.com/login';
		};

		add_filter( 'login_url', $custom_login_callback, 10, 3 );
		$this->assertSame( 'https://sso.example.com/login', wp_login_url() );
		remove_filter( 'login_url', $custom_login_callback, 10 );
	}
}
