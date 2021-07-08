<?php

/**
 * @group http
 * @group external-http
 * @requires extension openssl
 */
class Tests_HTTP_Functions extends WP_UnitTestCase {

	/**
	 * @covers ::wp_remote_head
	 */
	function test_head_request() {
		// This URL gives a direct 200 response.
		$url      = 'https://asdftestblog1.files.wordpress.com/2007/09/2007-06-30-dsc_4700-1.jpg';
		$response = wp_remote_head( $url );

		$this->skipTestOnTimeout( $response );

		$headers = wp_remote_retrieve_headers( $response );

		$this->assertIsArray( $response );

		$this->assertSame( 'image/jpeg', $headers['content-type'] );
		$this->assertSame( '40148', $headers['content-length'] );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * @covers ::wp_remote_head
	 */
	function test_head_redirect() {
		// This URL will 301 redirect.
		$url      = 'https://asdftestblog1.wordpress.com/files/2007/09/2007-06-30-dsc_4700-1.jpg';
		$response = wp_remote_head( $url );

		$this->skipTestOnTimeout( $response );
		$this->assertSame( 301, wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * @covers ::wp_remote_head
	 */
	function test_head_404() {
		$url      = 'https://asdftestblog1.files.wordpress.com/2007/09/awefasdfawef.jpg';
		$response = wp_remote_head( $url );

		$this->skipTestOnTimeout( $response );
		$this->assertSame( 404, wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_headers
	 * @covers ::wp_remote_retrieve_response_code
	 */
	function test_get_request() {
		$url = 'https://asdftestblog1.files.wordpress.com/2007/09/2007-06-30-dsc_4700-1.jpg';

		$response = wp_remote_get( $url );

		$this->skipTestOnTimeout( $response );

		$headers = wp_remote_retrieve_headers( $response );

		$this->assertIsArray( $response );

		// Should return the same headers as a HEAD request.
		$this->assertSame( 'image/jpeg', $headers['content-type'] );
		$this->assertSame( '40148', $headers['content-length'] );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_headers
	 * @covers ::wp_remote_retrieve_response_code
	 */
	function test_get_redirect() {
		// This will redirect to asdftestblog1.files.wordpress.com.
		$url = 'https://asdftestblog1.wordpress.com/files/2007/09/2007-06-30-dsc_4700-1.jpg';

		$response = wp_remote_get( $url );

		$this->skipTestOnTimeout( $response );

		$headers = wp_remote_retrieve_headers( $response );

		// Should return the same headers as a HEAD request.
		$this->assertSame( 'image/jpeg', $headers['content-type'] );
		$this->assertSame( '40148', $headers['content-length'] );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * @covers ::wp_remote_get
	 */
	function test_get_redirect_limit_exceeded() {
		// This will redirect to asdftestblog1.files.wordpress.com.
		$url = 'https://asdftestblog1.wordpress.com/files/2007/09/2007-06-30-dsc_4700-1.jpg';

		// Pretend we've already redirected 5 times.
		$response = wp_remote_get( $url, array( 'redirection' => -1 ) );

		$this->skipTestOnTimeout( $response );
		$this->assertWPError( $response );
	}

	/**
	 * @ticket 33711
	 *
	 * @covers ::wp_remote_head
	 * @covers ::wp_remote_retrieve_cookies
	 * @covers ::wp_remote_retrieve_cookie
	 * @covers ::wp_remote_retrieve_cookie_value
	 */
	function test_get_response_cookies() {
		$url = 'https://login.wordpress.org/wp-login.php';

		$response = wp_remote_head( $url );

		$this->skipTestOnTimeout( $response );

		$cookies = wp_remote_retrieve_cookies( $response );

		$this->assertNotEmpty( $cookies );

		$cookie = wp_remote_retrieve_cookie( $response, 'wordpress_test_cookie' );
		$this->assertInstanceOf( 'WP_Http_Cookie', $cookie );
		$this->assertSame( 'wordpress_test_cookie', $cookie->name );
		$this->assertSame( 'WP Cookie check', $cookie->value );

		$value = wp_remote_retrieve_cookie_value( $response, 'wordpress_test_cookie' );
		$this->assertSame( 'WP Cookie check', $value );

		$no_value = wp_remote_retrieve_cookie_value( $response, 'not_a_cookie' );
		$this->assertSame( '', $no_value );

		$no_cookie = wp_remote_retrieve_cookie( $response, 'not_a_cookie' );
		$this->assertSame( '', $no_cookie );
	}

	/**
	 * @ticket 37437
	 *
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_cookies
	 * @covers ::wp_remote_retrieve_cookie
	 */
	function test_get_response_cookies_with_wp_http_cookie_object() {
		$url = 'http://example.org';

		$response = wp_remote_get(
			$url,
			array(
				'cookies' => array(
					new WP_Http_Cookie(
						array(
							'name'  => 'test',
							'value' => 'foo',
						)
					),
				),
			)
		);

		$this->skipTestOnTimeout( $response );

		$cookies = wp_remote_retrieve_cookies( $response );

		$this->assertNotEmpty( $cookies );

		$cookie = wp_remote_retrieve_cookie( $response, 'test' );
		$this->assertInstanceOf( 'WP_Http_Cookie', $cookie );
		$this->assertSame( 'test', $cookie->name );
		$this->assertSame( 'foo', $cookie->value );
	}

	/**
	 * @ticket 37437
	 *
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_cookies
	 * @covers ::wp_remote_retrieve_cookie
	 */
	function test_get_response_cookies_with_name_value_array() {
		$url = 'http://example.org';

		$response = wp_remote_get(
			$url,
			array(
				'cookies' => array(
					'test' => 'foo',
				),
			)
		);

		$this->skipTestOnTimeout( $response );

		$cookies = wp_remote_retrieve_cookies( $response );

		$this->assertNotEmpty( $cookies );

		$cookie = wp_remote_retrieve_cookie( $response, 'test' );
		$this->assertInstanceOf( 'WP_Http_Cookie', $cookie );
		$this->assertSame( 'test', $cookie->name );
		$this->assertSame( 'foo', $cookie->value );
	}

	/**
	 * @ticket 43231
	 *
	 * @covers WP_HTTP_Requests_Response::__construct
	 * @covers ::wp_remote_retrieve_cookies
	 * @covers ::wp_remote_retrieve_cookie
	 * @covers WP_Http
	 */
	function test_get_cookie_host_only() {
		// Emulate WP_Http::request() internals.
		$requests_response = new Requests_Response();

		$requests_response->cookies['test'] = Requests_Cookie::parse( 'test=foo; domain=.wordpress.org' );

		$requests_response->cookies['test']->flags['host-only'] = false; // https://github.com/WordPress/Requests/issues/306

		$http_response = new WP_HTTP_Requests_Response( $requests_response );

		$response = $http_response->to_array();

		// Check the host_only flag in the resulting WP_Http_Cookie.
		$cookie = wp_remote_retrieve_cookie( $response, 'test' );
		$this->assertSame( $cookie->domain, 'wordpress.org' );
		$this->assertFalse( $cookie->host_only, 'host-only flag not set' );

		// Regurgitate (Requests_Cookie -> WP_Http_Cookie -> Requests_Cookie).
		$cookies = WP_Http::normalize_cookies( wp_remote_retrieve_cookies( $response ) );
		$this->assertFalse( $cookies['test']->flags['host-only'], 'host-only flag data lost' );
	}
}
