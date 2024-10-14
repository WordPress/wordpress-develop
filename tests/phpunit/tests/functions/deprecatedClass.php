<?php

/**
 * Tests for the _deprecated_class function.
 *
 * @group functions
 *
 * @covers ::__deprecated_class
 */
class Tests_Functions_deprecatedClass extends WP_UnitTestCase {

	/**
	 * Sets up the test case.
	 *
	 * This method is responsible for setting up the test case before each test method is executed.
	 * It removes certain actions related to deprecated and _deprecated_classfunctions.
	 */
	public function set_up() {
		parent::set_up();

		// Remove the spherical handling for _deprecated_classin the PHPUnint setup so we can test it.
		remove_action( 'deprecated_class_run', array( $this, 'deprecated_function_run' ), 10, 4 );
		remove_action( 'deprecated_class_trigger_error', '__return_false' );
	}


	/**
	 * @ticket 60114
	 *
	 * test_deprecated_class_action_called() method tests the action being called when _deprecated_class()
	 *
	 * It creates a MockAction object and registers it as a filter for the '_deprecated_class_run' action.
	 * It then calls __deprecated_class() function with the given 'function_name', 'message', and 1 as arguments.
	 * Finally, it asserts that the call count of the filter object is equal to 1.
	 *
	 * @return void
	 */
	public function test_deprecated_class_action_called() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Class class_name is deprecated since version 1! Use replacement_class instead.' );

		$action = new MockAction();
		add_filter( 'deprecated_class_run', array( $action, 'action' ) );

		_deprecated_class( 'class_name', 1, 'replacement_class' );

		$this->assertSame( 1, $action->get_call_count() );
	}

	/**
	 * @ticket 60114
	 *
	 * This method tests if the '_deprecated_class_trigger_error' filter is called
	 * when the _deprecated_class() function is invoked.
	 *
	 * It creates a mock action object and adds a filter to the '_deprecated_class_trigger_error'
	 * hook, using the mock action as the callback. Then, it calls the __deprecated_class()
	 * function with the specified function name, message, and error level.
	 * Finally, it asserts that the filter callback is called exactly once by checking
	 * the call count of the mock action object.
	 *
	 * @return void
	 */
	public function test_deprecated_class_filter_called() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Class class_name is deprecated since version 1! Use replacement_class instead.' );

		$filter = new MockAction();
		add_filter( 'deprecated_class_trigger_error', array( $filter, 'filter' ) );

		_deprecated_class( 'class_name', 1, 'replacement_class' );

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * @ticket 60114
	 *
	 * Tests the _deprecated_classfunction when called without a version number.
	 *
	 * This method verifies that the _deprecated_classfunction throws a notice and displays the correct message when called without a version number.
	 *
	 * @return void
	 */
	public function test_deprecated_class_no_replacement() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Class class_name is deprecated since version 1 with no alternative available.' );

		_deprecated_class( 'class_name', 1 );
	}
}
