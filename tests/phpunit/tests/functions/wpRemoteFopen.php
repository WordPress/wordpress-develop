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
	 *
	 * @group external-http
	 */
	public function test_wp_remote_fopen_empty() {
		$this->assertFalse( wp_remote_fopen( '' ) );
	}

	/**
	 * @ticket 48845
	 *
	 * @group external-http
	 */
	public function test_wp_remote_fopen_bad_url() {
		$this->assertFalse( wp_remote_fopen( 'wp.com' ) );
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
