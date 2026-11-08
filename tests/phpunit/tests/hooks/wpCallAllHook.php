<?php

/**
 * Tests for _wp_call_all_hook() and how the hook functions call the 'all' hook.
 *
 * Tests_Actions::test_all_action() and Tests_Filters::test_all_filter() already check
 * that the 'all' hook runs once per call with the hook name, and that it does not change
 * the filtered value. Tests_Hooks_DoAllHook covers WP_Hook::do_all_hook() on its own.
 * These tests add the arguments passed, the run order and the current filter.
 *
 * @group hooks
 * @group plugins
 *
 * @covers ::_wp_call_all_hook
 * @covers ::do_action
 * @covers ::do_action_ref_array
 * @covers ::apply_filters
 * @covers ::apply_filters_ref_array
 */
class Tests_Hooks_WpCallAllHook extends WP_UnitTestCase {

	/**
	 * Calls made by the test callbacks, in order.
	 *
	 * @var array[]
	 */
	private $calls = array();

	/**
	 * Tests that the 'all' hook receives the hook name and all arguments, whatever its accepted arguments.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_hook_calls
	 *
	 * @param string $hook_function The hook function to call.
	 * @param array  $args     The arguments after the hook name.
	 */
	public function test_should_pass_hook_name_and_arguments_to_all_hook( $hook_function, $args ) {
		add_action( 'all', array( $this, 'record_all' ), 10, 1 );

		$hook_function( 'test_hook', ...$args );

		$this->assertCount( 1, $this->calls, 'The all hook should run once.' );
		$this->assertSame( array_merge( array( 'test_hook' ), $args ), $this->calls[0][1], 'The all hook should receive the hook name and arguments.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_hook_calls() {
		return array(
			'do_action with arguments'        => array( 'do_action', array( 'a', 2, false ) ),
			'do_action_ref_array'             => array( 'do_action_ref_array', array( array( 'a', 2 ) ) ),
			'apply_filters without arguments' => array( 'apply_filters', array( 'value' ) ),
			'apply_filters with arguments'    => array( 'apply_filters', array( 'value', 'a', null ) ),
			'apply_filters_ref_array'         => array( 'apply_filters_ref_array', array( array( 'value', 'a' ) ) ),
		);
	}

	/**
	 * Tests that the 'all' hook runs inside the called hook and before its callbacks.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_hook_functions
	 *
	 * @param string $hook_function The hook function to call.
	 * @param array  $args     The arguments after the hook name.
	 */
	public function test_should_run_all_hook_before_hook_callbacks( $hook_function, $args ) {
		add_action( 'all', array( $this, 'record_all' ) );
		add_filter( 'test_hook', array( $this, 'record_hook' ) );

		$hook_function( 'test_hook', ...$args );

		$this->assertSame(
			array(
				array( 'all', 'test_hook' ),
				array( 'hook', 'test_hook' ),
			),
			array(
				array( $this->calls[0][0], $this->calls[0][2] ),
				array( $this->calls[1][0], $this->calls[1][2] ),
			),
			'The all hook should run first, while the called hook is the current filter.'
		);
		$this->assertCount( 2, $this->calls, 'Each callback should run once.' );
		$this->assertSame( array(), $GLOBALS['wp_current_filter'], 'The current filter should be reset.' );
	}

	/**
	 * Tests that the 'all' hook runs for a hook without callbacks, and the current filter is reset.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_hook_functions
	 *
	 * @param string $hook_function The hook function to call.
	 * @param array  $args     The arguments after the hook name.
	 */
	public function test_should_run_all_hook_for_hook_without_callbacks( $hook_function, $args ) {
		add_action( 'all', array( $this, 'record_all' ) );

		$hook_function( 'test_hook', ...$args );

		$this->assertCount( 1, $this->calls, 'The all hook should run once.' );
		$this->assertSame( 'test_hook', $this->calls[0][2], 'The called hook should be the current filter.' );
		$this->assertSame( array(), $GLOBALS['wp_current_filter'], 'The current filter should be reset.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_hook_functions() {
		return array(
			'do_action'               => array( 'do_action', array( 'a' ) ),
			'do_action_ref_array'     => array( 'do_action_ref_array', array( array( 'a' ) ) ),
			'apply_filters'           => array( 'apply_filters', array( 'a' ) ),
			'apply_filters_ref_array' => array( 'apply_filters_ref_array', array( array( 'a' ) ) ),
		);
	}

	/**
	 * Tests that the callbacks of the 'all' hook run in priority order.
	 *
	 * @ticket 65819
	 */
	public function test_should_run_all_hook_callbacks_in_priority_order() {
		$order = array();

		add_action(
			'all',
			static function () use ( &$order ) {
				$order[] = 20;
			},
			20
		);
		add_action(
			'all',
			static function () use ( &$order ) {
				$order[] = 5;
			},
			5
		);
		add_action(
			'all',
			static function () use ( &$order ) {
				$order[] = '5 again';
			},
			5
		);

		do_action( 'test_hook' );

		$this->assertSame( array( 5, '5 again', 20 ), $order );
	}

	/**
	 * Records a call of the 'all' hook.
	 *
	 * @param mixed ...$args The hook arguments.
	 */
	public function record_all( ...$args ) {
		$this->calls[] = array( 'all', $args, current_filter() );
	}

	/**
	 * Records a call of the tested hook.
	 *
	 * @param mixed $value The filtered value.
	 * @return mixed The unchanged value.
	 */
	public function record_hook( $value ) {
		$this->calls[] = array( 'hook', $value, current_filter() );

		return $value;
	}
}
