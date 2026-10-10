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
	 * cURL handle passed into the http_api_curl action.
	 *
	 * @var mixed
	 */
	private $action_handle;

	/**
	 * @ticket 39783
	 * @ticket 63914
	 */
	public function test_http_api_curl_stream_parameter_is_a_reference() {
		if ( ! function_exists( 'curl_init' ) ) {
			$this->markTestSkipped( 'The cURL extension is not available.' );
		}

		$handle              = curl_init();
		$this->action_handle = null;

		$hooks = new WP_HTTP_Requests_Hooks(
			'https://example.com/',
			array(
				'method' => 'GET',
			)
		);

		add_action( 'http_api_curl', array( $this, 'capture_http_api_curl_handle' ), 10, 3 );

		$hooks->dispatch( 'curl.before_send', array( &$handle ) );

		remove_action( 'http_api_curl', array( $this, 'capture_http_api_curl_handle' ), 10 );

		$this->assertNotNull( $this->action_handle, 'The http_api_curl action should run for curl.before_send.' );
		$this->assertNull( $handle, 'The cURL handle should be passed to http_api_curl by reference.' );
	}

	/**
	 * Captures the http_api_curl handle and clears it by reference.
	 *
	 * @param mixed  $handle cURL handle (passed by reference).
	 * @param array  $args   Request arguments.
	 * @param string $url    Request URL.
	 */
	public function capture_http_api_curl_handle( &$handle, $args, $url ) {
		unset( $args, $url );

		$this->action_handle = $handle;

		// Mutating the parameter must change the original handle variable.
		$handle = null;
	}
}
