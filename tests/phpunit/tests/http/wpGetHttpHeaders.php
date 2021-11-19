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

		// Hook a fake HTTP request response.
		add_filter( 'pre_http_request', array( $this, 'fake_http_request' ), 10, 3 );
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
	 * @param bool   $false     False.
	 * @param array  $arguments Request arguments.
	 * @param string $url       Request URL.
	 *
	 * @return array|bool
	 */
	public function fake_http_request( $false, $arguments, $url ) {
		if ( 'http://example.com' === $url ) {
			return array( 'headers' => true );
		}

		return false;
	}
}
