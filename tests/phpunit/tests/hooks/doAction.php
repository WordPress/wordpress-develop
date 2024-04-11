<?php

/**
 * Test do_action().
 *
 * @group hooks
 * @covers ::do_action
 */
class Tests_Hooks_DoAction extends WP_UnitTestCase {

	public function test_simple_action() {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;

		add_action( $hook_name, array( &$a, 'action' ) );
		do_action( $hook_name );

		// Only one event occurred for the hook, with empty args.
		$this->assertSame( 1, $a->get_call_count() );
		// Only our hook was called.
		$this->assertSame( array( $hook_name ), $a->get_hook_names() );

		$argsvar = $a->get_args();
		$args    = array_pop( $argsvar );
		$this->assertSame( array( '' ), $args );
	}

	public function test_all_action() {
		$a          = new MockAction();
		$hook_name1 = __FUNCTION__ . '_1';
		$hook_name2 = __FUNCTION__ . '_2';

		// Add an 'all' action.
		add_action( 'all', array( &$a, 'action' ) );
		$this->assertSame( 10, has_filter( 'all', array( &$a, 'action' ) ) );

		// Do some actions.
		do_action( $hook_name1 );
		do_action( $hook_name2 );
		do_action( $hook_name1 );
		do_action( $hook_name1 );

		// Our action should have been called once for each tag.
		$this->assertSame( 4, $a->get_call_count() );
		// Only our hook was called.
		$this->assertSame( array( $hook_name1, $hook_name2, $hook_name1, $hook_name1 ), $a->get_hook_names() );

		remove_action( 'all', array( &$a, 'action' ) );
		$this->assertFalse( has_filter( 'all', array( &$a, 'action' ) ) );
	}

	/**
	 * One tag with multiple actions.
	 *
	 * @covers ::do_action
	 */
	public function test_multiple_actions() {
		$a1        = new MockAction();
		$a2        = new MockAction();
		$hook_name = __FUNCTION__;

		// Add both actions to the hook.
		add_action( $hook_name, array( &$a1, 'action' ) );
		add_action( $hook_name, array( &$a2, 'action' ) );

		do_action( $hook_name );

		// Both actions called once each.
		$this->assertSame( 1, $a1->get_call_count() );
		$this->assertSame( 1, $a2->get_call_count() );
	}

	/**
	 * One tag with multiple actions.
	 *
	 * @covers ::do_action
	 */
	public function test_action_args_1() {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;
		$val       = __FUNCTION__ . '_val';

		add_action( $hook_name, array( &$a, 'action' ) );
		// Call the action with a single argument.
		do_action( $hook_name, $val );

		$call_count = $a->get_call_count();
		$this->assertSame( 1, $call_count );
		$argsvar = $a->get_args();
		$this->assertSame( array( $val ), array_pop( $argsvar ) );
	}

	/**
	 * One tag with multiple actions.
	 *
	 * @covers ::do_action
	 */
	public function test_action_args_2() {
		$a1        = new MockAction();
		$a2        = new MockAction();
		$hook_name = __FUNCTION__;
		$val1      = __FUNCTION__ . '_val1';
		$val2      = __FUNCTION__ . '_val2';

		// $a1 accepts two arguments, $a2 doesn't.
		add_action( $hook_name, array( &$a1, 'action' ), 10, 2 );
		add_action( $hook_name, array( &$a2, 'action' ) );
		// Call the action with two arguments.
		do_action( $hook_name, $val1, $val2 );

		$call_count = $a1->get_call_count();
		// $a1 should be called with both args.
		$this->assertSame( 1, $call_count );
		$argsvar1 = $a1->get_args();
		$this->assertSame( array( $val1, $val2 ), array_pop( $argsvar1 ) );

		// $a2 should be called with one only.
		$this->assertSame( 1, $a2->get_call_count() );
		$argsvar2 = $a2->get_args();
		$this->assertSame( array( $val1 ), array_pop( $argsvar2 ) );
	}

	/**
	 * Test that multiple callbacks receive the correct number of args even when the number
	 * is less than, or greater than previous hooks.
	 *
	 * @see https://core.trac.wordpress.org/ticket/17817#comment:72
	 * @ticket 17817
	 *
	 * @covers ::do_action
	 */
	public function test_action_args_3() {
		$a1        = new MockAction();
		$a2        = new MockAction();
		$a3        = new MockAction();
		$hook_name = __FUNCTION__;
		$val1      = __FUNCTION__ . '_val1';
		$val2      = __FUNCTION__ . '_val2';

		// $a1 accepts two arguments, $a2 doesn't, $a3 accepts two arguments.
		add_action( $hook_name, array( &$a1, 'action' ), 10, 2 );
		add_action( $hook_name, array( &$a2, 'action' ) );
		add_action( $hook_name, array( &$a3, 'action' ), 10, 2 );
		// Call the action with two arguments.
		do_action( $hook_name, $val1, $val2 );

		$call_count = $a1->get_call_count();
		// $a1 should be called with both args.
		$this->assertSame( 1, $call_count );
		$argsvar1 = $a1->get_args();
		$this->assertSame( array( $val1, $val2 ), array_pop( $argsvar1 ) );

		// $a2 should be called with one only.
		$this->assertSame( 1, $a2->get_call_count() );
		$argsvar2 = $a2->get_args();
		$this->assertSame( array( $val1 ), array_pop( $argsvar2 ) );

		// $a3 should be called with both args.
		$this->assertSame( 1, $a3->get_call_count() );
		$argsvar3 = $a3->get_args();
		$this->assertSame( array( $val1, $val2 ), array_pop( $argsvar3 ) );
	}

