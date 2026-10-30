<?php

/**
 * Tests for wp_logout_url().
 *
 * @group general
 * @group login
 *
 * @covers ::wp_logout_url
 */
class Tests_General_WpLogoutUrl extends WP_UnitTestCase {

	/**
	 * Tests that wp_logout_url() returns the default logout URL with a valid nonce.
	 *
	 * @ticket 65819
	 */
	public function test_wp_logout_url_default() {
		$logout_url = wp_logout_url();

		$this->assertStringStartsWith( site_url( 'wp-login.php?action=logout', 'login' ), $logout_url );

		$query = array();
		wp_parse_str( wp_parse_url( wp_specialchars_decode( $logout_url ), PHP_URL_QUERY ), $query );

		$this->assertSame( 'logout', $query['action'] );
		$this->assertArrayHasKey( '_wpnonce', $query );
		$this->assertSame( 1, wp_verify_nonce( $query['_wpnonce'], 'log-out' ) );
	}

	/**
	 * Tests that wp_logout_url() adds the redirect_to query arg when a redirect URL is passed.
	 *
	 * @ticket 65819
	 */
	public function test_wp_logout_url_with_redirect() {
		$redirect   = home_url( '/custom-page/' );
		$logout_url = wp_logout_url( $redirect );

		$query = array();
		wp_parse_str( wp_parse_url( wp_specialchars_decode( $logout_url ), PHP_URL_QUERY ), $query );

		$this->assertSame( $redirect, $query['redirect_to'] );
		$this->assertArrayHasKey( '_wpnonce', $query );
		$this->assertSame( 1, wp_verify_nonce( $query['_wpnonce'], 'log-out' ) );
	}

	/**
	 * Tests that the 'logout_url' filter receives the expected arguments and modifies the URL.
	 *
	 * @ticket 65819
	 */
	public function test_logout_url_filter() {
		$filter = new MockAction();
		add_filter( 'logout_url', array( $filter, 'filter' ), 10, 2 );

		$redirect = home_url( '/redirect/' );
		$url      = wp_logout_url( $redirect );

		$this->assertSame( 1, $filter->get_call_count() );

		$filter_args = $filter->get_args();
		$this->assertSame( $url, $filter_args[0][0] );
		$this->assertSame( $redirect, $filter_args[0][1] );

		$custom_logout_callback = static function ( $logout_url, $redirect ) {
			return 'https://custom-auth.example.com/logout';
		};

		add_filter( 'logout_url', $custom_logout_callback, 10, 2 );
		$this->assertSame( 'https://custom-auth.example.com/logout', wp_logout_url() );
		remove_filter( 'logout_url', $custom_logout_callback, 10 );
	}
}
