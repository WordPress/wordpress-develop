<?php

/**
 * @group http
 * @group external-http
 */
class Tests_HTTP_Functions extends WP_UnitTestCase {

	public function setUp() {
		if ( ! extension_loaded( 'openssl' ) ) {
			$this->markTestSkipped( 'Tests_HTTP_Functions requires openssl.' );
		}

		parent::setUp();
	}

	/**
	 * @covers ::wp_remote_head
	 */
	public function test_head_request() {
		// This URL gives a direct 200 response.
		$url      = 'https://s.w.org/screenshots/3.9/dashboard.png';
		$response = wp_remote_head( $url );

		$this->skipTestOnTimeout( $response );

		$headers = wp_remote_retrieve_headers( $response );

		$this->assertInternalType( 'array', $response );
		
		$this->assertSame( 'image/png', $headers['Content-Type'] );
		$this->assertSame( '153204', $headers['Content-Length'] );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * @covers ::wp_remote_head
	 */
	public function test_head_redirect() {
		// This URL will 301 redirect.
		$url      = 'https://wp.org/screenshots/3.9/dashboard.png';
		$response = wp_remote_head( $url );

		$this->skipTestOnTimeout( $response );
		$this->assertEquals( '301', wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * @covers ::wp_remote_head
	 */
	public function test_head_404() {
		$url      = 'https://wordpress.org/screenshots/3.9/awefasdfawef.jpg';
		$response = wp_remote_head( $url );

		$this->skipTestOnTimeout( $response );
		$this->assertEquals( '404', wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_headers
	 * @covers ::wp_remote_retrieve_response_code
	 */
	public function test_get_request() {
		$url = 'https://s.w.org/screenshots/3.9/dashboard.png';

		$response = wp_remote_get( $url );

		$this->skipTestOnTimeout( $response );

		$headers = wp_remote_retrieve_headers( $response );

		$this->assertInternalType( 'array', $response );
	
		// Should return the same headers as a HEAD request.
		$this->assertSame( 'image/png', $headers['Content-Type'] );
		$this->assertSame( '153204', $headers['Content-Length'] );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
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

		$this->skipTestOnTimeout( $response );

		$headers = wp_remote_retrieve_headers( $response );

		// Should return the same headers as a HEAD request.
		$this->assertSame( 'image/png', $headers['Content-Type'] );
		$this->assertSame( '153204', $headers['Content-Length'] );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * @covers ::wp_remote_get
	 */
	public function test_get_redirect_limit_exceeded() {
		// This will redirect to wordpress.org.
		$url = 'https://wp.org/screenshots/3.9/dashboard.png';

		// pretend we've already redirected 5 times
		$response = wp_remote_get( $url, array( 'redirection' => -1 ) );

		$this->skipTestOnTimeout( $response );
		$this->assertWPError( $response );
	}

	/**
	 * @ticket 33711
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
	 */
	function test_get_response_cookies_with_wp_http_cookie_object() {
		$url = 'http://example.org';

		$response = wp_remote_get( $url, array(
			'cookies' => array(
				new WP_Http_Cookie( array( 'name' => 'test', 'value' => 'foo' ) ),
			),
		) );

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
	 */
	function test_get_response_cookies_with_name_value_array() {
		$url = 'http://example.org';

		$response = wp_remote_get( $url, array(
			'cookies' => array(
				'test' => 'foo',
			),
		) );

		$this->skipTestOnTimeout( $response );

		$cookies = wp_remote_retrieve_cookies( $response );

		$this->assertNotEmpty( $cookies );

		$cookie = wp_remote_retrieve_cookie( $response, 'test' );
		$this->assertInstanceOf( 'WP_Http_Cookie', $cookie );
		$this->assertSame( 'test', $cookie->name );
		$this->assertSame( 'foo', $cookie->value );
	}
}
