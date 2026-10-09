<?php
/**
 * Fake Requests transport for HTTP unit tests.
 *
 * Returns canned raw HTTP responses so tests can exercise WP_Http request
 * handling (cookie normalization, redirect policy, response conversion)
 * without performing live network requests.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 7.2.0
 */

/**
 * Test-only Requests transport.
 */
class WP_Http_Unit_Test_Transport implements WpOrg\Requests\Transport {

	/**
	 * Performs a request against the canned responses.
	 *
	 * @param string       $url     URL to request.
	 * @param array        $headers Associative array of request headers.
	 * @param string|array $data    Request data.
	 * @param array        $options Request options.
	 * @return string Raw HTTP response.
	 *
	 * @throws WpOrg\Requests\Exception When no canned response exists for the URL.
	 */
	public function request( $url, $headers = array(), $data = array(), $options = array() ) {
		$response = $this->get_raw_response( $url );

		if ( null === $response ) {
			throw new WpOrg\Requests\Exception(
				sprintf( 'Unexpected HTTP request for URL: %s', $url ),
				'testtransport.unhandled'
			);
		}

		return $response;
	}

	/**
	 * Performs multiple requests sequentially.
	 *
	 * @param array $requests Request data.
	 * @param array $options  Global options.
	 * @return array Array of responses.
	 */
	public function request_multiple( $requests, $options ) {
		$responses = array();

		foreach ( $requests as $key => $request ) {
			$responses[ $key ] = $this->request(
				$request['url'],
				$request['headers'],
				$request['data'],
				$request['options']
			);
		}

		return $responses;
	}

	/**
	 * Self-test whether the transport can be used.
	 *
	 * @param array<string, bool> $capabilities Optional. Capabilities to test against.
	 * @return bool Always true for the test transport.
	 */
	public static function test( $capabilities = array() ) {
		return true;
	}

	/**
	 * Returns a canned raw HTTP response for a URL.
	 *
	 * @param string $url Request URL.
	 * @return string|null Raw HTTP response, or null when unhandled.
	 */
	private function get_raw_response( $url ) {
		$png_headers = "Content-Type: image/png\r\nContent-Length: 153204\r\n";

		switch ( $url ) {
			case 'https://s.w.org/screenshots/3.9/dashboard.png':
			case 'https://wordpress.org/screenshots/3.9/dashboard.png':
				return "HTTP/1.1 200 OK\r\n{$png_headers}\r\n";

			case 'https://wp.org/screenshots/3.9/dashboard.png':
				return "HTTP/1.1 301 Moved Permanently\r\nLocation: https://wordpress.org/screenshots/3.9/dashboard.png\r\n\r\n";

			case 'https://wordpress.org/screenshots/3.9/awefasdfawef.jpg':
				return "HTTP/1.1 404 Not Found\r\n\r\n";

			case 'https://login.wordpress.org/wp-login.php':
				return "HTTP/1.1 200 OK\r\nSet-Cookie: wordpress_test_cookie=WP Cookie check\r\n\r\n";
		}

		return null;
	}
}