	/**
	 * Tests PHP 4 notation for calling actions while passing in an object by reference.
	 *
	 * @ticket 48312
	 *
	 * @covers ::do_action
	 */
	public function test_action_args_with_php4_syntax() {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;
		$val       = new stdClass();

		add_action( $hook_name, array( &$a, 'action' ) );
		// Call the action with PHP 4 notation for passing object by reference.
		do_action( $hook_name, array( &$val ) );

		$call_count = $a->get_call_count();
		$argsvar    = $a->get_args();
		$this->assertSame( array( $val ), array_pop( $argsvar ) );
	}

	/**
	 * @ticket 60193
	 *
	 * @dataProvider data_priority_callback_order_with_integers
	 * @dataProvider data_priority_callback_order_with_unhappy_path_nonintegers
	 *
	 * @covers ::do_action
	 *
	 * @param array $priorities {
	 *     Indexed array of the priorities for the MockAction callbacks.
	 *
	 *     @type mixed $0 Priority for 'action' callback.
	 *     @type mixed $1 Priority for 'action2' callback.
	 * }
	 * @param array  $expected_call_order  An array of callback names in expected call order.
	 * @param string $expected_deprecation Optional. Deprecation message. Default ''.
	 */
	public function test_priority_callback_order( $priorities, $expected_call_order, $expected_deprecation = '' ) {
		$mock      = new MockAction();
		$hook_name = __FUNCTION__;

		if ( $expected_deprecation && PHP_VERSION_ID >= 80100 ) {
			$this->expectDeprecation();
			$this->expectDeprecationMessage( $expected_deprecation );
		}

		add_action( $hook_name, array( $mock, 'action' ), $priorities[0] );
		add_action( $hook_name, array( $mock, 'action2' ), $priorities[1] );
		do_action( $hook_name );

		$this->assertSame( 2, $mock->get_call_count(), 'The number of call counts does not match' );

		$actual_call_order = wp_list_pluck( $mock->get_events(), 'action' );
		$this->assertSame( $expected_call_order, $actual_call_order, 'The action callback order does not match the expected order' );
	}

