<?php

/**
 * Tests for the _deprecated_function function.
 *
 * @group Functions
 *
 * @covers ::__deprecated_function
 */
class Tests_Functions_deprecatedFunction extends WP_UnitTestCase {

	/**
	 * Sets up the test case.
	 *
	 * This method is responsible for setting up the test case before each test method is executed.
	 * It removes certain actions related to deprecated and _deprecated_functionfunctions.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// remove ethe spherical handling for _deprecated_functionin the PHPUnint setup so we can test it
		remove_action( 'deprecated_function_run', array( $this, 'deprecated_function_run' ), 10, 4 );
		remove_action( 'deprecated_function_trigger_error', '__return_false' );
	}


	/**
	 * @ticket 60116
	 *
	 * test_deprecated_function_action_called() method tests the action being called when _deprecated_function()
	 *
	 * It creates a MockAction object and registers it as a filter for the '_deprecated_function_run' action.
	 * It then calls __deprecated_function() function with the given 'function_name', 'message', and 1 as arguments.
	 * Finally, it asserts that the call count of the filter object is equal to 1.
	 *
	 * @return void
	 */
	public function test_deprecated_function_action_called() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Function function_name is deprecated since version 1! Use replacement_function instead.' );

		$action = new MockAction();
		add_filter( 'deprecated_function_run', array( $action, 'action' ) );

		_deprecated_function( 'function_name', 1, 'replacement_function' );

		$this->assertSame( 1, $action->get_call_count() );
	}

	/**
	 * @ticket 60116
	 *
	 * This method tests if the '_deprecated_function_trigger_error' filter is called
	 * when the _deprecated_function() function is invoked.
	 *
	 * It creates a mock action object and adds a filter to the '_deprecated_function_trigger_error'
	 * hook, using the mock action as the callback. Then, it calls the __deprecated_function()
	 * function with the specified function name, message, and error level.
	 * Finally, it asserts that the filter callback is called exactly once by checking
	 * the call count of the mock action object.
	 *
	 * @return void
	 */
	public function test_deprecated_function_filter_called() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Function function_name is deprecated since version 1! Use replacement_function instead.' );

		$filter = new MockAction();
		add_filter( 'deprecated_function_trigger_error', array( $filter, 'filter' ) );

		_deprecated_function( 'function_name', 1, 'replacement_function' );

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * @ticket 60116
	 *
	 *
	 * @return void
	 */
	public function test_deprecated_function_no_replacement() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Function function_name is deprecated since version 1 with no alternative available.' );

		_deprecated_function( 'function_name', 1 );
	}

}
