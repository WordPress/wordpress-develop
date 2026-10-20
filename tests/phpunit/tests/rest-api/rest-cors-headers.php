<?php
/**
 * Tests for the Access-Control-Allow-Methods header sent with REST API responses.
 *
 * rest_send_cors_headers() sends its headers with header(), which PHP does not record
 * under the CLI SAPI. These tests read the final method list from a late
 * `rest_allowed_cors_methods` callback instead of from the wire.
 *
 * @group restapi
 * @group restapi-cors
 *
 * @covers ::rest_send_cors_headers
 */
class Tests_REST_CORS_Headers extends WP_UnitTestCase {

	/**
	 * The default method list sent by rest_send_cors_headers().
	 */
	const DEFAULT_ALLOW_METHODS = array( 'OPTIONS', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );

	public function set_up() {
		parent::set_up();

		// The CORS headers are only sent when the request carries an Origin.
		$_SERVER['HTTP_ORIGIN']    = 'http://example.org';
		$_SERVER['REQUEST_METHOD'] = 'GET';

		// Create a spy server to capture headers.
		$GLOBALS['wp_rest_server'] = new Spy_REST_Server();
		do_action( 'rest_api_init', $GLOBALS['wp_rest_server'] );
	}

	public function tear_down() {
		unset( $_SERVER['HTTP_ORIGIN'], $_GET['_envelope'] );
		$GLOBALS['wp_rest_server'] = null;

		parent::tear_down();
	}

	/**
	 * Sends the CORS headers for a response to `/wp/v2/types`.
	 *
	 * @param array $response_headers Headers to set on the response.
	 */
	private function send_cors_headers( $response_headers = array() ) {
		$response = new WP_REST_Response( array() );
		$response->set_headers( $response_headers );

		rest_send_cors_headers( false, $response, new WP_REST_Request( 'GET', '/wp/v2/types' ), $GLOBALS['wp_rest_server'] );
	}

