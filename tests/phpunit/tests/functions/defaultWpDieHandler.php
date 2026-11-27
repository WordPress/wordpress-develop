<?php

/**
 * Tests for the _default_wp_die_handler() function.
 *
 * These tests run in separate processes so that no output has been sent
 * before `wp_die()` is called, allowing the HTTP response code to be set
 * and inspected.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * @group functions
 *
 * @covers ::_default_wp_die_handler
 */
class Tests_Functions_DefaultWpDieHandler extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// Override the test suite's wp_die handler, which throws an exception, with the default handler.
		add_filter( 'wp_die_handler', array( $this, 'get_default_wp_die_handler' ), 20 );
	}

	/**
	 * Returns the name of the default wp_die() handler.
	 *
	 * @return string
	 */
	public function get_default_wp_die_handler() {
		return '_default_wp_die_handler';
	}

	/**
	 * Simulates the admin header having been output, without running the callbacks hooked to it.
	 *
	 * In this state, _default_wp_die_handler() does not output its own HTML head.
	 */
	private function mark_admin_head_as_done() {
		global $wp_actions;

		$wp_actions['admin_head'] = 1;
	}

	/**
	 * Calls wp_die() without exiting and returns the resulting HTTP response code.
	 *
	 * @param string|WP_Error $message Error message or WP_Error object.
	 * @param array           $args    Arguments to pass to wp_die().
	 * @return int|false The HTTP response code, or false if none was set.
	 */
	private function get_response_code_from_wp_die( $message, $args = array() ) {
		$args['exit'] = false;

		ob_start();
		wp_die( $message, '', $args );
		ob_end_clean();

		return http_response_code();
	}

	/**
	 * Displays the default critical error template of the fatal error handler and returns the resulting HTTP response code.
	 *
	 * @return int|false The HTTP response code, or false if none was set.
	 */
	private function get_response_code_from_fatal_error_template() {
		$error = array(
			'type'    => E_ERROR,
			'message' => 'Fatal error',
			'file'    => __FILE__,
			'line'    => __LINE__,
		);

		$method = new ReflectionMethod( 'WP_Fatal_Error_Handler', 'display_default_error_template' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		ob_start();
		$method->invoke( new WP_Fatal_Error_Handler(), $error, false );
		ob_end_clean();

		return http_response_code();
	}

	/**
	 * @ticket 64256
	 */
	public function test_should_send_500_response_code_by_default() {
		$this->assertSame( 500, $this->get_response_code_from_wp_die( 'Error message' ) );
	}

	/**
	 * @ticket 64256
	 */
	public function test_should_send_custom_response_code() {
		$this->assertSame( 404, $this->get_response_code_from_wp_die( 'Error message', array( 'response' => 404 ) ) );
	}

	/**
	 * Tests that the response code passed by the caller is not overridden for internal server errors.
	 *
	 * @ticket 64256
	 */
	public function test_should_send_custom_response_code_for_internal_server_error_code() {
		$response_code = $this->get_response_code_from_wp_die(
			'Error message',
			array(
				'code'     => 'internal_server_error',
				'response' => 503,
			)
		);

		$this->assertSame( 503, $response_code );
	}

	/**
	 * Tests that the status header is only sent once for a critical error before the admin header was output.
	 *
	 * @ticket 64256
	 */
	public function test_should_apply_status_header_filter_once_for_internal_server_error() {
		$filter = new MockAction();
		add_filter( 'status_header', array( $filter, 'filter' ) );

		$this->get_response_code_from_wp_die( 'Error message', array( 'code' => 'internal_server_error' ) );

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * Tests that a critical error without a response sends the default 500 response code after the admin header was output.
	 *
	 * @ticket 64256
	 */
	public function test_should_send_500_response_code_for_internal_server_error_by_default_after_admin_head() {
		$this->mark_admin_head_as_done();

		$this->assertSame( 500, $this->get_response_code_from_wp_die( 'Error message', array( 'code' => 'internal_server_error' ) ) );
	}

	/**
	 * Tests that a critical error WP_Error without a status sends the default 500 response code after the admin header was output.
	 *
	 * @ticket 64256
	 */
	public function test_should_send_500_response_code_for_internal_server_error_wp_error_after_admin_head() {
		$this->mark_admin_head_as_done();

		$error = new WP_Error( 'internal_server_error', 'Error message' );

		$this->assertSame( 500, $this->get_response_code_from_wp_die( $error ) );
	}

	/**
	 * Tests that the response code passed with a critical error is respected after the admin header was output.
	 *
	 * @ticket 64256
	 */
	public function test_should_send_custom_response_code_for_internal_server_error_after_admin_head() {
		$this->mark_admin_head_as_done();

		$error = new WP_Error( 'internal_server_error', 'Error message', array( 'status' => 503 ) );

		$this->assertSame( 503, $this->get_response_code_from_wp_die( $error ) );
	}

	/**
	 * Tests that non-critical errors still do not send a response code after the admin header was output.
	 *
	 * @ticket 64256
	 */
	public function test_should_not_send_response_code_for_non_critical_error_after_admin_head() {
		$this->mark_admin_head_as_done();

		$this->assertFalse( $this->get_response_code_from_wp_die( 'Error message' ) );
	}

	/**
	 * Tests that non-critical errors with a response still do not send a response code after the admin header was output.
	 *
	 * @ticket 64256
	 */
	public function test_should_not_send_custom_response_code_for_non_critical_error_after_admin_head() {
		$this->mark_admin_head_as_done();

		$this->assertFalse( $this->get_response_code_from_wp_die( 'Error message', array( 'response' => 404 ) ) );
	}

	/**
	 * Tests that the status_header filter is applied to the response code of a critical error after the admin header was output.
	 *
	 * @ticket 64256
	 */
	public function test_should_apply_status_header_filter_for_internal_server_error_after_admin_head() {
		$this->mark_admin_head_as_done();

		$filter = new MockAction();
		add_filter( 'status_header', array( $filter, 'filter' ), 10, 2 );

		$this->get_response_code_from_wp_die(
			'Error message',
			array(
				'code'     => 'internal_server_error',
				'response' => 503,
			)
		);

		$this->assertSame( 1, $filter->get_call_count(), 'The status_header filter should be applied once.' );
		$this->assertSame( 503, $filter->get_args()[0][1], 'The status_header filter should receive the response code.' );
	}

	/**
	 * Tests the default critical error template of the fatal error handler, after the admin header was output.
	 *
	 * @ticket 64256
	 *
	 * @covers WP_Fatal_Error_Handler::display_default_error_template
	 */
	public function test_fatal_error_handler_default_template_should_send_500_response_code_after_admin_head() {
		$this->mark_admin_head_as_done();

		$this->assertSame( 500, $this->get_response_code_from_fatal_error_template() );
	}

	/**
	 * Tests that the response code set via the wp_php_error_args filter is respected.
	 *
	 * @ticket 64256
	 *
	 * @covers WP_Fatal_Error_Handler::display_default_error_template
	 */
	public function test_fatal_error_handler_default_template_should_respect_wp_php_error_args_response_after_admin_head() {
		$this->mark_admin_head_as_done();

		add_filter(
			'wp_php_error_args',
			static function ( $args ) {
				$args['response'] = 503;
				return $args;
			}
		);

		$this->assertSame( 503, $this->get_response_code_from_fatal_error_template() );
	}
}
