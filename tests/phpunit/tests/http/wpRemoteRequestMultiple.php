<?php
/**
 * Tests for wp_remote_request_multiple(), wp_safe_remote_request_multiple() and WP_Http::request_multiple().
 *
 * @group http
 *
 * @covers ::wp_remote_request_multiple
 * @covers ::wp_safe_remote_request_multiple
 * @covers WP_Http::request_multiple
 */
class Tests_HTTP_wpRemoteRequestMultiple extends WP_UnitTestCase {

	/**
	 * URL of a script that redirects as many times as requested.
	 */
	const REDIRECTION_SCRIPT = 'http://api.wordpress.org/core/tests/1.0/redirection.php';

	/**
	 * URL of a file that is served with a direct 200 response.
	 */
	const FILE_URL = 'https://s.w.org/screenshots/3.9/dashboard.png';

	/**
	 * @ticket 37459
	 */
	public function test_should_return_an_empty_array_when_there_are_no_requests() {
		$this->assertSame( array(), wp_remote_request_multiple( array() ) );
	}

	/**
	 * @ticket 37459
	 */
	public function test_should_key_and_order_the_responses_like_the_requests() {
		$this->allow_requests_rejected_before_sending();
		add_filter( 'pre_http_request', array( $this, 'short_circuit_example_org' ), 10, 3 );

		$responses = wp_remote_request_multiple(
			array(
				'first'  => 'https://example.org/first',
				// This request is not short-circuited and is answered after the others, but must keep its place.
				'second' => 'ssl://example.org/second',
				7        => array( 'url' => 'https://example.org/third' ),
				'fourth' => array(
					'url'  => 'https://example.org/fourth',
					'args' => array( 'method' => 'POST' ),
				),
			)
		);

		$this->assertSame( array( 'first', 'second', 7, 'fourth' ), array_keys( $responses ), 'The responses should be keyed and ordered like the requests.' );
		$this->assertSame( 'https://example.org/first', wp_remote_retrieve_response_message( $responses['first'] ) );
		$this->assertWPError( $responses['second'] );
		$this->assertSame( 'https://example.org/third', wp_remote_retrieve_response_message( $responses[7] ) );
		$this->assertSame( 'https://example.org/fourth', wp_remote_retrieve_response_message( $responses['fourth'] ) );
	}

	/**
	 * @ticket 37459
	 */
	public function test_should_filter_each_request_individually() {
		$methods = array();

		add_filter(
			'http_request_args',
			static function ( $parsed_args, $url ) use ( &$methods ) {
				$methods[ $url ] = $parsed_args['method'];
				return $parsed_args;
			},
			10,
			2
		);
		add_filter( 'pre_http_request', array( $this, 'short_circuit_example_org' ), 10, 3 );

		wp_remote_request_multiple(
			array(
				'https://example.org/get',
				array(
					'url'  => 'https://example.org/post',
					'args' => array( 'method' => 'POST' ),
				),
				array(
					'url'  => 'https://example.org/head',
					'args' => 'method=HEAD',
				),
			)
		);

		$this->assertSame(
			array(
				'https://example.org/get'  => 'GET',
				'https://example.org/post' => 'POST',
				'https://example.org/head' => 'HEAD',
			),
			$methods
		);
	}

	/**
	 * @ticket 37459
	 */
	public function test_should_perform_each_request_in_full() {
		$blocking = null;

		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args ) use ( &$blocking ) {
				$blocking = $parsed_args['blocking'];

				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			10,
			2
		);

		wp_remote_request_multiple(
			array(
				array(
					'url'  => 'https://example.org/',
					'args' => array( 'blocking' => false ),
				),
			)
		);

