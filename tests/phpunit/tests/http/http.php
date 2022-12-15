<?php
/**
 * Non-transport-specific WP_HTTP Tests
 *
 * @group http
 */
class Tests_HTTP_HTTP extends WP_UnitTestCase {

	/**
	 * @dataProvider make_absolute_url_testcases
	 */
	function test_make_absolute_url( $relative_url, $absolute_url, $expected ) {
		if ( ! is_callable( array( 'WP_HTTP', 'make_absolute_url' ) ) ) {
			$this->markTestSkipped( "This version of WP_HTTP doesn't support WP_HTTP::make_absolute_url()" );
			return;
		}

		$actual = WP_HTTP::make_absolute_url( $relative_url, $absolute_url );
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
	function test_parse_url( $url, $expected ) {
		if ( ! is_callable( array( 'WP_HTTP_Testable', 'parse_url' ) ) ) {
			$this->markTestSkipped( "This version of WP_HTTP doesn't support WP_HTTP::parse_url()" );
			return;
		}
		$actual = WP_HTTP_Testable::parse_url( $url );
		$this->assertEquals( $expected, $actual );
	}

	function parse_url_testcases() {
		// 0: The URL, 1: The expected resulting structure
		return array(
			array( 'http://example.com/', array( 'scheme' => 'http', 'host' => 'example.com', 'path' => '/' ) ),

			// < PHP 5.4.7: Schemeless URL
			array( '//example.com/path/', array( 'host' => 'example.com', 'path' => '/path/' ) ),
			array( '//example.com/', array( 'host' => 'example.com', 'path' => '/' ) ),
			array( 'http://example.com//path/', array( 'scheme' => 'http', 'host' => 'example.com', 'path' => '//path/' ) ),

			// < PHP 5.4.7: Scheme seperator in the URL
			array( 'http://example.com/http://example.net/', array( 'scheme' => 'http', 'host' => 'example.com', 'path' => '/http://example.net/' ) ),
			array( '/path/http://example.net/', array( 'path' => '/path/http://example.net/' ) ),
			// PHP's parse_url() calls this an invalid url, we handle it as a path
			array( '/://example.com/', array( 'path' => '/://example.com/' ) ),

		);
		/*
		Untestable edge cases in various PHP:
		  - ///example.com - Fails in PHP >= 5.4.7, assumed path in <5.4.7
		  - ://example.com - assumed path in PHP >= 5.4.7, fails in <5.4.7
		*/
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
		$pre_http_request_filter_has_run = false;
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

/**
 * A Wrapper of WP_HTTP to make parse_url() publicaly accessible for testing purposes.
 */
class WP_HTTP_Testable extends WP_HTTP {
	public static function parse_url( $url ) {
		return parent::parse_url( $url );
	}
}