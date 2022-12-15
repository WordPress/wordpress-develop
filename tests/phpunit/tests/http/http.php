<?php
/**
 * Non-transport-specific WP_HTTP Tests
 *
 * @group http
 */
class Tests_HTTP_HTTP extends WP_UnitTestCase {

	const FULL_TEST_URL = 'http://username:password@host.name:9090/path?arg1=value1&arg2=value2#anchor';

	/**
	 * @dataProvider make_absolute_url_testcases
	 */
	function test_make_absolute_url( $relative_url, $absolute_url, $expected ) {
		$actual = WP_Http::make_absolute_url( $relative_url, $absolute_url );
		$this->assertEquals( $expected, $actual );
	}

	function make_absolute_url_testcases() {
		// 0: The Location header, 1: The current url, 3: The expected url
		return array(
			array( 'http://site.com/', 'http://example.com/', 'http://site.com/' ), // Absolute URL provided
			array( '/location', '', '/location' ), // No current url provided

			array( '', 'http://example.com', 'http://example.com/' ), // No location provided

			// Location provided relative to site root
			array( '/root-relative-link.ext', 'http://example.com/', 'http://example.com/root-relative-link.ext' ),
			array( '/root-relative-link.ext?with=query', 'http://example.com/index.ext?query', 'http://example.com/root-relative-link.ext?with=query' ),

			// Location provided relative to current file/directory
			array( 'relative-file.ext', 'http://example.com/', 'http://example.com/relative-file.ext' ),
			array( 'relative-file.ext', 'http://example.com/filename', 'http://example.com/relative-file.ext' ),
			array( 'relative-file.ext', 'http://example.com/directory/', 'http://example.com/directory/relative-file.ext' ),

			// Location provided relative to current file/directory but in a parent directory
			array( '../file-in-parent.ext', 'http://example.com', 'http://example.com/file-in-parent.ext' ),
			array( '../file-in-parent.ext', 'http://example.com/filename', 'http://example.com/file-in-parent.ext' ),
			array( '../file-in-parent.ext', 'http://example.com/directory/', 'http://example.com/file-in-parent.ext' ),
			array( '../file-in-parent.ext', 'http://example.com/directory/filename', 'http://example.com/file-in-parent.ext' ),

			// Location provided in muliple levels higher, including impossible to reach (../ below DOCROOT)
			array( '../../file-in-grand-parent.ext', 'http://example.com', 'http://example.com/file-in-grand-parent.ext' ),
			array( '../../file-in-grand-parent.ext', 'http://example.com/filename', 'http://example.com/file-in-grand-parent.ext' ),
			array( '../../file-in-grand-parent.ext', 'http://example.com/directory/', 'http://example.com/file-in-grand-parent.ext' ),
			array( '../../file-in-grand-parent.ext', 'http://example.com/directory/filename/', 'http://example.com/file-in-grand-parent.ext' ),
			array( '../../file-in-grand-parent.ext', 'http://example.com/directory1/directory2/filename', 'http://example.com/file-in-grand-parent.ext' ),

			// Query strings should attach, or replace existing query string.
			array( '?query=string', 'http://example.com', 'http://example.com/?query=string' ),
			array( '?query=string', 'http://example.com/file.ext', 'http://example.com/file.ext?query=string' ),
			array( '?query=string', 'http://example.com/file.ext?existing=query-string', 'http://example.com/file.ext?query=string' ),
			array( 'otherfile.ext?query=string', 'http://example.com/file.ext?existing=query-string', 'http://example.com/otherfile.ext?query=string' ),

			// A file with a leading dot
			array( '.ext', 'http://example.com/', 'http://example.com/.ext' ),

			// URls within URLs
			array( '/expected', 'http://example.com/sub/http://site.com/sub/', 'http://example.com/expected' ),
			array( '/expected/http://site.com/sub/', 'http://example.com/', 'http://example.com/expected/http://site.com/sub/' ),

			// Schemeless URL's (Not valid in HTTP Headers, but may be used elsewhere)
			array( '//example.com/sub/', 'https://example.net', 'https://example.com/sub/' ),
		);
	}

	/**
	 * @dataProvider parse_url_testcases
	 */
	function test_wp_parse_url( $url, $expected ) {
		$actual = wp_parse_url( $url );
		$this->assertEquals( $expected, $actual );
	}