	/**
	 * A response that sets no Access-Control-Allow-Methods header is sent the default list.
	 *
	 * @ticket 46992
	 */
	public function test_default_list_is_sent() {
		$this->send_cors_headers();

		$this->assertSame( 'OPTIONS, GET, POST, PUT, PATCH, DELETE', $GLOBALS['wp_rest_server']->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * No method list is sent when the request has no Origin.
	 *
	 * @ticket 46992
	 */
	public function test_no_list_is_sent_without_an_origin() {
		unset( $_SERVER['HTTP_ORIGIN'] );

		$this->send_cors_headers();

		$this->assertArrayNotHasKey( 'Access-Control-Allow-Methods', $GLOBALS['wp_rest_server']->sent_headers );
	}

	/**
	 * An Access-Control-Allow-Methods header set on the response is passed through.
	 *
	 * rest_send_cors_headers() runs on 'rest_pre_serve_request', after the response's
	 * own headers have been sent, so sending the default list unconditionally replaced
	 * any value the response supplied.
	 *
	 * @ticket 46992
	 */
	public function test_response_value_is_passed_through() {
		$this->send_cors_headers( array( 'Access-Control-Allow-Methods' => 'OPTIONS, GET' ) );

		$this->assertSame( 'OPTIONS, GET', $GLOBALS['wp_rest_server']->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * The response header is matched without regard to case.
	 *
	 * @ticket 46992
	 */
	public function test_response_value_is_matched_case_insensitively() {
		$this->send_cors_headers( array( 'access-control-allow-methods' => 'OPTIONS, GET' ) );

		$this->assertSame( 'OPTIONS, GET', $GLOBALS['wp_rest_server']->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * A response value may name methods outside the default list.
	 *
	 * @ticket 46992
	 */
	public function test_response_value_can_add_methods_beyond_the_default() {
		$this->send_cors_headers( array( 'Access-Control-Allow-Methods' => 'OPTIONS, GET, PROPFIND' ) );

		$this->assertSame( 'OPTIONS, GET, PROPFIND', $GLOBALS['wp_rest_server']->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * Entries in a response value that are not valid method tokens are dropped.
	 *
	 * @ticket 46992
	 */
	public function test_response_value_drops_invalid_method_tokens() {
		$this->send_cors_headers( array( 'Access-Control-Allow-Methods' => 'GET, P@TCH, <script>, DELETE' ) );

		$this->assertSame( 'GET, DELETE', $GLOBALS['wp_rest_server']->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * Methods in a response value keep their case, as methods are case-sensitive.
	 *
	 * @ticket 46992
	 */
	public function test_response_value_method_case_is_preserved() {
		$this->send_cors_headers( array( 'Access-Control-Allow-Methods' => 'options, Get' ) );

		$this->assertSame( 'options, Get', $GLOBALS['wp_rest_server']->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * Unexpected values passed through 'rest_pre_serve_request' are ignored.
	 *
	 * @ticket 46992
	 */
	public function test_unexpected_result_and_request_are_ignored() {
		$received = false;

		add_filter(
			'rest_allowed_cors_methods',
			static function ( $methods, $request ) use ( &$received ) {
				$received = $request;
				return $methods;
			},
			10,
			2
		);

		rest_send_cors_headers( false, 'not a response', 'not a request', $GLOBALS['wp_rest_server'] );

		$this->assertNull( $received );
		$this->assertSame( 'OPTIONS, GET, POST, PUT, PATCH, DELETE', $GLOBALS['wp_rest_server']->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * The rest_allowed_cors_methods filter changes the list.
	 *
	 * @ticket 46992
	 */
	public function test_filter_changes_the_list() {
		add_filter(
			'rest_allowed_cors_methods',
			static function ( $methods ) {
				$methods[] = 'HEAD';
				return $methods;
			}
		);

		$this->send_cors_headers();

		$this->assertSame( 'OPTIONS, GET, POST, PUT, PATCH, DELETE, HEAD', $GLOBALS['wp_rest_server']->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * The filter is seeded with the response's value and can replace it.
	 *
	 * @ticket 46992
	 */
	public function test_filter_can_override_a_response_value() {
		$received = null;

		add_filter(
			'rest_allowed_cors_methods',
			static function ( $methods ) use ( &$received ) {
				$received = $methods;
				return array( 'OPTIONS', 'DELETE' );
			}
		);

		$this->send_cors_headers( array( 'Access-Control-Allow-Methods' => 'OPTIONS, GET' ) );

		$this->assertSame( array( 'OPTIONS', 'GET' ), $received, 'The filter was not seeded with the response value.' );
		$this->assertSame( 'OPTIONS, DELETE', $GLOBALS['wp_rest_server']->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * Serving a request passes the request in context to the filter.
	 *
	 * @ticket 46992
	 */
	public function test_filter_receives_the_request_when_serving() {
		$received = null;

		add_filter(
			'rest_allowed_cors_methods',
			static function ( $methods, $request ) use ( &$received ) {
				$received = $request;
				return $methods;
			},
			10,
			2
		);

		rest_get_server()->serve_request( '/wp/v2/types' );

		$this->assertInstanceOf( WP_REST_Request::class, $received );
		$this->assertSame( '/wp/v2/types', $received->get_route() );
	}

	/**
	 * Under `?_envelope=1`, a response header is not seen and the default is sent.
	 *
	 * envelope_response() moves the response's headers into the body on
	 * 'rest_post_dispatch', ahead of rest_send_cors_headers().
	 *
	 * @ticket 46992
	 */
	public function test_envelope_response_does_not_see_a_response_value() {
		$_GET['_envelope'] = '1';

		add_filter(
			'rest_post_dispatch',
			static function ( WP_HTTP_Response $response ) {
				$response->header( 'Access-Control-Allow-Methods', 'OPTIONS, GET' );
				return $response;
			}
		);

		rest_get_server()->serve_request( '/wp/v2/types' );

		$this->assertSame( 'OPTIONS, GET, POST, PUT, PATCH, DELETE', $GLOBALS['wp_rest_server']->sent_headers['Access-Control-Allow-Methods'] );
	}
}
