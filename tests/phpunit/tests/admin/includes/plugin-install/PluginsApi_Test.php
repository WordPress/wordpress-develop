<?php

/**
 * Tests for plugins_api().
 *
 * @group plugins
 * @group admin
 *
 * @covers ::plugins_api
 */
class Admin_Includes_Plugin_Install_PluginsApi_Test extends WP_UnitTestCase {

	/**
	 * The arguments that the filters of the current test received.
	 *
	 * @var object|null
	 */
	private $filtered_args = null;

	/**
	 * HTTP requests made by the current test, as arrays of the URL and the arguments.
	 *
	 * @var array[]
	 */
	private $requests = array();

	/**
	 * Errors that plugins_api() triggered during the current test.
	 *
	 * @var array[]
	 */
	private $triggered_errors = array();

	/**
	 * Loads the tested file.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	}

	/**
	 * Resets the records of the test.
	 */
	public function set_up() {
		parent::set_up();

		$this->filtered_args    = null;
		$this->requests         = array();
		$this->triggered_errors = array();

		add_filter( 'wp_trigger_error_trigger_error', '__return_false' );
		add_action( 'wp_trigger_error_always_run', array( $this, 'record_triggered_error' ), 10, 3 );
	}

	/**
	 * Records an error that was triggered.
	 *
	 * @param string $function_name The function that triggered the error.
	 * @param string $message       The message of the error.
	 * @param int    $error_level   The level of the error.
	 */
	public function record_triggered_error( $function_name, $message, $error_level ) {
		$this->triggered_errors[] = array( $function_name, $message, $error_level );
	}

