<?php
/**
 * @group http
 * @group functions
 *
 * @covers ::wp_remote_fopen
 */
class Tests_Functions_wpRemoteFopen extends WP_UnitTestCase {

	/**
	 * @ticket 48845
	 * @ticket 63914
	 */
	public function test_wp_remote_fopen_empty() {
		$this->assertFalse( wp_remote_fopen( '' ) );
	}

	/**
	 * @ticket 48845
	 * @ticket 63914
	 */
	public function test_wp_remote_fopen_bad_url() {
		/*
		 * Mock the HTTP layer error that WP_Http would return for a schemeless
		 * URL. Removing the external-HTTP guard and letting validation run
		 * hits parse_url() with a non-string and raises PHP warnings in tests.
		 * A pre_http_request mock still covers wp_remote_fopen()'s WP_Error → false path.
		 */
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'A valid URL was not provided.' );
			}
		);

		$this->assertFalse( wp_remote_fopen( 'wp.com' ) );
	}

	/**
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
