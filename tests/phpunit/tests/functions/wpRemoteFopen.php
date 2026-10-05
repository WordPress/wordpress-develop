<?php
/**
 * @group http
 * @group functions
 *
 * @covers ::wp_remote_fopen
 */
class Tests_Functions_wpRemoteFopen extends WP_UnitTestCase {

	/**
	 * Empty input is rejected before any HTTP request is made.
	 *
	 * @ticket 48845
	 * @ticket 63914
	 */
	public function test_wp_remote_fopen_empty() {
		$this->assertFalse( wp_remote_fopen( '' ) );
	}

	/**
	 * A schemeless URL reaches the HTTP API and returns false on WP_Error.
	 *
	 * Per #63914 this covers `wp_remote_fopen()`'s failure path with a mocked
	 * response instead of a live request. The mock returns the same WP_Error
	 * shape WP_Http uses when a valid URL was not provided.
	 *
	 * @ticket 48845
	 * @ticket 63914
	 */
	public function test_wp_remote_fopen_bad_url() {
		$request_url  = null;
		$request_args = null;

		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args, $url ) use ( &$request_url, &$request_args ) {
				$request_url  = $url;
				$request_args = $parsed_args;

				return new WP_Error( 'http_request_failed', 'A valid URL was not provided.' );
			},
			10,
			3
		);

		$this->assertFalse( wp_remote_fopen( 'wp.com' ) );
		$this->assertSame( 'wp.com', $request_url, 'The bad URL should still be passed to the HTTP API.' );
		$this->assertTrue( $request_args['reject_unsafe_urls'], 'The request should use wp_safe_remote_get().' );
		$this->assertSame( 10, $request_args['timeout'] );
	}

	/**
	 * A successful response returns the remote body from wp_safe_remote_get().
	 *
	 * @ticket 48845
	 * @ticket 63914
	 */
	public function test_wp_remote_fopen() {
		$body         = 'Hello World';
		$request_args = null;

		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args ) use ( $body, &$request_args ) {
				$request_args = $parsed_args;

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
			},
			10,
			2
		);

		$response = wp_remote_fopen( 'https://example.com/' );

		$this->assertSame( $body, $response );
		$this->assertTrue( $request_args['reject_unsafe_urls'], 'The request should use wp_safe_remote_get().' );
		$this->assertSame( 10, $request_args['timeout'] );
	}
}
