<?php

namespace WordPress\Tests\WP_Includes\Functions;

use MockAction;
use WP_UnitTestCase;

/**
 * Tests for the `_deprecated_function()` function.
 *
 * @group functions
 *
 * @covers ::_deprecated_function
 */
class DeprecatedFunctionTest extends WP_UnitTestCase {

	/**
	 * Sets up the test case.
	 *
	 * This method is responsible for setting up the test case before each test method is executed.
	 * It removes certain actions related to deprecated and _deprecated_function functions.
	 */
	public function set_up() {
		parent::set_up();

		// Remove the special handling for _deprecated_function in the PHPUnit setup so we can test it.
		remove_action( 'deprecated_function_run', array( $this, 'deprecated_function_run' ), 10, 4 );
		remove_action( 'deprecated_function_trigger_error', '__return_false' );
	}

	/**
	 * Tests the action being called when _deprecated_function() is invoked.
	 *
	 * @ticket 60116
	 */
	public function test_deprecated_function_action_called() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Function function_name is <strong>deprecated</strong> since version 1! Use replacement_function instead.' );

		$action = new MockAction();
		add_filter( 'deprecated_function_run', array( $action, 'action' ) );

		_deprecated_function( 'function_name', 1, 'replacement_function' );

		$this->assertSame( 1, $action->get_call_count() );
	}

	/**
	 * Tests if the '_deprecated_function_trigger_error' filter is called when _deprecated_function() is invoked.
	 *
	 * @ticket 60116
	 */
	public function test_deprecated_function_filter_called() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Function function_name is <strong>deprecated</strong> since version 1! Use replacement_function instead.' );

		$filter = new MockAction();
		add_filter( 'deprecated_function_trigger_error', array( $filter, 'filter' ) );

		_deprecated_function( 'function_name', 1, 'replacement_function' );

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * Tests deprecation notice when no replacement function is specified.
	 *
	 * @ticket 60116
	 */
	public function test_deprecated_function_no_replacement() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Function function_name is <strong>deprecated</strong> since version 1 with no alternative available.' );

		_deprecated_function( 'function_name', 1 );
	}
}
