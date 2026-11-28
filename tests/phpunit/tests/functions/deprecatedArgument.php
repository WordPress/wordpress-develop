<?php

/**
 * Tests for the _deprecated_argumentfunction.
 *
 * @group Functions
 *
 * @covers ::__deprecated_argument
 */
class Tests_Functions_deprecatedArgument extends WP_UnitTestCase {

	/**
	 * Sets up the test case.
	 *
	 * This method is responsible for setting up the test case before each test method is executed.
	 * It removes certain actions related to deprecated and _deprecated_argumentfunctions.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// remove ethe spherical handling for _deprecated_argumentin the PHPUnint setup so we can test it
		remove_action( 'deprecated_argument_run', array( $this, 'deprecated_function_run' ), 10, 4 );
		remove_action( 'deprecated_argument_trigger_error', '__return_false' );
	}


	/**
	 * @ticket 60112
	 *
	 * test_deprecated_argument_action_called() method tests the action being called when _deprecated_argument()
	 *
	 * It creates a MockAction object and registers it as a filter for the '_deprecated_argument_run' action.
	 * It then calls __deprecated_argument() function with the given 'function_name', 'message', and 1 as arguments.
	 * Finally, it asserts that the call count of the filter object is equal to 1.
	 *
	 * @return void
	 */
	public function test_deprecated_argument_action_called() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Function function_name was called with an argument that is deprecated since version 1! message' );

		$action = new MockAction();
		add_filter( 'deprecated_argument_run', array( $action, 'action' ) );

		_deprecated_argument( 'function_name', 1, 'message' );

		$this->assertSame( 1, $action->get_call_count() );
	}

	/**
	 * @ticket 60112
	 *
	 * This method tests if the '_deprecated_argument_trigger_error' filter is called
	 * when the _deprecated_argument() function is invoked.
	 *
	 * It creates a mock action object and adds a filter to the '_deprecated_argument_trigger_error'
	 * hook, using the mock action as the callback. Then, it calls the __deprecated_argument()
	 * function with the specified function name, message, and error level.
	 * Finally, it asserts that the filter callback is called exactly once by checking
	 * the call count of the mock action object.
	 *
	 * @return void
	 */
	public function test_deprecated_argument_filter_called() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Function function_name was called with an argument that is deprecated since version 1! message' );

		$filter = new MockAction();
		add_filter( 'deprecated_argument_trigger_error', array( $filter, 'filter' ) );

		_deprecated_argument( 'function_name', 1, 'message' );

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * @ticket 60112
  	 *
	 * @return void
	 */
	public function test_deprecated_argument_no_message() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Function function_name was called with an argument that is deprecated since version 1 with no alternative available.' );

		_deprecated_argument( 'function_name', 1 );
	}
}
