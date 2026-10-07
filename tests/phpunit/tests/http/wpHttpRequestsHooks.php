<?php
/**
 * Tests for WP_HTTP_Requests_Hooks.
 *
 * @group http
 *
 * @covers WP_HTTP_Requests_Hooks
 * @covers WP_HTTP_Requests_Hooks::dispatch
 */
class Tests_HTTP_wpHttpRequestsHooks extends WP_UnitTestCase {

	/**
	 * Verifies http_api_curl receives the cURL handle by reference.
	 *
	 * Per #63914 this covers the same assertion as the former live-network test in
	 * Tests_HTTP_curl without performing an HTTP request: WP_HTTP_Requests_Hooks
	 * bridges Requests' curl.before_send hook to http_api_curl via do_action_ref_array().
	 *
	 * @ticket 39783
	 * @ticket 63914
	 */
	public function test_http_api_curl_handle_is_passed_by_reference() {
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
