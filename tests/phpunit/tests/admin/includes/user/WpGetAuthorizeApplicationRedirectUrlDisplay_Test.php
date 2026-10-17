<?php

/**
 * @group admin
 * @group user
 *
 * @covers ::wp_get_authorize_application_redirect_url_display
 */
class Admin_Includes_User_WpGetAuthorizeApplicationRedirectUrlDisplay_Test extends WP_UnitTestCase {

	/**
	 * Test the destination displayed to the user on the application password authorization screen.
	 *
	 * @ticket 66009
	 *
	 * @dataProvider data_wp_get_authorize_application_redirect_url_display
	 *
	 * @param string $url      The redirect URL.
	 * @param string $expected The expected display value.
	 */
	public function test_wp_get_authorize_application_redirect_url_display( $url, $expected ) {
		$actual = wp_get_authorize_application_redirect_url_display( $url );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for test_wp_get_authorize_application_redirect_url_display.
	 *
	 * @return list<array{
	 *   url: string,
	 *   expected: string,
	 * }>
	 */
	public function data_wp_get_authorize_application_redirect_url_display() {
		return array(
			'an "https" URL'                  => array(
				'url'      => 'https://example.org/callback?foo=bar#baz',
				'expected' => 'example.org',
			),
			'an "http" URL'                   => array(
				'url'      => 'http://example.org/callback',
				'expected' => 'example.org',
			),
			'a URL with a port'               => array(
				'url'      => 'https://example.org:8443/callback',
				'expected' => 'example.org',
			),
			'a subdomain'                     => array(
				'url'      => 'https://app.example.org/callback',
				'expected' => 'app.example.org',
			),
			'a loopback IPv4 address'         => array(
				'url'      => 'http://127.0.0.1:8080/callback',
				'expected' => '127.0.0.1',
			),
			'a loopback IPv6 address'         => array(
				'url'      => 'http://[::1]:8080/callback',
				'expected' => '[::1]',
			),
			'a mixed case scheme and host'    => array(
				'url'      => 'HTTPS://Example.ORG/Callback',
				'expected' => 'example.org',
			),
			'a custom scheme'                 => array(
				'url'      => 'wordpress://example/callback',
				'expected' => 'wordpress://example',
			),
			'a custom scheme with a dot host' => array(
				'url'      => 'com.example.app://auth/callback',
				'expected' => 'com.example.app://auth',
			),
			'a mixed case custom scheme'      => array(
				'url'      => 'WordPress://Example/callback',
				'expected' => 'wordpress://example',
			),
		);
	}
}
