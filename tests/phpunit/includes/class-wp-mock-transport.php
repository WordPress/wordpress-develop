<?php

use WpOrg\Requests\Transport;

/**
 * Mock HTTP transport.
 */
class WP_Mock_Transport implements Transport {
	/**
	 * Perform a request
	 *
	 * @param string       $url     URL to request
	 * @param array        $headers Associative array of request headers
	 * @param string|array $data    Data to send either as the POST body, or as parameters in the URL for a GET/HEAD
	 * @param array        $options Request options, see {@see \WpOrg\Requests\Requests::response()} for documentation
	 * @return string Raw HTTP result
	 */
	public function request( $url, $headers = array(), $data = array(), $options = array() ) {
		$test_file = DIR_TESTDATA . '/http/' . $options['_mock_response'] . '.txt';
		$dirname   = dirname( $test_file );

		if ( ! is_dir( $dirname ) ) {
			mkdir( $dirname, 0755, true );
		}

		if ( ! file_exists( $test_file ) ) {
			$transport = new WpOrg\Requests\Transport\Curl();
			file_put_contents( $test_file, $transport->request( $url, $headers, $data, $options ) );
			throw new RuntimeException( 'Mock response file created. Please re-run the test.' );
		}

		return file_get_contents( $test_file );
	}

	/**
	 * Not implemented.
	 *
	 * @throws Exception
	 *
	 * @param array $requests Request data (array of 'url', 'headers', 'data', 'options') as per {@see \WpOrg\Requests\Transport::request()}
	 * @param array $options  Global options, see {@see \WpOrg\Requests\Requests::response()} for documentation
	 * @return array Not implemented.
	 */
	public function request_multiple( $requests, $options ) {
		throw new Exception( 'Not implemented.' );
	}

	/**
	 * Self-test whether the transport can be used.
	 *
	 * @param array<string, bool> $capabilities Optional. Associative array of capabilities to test against, i.e. `['<capability>' => true]`.
	 * @return bool Whether the transport can be used.
	 */
	public static function test( $capabilities = array() ) {
		return true;
	}
}
