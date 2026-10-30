<?php
/**
 * Tests for the Access-Control-Allow-Methods header sent with REST API responses.
 *
 * PHP does not record headers sent with header() under the CLI SAPI. When it is passed
 * a server, rest_send_cors_headers() sends its headers through the server instead, so
 * these tests read them from Spy_REST_Server::$sent_headers.
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
	const DEFAULT_ALLOW_METHODS = 'OPTIONS, GET, POST, PUT, PATCH, DELETE';

	/**
	 * The `$_SERVER` values the tests change, as they were before the test, with null for one that was not set.
	 *
	 * @var array<string, mixed>
	 */
	private $original_server_values = array();

	public function set_up() {
		parent::set_up();

		foreach ( array( 'HTTP_ORIGIN', 'REQUEST_METHOD' ) as $key ) {
			$this->original_server_values[ $key ] = $_SERVER[ $key ] ?? null;
		}

		// The CORS headers are only sent when the request carries an Origin.
		$_SERVER['HTTP_ORIGIN']    = 'http://example.org';
		$_SERVER['REQUEST_METHOD'] = 'GET';

		// Create a spy server to capture headers.
		$GLOBALS['wp_rest_server'] = new Spy_REST_Server();

		/** This action is documented in wp-includes/rest-api.php */
		do_action( 'rest_api_init', $GLOBALS['wp_rest_server'] );
	}

	public function tear_down() {
		unset( $_GET['_envelope'] );
		foreach ( $this->original_server_values as $key => $value ) {
			if ( null === $value ) {
				unset( $_SERVER[ $key ] );
			} else {
				$_SERVER[ $key ] = $value;
			}
		}
		$GLOBALS['wp_rest_server'] = null;

		parent::tear_down();
	}

	/**
	 * Sends the CORS headers for a response to `/wp/v2/types`.
	 *
	 * @param array<string, string> $response_headers Headers to set on the response.
	 */
	private function send_cors_headers( array $response_headers = array() ): void {
		$response = new WP_REST_Response( array() );
		$response->set_headers( $response_headers );

		rest_send_cors_headers( false, $response, new WP_REST_Request( 'GET', '/wp/v2/types' ), $this->get_rest_server() );
	}

	/**
	 * A response that sets no Access-Control-Allow-Methods header is sent the default list.
	 *
	 * @ticket 46992
	 */
	public function test_default_list_is_sent() {
		$this->send_cors_headers();

		$this->assertSame( self::DEFAULT_ALLOW_METHODS, $this->get_rest_server()->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * No method list is sent when the request has no Origin.
	 *
	 * @ticket 46992
	 */
	public function test_no_list_is_sent_without_an_origin() {
		unset( $_SERVER['HTTP_ORIGIN'] );

		$this->send_cors_headers();

		$this->assertArrayNotHasKey( 'Access-Control-Allow-Methods', $this->get_rest_server()->sent_headers );
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

		$this->assertSame( 'OPTIONS, GET', $this->get_rest_server()->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * The response header is matched without regard to case.
	 *
	 * @ticket 46992
	 */
	public function test_response_value_is_matched_case_insensitively() {
		$this->send_cors_headers( array( 'access-control-allow-methods' => 'OPTIONS, GET' ) );

		$this->assertSame( 'OPTIONS, GET', $this->get_rest_server()->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * A response value may name methods outside the default list.
	 *
	 * @ticket 46992
	 */
	public function test_response_value_can_add_methods_beyond_the_default() {
		$this->send_cors_headers( array( 'Access-Control-Allow-Methods' => 'OPTIONS, GET, PROPFIND' ) );

		$this->assertSame( 'OPTIONS, GET, PROPFIND', $this->get_rest_server()->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * Entries in a response value that are not valid method tokens are dropped.
	 *
	 * @ticket 46992
	 */
	public function test_response_value_drops_invalid_method_tokens() {
		$this->send_cors_headers( array( 'Access-Control-Allow-Methods' => 'GET, P@TCH, <script>, DELETE' ) );

		$this->assertSame( 'GET, DELETE', $this->get_rest_server()->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * A response value that names no valid methods falls back to the default list.
	 *
	 * @ticket 46992
	 *
	 * @dataProvider data_response_value_without_valid_methods
	 *
	 * @param string $response_value Access-Control-Allow-Methods value set on the response.
	 */
	public function test_response_value_without_valid_methods_falls_back_to_the_default( string $response_value ) {
		$this->send_cors_headers( array( 'Access-Control-Allow-Methods' => $response_value ) );

		$this->assertSame( self::DEFAULT_ALLOW_METHODS, $this->get_rest_server()->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: string }>
	 */
	public static function data_response_value_without_valid_methods(): array {
		return array(
			'empty'           => array( '' ),
			'whitespace'      => array( ' ' ),
			'invalid methods' => array( 'GET;POST' ),
		);
	}

	/**
	 * Methods in a response value keep their case, as methods are case-sensitive.
	 *
	 * @ticket 46992
	 */
	public function test_response_value_method_case_is_preserved() {
		$this->send_cors_headers( array( 'Access-Control-Allow-Methods' => 'options, Get' ) );

		$server = $this->get_rest_server();
		$this->assertSame( 'options, Get', $server->sent_headers['Access-Control-Allow-Methods'] );
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

		$server = $this->get_rest_server();
		rest_send_cors_headers( false, new WP_Error( 'unexpected' ), array( 'unexpected' ), $server ); // @phpstan-ignore argument.type, argument.type (Intentionally passing invalid values.)

		$this->assertNull( $received );
		$this->assertSame( self::DEFAULT_ALLOW_METHODS, $server->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * A direct call without a server does not set up the REST server.
	 *
	 * @ticket 46992
	 */
	public function test_direct_call_without_a_server_does_not_set_up_the_server() {
		$GLOBALS['wp_rest_server'] = null;
		$init_count                = did_action( 'rest_api_init' );

		$this->assertSame( 'value', rest_send_cors_headers( 'value' ) );

		$this->assertNull( $GLOBALS['wp_rest_server'], 'The REST server was set up.' );
		$this->assertSame( $init_count, did_action( 'rest_api_init' ), 'The rest_api_init action was fired.' );
	}

	/**
	 * The rest_allowed_cors_methods filter changes the list.
	 *
	 * @ticket 46992
	 */
	public function test_filter_changes_the_list() {
		add_filter(
			'rest_allowed_cors_methods',
			static function ( array $methods ): array {
				$methods[] = 'HEAD';
				return $methods;
			}
		);

		$this->send_cors_headers();

		$server = $this->get_rest_server();
		$this->assertSame( self::DEFAULT_ALLOW_METHODS . ', HEAD', $server->sent_headers['Access-Control-Allow-Methods'] );
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

		$server = $this->get_rest_server();
		$this->assertSame( 'OPTIONS, DELETE', $server->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * A filter may return the list as a string, as in the header value.
	 *
	 * @ticket 46992
	 */
	public function test_filter_may_return_a_string() {
		add_filter(
			'rest_allowed_cors_methods',
			static function () {
				return 'OPTIONS, GET';
			}
		);

		$this->send_cors_headers();

		$this->assertSame( 'OPTIONS, GET', $this->get_rest_server()->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * Entries in the filtered list that are not valid method tokens are dropped.
	 *
	 * @ticket 46992
	 */
	public function test_filter_value_drops_invalid_method_tokens() {
		add_filter(
			'rest_allowed_cors_methods',
			static function () {
				return array( 'GET', 'P@TCH', '<script>', 'X-Injected:', '0' );
			}
		);

		$this->send_cors_headers();

		$this->assertSame( 'GET, 0', $this->get_rest_server()->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * Line breaks in the filtered list separate entries and never reach the header.
	 *
	 * @ticket 46992
	 */
	public function test_filter_value_line_breaks_do_not_reach_the_header() {
		add_filter(
			'rest_allowed_cors_methods',
			static function () {
				return array( 'GET', "DELETE\r\nX-Injected: 1", "PUT\n" );
			}
		);

		$this->send_cors_headers();

		$this->assertSame( 'GET, DELETE, 1, PUT', $this->get_rest_server()->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * Entries in the filtered list are split and trimmed like a string value.
	 *
	 * @ticket 46992
	 */
	public function test_filter_value_entries_are_split() {
		add_filter(
			'rest_allowed_cors_methods',
			static function () {
				return array( 'OPTIONS, GET', ' POST ' );
			}
		);

		$this->send_cors_headers();

		$this->assertSame( 'OPTIONS, GET, POST', $this->get_rest_server()->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * Duplicate methods in the filtered list are sent once.
	 *
	 * Methods are case-sensitive, so entries differing only in case are kept.
	 *
	 * @ticket 46992
	 */
	public function test_filter_value_duplicates_are_removed() {
		add_filter(
			'rest_allowed_cors_methods',
			static function ( array $methods ): array {
				$methods[] = 'GET, POST';
				$methods[] = 'get';
				return $methods;
			}
		);

		$this->send_cors_headers();

		$this->assertSame( self::DEFAULT_ALLOW_METHODS . ', get', $this->get_rest_server()->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * Entries in the filtered list that are not strings are dropped.
	 *
	 * @ticket 46992
	 */
	public function test_filter_value_drops_entries_that_are_not_strings() {
		add_filter(
			'rest_allowed_cors_methods',
			static function () {
				return array( 'GET', true, 1, 1.5, null, array( 'POST' ) );
			}
		);

		$this->send_cors_headers();

		$this->assertSame( 'GET', $this->get_rest_server()->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * An empty filtered list removes a value the response already sent.
	 *
	 * @ticket 46992
	 */
	public function test_empty_filter_value_removes_the_header() {
		add_filter(
			'rest_post_dispatch',
			static function ( WP_HTTP_Response $response ) {
				$response->header( 'Access-Control-Allow-Methods', 'OPTIONS, GET' );
				return $response;
			}
		);
		add_filter( 'rest_allowed_cors_methods', '__return_empty_array' );

		$server = $this->get_rest_server();
		$server->serve_request( '/wp/v2/types' );

		$this->assertArrayNotHasKey( 'Access-Control-Allow-Methods', $server->sent_headers );
	}

	/**
	 * A filtered value that is not a list is ignored, keeping the unfiltered list.
	 *
	 * @ticket 46992
	 *
	 * @dataProvider data_filter_value_that_is_not_a_list
	 *
	 * @param callable              $callback         Filter callback returning a value that is not a list.
	 * @param array<string, string> $response_headers Headers to set on the response.
	 * @param string                $expected         Expected Access-Control-Allow-Methods value.
	 */
	public function test_filter_value_that_is_not_a_list_is_ignored( callable $callback, array $response_headers, string $expected ) {
		add_filter( 'rest_allowed_cors_methods', $callback );

		$this->send_cors_headers( $response_headers );

		$this->assertSame( $expected, $this->get_rest_server()->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: callable, 1: array<string, string>, 2: string }>
	 */
	public static function data_filter_value_that_is_not_a_list(): array {
		return array(
			'null'                    => array( '__return_null', array(), self::DEFAULT_ALLOW_METHODS ),
			'false'                   => array( '__return_false', array(), self::DEFAULT_ALLOW_METHODS ),
			'null with response list' => array( '__return_null', array( 'Access-Control-Allow-Methods' => 'OPTIONS, GET' ), 'OPTIONS, GET' ),
		);
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

		$server = $this->get_rest_server();
		$server->serve_request( '/wp/v2/types' );
		$this->assertSame( self::DEFAULT_ALLOW_METHODS, $server->sent_headers['Access-Control-Allow-Methods'] );
	}

	/**
	 * Gets the spy REST Server.
	 *
	 * @return Spy_REST_Server
	 */
	private function get_rest_server(): Spy_REST_Server {
		/** @var Spy_REST_Server $server */
		$server = rest_get_server();
		return $server;
	}
}
