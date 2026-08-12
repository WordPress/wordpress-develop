<?php

/**
 * Tests for the _doing_it_wrong function.
 *
 * @group Functions
 *
 * @covers ::_doing_it_wrong
 */
class Tests_Functions_doingItWrong extends WP_UnitTestCase {

	/**
	 * Sets up the test case.
	 *
	 * This method is responsible for setting up the test case before each test method is executed.
	 * It removes certain actions related to deprecated and doing_it_wrong functions.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// remove ethe spherical handling for doing_it_wrong in the PHPUnint setup so we can test it
		remove_action( 'doing_it_wrong_run', array( $this, 'doing_it_wrong_run' ), 10, 3 );
		remove_action( 'doing_it_wrong_trigger_error', '__return_false' );
	}


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
		$this->expectNotice();
		$this->expectNoticeMessage( 'Function function_name was called incorrectly. message Please see <a>Debugging in WordPress</a> for more information. (This message was added in version 1.)' );

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
		$this->expectNotice();
		$this->expectNoticeMessage( 'Function function_name was called incorrectly. message Please see <a>Debugging in WordPress</a> for more information. (This message was added in version 1.)' );

		$filter = new MockAction();
		add_filter( 'doing_it_wrong_trigger_error', array( $filter, 'filter' ) );

		_doing_it_wrong( 'function_name', 'message', 1 );

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * @ticket 60057
	 *
	 * Tests the _doing_it_wrong function when called without a version number.
	 *
	 * This method verifies that the _doing_it_wrong function throws a notice and displays the correct message when called without a version number.
	 *
	 * @return void
	 */
	public function test__doing_it_wrong_no_version() {
		$this->expectNotice();
		$this->expectNoticeMessage( 'Function function_name was called incorrectly. message Please see <a>Debugging in WordPress</a> for more information.' );

		_doing_it_wrong( 'function_name', 'message', false );
	}
}
