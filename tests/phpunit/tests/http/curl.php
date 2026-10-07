<?php

require_once __DIR__ . '/base.php';

/**
 * @group http
 * @group external-http
 */
class Tests_HTTP_curl extends WP_HTTP_UnitTestCase {
	public $transport = 'curl';

	/**
	 * Verifies http_api_curl receives the cURL handle by reference.
	 *
	 * Per #63914 this no longer performs a live HTTP request. It exercises the
	 * Requests → http_api_curl bridge in WP_HTTP_Requests_Hooks instead.
	 *
	 * @ticket 39783
	 * @ticket 63914
	 *
	 * @covers WP_HTTP_Requests_Hooks::dispatch
	 */
	public function test_http_api_curl_stream_parameter_is_a_reference() {
		if ( ! function_exists( 'curl_init' ) ) {
			$this->markTestSkipped( 'The cURL extension is not available.' );
		}

		$handle = curl_init();
		$hooks  = new WP_HTTP_Requests_Hooks(
			'https://example.com/',
			array(
				'method' => 'GET',
			)
		);

		$action_handle = null;

		add_action(
			'http_api_curl',
			static function ( &$stream, $r, $url ) use ( &$action_handle ) {
				unset( $r, $url );
				$action_handle = $stream;
				// Mutating the parameter must change the original handle variable.
				$stream = null;
			},
			10,
			3
		);

		$hooks->dispatch( 'curl.before_send', array( &$handle ) );

		$this->assertNotNull( $action_handle, 'The http_api_curl action should run for curl.before_send.' );
		$this->assertNull( $handle, 'The cURL handle should be passed to http_api_curl by reference.' );
	}
}
