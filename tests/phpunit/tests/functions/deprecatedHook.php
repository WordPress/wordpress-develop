<?php

/**
 * Tests for the __deprecated_hook function.
 *
 * @group Functions
 *
 * @covers ::__deprecated_hook
 */
class Tests_Functions_deprecatedHook extends WP_UnitTestCase {

	/**
	 * Sets up the test case.
	 *
	 * This method is responsible for setting up the test case before each test method is executed.
	 * It removes certain actions related to deprecated and _deprecated_hook functions.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// remove ethe spherical handling for _deprecated_hook in the PHPUnint setup so we can test it
		remove_action( 'deprecated_hook_run', array( $this, 'deprecated_function_run' ), 10, 4 );
		remove_action( 'deprecated_hook_trigger_error', '__return_false' );
	}


	/**
	 * @ticket 60110
	 *
	 * test___deprecated_hook_action_called() method tests the action being called when _deprecated_hook()
	 *
	 * It creates a MockAction object and registers it as a filter for the '_deprecated_hook_run' action.
	 * It then calls __deprecated_hook() function with the given 'function_name', 'message', and 1 as arguments.
	 * Finally, it asserts that the call count of the filter object is equal to 1.
	 *
	 * @return void
	 */
	public function test_deprecated_hook_action_called() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Hook hook_name is deprecated since version 1! Use replacement_hook instead. message' );

		$action = new MockAction();
		add_filter( 'deprecated_hook_run', array( $action, 'action' ) );

		_deprecated_hook( 'hook_name', 1, 'replacement_hook', 'message' );

		$this->assertSame( 1, $action->get_call_count() );
	}

	/**
	 * @ticket 60110
	 *
	 * This method tests if the '_deprecated_hook_trigger_error' filter is called
	 * when the __deprecated_hook() function is invoked.
	 *
	 * It creates a mock action object and adds a filter to the '_deprecated_hook_trigger_error'
	 * hook, using the mock action as the callback. Then, it calls the __deprecated_hook()
	 * function with the specified function name, message, and error level.
	 * Finally, it asserts that the filter callback is called exactly once by checking
	 * the call count of the mock action object.
	 *
	 * @return void
	 */
	public function test_deprecated_hook_filter_called() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Hook hook_name is deprecated since version 1! Use replacement_hook instead. message' );

		$filter = new MockAction();
		add_filter( 'deprecated_hook_trigger_error', array( $filter, 'filter' ) );

		_deprecated_hook( 'hook_name', 1, 'replacement_hook', 'message' );

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * @ticket 60110
	 *
	 * @return void
	 */
	public function test_deprecated_hook_no_replacement() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Hook hook_name is deprecated since version 1 with no alternative available. message' );

		_deprecated_hook( 'hook_name', 1, '', 'message' );
	}

	/**
	 * @ticket 60110
	 *
	 * @return void
	 */
	public function test_deprecated_hook_no_message() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Hook hook_name is deprecated since version 1! Use replacement_hook instead.' );

		_deprecated_hook( 'hook_name', 1, 'replacement_hook' );
	}

	/**
	 * @ticket 60110
	 *
	 * @return void
	 */
	public function test_deprecated_hook() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Hook hook_name is deprecated since version 1 with no alternative available.' );

		_deprecated_hook( 'hook_name', 1 );
	}
}