	/**
	 * Makes the requests to WordPress.org return a response, and records them.
	 *
	 * @param array|WP_Error $response The response to return, or an error.
	 */
	private function mock_response( $response ) {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $response ) {
				$this->requests[] = array( $url, $args );

				return $response;
			},
			10,
			3
		);
	}

	/**
	 * Returns a successful HTTP response.
	 *
	 * @param string $body The body of the response.
	 * @return array The response.
	 */
	private function response( $body ) {
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
	}

	/**
	 * Returns the arguments of the request that plugins_api() makes.
	 *
	 * @param array $query The query arguments of the request URL.
	 * @return array The `request` arguments.
	 */
	private function get_request_args( array $query ) {
		return $query['request'];
	}

	/**
	 * Tests that the filter can answer the request without a request to WordPress.org.
	 *
	 * @ticket 66553
	 */
	public function test_should_return_the_result_of_the_plugins_api_filter_without_a_request() {
		$result = (object) array( 'name' => 'Filtered plugin' );
		$this->mock_response( new WP_Error( 'unexpected', 'A request should not be made.' ) );
		add_filter(
			'plugins_api',
			static function () use ( $result ) {
				return $result;
			}
		);

		$this->assertSame( $result, plugins_api( 'plugin_information', array( 'slug' => 'my-plugin' ) ) );
		$this->assertTrue( $result->external, 'The result of the filter should be marked as external.' );
		$this->assertSame( array(), $this->requests, 'No request should be made.' );
	}

	/**
	 * Tests that an error that the filter returns is not marked as external.
	 *
	 * @ticket 66553
	 */
	public function test_should_return_the_error_of_the_plugins_api_filter_as_it_is() {
		$error = new WP_Error( 'filtered_error', 'Filtered error.' );
		add_filter(
			'plugins_api',
			static function () use ( $error ) {
				return $error;
			}
		);

		$result = plugins_api( 'plugin_information', array( 'slug' => 'my-plugin' ) );

		$this->assertSame( $error, $result );
		$this->assertObjectNotHasProperty( 'external', $result );
	}

	/**
	 * Tests that the filter receives the action and the arguments as an object with the defaults.
	 *
	 * @ticket 66553
	 */
	public function test_should_pass_the_arguments_as_an_object_with_the_defaults_to_the_filters() {
		$actions = array();
		add_filter(
			'plugins_api',
			function ( $result, $action, $args ) use ( &$actions ) {
				$actions[]           = $action;
				$this->filtered_args = $args;

				return (object) array();
			},
			10,
			3
		);

		plugins_api( 'plugin_information', array( 'slug' => 'my-plugin' ) );

		$this->assertSame( array( 'plugin_information' ), $actions );
		$this->assertIsObject( $this->filtered_args, 'The arguments should be an object.' );
		$this->assertSame( 'my-plugin', $this->filtered_args->slug );
		$this->assertSame( get_user_locale(), $this->filtered_args->locale, 'The locale of the user should be the default.' );
		$this->assertSame( substr( wp_get_wp_version(), 0, 3 ), $this->filtered_args->wp_version, 'The major version should be the default.' );
		$this->assertObjectNotHasProperty( 'per_page', $this->filtered_args, 'Only queries get a number of plugins per page.' );
	}

	/**
	 * Tests that the arguments of an object are kept.
	 *
	 * @ticket 66553
	 */
	public function test_should_keep_the_locale_and_version_of_the_arguments() {
		add_filter(
			'plugins_api',
			function ( $result, $action, $args ) {
				$this->filtered_args = $args;

				return (object) array();
			},
			10,
			3
		);

		plugins_api(
			'plugin_information',
			(object) array(
				'slug'       => 'my-plugin',
				'locale'     => 'de_DE',
				'wp_version' => '5.0',
			)
		);

		$this->assertSame( 'de_DE', $this->filtered_args->locale );
		$this->assertSame( '5.0', $this->filtered_args->wp_version );
	}

	/**
	 * Tests the number of plugins per page of a query.
	 *
	 * @ticket 66553
	 *
	 * @dataProvider data_per_page
	 *
	 * @param array    $args     The arguments.
	 * @param int|null $expected The expected number of plugins per page.
	 */
	public function test_should_set_the_number_of_plugins_per_page_of_a_query( array $args, $expected ) {
		add_filter(
			'plugins_api',
			function ( $result, $action, $args ) {
				$this->filtered_args = $args;

				return (object) array();
			},
			10,
			3
		);

		plugins_api( 'query_plugins', $args );

		$this->assertSame( $expected, $this->filtered_args->per_page ?? null );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_per_page() {
		return array(
			'default'  => array( array( 'search' => 'seo' ), 24 ),
			'a number' => array(
				array(
					'search'   => 'seo',
					'per_page' => 5,
				),
				5,
			),
		);
	}

	/**
	 * Tests that the arguments can be filtered.
	 *
	 * @ticket 66553
	 */
	public function test_should_use_the_arguments_of_the_plugins_api_args_filter() {
		add_filter(
			'plugins_api_args',
			static function ( $args, $action ) {
				$args->slug = 'filtered-' . $action;

				return $args;
			},
			10,
			2
		);
		add_filter(
			'plugins_api',
			function ( $result, $action, $args ) {
				$this->filtered_args = $args;

				return (object) array();
			},
			10,
			3
		);

		plugins_api( 'plugin_information', array( 'slug' => 'my-plugin' ) );

		$this->assertSame( 'filtered-plugin_information', $this->filtered_args->slug );
	}

	/**
	 * Tests the request to WordPress.org.
	 *
	 * @ticket 66553
	 */
	public function test_should_request_the_plugin_information_from_wordpress_org() {
		$this->mock_response( $this->response( '{"name":"Remote plugin","slug":"remote-plugin"}' ) );

		$result = plugins_api( 'plugin_information', array( 'slug' => 'remote-plugin' ) );

		$this->assertCount( 1, $this->requests, 'One request should be made.' );

		list( $url, $args ) = $this->requests[0];
		$parts              = wp_parse_url( $url );
		parse_str( $parts['query'], $query );

		$this->assertSame( '/plugins/info/1.2/', $parts['path'] );
		$this->assertSame( 'api.wordpress.org', $parts['host'] );
		$this->assertSame( 'plugin_information', $query['action'] );
		$this->assertSame( 'remote-plugin', $this->get_request_args( $query )['slug'] );
		$this->assertSame( 15, $args['timeout'], 'The timeout should be 15 seconds.' );
		$this->assertSame( 'WordPress/' . wp_get_wp_version() . '; ' . home_url( '/' ), $args['user-agent'] );
		$this->assertIsObject( $result, 'The response should be an object.' );
		$this->assertSame( 'Remote plugin', $result->name );
		$this->assertSame( 'remote-plugin', $result->slug );
		$this->assertObjectNotHasProperty( 'external', $result, 'The result of a request is not external.' );
	}

	/**
	 * Tests the error for a response that is not JSON.
	 *
	 * @ticket 66553
	 */
	public function test_should_return_an_error_for_a_response_that_is_not_json() {
		$this->mock_response( $this->response( 'Bad gateway' ) );

		$result = plugins_api( 'plugin_information', array( 'slug' => 'remote-plugin' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'plugins_api_failed', $result->get_error_code() );
		$this->assertStringContainsString( 'An unexpected error occurred.', $result->get_error_message() );
		$this->assertSame( 'Bad gateway', $result->get_error_data(), 'The body of the response should be the data of the error.' );
	}

	/**
	 * Tests the error that WordPress.org returns.
	 *
	 * @ticket 66553
	 */
	public function test_should_return_the_error_of_the_response() {
		$this->mock_response( $this->response( '{"error":"Plugin not found."}' ) );

		$result = plugins_api( 'plugin_information', array( 'slug' => 'missing-plugin' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'plugins_api_failed', $result->get_error_code() );
		$this->assertSame( 'Plugin not found.', $result->get_error_message() );
	}

	/**
	 * Tests that a response that is a JSON value other than an array is returned as it is.
	 *
	 * @ticket 66553
	 */
	public function test_should_return_a_response_that_is_not_an_array_as_it_is() {
		$this->mock_response( $this->response( '"text"' ) );

		$this->assertSame( 'text', plugins_api( 'plugin_information', array( 'slug' => 'remote-plugin' ) ) );
	}

	/**
	 * Tests that a failed request is made again without SSL, and that the failure is reported.
	 *
	 * @ticket 66553
	 */
	public function test_should_try_the_request_again_without_ssl_when_it_fails() {
		if ( ! wp_http_supports( array( 'ssl' ) ) ) {
			$this->markTestSkipped( 'SSL is not supported.' );
		}

		$this->mock_response( new WP_Error( 'http_request_failed', 'cURL error 60.' ) );

		$result = plugins_api( 'plugin_information', array( 'slug' => 'remote-plugin' ) );

		$this->assertCount( 2, $this->requests, 'The request should be made twice.' );
		$this->assertStringStartsWith( 'https://', $this->requests[0][0], 'The first request should use SSL.' );
		$this->assertStringStartsWith( 'http://', $this->requests[1][0], 'The second request should not use SSL.' );
		$this->assertCount( 1, $this->triggered_errors, 'The failure of the secure request should be reported.' );
		$this->assertSame( 'plugins_api', $this->triggered_errors[0][0] );
		$this->assertSame( E_USER_WARNING, $this->triggered_errors[0][2], 'The failure should be a warning when debugging.' );
		$this->assertStringContainsString( 'could not establish a secure connection', $this->triggered_errors[0][1] );
		$this->assertWPError( $result );
		$this->assertSame( 'plugins_api_failed', $result->get_error_code() );
		$this->assertSame( 'cURL error 60.', $result->get_error_data(), 'The message of the request error should be the data of the error.' );
	}

	/**
	 * Tests that the failure of the secure request is not reported in a JSON request.
	 *
	 * @ticket 66553
	 */
	public function test_should_not_report_the_failure_of_the_secure_request_in_a_json_request() {
		if ( ! wp_http_supports( array( 'ssl' ) ) ) {
			$this->markTestSkipped( 'SSL is not supported.' );
		}

		$accept = $_SERVER['HTTP_ACCEPT'] ?? null;

		$_SERVER['HTTP_ACCEPT'] = 'application/json';
		$this->mock_response( new WP_Error( 'http_request_failed', 'cURL error 60.' ) );

		try {
			plugins_api( 'plugin_information', array( 'slug' => 'remote-plugin' ) );
		} finally {
			if ( null === $accept ) {
				unset( $_SERVER['HTTP_ACCEPT'] );
			} else {
				$_SERVER['HTTP_ACCEPT'] = $accept;
			}
		}

		$this->assertCount( 2, $this->requests, 'The request should be made twice.' );
		$this->assertSame( array(), $this->triggered_errors, 'Nothing should be reported.' );
	}

	/**
	 * Tests the filter that can change the result of a request.
	 *
	 * @ticket 66553
	 */
	public function test_should_pass_the_result_to_the_plugins_api_result_filter() {
		$this->mock_response( $this->response( '{"name":"Remote plugin"}' ) );

		$received = null;
		add_filter(
			'plugins_api_result',
			static function ( $res, $action, $args ) use ( &$received ) {
				$received = array( $res, $action, $args );

				return 'changed';
			},
			10,
			3
		);

		$result = plugins_api( 'plugin_information', array( 'slug' => 'remote-plugin' ) );

		$this->assertSame( 'changed', $result );
		$this->assertSame( 'Remote plugin', $received[0]->name );
		$this->assertSame( 'plugin_information', $received[1] );
		$this->assertSame( 'remote-plugin', $received[2]->slug );
	}
}
