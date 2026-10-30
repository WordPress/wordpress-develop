<?php

/**
 * Tests for the wp_lostpassword_url() function.
 *
 * @group general
 * @group template
 *
 * @covers ::wp_lostpassword_url
 */
class Tests_General_WpLostpasswordUrl extends WP_UnitTestCase {

	/**
	 * Tests that wp_lostpassword_url() returns the default lost password URL.
	 *
	 * @ticket 65819
	 */
	public function test_wp_lostpassword_url_default() {
		$login_path = is_multisite() ? get_site()->path . 'wp-login.php' : 'wp-login.php';
		$expected   = add_query_arg( 'action', 'lostpassword', network_site_url( $login_path, 'login' ) );

		$this->assertSame( $expected, wp_lostpassword_url() );
	}

	/**
	 * Tests that wp_lostpassword_url() appends redirect_to query parameter.
	 *
	 * @ticket 65819
	 */
	public function test_wp_lostpassword_url_with_redirect() {
		$redirect = 'https://example.org/dashboard';
		$url      = wp_lostpassword_url( $redirect );

		$query = wp_parse_url( $url, PHP_URL_QUERY );
		$this->assertIsString( $query );

		wp_parse_str( $query, $query_args );

		$this->assertArrayHasKey( 'action', $query_args );
		$this->assertSame( 'lostpassword', $query_args['action'] );
		$this->assertArrayHasKey( 'redirect_to', $query_args );
		$this->assertSame( $redirect, $query_args['redirect_to'] );
	}

	/**
	 * Tests that the 'lostpassword_url' filter receives expected arguments and modifies the output.
	 *
	 * @ticket 65819
	 */
	public function test_wp_lostpassword_url_filtered() {
		$filter = new MockAction();
		add_filter( 'lostpassword_url', array( $filter, 'filter' ), 10, 2 );

		$redirect = 'https://example.org/dashboard';
		$url      = wp_lostpassword_url( $redirect );

		$this->assertSame( 1, $filter->get_call_count() );
		$args = $filter->get_args();
		$this->assertSame( $url, $args[0][0] );
		$this->assertSame( $redirect, $args[0][1] );

		add_filter(
			'lostpassword_url',
			static function () {
				return 'https://example.org/custom-lost-password';
			}
		);

		$this->assertSame( 'https://example.org/custom-lost-password', wp_lostpassword_url() );
	}
}