		$this->assertTrue( $blocking, 'Batched requests should always be blocking.' );
	}

	/**
	 * @ticket 37459
	 */
	public function test_should_return_a_null_short_circuit_value_as_is() {
		add_filter( 'pre_http_request', '__return_null' );

		$responses = wp_remote_request_multiple( array( 'first' => 'https://example.org/first' ) );

		$this->assertSame( array( 'first' => null ), $responses, 'A null short-circuit value should be returned like any other.' );
		$this->assertNull( wp_remote_request( 'https://example.org/first' ), 'A single request should still return a null short-circuit value as is.' );
	}

	/**
	 * @ticket 37459
	 */
	public function test_should_return_a_wp_error_for_a_request_that_cannot_be_prepared() {
		$this->allow_requests_rejected_before_sending();
		add_filter( 'pre_http_request', array( $this, 'short_circuit_example_org' ), 10, 3 );

		$responses = wp_remote_request_multiple(
			array(
				'valid'   => 'https://example.org/',
				'invalid' => 'not a url',
				'missing' => array( 'args' => array( 'method' => 'POST' ) ),
			)
		);

		$this->assertSame( 200, wp_remote_retrieve_response_code( $responses['valid'] ) );
		$this->assertWPError( $responses['invalid'] );
		$this->assertSame( 'http_request_failed', $responses['invalid']->get_error_code() );
		$this->assertWPError( $responses['missing'] );
		$this->assertSame( 'http_request_failed', $responses['missing']->get_error_code() );
	}

	/**
	 * @ticket 37459
	 */
	public function test_should_return_a_wp_error_for_a_request_the_requests_library_rejects() {
		$this->allow_requests_rejected_before_sending();

		$responses = wp_remote_request_multiple( array( 'ssl' => 'ssl://example.org/' ) );

		$this->assertWPError( $responses['ssl'] );
		$this->assertSame( 'http_request_failed', $responses['ssl']->get_error_code() );
		$this->assertSame( 'Only HTTP(S) requests are handled.', $responses['ssl']->get_error_message() );
	}

	/**
	 * @ticket 37459
	 */
	public function test_safe_variant_should_reject_unsafe_urls_for_every_request() {
		$reject_unsafe_urls = array();

		add_filter(
			'http_request_args',
			static function ( $parsed_args, $url ) use ( &$reject_unsafe_urls ) {
				$reject_unsafe_urls[ $url ] = $parsed_args['reject_unsafe_urls'];
				return $parsed_args;
			},
			10,
			2
		);
		add_filter( 'pre_http_request', array( $this, 'short_circuit_example_org' ), 10, 3 );

		$responses = wp_safe_remote_request_multiple(
			array(
				'https://example.org/string',
				array( 'url' => 'https://example.org/no-args' ),
				array(
					'url'  => 'https://example.org/string-args',
					'args' => 'method=HEAD',
				),
				array(
					'url'  => 'https://example.org/array-args',
					'args' => array( 'reject_unsafe_urls' => false ),
				),
			)
		);

		$this->assertSame(
			array(
				'https://example.org/string'      => true,
				'https://example.org/no-args'     => true,
				'https://example.org/string-args' => true,
				'https://example.org/array-args'  => true,
			),
			$reject_unsafe_urls
		);

		foreach ( $responses as $response ) {
			$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
		}
	}

	/**
	 * @ticket 37459
	 * @group external-http
	 */
	public function test_should_send_the_requests_and_return_their_responses() {
		$file = $this->temp_filename();

		$responses = wp_remote_request_multiple(
			array(
				'redirected'   => array(
					'url'  => self::REDIRECTION_SCRIPT . '?code=301&rt=2',
					'args' => array( 'redirection' => 5 ),
				),
				'not_followed' => array(
					'url'  => self::REDIRECTION_SCRIPT . '?code=302&rt=1',
					'args' => array( 'redirection' => 0 ),
				),
				'head'         => array(
					'url'  => self::FILE_URL,
					'args' => array( 'method' => 'HEAD' ),
				),
				'stream'       => array(
					'url'  => self::FILE_URL,
					'args' => array(
						'stream'   => true,
						'filename' => $file,
						'timeout'  => 30,
					),
				),
			)
		);

		foreach ( $responses as $response ) {
			$this->skipTestOnTimeout( $response );
		}

		$this->assertSame( array( 'redirected', 'not_followed', 'head', 'stream' ), array_keys( $responses ) );

		$this->assertNotWPError( $responses['redirected'] );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $responses['redirected'] ) );

		$this->assertNotWPError( $responses['not_followed'] );
		$this->assertSame( 302, wp_remote_retrieve_response_code( $responses['not_followed'] ) );

		$this->assertNotWPError( $responses['head'] );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $responses['head'] ) );
		$this->assertSame( 'image/png', wp_remote_retrieve_header( $responses['head'], 'Content-Type' ) );
		$this->assertSame( '', wp_remote_retrieve_body( $responses['head'] ) );

		$this->assertNotWPError( $responses['stream'] );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $responses['stream'] ) );
		$this->assertSame( $file, $responses['stream']['filename'] );
		$this->assertSame( 153204, filesize( $file ) );

		unlink( $file );
	}

	/**
	 * @ticket 37459
	 * @group external-http
	 */
	public function test_should_process_each_response_individually() {
		$filtered = array();
		$debugged = array();

		add_filter(
			'http_response',
			static function ( $response, $parsed_args, $url ) use ( &$filtered ) {
				$filtered[] = $url;
				return $response;
			},
			10,
			3
		);
		add_action(
			'http_api_debug',
			static function ( $response, $context, $transport, $parsed_args, $url ) use ( &$debugged ) {
				$debugged[] = $url;
			},
			10,
			5
		);

		$requests  = $this->get_head_requests( 2 );
		$responses = wp_remote_request_multiple( $requests );

		foreach ( $responses as $response ) {
			$this->skipTestOnTimeout( $response );
		}

		$urls = wp_list_pluck( $requests, 'url' );

		$this->assertSame( $urls, $filtered, "The 'http_response' filter should be applied to each response." );
		$this->assertSame( $urls, $debugged, "The 'http_api_debug' action should fire for each response." );
	}

	/**
	 * @ticket 37459
	 * @group external-http
	 */
	public function test_should_send_the_other_requests_when_one_cannot_be_batched() {
		$responses = wp_remote_request_multiple(
			array(
				'ssl'  => 'ssl://example.org/',
				'head' => array(
					'url'  => self::FILE_URL,
					'args' => array( 'method' => 'HEAD' ),
				),
			)
		);

		$this->skipTestOnTimeout( $responses['head'] );

		$this->assertWPError( $responses['ssl'] );
		$this->assertNotWPError( $responses['head'] );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $responses['head'] ) );
	}

	/**
	 * @ticket 37459
	 * @group external-http
	 *
	 * @covers WP_HTTP_Requests_Hooks::dispatch
	 */
	public function test_should_fire_http_api_curl_for_each_batched_request() {
		$this->skip_without_curl();

		$count = 0;

		add_action(
			'http_api_curl',
			static function () use ( &$count ) {
				++$count;
			}
		);

		$responses = wp_remote_request_multiple( $this->get_head_requests( 2 ) );

		foreach ( $responses as $response ) {
			$this->skipTestOnTimeout( $response );
		}

		$this->assertSame( 2, $count );
	}

	/**
	 * @ticket 37459
	 * @group external-http
	 */
	public function test_should_send_at_most_concurrency_requests_at_once() {
		$this->skip_without_curl();

		$batches = 0;

		add_action(
			'requests-curl.before_multi_exec',
			static function () use ( &$batches ) {
				++$batches;
			}
		);

		$responses = wp_remote_request_multiple( $this->get_head_requests( 3 ), array( 'concurrency' => 2 ) );

		foreach ( $responses as $response ) {
			$this->skipTestOnTimeout( $response );
		}

		$this->assertSame( 2, $batches );
	}

	/**
	 * @ticket 37459
	 * @group external-http
	 */
	public function test_should_apply_the_concurrency_filter() {
		$this->skip_without_curl();

		$batches = 0;

		add_action(
			'requests-curl.before_multi_exec',
			static function () use ( &$batches ) {
				++$batches;
			}
		);
		add_filter(
			'http_request_multiple_concurrency',
			static function () {
				return 1;
			}
		);

		$responses = wp_remote_request_multiple( $this->get_head_requests( 3 ) );

		foreach ( $responses as $response ) {
			$this->skipTestOnTimeout( $response );
		}

		$this->assertSame( 3, $batches );
	}

	/**
	 * Short-circuits requests to example.org with a 200 response whose message is the request URL.
	 *
	 * @param false|array|WP_Error $response    A preemptive return value of an HTTP request.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url         The request URL.
	 * @return false|array|WP_Error The short-circuited response for example.org, $response otherwise.
	 */
	public function short_circuit_example_org( $response, $parsed_args, $url ) {
		if ( 0 !== strpos( $url, 'https://example.org/' ) ) {
			return $response;
		}

		return array(
			'headers'  => array(),
			'body'     => '',
			'response' => array(
				'code'    => 200,
				'message' => $url,
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Returns HEAD requests for distinct URLs that are served with a direct 200 response.
	 *
	 * @param int $count Number of requests.
	 * @return array Requests, see wp_remote_request_multiple().
	 */
	private function get_head_requests( $count ) {
		$requests = array();

		for ( $i = 1; $i <= $count; $i++ ) {
			$requests[] = array(
				'url'  => self::FILE_URL . '?' . $i,
				'args' => array( 'method' => 'HEAD' ),
			);
		}

		return $requests;
	}

	/**
	 * Lets requests reach WP_Http, for tests whose URLs are rejected before anything is sent.
	 *
	 * The test suite blocks external requests on the 'pre_http_request' filter, which runs
	 * before WP_Http validates the URL. The tests calling this only use URLs that WP_Http or
	 * the Requests library reject before a transport is used, so no request leaves the process.
	 */
	private function allow_requests_rejected_before_sending() {
		remove_filter( 'pre_http_request', array( $this, 'block_external_http_request' ), PHP_INT_MAX );
	}

	/**
	 * Skips the current test when the cURL transport of the Requests library is not available.
	 */
	private function skip_without_curl() {
		if ( ! WpOrg\Requests\Transport\Curl::test() ) {
			$this->markTestSkipped( 'This test requires the cURL transport.' );
		}
	}
}
