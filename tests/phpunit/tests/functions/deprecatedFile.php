<?php

/**
 * Tests for the _deprecated_filefunction.
 *
 * @group Functions
 *
 * @covers ::__deprecated_file
 */
class Tests_Functions_deprecatedFile extends WP_UnitTestCase {

	/**
	 * Sets up the test case.
	 *
	 * This method is responsible for setting up the test case before each test method is executed.
	 * It removes certain actions related to deprecated and _deprecated_filefunctions.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// remove ethe spherical handling for _deprecated_filein the PHPUnint setup so we can test it
		remove_action( 'deprecated_file_included', array( $this, 'deprecated_function_run' ), 10, 4 );
		remove_action( 'deprecated_file_trigger_error', '__return_false' );
	}


	/**
	 * @ticket 60113
	 *
	 * test_deprecated_file_action_called() method tests the action being called when _deprecated_file()
	 *
	 * It creates a MockAction object and registers it as a filter for the '_deprecated_file_run' action.
	 * It then calls __deprecated_file() function with the given 'function_name', 'message', and 1 as arguments.
	 * Finally, it asserts that the call count of the filter object is equal to 1.
	 *
	 * @return void
	 */
	public function test_deprecated_file_action_called() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'File file_name is deprecated since version 1! Use replacement_file instead. message' );

		$action = new MockAction();
		add_filter( 'deprecated_file_run', array( $action, 'action' ) );

		_deprecated_file( 'file_name', 1, 'replacement_file', 'message' );

		$this->assertSame( 1, $action->get_call_count() );
	}

	/**
	 * @ticket 60113
	 *
	 * This method tests if the '_deprecated_file_trigger_error' filter is called
	 * when the _deprecated_file() function is invoked.
	 *
	 * It creates a mock action object and adds a filter to the '_deprecated_file_trigger_error'
	 * hook, using the mock action as the callback. Then, it calls the __deprecated_file()
	 * function with the specified function name, message, and error level.
	 * Finally, it asserts that the filter callback is called exactly once by checking
	 * the call count of the mock action object.
	 *
	 * @return void
	 */
	public function test_deprecated_file_filter_called() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'File file_name is deprecated since version 1! Use replacement_file instead. message' );

		$filter = new MockAction();
		add_filter( 'deprecated_file_trigger_error', array( $filter, 'filter' ) );

		_deprecated_file( 'file_name', 1, 'replacement_file', 'message' );

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * @ticket 60113
	 *
	 * @return void
	 */
	public function test_deprecated_file_no_replacement() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'File file_name is deprecated since version 1 with no alternative available. message' );

		_deprecated_file( 'file_name', 1, '', 'message' );
	}

	/**
	 * @ticket 60113
	 *
	 * @return void
	 */
	public function test_deprecated_file_no_message() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'File file_name is deprecated since version 1! Use replacement_file instead.' );

		_deprecated_file( 'file_name', 1, 'replacement_file' );
	}
	/**
	 * @ticket 60113
	 *
	 * @return void
	 */
	public function test_deprecated_file() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'File file_name is deprecated since version 1 with no alternative available.' );

		_deprecated_file( 'file_name', 1 );
	}
}
