<?php

/**
 * Tests for the WP_Http::validate_redirects() method.
 *
 * @group http
 *
 * @covers WP_Http::validate_redirects
 */
class Tests_HTTP_WpHttpValidateRedirects extends WP_UnitTestCase {

	/**
	 * Tests that a valid URL is accepted.
	 *
	 * @ticket 66507
	 *
	 * @dataProvider data_valid_urls
	 *
	 * @param string $url Valid URL.
	 */
	public function test_should_accept_a_valid_url( $url ) {
		$this->assertNull( WP_Http::validate_redirects( $url ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_valid_urls() {
		return array(
			'https'            => array( 'https://example.org/' ),
			'http'             => array( 'http://example.org/' ),
			'with a path'      => array( 'https://example.org/path/page?query=1' ),
			'with a port'      => array( 'https://example.org:8080/' ),
			'with a subdomain' => array( 'https://www.example.org/' ),
		);
	}

	/**
	 * Tests that a URL that is not valid is rejected with an exception.
	 *
	 * @ticket 66507
	 *
	 * @dataProvider data_invalid_urls
	 *
	 * @param string $url URL that is not valid.
	 */
	public function test_should_throw_an_exception_for_a_url_that_is_not_valid( $url ) {
		try {
			WP_Http::validate_redirects( $url );
			$this->fail( 'An exception should be thrown.' );
		} catch ( WpOrg\Requests\Exception $e ) {
			$this->assertSame( 'wp_http.redirect_failed_validation', $e->getType(), 'The type of the exception should identify the validation.' );
			$this->assertSame( 'A valid URL was not provided.', $e->getMessage(), 'The message should be the one of the validation.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_urls() {
		return array(
			'empty string'              => array( '' ),
			'not a url'                 => array( 'not a url' ),
			'without a host'            => array( 'http://' ),
			'a scheme that is not http' => array( 'ftp://example.org/' ),
			'with credentials'          => array( 'https://user:password@example.org/' ),
			'a relative path'           => array( '/path/page' ),
			'a host with a space'       => array( 'https://exam ple.org/' ),
		);
	}
}
