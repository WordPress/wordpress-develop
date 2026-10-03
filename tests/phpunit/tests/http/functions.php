<?php

/**
 * @group http
 *
 * @ticket 63914
 */
class Tests_HTTP_Functions extends WP_UnitTestCase {

	/**
	 * Set up the mocked HTTP responses.
	 */
	public function set_up() {
		parent::set_up();

		add_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10, 3 );
	}

	/**
	 * Mock external HTTP responses for these tests.
	 *
	 * @param false|array|WP_Error $response    A preemptive return value of an HTTP request. Default false.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url         The request URL.
	 * @return array|WP_Error Response data.
	 */
	public function mock_http_request( $response, $parsed_args, $url ) {
		// Simulate too many redirects without performing a live request.
		if ( isset( $parsed_args['redirection'] ) && $parsed_args['redirection'] < 0 ) {
			return new WP_Error( 'http_request_failed', 'Too many redirects.' );
		}

		$png_headers = array(
			'Content-Type'   => 'image/png',
			'Content-Length' => '153204',
		);

		switch ( $url ) {
			case 'https://s.w.org/screenshots/3.9/dashboard.png':
				return $this->build_mock_http_response( 200, 'OK', $png_headers );

			case 'https://wp.org/screenshots/3.9/dashboard.png':
				if ( isset( $parsed_args['method'] ) && 'HEAD' === $parsed_args['method'] ) {
					return $this->build_mock_http_response(
						301,
						'Moved Permanently',
						array(
							'location' => 'https://wordpress.org/screenshots/3.9/dashboard.png',
						)
					);
				}

				// GET follows the redirect and returns the final image response.
				return $this->build_mock_http_response( 200, 'OK', $png_headers );

			case 'https://wordpress.org/screenshots/3.9/awefasdfawef.jpg':
				return $this->build_mock_http_response( 404, 'Not Found' );

			case 'https://login.wordpress.org/wp-login.php':
				$cookies = array(
					new WP_Http_Cookie(
						array(
							'name'  => 'wordpress_test_cookie',
							'value' => 'WP Cookie check',
						)
					),
				);

				if ( ! empty( $parsed_args['cookies'] ) ) {
					foreach ( $parsed_args['cookies'] as $name => $value ) {
						if ( $value instanceof WP_Http_Cookie ) {
							$cookies[] = $value;
							continue;
						}

						$cookies[] = new WP_Http_Cookie(
							array(
								'name'  => $name,
								'value' => $value,
							)
						);
					}
				}

				return $this->build_mock_http_response( 200, 'OK', array(), $cookies );
		}

		return new WP_Error(
			'unexpected_http_request',
			sprintf( 'Unexpected HTTP request for URL: %s', $url )
		);
	}

	/**
	 * Builds a mocked HTTP API response array.
	 *
	 * @param int              $code     HTTP response code.
	 * @param string           $message  HTTP response message.
	 * @param array            $headers  Optional. Response headers. Default empty array.
	 * @param WP_Http_Cookie[] $cookies  Optional. Response cookies. Default empty array.
	 * @return array Response data in the shape returned by the HTTP API.
	 */
	private function build_mock_http_response( $code, $message, $headers = array(), $cookies = array() ) {
		return array(
			'headers'  => $headers,
			'body'     => '',
			'response' => array(
				'code'    => $code,
				'message' => $message,
			),
			'cookies'  => $cookies,
			'filename' => null,
		);
	}

	/**
	 * @covers ::wp_remote_head
	 */
	public function test_head_request() {
		// This URL gives a direct 200 response.
		$url      = 'https://s.w.org/screenshots/3.9/dashboard.png';
		$response = wp_remote_head( $url );

		$this->assertNotWPError( $response );

		$headers = wp_remote_retrieve_headers( $response );

		$this->assertIsArray( $response );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
		$this->assertSame( 'image/png', $headers['Content-Type'] );
		$this->assertSame( '153204', $headers['Content-Length'] );
	}

	/**
	 * @covers ::wp_remote_head
	 */
	public function test_head_redirect() {
		// This URL will 301 redirect.
		$url      = 'https://wp.org/screenshots/3.9/dashboard.png';
		$response = wp_remote_head( $url );

		$this->assertNotWPError( $response );
		$this->assertSame( 301, wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * @covers ::wp_remote_head
	 */
	public function test_head_404() {
		$url      = 'https://wordpress.org/screenshots/3.9/awefasdfawef.jpg';
		$response = wp_remote_head( $url );

		$this->assertNotWPError( $response );
		$this->assertSame( 404, wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_headers
	 * @covers ::wp_remote_retrieve_response_code
	 */
	public function test_get_request() {
		$url = 'https://s.w.org/screenshots/3.9/dashboard.png';

		$response = wp_remote_get( $url );

		$this->assertNotWPError( $response );

		$headers = wp_remote_retrieve_headers( $response );

		// Should return the same headers as a HEAD request.
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
		$this->assertSame( 'image/png', $headers['Content-Type'] );
		$this->assertSame( '153204', $headers['Content-Length'] );
	}

	/**
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_headers
	 * @covers ::wp_remote_retrieve_response_code
	 */
	public function test_get_redirect() {
		// This will redirect to wordpress.org.
		$url = 'https://wp.org/screenshots/3.9/dashboard.png';

		$response = wp_remote_get( $url );

		$this->assertNotWPError( $response );

		$headers = wp_remote_retrieve_headers( $response );

		// Should return the same headers as a HEAD request.
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
		$this->assertSame( 'image/png', $headers['Content-Type'] );
		$this->assertSame( '153204', $headers['Content-Length'] );
	}

	/**
	 * @covers ::wp_remote_get
	 */
	public function test_get_redirect_limit_exceeded() {
		// This will redirect to wordpress.org.
		$url = 'https://wp.org/screenshots/3.9/dashboard.png';

		// Pretend we've already redirected 5 times.
		$response = wp_remote_get( $url, array( 'redirection' => -1 ) );

		$this->assertWPError( $response );
		$this->assertSame( 'http_request_failed', $response->get_error_code() );
	}

	/**
	 * @ticket 33711
	 *
	 * @covers ::wp_remote_head
	 * @covers ::wp_remote_retrieve_cookies
	 * @covers ::wp_remote_retrieve_cookie
	 * @covers ::wp_remote_retrieve_cookie_value
	 */
	public function test_get_response_cookies() {
		$url = 'https://login.wordpress.org/wp-login.php';

		$response = wp_remote_head( $url );

		$this->assertNotWPError( $response );

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
	public function test_get_response_cookies_with_wp_http_cookie_object() {
		$url = 'https://login.wordpress.org/wp-login.php';

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

		$this->assertNotWPError( $response );

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
	public function test_get_response_cookies_with_name_value_array() {
		$url = 'https://login.wordpress.org/wp-login.php';

		$response = wp_remote_get(
			$url,
			array(
				'cookies' => array(
					'test' => 'foo',
				),
			)
		);

		$this->assertNotWPError( $response );

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
	 * @covers WP_Http_Cookie::__construct
	 * @covers WP_Http::normalize_cookies
	 * @covers ::wp_remote_retrieve_cookies
	 * @covers ::wp_remote_retrieve_cookie
	 */
	public function test_get_cookie_host_only() {
		// Emulate WP_Http::request() internals.
		$requests_response = new WpOrg\Requests\Response();

		$requests_response->cookies['test'] = WpOrg\Requests\Cookie::parse( 'test=foo; domain=.wordpress.org' );

		$requests_response->cookies['test']->flags['host-only'] = false; // https://github.com/WordPress/Requests/issues/306

		$http_response = new WP_HTTP_Requests_Response( $requests_response );

		$response = $http_response->to_array();

		// Check the host_only flag in the resulting WP_Http_Cookie.
		$cookie = wp_remote_retrieve_cookie( $response, 'test' );
		$this->assertSame( $cookie->domain, 'wordpress.org' );
		$this->assertFalse( $cookie->host_only, 'host-only flag not set' );

		// Regurgitate (WpOrg\Requests\Cookie -> WP_Http_Cookie -> WpOrg\Requests\Cookie).
		$cookies = WP_Http::normalize_cookies( wp_remote_retrieve_cookies( $response ) );
		$this->assertFalse( $cookies['test']->flags['host-only'], 'host-only flag data lost' );
	}
}
