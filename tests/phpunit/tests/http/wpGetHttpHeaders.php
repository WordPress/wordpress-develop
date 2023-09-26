<?php

/**
 * @group http
 * @covers ::wp_get_http_headers
 */
class Tests_HTTP_wpGetHttpHeaders extends WP_UnitTestCase {

	/**
	 * Set up the environment
	 */
	public function set_up() {
		parent::set_up();

		// Hook a mocked HTTP request response.
		add_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10, 3 );
	}

	/**
	 * Test with a valid URL
	 */
	public function test_wp_get_http_headers_valid_url() {
		$result = wp_get_http_headers( 'http://example.com' );
		$this->assertTrue( $result );
	}

	/**
	 * Test with an invalid URL
	 */
	public function test_wp_get_http_headers_invalid_url() {
		$result = wp_get_http_headers( 'not_an_url' );
		$this->assertFalse( $result );
	}

	/**
	 * Test to see if the deprecated argument is working
	 */
	public function test_wp_get_http_headers_deprecated_argument() {
		$this->setExpectedDeprecated( 'wp_get_http_headers' );

		wp_get_http_headers( 'does_not_matter', $deprecated = true );
	}

	/**
	 * Mock the HTTP request response
	 *
	 * @param false|array|WP_Error $response    A preemptive return value of an HTTP request. Default false.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url         The request URL.
	 * @return false|array|WP_Error Response data.
	 */
	public function mock_http_request( $response, $parsed_args, $url ) {
		if ( 'http://example.com' === $url ) {
			return array( 'headers' => true );
		}

		return $response;
	}
}