	/**
	 * Happy path data provider.
	 *
	 * @return array[]
	 */
	public function data_priority_callback_order_with_integers() {
		return array(
			'int DESC' => array(
				'priorities'          => array( 10, 9 ),
				'expected_call_order' => array( 'action2', 'action' ),
			),
			'int ASC'  => array(
				'priorities'          => array( 9, 10 ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
		);
	}

	/**
	 * Unhappy path data provider.
	 *
	 * @return array[]
	 */
	public function data_priority_callback_order_with_unhappy_path_nonintegers() {
		return array(
			// Numbers as strings and floats.
			'int as string DESC'               => array(
				'priorities'          => array( '10', '9' ),
				'expected_call_order' => array( 'action2', 'action' ),
			),
			'int as string ASC'                => array(
				'priorities'          => array( '9', '10' ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
			'float DESC'                       => array(
				'priorities'           => array( 10.0, 9.5 ),
				'expected_call_order'  => array( 'action2', 'action' ),
				'expected_deprecation' => 'Implicit conversion from float 9.5 to int loses precision',
			),
			'float ASC'                        => array(
				'priorities'           => array( 9.5, 10.0 ),
				'expected_call_order'  => array( 'action', 'action2' ),
				'expected_deprecation' => 'Implicit conversion from float 9.5 to int loses precision',
			),
			'float as string DESC'             => array(
				'priorities'          => array( '10.0', '9.5' ),
				'expected_call_order' => array( 'action2', 'action' ),
			),
			'float as string ASC'              => array(
				'priorities'          => array( '9.5', '10.0' ),
				'expected_call_order' => array( 'action', 'action2' ),
			),

			// Non-numeric.
			'null'                             => array(
				'priorities'          => array( null, null ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
			'bool DESC'                        => array(
				'priorities'          => array( true, false ),
				'expected_call_order' => array( 'action2', 'action' ),
			),
			'bool ASC'                         => array(
				'priorities'          => array( false, true ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
			'non-numerical string DESC'        => array(
				'priorities'          => array( 'test1', 'test2' ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
			'non-numerical string ASC'         => array(
				'priorities'          => array( 'test1', 'test2' ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
			'int, non-numerical string DESC'   => array(
				'priorities'          => array( 10, 'test' ),
				'expected_call_order' => array( 'action2', 'action' ),
			),
			'int, non-numerical string ASC'    => array(
				'priorities'          => array( 'test', 10 ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
			'float, non-numerical string DESC' => array(
				'priorities'          => array( 10.0, 'test' ),
				'expected_call_order' => array( 'action2', 'action' ),
			),
			'float, non-numerical string ASC'  => array(
				'priorities'          => array( 'test', 10.0 ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
		);
	}

	/**
	 * @ticket 11241
	 *
	 * @covers ::do_action
	 */
	public function test_action_keyed_array() {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;

		add_action( $hook_name, array( &$a, 'action' ) );

		$context = array( 'key1' => 'val1' );
		do_action( $hook_name, $context );

		$args = $a->get_args();
		$this->assertSame( $args[0][0], $context );

		$context2 = array(
			'key2' => 'val2',
			'key3' => 'val3',
		);
		do_action( $hook_name, $context2 );

		$args = $a->get_args();
		$this->assertSame( $args[1][0], $context2 );
	}

	/**
	 * @ticket 10493
	 *
	 * @covers ::add_action
	 * @covers ::has_action
	 * @covers ::do_action
	 */
	public function test_action_closure() {
		$hook_name = __FUNCTION__;
		$closure   = static function ( $a, $b ) {
			$GLOBALS[ $a ] = $b;
		};
		add_action( $hook_name, $closure, 10, 2 );

		$this->assertSame( 10, has_action( $hook_name, $closure ) );

		$context = array( 'val1', 'val2' );
		do_action( $hook_name, $context[0], $context[1] );

		$this->assertSame( $GLOBALS[ $context[0] ], $context[1] );

		$hook_name2 = __FUNCTION__ . '_2';
		$closure2   = static function () {
			$GLOBALS['closure_no_args'] = true;
		};
		add_action( $hook_name2, $closure2 );

		$this->assertSame( 10, has_action( $hook_name2, $closure2 ) );

		do_action( $hook_name2 );

		$this->assertTrue( $GLOBALS['closure_no_args'] );

		remove_action( $hook_name, $closure );
		remove_action( $hook_name2, $closure2 );
	}

	/**
	 * @ticket 17817
	 *
	 * @covers ::do_action
	 */
	public function test_action_recursion() {
		$hook_name = __FUNCTION__;
		$a         = new MockAction();
		$b         = new MockAction();

		add_action( $hook_name, array( $a, 'action' ), 11, 1 );
		add_action( $hook_name, array( $b, 'action' ), 13, 1 );
		add_action( $hook_name, array( $this, 'action_that_causes_recursion' ), 12, 1 );
		do_action( $hook_name, $hook_name );

		$this->assertSame( 2, $a->get_call_count(), 'recursive actions should call all callbacks with earlier priority' );
		$this->assertSame( 2, $b->get_call_count(), 'recursive actions should call callbacks with later priority' );
	}

	/**
	 * @covers ::do_action
	 */
	public function action_that_causes_recursion( $hook_name ) {
		static $recursing = false;
		if ( ! $recursing ) {
			$recursing = true;
			do_action( $hook_name, $hook_name );
		}
		$recursing = false;
	}

	/**
	 * @ticket 9968
	 * @ticket 17817
	 *
	 * @covers ::remove_action
	 * @covers ::add_action
	 */
	public function test_action_callback_manipulation_while_running() {
		$hook_name = __FUNCTION__;
		$a         = new MockAction();
		$b         = new MockAction();
		$c         = new MockAction();
		$d         = new MockAction();
		$e         = new MockAction();

		add_action( $hook_name, array( $a, 'action' ), 11, 2 );
		add_action( $hook_name, array( $this, 'action_that_manipulates_a_running_hook' ), 12, 2 );
		add_action( $hook_name, array( $b, 'action' ), 12, 2 );

		do_action( $hook_name, $hook_name, array( $a, $b, $c, $d, $e ) );
		do_action( $hook_name, $hook_name, array( $a, $b, $c, $d, $e ) );

		$this->assertSame( 2, $a->get_call_count(), 'callbacks should run unless otherwise instructed' );
		$this->assertSame( 1, $b->get_call_count(), 'callback removed by same priority callback should still get called' );
		$this->assertSame( 1, $c->get_call_count(), 'callback added by same priority callback should not get called' );
		$this->assertSame( 2, $d->get_call_count(), 'callback added by earlier priority callback should get called' );
		$this->assertSame( 1, $e->get_call_count(), 'callback added by later priority callback should not get called' );
	}

	public function action_that_manipulates_a_running_hook( $hook_name, $mocks ) {
		remove_action( $hook_name, array( $mocks[1], 'action' ), 12, 2 );
		add_action( $hook_name, array( $mocks[2], 'action' ), 12, 2 );
		add_action( $hook_name, array( $mocks[3], 'action' ), 13, 2 );
		add_action( $hook_name, array( $mocks[4], 'action' ), 10, 2 );
	}
}