	function parse_url_testcases() {
		// 0: The URL, 1: The expected resulting structure
		return array(
			array(
				self::FULL_TEST_URL,
				array(
					'scheme'   => 'http',
					'host'     => 'host.name',
					'port'     => 9090,
					'user'     => 'username',
					'pass'     => 'password',
					'path'     => '/path',
					'query'    => 'arg1=value1&arg2=value2',
					'fragment' => 'anchor',
				),
			),
			array(
				'http://example.com/',
				array(
					'scheme' => 'http',
					'host'   => 'example.com',
					'path'   => '/',
				),
			),

			// < PHP 5.4.7: Schemeless URL
			array(
				'//example.com/path/',
				array(
					'host' => 'example.com',
					'path' => '/path/',
				),
			),
			array(
				'//example.com/',
				array(
					'host' => 'example.com',
					'path' => '/',
				),
			),
			array(
				'http://example.com//path/',
				array(
					'scheme' => 'http',
					'host'   => 'example.com',
					'path'   => '//path/',
				),
			),

			// < PHP 5.4.7: Scheme separator in the URL.
			array(
				'http://example.com/http://example.net/',
				array(
					'scheme' => 'http',
					'host'   => 'example.com',
					'path'   => '/http://example.net/',
				),
			),
			array( '/path/http://example.net/', array( 'path' => '/path/http://example.net/' ) ),

			// < PHP 5.4.7: IPv6 literals in schemeless URLs are handled incorrectly.
			array(
				'//[::FFFF::127.0.0.1]/',
				array(
					'host' => '[::FFFF::127.0.0.1]',
					'path' => '/',
				),
			),

			// PHP's parse_url() calls this an invalid url, we handle it as a path
			array( '/://example.com/', array( 'path' => '/://example.com/' ) ),

			// Schemeless URL containing colons cause parse errors in PHP 7+.
			array(
				'//fonts.googleapis.com/css?family=Open+Sans:400&subset=latin',
				array(
					'host'  => 'fonts.googleapis.com',
					'path'  => '/css',
					'query' => 'family=Open+Sans:400&subset=latin',
				),
			),
			array(
				'//fonts.googleapis.com/css?family=Open+Sans:400',
				array(
					'host'  => 'fonts.googleapis.com',
					'path'  => '/css',
					'query' => 'family=Open+Sans:400',
				),
			),

			array( 'filenamefound', array( 'path' => 'filenamefound' ) ),

			// Empty string or non-string passed in.
			array( '', array( 'path' => '' ) ),
			array( 123, array( 'path' => '123' ) ),
		);
		/*
		 * Untestable edge cases in various PHP:
		 * - ///example.com - Fails in PHP >= 5.4.7, assumed path in <5.4.7
		 * - ://example.com - assumed path in PHP >= 5.4.7, fails in <5.4.7
		 */
	}

	/**
	 * @ticket 36356
	 */
	function test_wp_parse_url_with_default_component() {
		$actual = wp_parse_url( self::FULL_TEST_URL, -1 );
		$this->assertEquals(
			array(
				'scheme'   => 'http',
				'host'     => 'host.name',
				'port'     => 9090,
				'user'     => 'username',
				'pass'     => 'password',
				'path'     => '/path',
				'query'    => 'arg1=value1&arg2=value2',
				'fragment' => 'anchor',
			),
			$actual
		);
	}

	/**
	 * @ticket 36356
	 *
	 * @dataProvider parse_url_component_testcases
	 */
	function test_wp_parse_url_with_component( $url, $component, $expected ) {
		$actual = wp_parse_url( $url, $component );
		$this->assertSame( $expected, $actual );
	}

