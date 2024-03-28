<?php

/**
 * Test apply_filters().
 *
 * @group hooks
 * @covers ::apply_filters
 */
class Tests_Hooks_ApplyFilters extends WP_UnitTestCase {
	private $current_priority;

	public function test_simple_filter() {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;
		$val       = __FUNCTION__ . '_val';

		add_filter( $hook_name, array( $a, 'filter' ) );
		$this->assertSame( $val, apply_filters( $hook_name, $val ) );

		// Only one event occurred for the hook, with empty args.
		$this->assertSame( 1, $a->get_call_count() );
		// Only our hook was called.
		$this->assertSame( array( $hook_name ), $a->get_hook_names() );

		$argsvar = $a->get_args();
		$args    = array_pop( $argsvar );
		$this->assertSame( array( $val ), $args );
	}

	// One tag with multiple filters.
	public function test_multiple_filters() {
		$a1        = new MockAction();
		$a2        = new MockAction();
		$hook_name = __FUNCTION__;
		$val       = __FUNCTION__ . '_val';

		// Add both filters to the hook.
		add_filter( $hook_name, array( $a1, 'filter' ) );
		add_filter( $hook_name, array( $a2, 'filter' ) );

		$this->assertSame( $val, apply_filters( $hook_name, $val ) );

		// Both filters called once each.
		$this->assertSame( 1, $a1->get_call_count() );
		$this->assertSame( 1, $a2->get_call_count() );
	}

	public function test_filter_args_1() {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;
		$val       = __FUNCTION__ . '_val';
		$arg1      = __FUNCTION__ . '_arg1';

		add_filter( $hook_name, array( $a, 'filter' ), 10, 2 );
		// Call the filter with a single argument.
		$this->assertSame( $val, apply_filters( $hook_name, $val, $arg1 ) );

		$this->assertSame( 1, $a->get_call_count() );
		$argsvar = $a->get_args();
		$this->assertSame( array( $val, $arg1 ), array_pop( $argsvar ) );
	}

	public function test_filter_args_2() {
		$a1        = new MockAction();
		$a2        = new MockAction();
		$hook_name = __FUNCTION__;
		$val       = __FUNCTION__ . '_val';
		$arg1      = __FUNCTION__ . '_arg1';
		$arg2      = __FUNCTION__ . '_arg2';

		// $a1 accepts two arguments, $a2 doesn't.
		add_filter( $hook_name, array( $a1, 'filter' ), 10, 3 );
		add_filter( $hook_name, array( $a2, 'filter' ) );
		// Call the filter with two arguments.
		$this->assertSame( $val, apply_filters( $hook_name, $val, $arg1, $arg2 ) );

		// $a1 should be called with both args.
		$this->assertSame( 1, $a1->get_call_count() );
		$argsvar1 = $a1->get_args();
		$this->assertSame( array( $val, $arg1, $arg2 ), array_pop( $argsvar1 ) );

		// $a2 should be called with one only.
		$this->assertSame( 1, $a2->get_call_count() );
		$argsvar2 = $a2->get_args();
		$this->assertSame( array( $val ), array_pop( $argsvar2 ) );
	}

