<?php

/**
 * Tests for the _doing_it_wrong function.
 *
 * @group Functions
 *
 * @covers ::_doing_it_wrong
 */
class Tests_Functions_doingItWrong extends WP_UnitTestCase{

	/**
	 * @ticket 60057
	 *
	 * test__doing_it_wrong_action_called() method tests the action being called when doing_it_wrong()
	 *
	 * It creates a MockAction object and registers it as a filter for the 'doing_it_wrong_run' action.
	 * It then calls _doing_it_wrong() function with the given 'function_name', 'message', and 1 as arguments.
	 * Finally, it asserts that the call count of the filter object is equal to 1.
	 *
	 * @return void
	 */
	public function test__doing_it_wrong_action_called() {
		$this->expectError();
//		$this->expectErrorMessage( 'function_name(): expected the function name and message' );

		$action = new MockAction();
		add_filter( 'doing_it_wrong_run', array( $action, 'action' ) );

		_doing_it_wrong( 'function_name', 'message', 1 );

		$this->assertSame( 1, $action->get_call_count() );
	}

	/**
	 * @ticket 60057
	 *
	 * This method tests if the 'doing_it_wrong_trigger_error' filter is called
	 * when the _doing_it_wrong() function is invoked.
	 *
	 * It creates a mock action object and adds a filter to the 'doing_it_wrong_trigger_error'
	 * hook, using the mock action as the callback. Then, it calls the _doing_it_wrong()
	 * function with the specified function name, message, and error level.
	 * Finally, it asserts that the filter callback is called exactly once by checking
	 * the call count of the mock action object.
	 *
	 * @return void
	 */
	public function test__doing_it_wrong_filter_called() {
//		$this->expectError();
//		$this->expectErrorMessage( 'function_name(): expected the function name and message' );

		$filter = new MockAction();
		add_filter( 'doing_it_wrong_trigger_error', array( $filter, 'filter' ) );

		_doing_it_wrong( 'function_name', 'message', 1 );

		$this->assertSame( 1, $filter->get_call_count() );
	}
}
