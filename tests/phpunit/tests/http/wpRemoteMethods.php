<?php

/**
 * Tests for wp_remote_put(), wp_remote_delete(), wp_remote_patch(),
 * and their wp_safe_remote_* counterparts.
 *
 * @ticket 40142
 *
 * @group http
 */
class Tests_HTTP_wp_remote_methods extends WP_UnitTestCase {

	/**
	 * Stores the HTTP request args captured by the filter.
	 *
	 * @var array
	 */
	private $http_args = array();

	/**
	 * Intercepts the HTTP request and captures its args without making a real network call.
	 *
	 * @param false|array|WP_Error $preempt Whether to preempt the request.
	 * @param array                $args    HTTP request arguments.
	 * @return WP_Error Short-circuits the request.
	 */
	public function http_catcher( $preempt, $args ) {
		$this->http_args = $args;
		return new WP_Error( 'test_short_circuit', 'Request short-circuited for testing.' );
	}

	/**
	 * Helper to run a wp_remote_* function with the request intercepted.
	 *
	 * @param string $function_name The function to call, e.g. 'wp_remote_put'.
	 * @param string $url           URL to pass.
	 * @param array  $args          Additional args to pass.
	 */
	private function call_remote_function( $function_name, $url = 'http://example.com/', $args = array() ) {
		$this->http_args = array();

		add_filter( 'pre_http_request', array( $this, 'http_catcher' ), 10, 2 );
		call_user_func( $function_name, $url, $args );
		remove_filter( 'pre_http_request', array( $this, 'http_catcher' ), 10, 2 );
	}

	/**
	 * Data provider yielding unsafe wp_remote_* function names and their expected HTTP methods.
	 *
	 * @return array[]
	 */
	public function data_remote_methods() {
		return array(
			'PUT'    => array( 'wp_remote_put', 'PUT' ),
			'DELETE' => array( 'wp_remote_delete', 'DELETE' ),
			'PATCH'  => array( 'wp_remote_patch', 'PATCH' ),
		);
	}

	/**
	 * Data provider yielding safe wp_safe_remote_* function names and their expected HTTP methods.
	 *
	 * @return array[]
	 */
	public function data_safe_remote_methods() {
		return array(
			'PUT'    => array( 'wp_safe_remote_put', 'PUT' ),
			'DELETE' => array( 'wp_safe_remote_delete', 'DELETE' ),
			'PATCH'  => array( 'wp_safe_remote_patch', 'PATCH' ),
		);
	}

	/**
	 * Data provider yielding only unsafe function names (no expected method needed).
	 *
	 * @return array[]
	 */
	public function data_remote_function_names() {
		return array(
			'wp_remote_put'    => array( 'wp_remote_put' ),
			'wp_remote_delete' => array( 'wp_remote_delete' ),
			'wp_remote_patch'  => array( 'wp_remote_patch' ),
		);
	}

	/**
	 * Data provider yielding only safe function names (no expected method needed).
	 *
	 * @return array[]
	 */
	public function data_safe_remote_function_names() {
		return array(
			'wp_safe_remote_put'    => array( 'wp_safe_remote_put' ),
			'wp_safe_remote_delete' => array( 'wp_safe_remote_delete' ),
			'wp_safe_remote_patch'  => array( 'wp_safe_remote_patch' ),
		);
	}

	/**
	 * Tests that each wp_remote_* wrapper sets the correct HTTP method.
	 *
	 * @ticket 40142
	 *
	 * @dataProvider data_remote_methods
	 *
	 * @covers ::wp_remote_put
	 * @covers ::wp_remote_delete
	 * @covers ::wp_remote_patch
	 *
	 * @param string $function_name  The function name to call.
	 * @param string $expected_method The expected HTTP method string.
	 */
	public function test_remote_methods_use_correct_http_method( $function_name, $expected_method ) {
		$this->call_remote_function( $function_name );

		$this->assertNotEmpty(
			$this->http_args,
			"$function_name() did not trigger a request."
		);

		$this->assertSame(
			$expected_method,
			$this->http_args['method'],
			"$function_name() did not set the expected HTTP method '$expected_method'."
		);
	}

	/**
	 * Tests that each wp_safe_remote_* wrapper sets the correct HTTP method.
	 *
	 * @ticket 40142
	 *
	 * @dataProvider data_safe_remote_methods
	 *
	 * @covers ::wp_safe_remote_put
	 * @covers ::wp_safe_remote_delete
	 * @covers ::wp_safe_remote_patch
	 *
	 * @param string $function_name  The function name to call.
	 * @param string $expected_method The expected HTTP method string.
	 */
	public function test_safe_remote_methods_use_correct_http_method( $function_name, $expected_method ) {
		$this->call_remote_function( $function_name );

		$this->assertNotEmpty(
			$this->http_args,
			"$function_name() did not trigger a request."
		);

		$this->assertSame(
			$expected_method,
			$this->http_args['method'],
			"$function_name() did not set the expected HTTP method '$expected_method'."
		);
	}