	/**
	 * @ticket 60193
	 *
	 * @dataProvider data_priority_callback_order_with_integers
	 * @dataProvider data_priority_callback_order_with_unhappy_path_nonintegers
	 *
	 * @covers ::apply_filters
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

		add_filter( $hook_name, array( $mock, 'filter' ), $priorities[0] );
		add_filter( $hook_name, array( $mock, 'filter2' ), $priorities[1] );
		apply_filters( $hook_name, __FUNCTION__ . '_val' );

		$this->assertSame( 2, $mock->get_call_count(), 'The number of call counts does not match' );

		$actual_call_order = wp_list_pluck( $mock->get_events(), 'filter' );
		$this->assertSame( $expected_call_order, $actual_call_order, 'The filter callback order does not match the expected order' );
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
				'expected_call_order' => array( 'filter2', 'filter' ),
			),
			'int ASC'  => array(
				'priorities'          => array( 9, 10 ),
				'expected_call_order' => array( 'filter', 'filter2' ),
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
				'expected_call_order' => array( 'filter2', 'filter' ),
			),
			'int as string ASC'                => array(
				'priorities'          => array( '9', '10' ),
				'expected_call_order' => array( 'filter', 'filter2' ),
			),
			'float DESC'                       => array(
				'priorities'           => array( 10.0, 9.5 ),
				'expected_call_order'  => array( 'filter2', 'filter' ),
				'expected_deprecation' => 'Implicit conversion from float 9.5 to int loses precision',
			),
			'float ASC'                        => array(
				'priorities'           => array( 9.5, 10.0 ),
				'expected_call_order'  => array( 'filter', 'filter2' ),
				'expected_deprecation' => 'Implicit conversion from float 9.5 to int loses precision',
			),
			'float as string DESC'             => array(
				'priorities'          => array( '10.0', '9.5' ),
				'expected_call_order' => array( 'filter2', 'filter' ),
			),
			'float as string ASC'              => array(
				'priorities'          => array( '9.5', '10.0' ),
				'expected_call_order' => array( 'filter', 'filter2' ),
			),

			// Non-numeric.
			'null'                             => array(
				'priorities'          => array( null, null ),
				'expected_call_order' => array( 'filter', 'filter2' ),
			),
			'bool DESC'                        => array(
				'priorities'          => array( true, false ),
				'expected_call_order' => array( 'filter2', 'filter' ),
			),
			'bool ASC'                         => array(
				'priorities'          => array( false, true ),
				'expected_call_order' => array( 'filter', 'filter2' ),
			),
			'non-numerical string DESC'        => array(
				'priorities'          => array( 'test1', 'test2' ),
				'expected_call_order' => array( 'filter', 'filter2' ),
			),
			'non-numerical string ASC'         => array(
				'priorities'          => array( 'test1', 'test2' ),
				'expected_call_order' => array( 'filter', 'filter2' ),
			),
			'int, non-numerical string DESC'   => array(
				'priorities'          => array( 10, 'test' ),
				'expected_call_order' => array( 'filter2', 'filter' ),
			),
			'int, non-numerical string ASC'    => array(
				'priorities'          => array( 'test', 10 ),
				'expected_call_order' => array( 'filter', 'filter2' ),
			),
			'float, non-numerical string DESC' => array(
				'priorities'          => array( 10.0, 'test' ),
				'expected_call_order' => array( 'filter2', 'filter' ),
			),
			'float, non-numerical string ASC'  => array(
				'priorities'          => array( 'test', 10.0 ),
				'expected_call_order' => array( 'filter', 'filter2' ),
			),
		);
	}

	public function test_all_filter() {
		$a          = new MockAction();
		$hook_name1 = __FUNCTION__ . '_1';
		$hook_name2 = __FUNCTION__ . '_2';
		$val        = __FUNCTION__ . '_val';

		// Add an 'all' filter.
		add_filter( 'all', array( $a, 'filterall' ) );
		// Apply some filters.
		$this->assertSame( $val, apply_filters( $hook_name1, $val ) );
		$this->assertSame( $val, apply_filters( $hook_name2, $val ) );
		$this->assertSame( $val, apply_filters( $hook_name1, $val ) );
		$this->assertSame( $val, apply_filters( $hook_name1, $val ) );

		// Our filter should have been called once for each apply_filters call.
		$this->assertSame( 4, $a->get_call_count() );
		// The right hooks should have been called in order.
		$this->assertSame( array( $hook_name1, $hook_name2, $hook_name1, $hook_name1 ), $a->get_hook_names() );

		remove_filter( 'all', array( $a, 'filterall' ) );
		$this->assertFalse( has_filter( 'all', array( $a, 'filterall' ) ) );
	}

	/**
	 * @ticket 53218
	 */
	public function test_filter_with_ref_value() {
		$obj       = new stdClass();
		$ref       = &$obj;
		$a         = new MockAction();
		$hook_name = __FUNCTION__;

		add_action( $hook_name, array( $a, 'filter' ) );

		$filtered = apply_filters( $hook_name, $ref );

		$args = $a->get_args();
		$this->assertSame( $args[0][0], $obj );
		$this->assertSame( $filtered, $obj );
		// Just in case we don't trust assertSame().
		$obj->foo = true;
		$this->assertNotEmpty( $args[0][0]->foo );
		$this->assertNotEmpty( $filtered->foo );
	}

	/**
	 * @ticket 53218
	 */
	public function test_filter_with_ref_argument() {
		$obj       = new stdClass();
		$ref       = &$obj;
		$a         = new MockAction();
		$hook_name = __FUNCTION__;
		$val       = 'Hello';

		add_action( $hook_name, array( $a, 'filter' ), 10, 2 );

		apply_filters( $hook_name, $val, $ref );

		$args = $a->get_args();
		$this->assertSame( $args[0][1], $obj );
		// Just in case we don't trust assertSame().
		$obj->foo = true;
		$this->assertNotEmpty( $args[0][1]->foo );
	}

	/**
	 * @ticket 39007
	 */
	public function test_current_priority() {
		add_action( 'test_current_priority', array( $this, 'current_priority_action' ), 99 );
		do_action( 'test_current_priority' );
		remove_action( 'test_current_priority', array( $this, 'current_priority_action' ), 99 );

		$this->assertSame( 99, $this->current_priority );
	}

	public function current_priority_action() {
		global $wp_filter;

		$this->current_priority = $wp_filter[ current_filter() ]->current_priority();
	}

	/**
	 * @ticket 39007
	 */
	public function test_other_priority() {
		add_action( 'test_current_priority', array( $this, 'other_priority_action' ), 99 );
		do_action( 'test_current_priority' );
		remove_action( 'test_current_priority', array( $this, 'other_priority_action' ), 99 );

		$this->assertFalse( $this->current_priority );
	}

	public function other_priority_action() {
		global $wp_filter;
		$this->current_priority = $wp_filter['the_content']->current_priority();
	}
}
