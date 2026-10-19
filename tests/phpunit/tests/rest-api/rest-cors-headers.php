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

	/**
	 * The method list the header was sent with, or null if the filter did not run.
	 *
	 * @var string[]|null
	 */
	private $sent_methods;

	public function set_up() {
		parent::set_up();

		// The CORS headers are only sent when the request carries an Origin.
		$_SERVER['HTTP_ORIGIN']    = 'http://example.org';
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$this->sent_methods = null;
		add_filter( 'rest_allowed_cors_methods', array( $this, 'record_sent_methods' ), PHP_INT_MAX );
	}

	public function tear_down() {
		unset( $_SERVER['HTTP_ORIGIN'], $_GET['_envelope'] );
		$GLOBALS['wp_rest_server'] = null;

		parent::tear_down();
	}

	/**
	 * Records the method list after every other callback has run.
	 *
	 * @param string[] $methods The list of HTTP methods to allow.
	 * @return string[] The unchanged list.
	 */
	public function record_sent_methods( $methods ) {
		$this->sent_methods = $methods;
		return $methods;
	}

	/**
	 * Calls a function, ignoring the warnings from header() once PHPUnit has sent output.
	 *
	 * @param callable $callback Function to call.
	 * @return mixed The callback's return value.
	 */
	private function ignoring_header_warnings( $callback ) {
		set_error_handler(
			static function ( $errno, $errstr ) {
				return str_starts_with( $errstr, 'Cannot modify header information' );
			},
			E_WARNING
		);

		try {
			return $callback();
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Sends the CORS headers for a response to `/wp/v2/types`.
	 *
	 * @param array $response_headers Headers to set on the response.
	 */
	private function send_cors_headers( $response_headers = array() ) {
		$response = new WP_REST_Response( array() );
		$response->set_headers( $response_headers );

		$this->ignoring_header_warnings(
			static function () use ( $response ) {
				rest_send_cors_headers( false, $response, new WP_REST_Request( 'GET', '/wp/v2/types' ) );
			}
		);
	}

	/**
	 * A response that sets no Access-Control-Allow-Methods header is sent the default list.
	 *
	 * @ticket 46992
	 */
	public function test_default_list_is_sent() {
		$this->send_cors_headers();

		$this->assertSame( self::DEFAULT_ALLOW_METHODS, $this->sent_methods );
	}

	/**
	 * No method list is sent when the request has no Origin.
	 *
	 * @ticket 46992
	 */
	public function test_no_list_is_sent_without_an_origin() {
		unset( $_SERVER['HTTP_ORIGIN'] );

		$this->send_cors_headers();

		$this->assertNull( $this->sent_methods );
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

		$this->assertSame( array( 'OPTIONS', 'GET' ), $this->sent_methods );
	}

	/**
	 * The response header is matched without regard to case.
	 *
	 * @ticket 46992
	 */
	public function test_response_value_is_matched_case_insensitively() {
		$this->send_cors_headers( array( 'access-control-allow-methods' => 'OPTIONS, GET' ) );

		$this->assertSame( array( 'OPTIONS', 'GET' ), $this->sent_methods );
	}

	/**
	 * A response value is limited to methods in the default list.
	 *
	 * @ticket 46992
	 */
	public function test_response_value_is_limited_to_the_default_methods() {
		$this->send_cors_headers( array( 'Access-Control-Allow-Methods' => 'GET, TRACE, DELETE' ) );

		$this->assertSame( array( 'GET', 'DELETE' ), $this->sent_methods );
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

		$this->assertSame( array_merge( self::DEFAULT_ALLOW_METHODS, array( 'HEAD' ) ), $this->sent_methods );
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
		$this->assertSame( array( 'OPTIONS', 'DELETE' ), $this->sent_methods );
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

		$this->ignoring_header_warnings(
			static function () {
				rest_get_server()->serve_request( '/wp/v2/types' );
			}
		);

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

		$this->ignoring_header_warnings(
			static function () {
				rest_get_server()->serve_request( '/wp/v2/types' );
			}
		);

		$this->assertSame( self::DEFAULT_ALLOW_METHODS, $this->sent_methods );
	}
}
