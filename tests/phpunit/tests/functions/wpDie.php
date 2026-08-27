<?php

/**
 * Tests the wp_die() function.
 *
 * @group functions
 *
 * @covers ::wp_die
 */
class Tests_Functions_WpDie extends WP_UnitTestCase {

	/**
	 * Tests that wp_die() calls the expected handler.
	 *
	 * @ticket 65655
	 */
	public function test_wp_die_handler_selection() {
		$filter = new MockAction();
		add_filter( 'wp_die_handler', array( $filter, 'filter' ) );

		try {
			wp_die( 'Message', 'Title', array( 'exit' => false ) );
		} catch ( WPDieException $e ) {
			// The default test handler throws this.
		}

		$this->assertSame( 1, $filter->get_call_count(), 'The wp_die_handler filter should have been called once.' );
	}

	/**
	 * Tests that wp_die() respects the Ajax handler filter.
	 *
	 * @ticket 65655
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_die_ajax_handler_selection() {
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		$filter = new MockAction();
		add_filter( 'wp_die_ajax_handler', array( $filter, 'filter' ) );

		ob_start();
		try {
			wp_die( 'Ajax Message', 'Ajax Title', array( 'exit' => false ) );
		} catch ( WPDieException $e ) {
		}
		ob_end_clean();

		$this->assertSame( 1, $filter->get_call_count(), 'The wp_die_ajax_handler filter should have been called once.' );
	}

	/**
	 * Tests that wp_die() handles WP_Error objects.
	 *
	 * @ticket 65655
	 */
	public function test_wp_die_with_wp_error() {
		$error = new WP_Error( 'test_error', 'Test Error Message' );

		// Use a simple variable to capture the first argument.
		$captured_message = null;
		add_filter(
			'wp_die_handler',
			function ( $callback ) use ( &$captured_message ) {
				return function ( $message ) use ( &$captured_message ) {
					$captured_message = $message;
					throw new WPDieException( 'Intercepted' );
				};
			}
		);

		try {
			wp_die( $error, '', array( 'exit' => false ) );
		} catch ( WPDieException $e ) {
		}

		$this->assertSame( $error, $captured_message, 'The error object should be passed as the first argument to the handler.' );
	}
}
