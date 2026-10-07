<?php
/**
 * @group http
 * @group functions
 *
 * @covers ::wp_remote_fopen
 */
class Tests_Functions_wpRemoteFopen extends WP_UnitTestCase {

	/**
	 * Captured HTTP request arguments from the last mocked request.
	 *
	 * @var array|null
	 */
	private $request_args = null;

	/**
	 * Captured HTTP request URL from the last mocked request.
	 *
	 * @var string|null
	 */
	private $request_url = null;

	/**
	 * Set up mocked HTTP responses for all cases in this class.
	 *
	 * Per #63914, prove the same behavior with mocked responses and leave the
	 * external-http group. Pattern matches Tests_HTTP_wpGetHttpHeaders.
	 */
	public function set_up() {
		parent::set_up();

		$this->request_args = null;
		$this->request_url  = null;

		add_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10, 3 );
	}

	/**
	 * Empty input is rejected before any HTTP request is made.
	 *
	 * @ticket 48845
	 * @ticket 63914
	 */
	public function test_wp_remote_fopen_empty() {
		$this->assertFalse( wp_remote_fopen( '' ) );
		$this->assertNull( $this->request_url, 'Empty input should not reach the HTTP API.' );
	}

	/**
	 * A schemeless URL returns false when the HTTP API returns a WP_Error.
	 *
	 * @ticket 48845
	 * @ticket 63914
	 */
	public function test_wp_remote_fopen_bad_url() {
		$this->assertFalse( wp_remote_fopen( 'wp.com' ) );
		$this->assertSame( 'wp.com', $this->request_url );
		$this->assertTrue( $this->request_args['reject_unsafe_urls'], 'The request should use wp_safe_remote_get().' );
		$this->assertSame( 10, $this->request_args['timeout'] );
	}

	/**
	 * A successful response returns the remote body from wp_safe_remote_get().
	 *
	 * @ticket 48845
	 * @ticket 63914
	 */
	public function test_wp_remote_fopen() {
		$response = wp_remote_fopen( 'https://example.com/' );

		$this->assertSame( 'Hello World', $response );
		$this->assertSame( 'https://example.com/', $this->request_url );
		$this->assertTrue( $this->request_args['reject_unsafe_urls'], 'The request should use wp_safe_remote_get().' );
		$this->assertSame( 10, $this->request_args['timeout'] );
	}

	/**
	 * Mock the HTTP request response.
	 *
	 * @param false|array|WP_Error $response    A preemptive return value of an HTTP request. Default false.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url         The request URL.
	 * @return false|array|WP_Error Response data.
	 */
	public function mock_http_request( $response, $parsed_args, $url ) {
		$this->request_url  = $url;
		$this->request_args = $parsed_args;

		if ( 'https://example.com/' === $url ) {
			return array(
				'headers'  => array(),
				'body'     => 'Hello World',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		}

		if ( 'wp.com' === $url ) {
			return new WP_Error( 'http_request_failed', 'A valid URL was not provided.' );
		}

		return $response;
	}
}
