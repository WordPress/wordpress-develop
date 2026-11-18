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
	 * In this state, _default_wp_die_handler() does not output its own HTML head or call status_header().
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
	 * @ticket 64256
	 */
	public function test_should_send_500_response_code_by_default() {
		$this->assertSame( 500, $this->get_response_code_from_wp_die( 'Error message' ) );
	}

	/**
	 * @ticket 64256
	 */
	public function test_should_send_500_response_code_when_response_is_500() {
		$this->assertSame( 500, $this->get_response_code_from_wp_die( 'Error message', array( 'response' => 500 ) ) );
	}

	/**
	 * @ticket 64256
	 */
	public function test_should_send_500_response_code_for_internal_server_error_code() {
		$response_code = $this->get_response_code_from_wp_die(
			'Error message',
			array(
				'code'     => 'internal_server_error',
				'response' => 403,
			)
		);

		$this->assertSame( 500, $response_code );
	}

	/**
	 * @ticket 64256
	 */
	public function test_should_send_500_response_code_for_internal_server_error_wp_error() {
		$error = new WP_Error( 'internal_server_error', 'Error message', array( 'status' => 403 ) );

		$this->assertSame( 500, $this->get_response_code_from_wp_die( $error ) );
	}

	/**
	 * Tests that a critical error sends a 500 response code even after the admin header was output.
	 *
	 * @ticket 64256
	 */
	public function test_should_send_500_response_code_for_critical_error_after_admin_head() {
		$this->mark_admin_head_as_done();

		$error = new WP_Error( 'internal_server_error', 'There has been a critical error on this website.' );

		$this->assertSame( 500, $this->get_response_code_from_wp_die( $error, array( 'response' => 500 ) ) );
	}

	/**
	 * Tests that non-critical errors still do not send a response code after the admin header was output.
	 *
	 * @ticket 64256
	 */
	public function test_should_not_send_response_code_for_non_critical_error_after_admin_head() {
		$this->mark_admin_head_as_done();

		$response_code = $this->get_response_code_from_wp_die(
			'Error message',
			array(
				'code'     => 'not_found',
				'response' => 404,
			)
		);

		$this->assertFalse( $response_code );
	}

	/**
	 * Tests the default critical error template of the fatal error handler, after the admin header was output.
	 *
	 * @ticket 64256
	 *
	 * @covers WP_Fatal_Error_Handler::display_default_error_template
	 */
	public function test_fatal_error_handler_default_template_should_send_500_response_code() {
		$this->mark_admin_head_as_done();

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

		$this->assertSame( 500, http_response_code() );
	}

	/**
	 * @ticket 64256
	 */
	public function test_should_send_custom_response_code_for_non_critical_errors() {
		$response_code = $this->get_response_code_from_wp_die(
			'Error message',
			array(
				'code'     => 'not_found',
				'response' => 404,
			)
		);

		$this->assertSame( 404, $response_code );
	}
}
