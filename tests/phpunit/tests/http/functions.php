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

		$this->assertInternalType( 'array', $headers, "Reply wasn't array." );
		$this->assertSame( 'image/png', $headers['content-type'] );
		$this->assertSame( '153204', $headers['content-length'] );
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
		$this->assertInternalType( 'array', $response, "Reply wasn't array." );
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

		// Should return the same headers as a HEAD request.
		$this->assertInternalType( 'array', $headers, "Reply wasn't array." );
		$this->assertSame( 'image/png', $headers['content-type'] );
		$this->assertSame( '153204', $headers['content-length'] );
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
		$this->assertInternalType( 'array', $headers, "Reply wasn't array." );
		$this->assertSame( 'image/png', $headers['content-type'] );
		$this->assertSame( '153204', $headers['content-length'] );
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
		$this->assertTrue( is_wp_error( $response ) );
	}
}