	function parse_url_component_testcases() {
		// 0: The URL, 1: The requested component, 2: The expected resulting structure.
		return array(
			array( self::FULL_TEST_URL, PHP_URL_SCHEME, 'http' ),
			array( self::FULL_TEST_URL, PHP_URL_USER, 'username' ),
			array( self::FULL_TEST_URL, PHP_URL_PASS, 'password' ),
			array( self::FULL_TEST_URL, PHP_URL_HOST, 'host.name' ),
			array( self::FULL_TEST_URL, PHP_URL_PORT, 9090 ),
			array( self::FULL_TEST_URL, PHP_URL_PATH, '/path' ),
			array( self::FULL_TEST_URL, PHP_URL_QUERY, 'arg1=value1&arg2=value2' ),
			array( self::FULL_TEST_URL, PHP_URL_FRAGMENT, 'anchor' ),

			// < PHP 5.4.7: Schemeless URL.
			array( '//example.com/path/', PHP_URL_HOST, 'example.com' ),
			array( '//example.com/path/', PHP_URL_PATH, '/path/' ),
			array( '//example.com/', PHP_URL_HOST, 'example.com' ),
			array( '//example.com/', PHP_URL_PATH, '/' ),
			array( 'http://example.com//path/', PHP_URL_HOST, 'example.com' ),
			array( 'http://example.com//path/', PHP_URL_PATH, '//path/' ),

			// < PHP 5.4.7: Scheme separator in the URL.
			array( 'http://example.com/http://example.net/', PHP_URL_HOST, 'example.com' ),
			array( 'http://example.com/http://example.net/', PHP_URL_PATH, '/http://example.net/' ),
			array( '/path/http://example.net/', PHP_URL_HOST, null ),
			array( '/path/http://example.net/', PHP_URL_PATH, '/path/http://example.net/' ),

			// < PHP 5.4.7: IPv6 literals in schemeless URLs are handled incorrectly.
			array( '//[::FFFF::127.0.0.1]/', PHP_URL_HOST, '[::FFFF::127.0.0.1]' ),
			array( '//[::FFFF::127.0.0.1]/', PHP_URL_PATH, '/' ),

			// PHP's parse_url() calls this an invalid URL, we handle it as a path.
			array( '/://example.com/', PHP_URL_PATH, '/://example.com/' ),

			// Schemeless URL containing colons cause parse errors in PHP 7+.
			array( '//fonts.googleapis.com/css?family=Open+Sans:400&subset=latin', PHP_URL_HOST, 'fonts.googleapis.com' ),
			array( '//fonts.googleapis.com/css?family=Open+Sans:400&subset=latin', PHP_URL_PORT, null ),
			array( '//fonts.googleapis.com/css?family=Open+Sans:400&subset=latin', PHP_URL_PATH, '/css' ),
			array( '//fonts.googleapis.com/css?family=Open+Sans:400&subset=latin', PHP_URL_QUERY, 'family=Open+Sans:400&subset=latin' ),
			array( '//fonts.googleapis.com/css?family=Open+Sans:400', PHP_URL_HOST, 'fonts.googleapis.com' ), // 25
			array( '//fonts.googleapis.com/css?family=Open+Sans:400', PHP_URL_PORT, null ),
			array( '//fonts.googleapis.com/css?family=Open+Sans:400', PHP_URL_PATH, '/css' ), //27
			array( '//fonts.googleapis.com/css?family=Open+Sans:400', PHP_URL_QUERY, 'family=Open+Sans:400' ), //28

			// Empty string or non-string passed in.
			array( '', PHP_URL_PATH, '' ),
			array( '', PHP_URL_QUERY, null ),
			array( 123, PHP_URL_PORT, null ),
			array( 123, PHP_URL_PATH, '123' ),
		);
	}

	/**
	 * @ticket 35426
	 */
	public function test_http_response_code_constants() {
		global $wp_header_to_desc;

		$ref       = new ReflectionClass( 'WP_Http' );
		$constants = $ref->getConstants();

		// This primes the `$wp_header_to_desc` global:
		get_status_header_desc( 200 );

		$this->assertEquals( array_keys( $wp_header_to_desc ), array_values( $constants ) );

	}

	/**
	 * @ticket 37768
	 */
	public function test_normalize_cookies_scalar_values() {
		$http = _wp_http_get_object();

		$cookies = array(
			'x'   => 'foo',
			'y'   => 2,
			'z'   => 0.45,
			'foo' => array( 'bar' ),
		);

		$cookie_jar = $http->normalize_cookies(
			array(
				'x'   => 'foo',
				'y'   => 2,
				'z'   => 0.45,
				'foo' => array( 'bar' ),
			)
		);

		$this->assertInstanceOf( 'Requests_Cookie_Jar', $cookie_jar );

		foreach ( array_keys( $cookies ) as $cookie ) {
			if ( 'foo' === $cookie ) {
				$this->assertFalse( isset( $cookie_jar[ $cookie ] ) );
			} else {
				$this->assertInstanceOf( 'Requests_Cookie', $cookie_jar[ $cookie ] );
			}
		}
	}

