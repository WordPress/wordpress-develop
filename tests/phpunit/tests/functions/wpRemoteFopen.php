<?php
/**
 * @group http
 * @group functions
 *
 * @covers ::wp_remote_fopen
 */
class Tests_Functions_wpRemoteFopen extends WP_UnitTestCase {

	/**
	 * An empty URL returns false when the HTTP API returns a WP_Error.
	 *
	 * On PHP 8+, `parse_url( '' )` returns `array( 'path' => '' )`, so
	 * `wp_remote_fopen()` still calls `wp_safe_remote_get()`.
	 *
	 * @ticket 48845
	 * @ticket 63914
	 */
	public function test_wp_remote_fopen_empty() {
		$request_args = null;

		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args ) use ( &$request_args ) {
				$request_args = $parsed_args;

				return new WP_Error( 'http_request_failed', 'A valid URL was not provided.' );
			},
			10,
			2
		);

		$this->assertFalse( wp_remote_fopen( '' ) );
		$this->assertTrue( $request_args['reject_unsafe_urls'], 'The request should use wp_safe_remote_get().' );
		$this->assertSame( 10, $request_args['timeout'] );
	}

	/**
	 * A schemeless URL returns false when the HTTP API returns a WP_Error.
	 *
	 * @ticket 48845
	 * @ticket 63914
	 */
	public function test_wp_remote_fopen_bad_url() {
		$request_args = null;

		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args ) use ( &$request_args ) {
				$request_args = $parsed_args;

				return new WP_Error( 'http_request_failed', 'A valid URL was not provided.' );
			},
			10,
			2
		);

		$this->assertFalse( wp_remote_fopen( 'wp.com' ) );
		$this->assertTrue( $request_args['reject_unsafe_urls'], 'The request should use wp_safe_remote_get().' );
		$this->assertSame( 10, $request_args['timeout'] );
	}

	/**
	 * @ticket 48845
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
