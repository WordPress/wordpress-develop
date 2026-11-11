<?php

/**
 * Tests for the _deprecated_argument() function.
 *
 * @group functions
 *
 * @covers ::_deprecated_argument
 */
class Tests_Functions_DeprecatedArgument extends WP_UnitTestCase {

	/**
	 * Sets up the test fixture.
	 */
	public function set_up() {
		parent::set_up();

		// Remove the default handling for _deprecated_argument in the PHPUnit setup so we can test it.
		remove_action( 'deprecated_argument_run', array( $this, 'deprecated_function_run' ), 10 );
		remove_action( 'deprecated_argument_trigger_error', '__return_false' );
	}

	/**
	 * Tests that the 'deprecated_argument_run' action is called when _deprecated_argument() is invoked.
	 *
	 * @ticket 60117
	 */
	public function test_deprecated_argument_action_called() {
		add_filter( 'deprecated_argument_trigger_error', '__return_false' );

		$action = new MockAction();
		add_action( 'deprecated_argument_run', array( $action, 'action' ) );

		_deprecated_argument( 'function_name', '1', 'message' );

		$this->assertSame( 1, $action->get_call_count() );
	}

	/**
	 * Tests that the 'deprecated_argument_trigger_error' filter is called when _deprecated_argument() is invoked.
	 *
	 * @ticket 60117
	 */
	public function test_deprecated_argument_filter_called() {
		$filter = new MockAction();
		add_filter( 'deprecated_argument_trigger_error', array( $filter, 'filter' ) );
		add_filter( 'deprecated_argument_trigger_error', '__return_false' );

		_deprecated_argument( 'function_name', '1', 'message' );

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * Tests that _deprecated_argument() triggers a deprecation with the provided message.
	 *
	 * @ticket 60117
	 */
	public function test_deprecated_argument_with_message() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Function function_name was called with an argument that is deprecated since version 1! message' );

		_deprecated_argument( 'function_name', '1', 'message' );
	}

	/**
	 * Tests that _deprecated_argument() triggers a deprecation with the default message when no message is provided.
	 *
	 * @ticket 60117
	 */
	public function test_deprecated_argument_no_message() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage( 'Function function_name was called with an argument that is deprecated since version 1 with no alternative available.' );

		_deprecated_argument( 'function_name', '1' );
	}
}