	/**
	 * Tests that wp_safe_remote_* wrappers set the reject_unsafe_urls flag.
	 *
	 * @ticket 40142
	 *
	 * @dataProvider data_safe_remote_function_names
	 *
	 * @covers ::wp_safe_remote_put
	 * @covers ::wp_safe_remote_delete
	 * @covers ::wp_safe_remote_patch
	 *
	 * @param string $function_name The function name to call.
	 */
	public function test_safe_remote_methods_reject_unsafe_urls( $function_name ) {
		$this->call_remote_function( $function_name );

		$this->assertNotEmpty(
			$this->http_args,
			"$function_name() did not trigger a request."
		);

		$this->assertTrue(
			$this->http_args['reject_unsafe_urls'],
			"$function_name() did not set 'reject_unsafe_urls' to true."
		);
	}

	/**
	 * Tests that the unsafe wp_remote_* wrappers do NOT set reject_unsafe_urls.
	 *
	 * @ticket 40142
	 *
	 * @dataProvider data_remote_function_names
	 *
	 * @covers ::wp_remote_put
	 * @covers ::wp_remote_delete
	 * @covers ::wp_remote_patch
	 *
	 * @param string $function_name The function name to call.
	 */
	public function test_remote_methods_do_not_reject_unsafe_urls( $function_name ) {
		$this->call_remote_function( $function_name );

		$this->assertNotEmpty(
			$this->http_args,
			"$function_name() did not trigger a request."
		);

		$this->assertEmpty(
			$this->http_args['reject_unsafe_urls'] ?? '',
			"$function_name() should NOT set 'reject_unsafe_urls', but it did."
		);
	}

	/**
	 * Tests that caller-supplied args are passed through and merged correctly
	 * for the unsafe wp_remote_* wrappers.
	 *
	 * @ticket 40142
	 *
	 * @dataProvider data_remote_methods
	 *
	 * @covers ::wp_remote_put
	 * @covers ::wp_remote_delete
	 * @covers ::wp_remote_patch
	 *
	 * @param string $function_name  The function name to call.
	 * @param string $expected_method The expected HTTP method string.
	 */
	public function test_remote_methods_pass_through_args( $function_name, $expected_method ) {
		$this->call_remote_function(
			$function_name,
			'http://example.com/',
			array(
				'timeout' => 42,
				'headers' => array( 'X-Custom-Header' => 'test-value' ),
			)
		);

		$this->assertSame(
			$expected_method,
			$this->http_args['method'],
			"$function_name() did not set the expected HTTP method '$expected_method'."
		);

		$this->assertSame(
			42,
			$this->http_args['timeout'],
			"$function_name() did not pass through the 'timeout' argument."
		);
	}

	/**
	 * Tests that caller-supplied args are passed through and merged correctly
	 * for the safe wp_safe_remote_* wrappers.
	 *
	 * @ticket 40142
	 *
	 * @dataProvider data_safe_remote_methods
	 *
	 * @covers ::wp_safe_remote_put
	 * @covers ::wp_safe_remote_delete
	 * @covers ::wp_safe_remote_patch
	 *
	 * @param string $function_name  The function name to call.
	 * @param string $expected_method The expected HTTP method string.
	 */
	public function test_safe_remote_methods_pass_through_args( $function_name, $expected_method ) {
		$this->call_remote_function(
			$function_name,
			'http://example.com/',
			array(
				'timeout' => 99,
				'headers' => array( 'X-Safe-Header' => 'safe-value' ),
			)
		);

		$this->assertSame(
			$expected_method,
			$this->http_args['method'],
			"$function_name() did not set the expected HTTP method '$expected_method'."
		);

		$this->assertSame(
			99,
			$this->http_args['timeout'],
			"$function_name() did not pass through the 'timeout' argument."
		);

		$this->assertTrue(
			$this->http_args['reject_unsafe_urls'],
			"$function_name() did not set 'reject_unsafe_urls' to true."
		);
	}

	/**
	 * Tests that a caller-supplied 'method' argument overrides the function's default HTTP method.
	 *
	 * wp_parse_args() gives caller-supplied values priority over defaults, so this is
	 * intentional and consistent with the behaviour of wp_remote_get(), wp_remote_post(), etc.
	 *
	 * @ticket 40142
	 *
	 * @dataProvider data_remote_function_names
	 *
	 * @covers ::wp_remote_put
	 * @covers ::wp_remote_delete
	 * @covers ::wp_remote_patch
	 *
	 * @param string $function_name The function name to call.
	 */
	public function test_remote_methods_caller_can_override_method( $function_name ) {
		$this->call_remote_function(
			$function_name,
			'http://example.com/',
			array( 'method' => 'GET' )
		);

		$this->assertSame(
			'GET',
			$this->http_args['method'],
			"$function_name() should allow callers to override the HTTP method via \$args, but it did not."
		);
	}
}
