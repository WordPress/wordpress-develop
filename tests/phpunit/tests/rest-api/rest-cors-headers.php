<?php
/**
 * Tests for the CORS headers sent with REST API responses.
 *
 * rest_send_cors_headers() emits its headers with raw header() calls rather than
 * WP_REST_Server::send_header(), so Spy_REST_Server cannot observe them. These tests
 * serve requests through a real WP_REST_Server and read the emitted headers with
 * xdebug_get_headers(), following tests/phpunit/tests/oembed/headers.php.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @group restapi
 * @group restapi-cors
 * @group xdebug
 *
 * @covers ::rest_send_cors_headers
 */
class Tests_REST_CORS_Headers extends WP_UnitTestCase {

	/**
	 * The default method list sent by rest_send_cors_headers().
	 */
	const DEFAULT_ALLOW_METHODS = 'Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE';

	public function set_up() {
		parent::set_up();

		// The CORS headers are only sent when the request carries an Origin.
		$_SERVER['HTTP_ORIGIN']    = 'http://example.org';
		$_SERVER['REQUEST_METHOD'] = 'GET';

		/*
		 * A real server rather than Spy_REST_Server, which intercepts send_header()
		 * before it reaches PHP and would hide the interaction being tested.
		 */
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	public function tear_down() {
		unset( $_SERVER['HTTP_ORIGIN'] );

		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Serves a request through the full pipeline and returns the emitted headers.
	 *
	 * @return string[] Headers as xdebug reports them, after replace semantics apply.
	 */
	protected function serve_and_get_headers() {
		ob_start();
		rest_get_server()->serve_request( '/wp/v2/types' );
		ob_end_clean();

		return xdebug_get_headers();
	}

	/**
	 * An Access-Control-Allow-Methods header set on the response reaches the client.
	 *
	 * rest_send_cors_headers() runs on 'rest_pre_serve_request', after the response's
	 * own headers have been emitted, so sending the default list unconditionally
	 * replaced any value the response supplied.
	 *
	 * @ticket 46992
	 *
	 * @requires function xdebug_get_headers
	 */
	public function test_response_can_set_access_control_allow_methods() {
		add_filter(
			'rest_post_dispatch',
			static function ( $response ) {
				$response->header( 'Access-Control-Allow-Methods', 'OPTIONS, GET' );
				return $response;
			}
		);

		$headers = $this->serve_and_get_headers();

		$this->assertContains( 'Access-Control-Allow-Methods: OPTIONS, GET', $headers );
	}

	/**
	 * The default list is suppressed rather than duplicated when the response sets one,
	 * and Vary continues to append.
	 *
	 * Sending both values would leave two Access-Control-Allow-Methods headers on the wire.
	 *
	 * @ticket 46992
	 *
	 * @requires function xdebug_get_headers
	 */
	public function test_response_value_suppresses_the_default_and_vary_still_appends() {
		add_filter(
			'rest_post_dispatch',
			static function ( $response ) {
				$response->header( 'Access-Control-Allow-Methods', 'OPTIONS, GET' );
				$response->header( 'Vary', 'Accept-Encoding' );
				return $response;
			}
		);

		$headers = $this->serve_and_get_headers();

		$this->assertContains( 'Access-Control-Allow-Methods: OPTIONS, GET', $headers );
		$this->assertNotContains( self::DEFAULT_ALLOW_METHODS, $headers );
		$this->assertContains( 'Vary: Accept-Encoding', $headers );
		$this->assertContains( 'Vary: Origin', $headers );
	}

	/**
	 * The response header is matched without regard to case, as header names are
	 * case-insensitive.
	 *
	 * @ticket 46992
	 *
	 * @requires function xdebug_get_headers
	 */
	public function test_response_value_is_matched_case_insensitively() {
		add_filter(
			'rest_post_dispatch',
			static function ( $response ) {
				$response->header( 'access-control-allow-methods', 'OPTIONS, GET' );
				return $response;
			}
		);

		$headers = $this->serve_and_get_headers();

		$this->assertNotContains( self::DEFAULT_ALLOW_METHODS, $headers );
	}

	/**
	 * A callback added to 'rest_pre_serve_request' after rest_send_cors_headers()
	 * can still send its own method list.
	 *
	 * This has always worked and continues to work.
	 *
	 * @ticket 46992
	 *
	 * @requires function xdebug_get_headers
	 */
	public function test_late_rest_pre_serve_request_callback_can_send_its_own_list() {
		add_filter(
			'rest_pre_serve_request',
			static function ( $served ) {
				header( 'Access-Control-Allow-Methods: OPTIONS, GET' );
				return $served;
			},
			11
		);

		$headers = $this->serve_and_get_headers();

		$this->assertContains( 'Access-Control-Allow-Methods: OPTIONS, GET', $headers );
	}

	/**
	 * A response that sets no CORS header receives the same headers as before.
	 *
	 * @ticket 46992
	 *
	 * @requires function xdebug_get_headers
	 */
	public function test_default_cors_output_is_unchanged() {
		$headers = $this->serve_and_get_headers();

		$this->assertContains( 'Access-Control-Allow-Origin: http://example.org', $headers );
		$this->assertContains( self::DEFAULT_ALLOW_METHODS, $headers );
		$this->assertContains( 'Access-Control-Allow-Credentials: true', $headers );
		$this->assertContains( 'Vary: Origin', $headers );
	}

	/**
	 * No CORS headers are sent when the request has no Origin.
	 *
	 * Limited to the three headers rest_send_cors_headers() sends. WP_REST_Server
	 * sends Access-Control-Expose-Headers from send_headers() on every request,
	 * with or without an Origin.
	 *
	 * @ticket 46992
	 *
	 * @requires function xdebug_get_headers
	 */
	public function test_no_cors_headers_are_sent_without_an_origin() {
		unset( $_SERVER['HTTP_ORIGIN'] );

		$headers = $this->serve_and_get_headers();

		foreach ( array( 'Access-Control-Allow-Origin', 'Access-Control-Allow-Methods', 'Access-Control-Allow-Credentials' ) as $name ) {
			foreach ( $headers as $header ) {
				$this->assertStringStartsNotWith( $name . ':', $header );
			}
		}
	}

	/**
	 * Access-Control-Allow-Origin and Access-Control-Allow-Credentials are not
	 * settable from the response.
	 *
	 * Those two headers determine who may read an authenticated response, so they
	 * remain under the control of rest_send_cors_headers() alone.
	 *
	 * @ticket 46992
	 *
	 * @requires function xdebug_get_headers
	 */
	public function test_origin_and_credentials_headers_are_not_response_settable() {
		add_filter(
			'rest_post_dispatch',
			static function ( $response ) {
				$response->header( 'Access-Control-Allow-Origin', 'http://plugin.example' );
				$response->header( 'Access-Control-Allow-Credentials', 'false' );
				return $response;
			}
		);

		$headers = $this->serve_and_get_headers();

		$this->assertContains( 'Access-Control-Allow-Origin: http://example.org', $headers );
		$this->assertContains( 'Access-Control-Allow-Credentials: true', $headers );
	}
}