	/**
	 * @ticket 36356
	 *
	 * @dataProvider get_component_from_parsed_url_array_testcases
	 */
	function test_get_component_from_parsed_url_array( $url, $component, $expected ) {
		$parts  = wp_parse_url( $url );
		$actual = _get_component_from_parsed_url_array( $parts, $component );
		$this->assertSame( $expected, $actual );
	}

	function get_component_from_parsed_url_array_testcases() {
		// 0: A URL, 1: PHP URL constant, 2: The expected result.
		return array(
			array(
				'http://example.com/',
				-1,
				array(
					'scheme' => 'http',
					'host'   => 'example.com',
					'path'   => '/',
				),
			),
			array(
				'http://example.com/',
				-1,
				array(
					'scheme' => 'http',
					'host'   => 'example.com',
					'path'   => '/',
				),
			),
			array( 'http://example.com/', PHP_URL_HOST, 'example.com' ),
			array( 'http://example.com/', PHP_URL_USER, null ),
			array( 'http:///example.com', -1, false ), // Malformed.
			array( 'http:///example.com', PHP_URL_HOST, null ), // Malformed.
		);
	}

	/**
	 * @ticket 36356
	 *
	 * @dataProvider wp_translate_php_url_constant_to_key_testcases
	 */
	function test_wp_translate_php_url_constant_to_key( $input, $expected ) {
		$actual = _wp_translate_php_url_constant_to_key( $input );
		$this->assertSame( $expected, $actual );
	}

	function wp_translate_php_url_constant_to_key_testcases() {
		// 0: PHP URL constant, 1: The expected result.
		return array(
			array( PHP_URL_SCHEME, 'scheme' ),
			array( PHP_URL_HOST, 'host' ),
			array( PHP_URL_PORT, 'port' ),
			array( PHP_URL_USER, 'user' ),
			array( PHP_URL_PASS, 'pass' ),
			array( PHP_URL_PATH, 'path' ),
			array( PHP_URL_QUERY, 'query' ),
			array( PHP_URL_FRAGMENT, 'fragment' ),

			// Test with non-PHP_URL_CONSTANT parameter.
			array( 'something', false ),
			array( ABSPATH, false ),
		);
	}

	/**
	 * Test HTTP Redirects with multiple Location headers specified.
	 *
	 * Ensure the WP_HTTP::handle_redirects() method handles multiple Location headers
	 * and the HTTP request it makes uses the last Location header.
	 *
	 * @ticket 16890
	 * @ticket 57306
	 *
	 * @covers WP_HTTP::handle_redirects
	 */
	public function test_multiple_location_headers() {
		// Filter the response made by WP_HTTP::handle_redirects().
		add_filter(
			'pre_http_request',
			array( $this, 'filter_for_multiple_location_headers' ),
			10,
			3
		);

		$headers = array(
			'server'       => 'nginx',
			'date'         => 'Sun, 11 Dec 2022 23:11:22 GMT',
			'content-type' => 'text/html; charset=utf-8',
			'location'     => array(
				'http://example.com/?multiple-location-headers=1&redirected=one',
				'http://example.com/?multiple-location-headers=1&redirected=two',
			),
		);

		// Test the tests: ensure multiple locations are passed to WP_HTTP::handle_redirects().
		$this->assertTrue( is_array( $headers['location'] ), 'Location header is expected to be an array.' );
		$this->assertCount( 2, $headers['location'], 'Location header is expected to contain two values.' );

		$args = array(
			'timeout'      => 30,
			'_redirection' => 3,
			'redirection'  => 2,
			'method'       => 'GET',
		);

		$redirect_response = _wp_http_get_object()->handle_redirects(
			'http://example.com/?multiple-location-headers=1',
			$args,
			array(
				'headers'  => $headers,
				'body'     => '',
				'cookies'  => array(),
				'filename' => null,
				'response' => array(
					'code'    => 302,
					'message' => 'Found',
				),
			)
		);
		$this->assertSame( 'PASS', wp_remote_retrieve_body( $redirect_response ), 'Redirect response body is expected to be PASS.' );
	}

	public function filter_for_multiple_location_headers( $response, $args, $url ) {
		if ( 'http://example.com/?multiple-location-headers=1&redirected=two' === $url ) {
			$body = 'PASS';
		} else {
			$body = 'FAIL';
		}

		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
