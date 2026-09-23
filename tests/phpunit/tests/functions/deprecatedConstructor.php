<?php

/**
 * Tests for the _deprecated_constructor function.
 *
 * @group Functions
 *
 * @covers ::__deprecated_constructor
 */
class Tests_Functions_deprecatedConstructor extends WP_UnitTestCase {

	/**
	 * Sets up the test case.
	 *
	 * This method is responsible for setting up the test case before each test method is executed.
	 * It removes certain actions related to deprecated and _deprecated_constructorfunctions.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// remove ethe spherical handling for _deprecated_constructorin the PHPUnint setup so we can test it
		remove_action( 'deprecated_constructor_run', array( $this, 'deprecated_function_run' ), 10, 4 );
		remove_action( 'deprecated_constructor_trigger_error', '__return_false' );
	}


	/**
	 * @ticket 60115
	 *
	 * test_deprecated_constructor_action_called() method tests the action being called when _deprecated_constructor()
	 *
	 * It creates a MockAction object and registers it as a filter for the '_deprecated_constructor_run' action.
	 * It then calls __deprecated_constructor() function with the given 'function_name', 'message', and 1 as arguments.
	 * Finally, it asserts that the call count of the filter object is equal to 1.
	 *
	 * @return void
	 */
	public function test_deprecated_constructor_action_called() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'The called constructor method for class_name class in parent_class is deprecated since version 1! Use __construct() instead.' );

		$action = new MockAction();
		add_filter( 'deprecated_constructor_run', array( $action, 'action' ) );

		_deprecated_constructor( 'class_name', 1, 'parent_class' );

		$this->assertSame( 1, $action->get_call_count() );
	}

	/**
	 * @ticket 60115
	 *
	 * This method tests if the '_deprecated_constructor_trigger_error' filter is called
	 * when the _deprecated_constructor() function is invoked.
	 *
	 * It creates a mock action object and adds a filter to the '_deprecated_constructor_trigger_error'
	 * hook, using the mock action as the callback. Then, it calls the __deprecated_constructor()
	 * function with the specified function name, message, and error level.
	 * Finally, it asserts that the filter callback is called exactly once by checking
	 * the call count of the mock action object.
	 *
	 * @return void
	 */
	public function test_deprecated_constructor_filter_called() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'The called constructor method for class_name class in parent_class is deprecated since version 1! Use __construct() instead.' );

		$filter = new MockAction();
		add_filter( 'deprecated_constructor_trigger_error', array( $filter, 'filter' ) );

		_deprecated_constructor( 'class_name', 1, 'parent_class' );

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * @ticket 60115
	 *
	 * @return void
	 */
	public function test_deprecated_constructor_no_replacement() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'The called constructor method for class_name class is deprecated since version 1! Use __construct() instead.' );

		_deprecated_constructor( 'class_name', 1 );
	}
}
