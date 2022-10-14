<?php

/**
 * Test apply_filters() and related functions
 *
 * @group hooks
 */
class Tests_Filters extends WP_UnitTestCase {

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

	public function test_remove_filter() {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;
		$val       = __FUNCTION__ . '_val';

		add_filter( $hook_name, array( $a, 'filter' ) );
		$this->assertSame( $val, apply_filters( $hook_name, $val ) );

		// Make sure our hook was called correctly.
		$this->assertSame( 1, $a->get_call_count() );
		$this->assertSame( array( $hook_name ), $a->get_hook_names() );

		// Now remove the filter, do it again, and make sure it's not called this time.
		remove_filter( $hook_name, array( $a, 'filter' ) );
		$this->assertSame( $val, apply_filters( $hook_name, $val ) );
		$this->assertSame( 1, $a->get_call_count() );
		$this->assertSame( array( $hook_name ), $a->get_hook_names() );

	}

	public function test_has_filter() {
		$hook_name = __FUNCTION__;
		$callback  = __FUNCTION__ . '_func';

		$this->assertFalse( has_filter( $hook_name, $callback ) );
		$this->assertFalse( has_filter( $hook_name ) );

		add_filter( $hook_name, $callback );
		$this->assertSame( 10, has_filter( $hook_name, $callback ) );
		$this->assertTrue( has_filter( $hook_name ) );

		remove_filter( $hook_name, $callback );
		$this->assertFalse( has_filter( $hook_name, $callback ) );
		$this->assertFalse( has_filter( $hook_name ) );
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

	public function test_filter_priority() {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;
		$val       = __FUNCTION__ . '_val';

		// Make two filters with different priorities.
		add_filter( $hook_name, array( $a, 'filter' ), 10 );
		add_filter( $hook_name, array( $a, 'filter2' ), 9 );
		$this->assertSame( $val, apply_filters( $hook_name, $val ) );

		// There should be two events, one per filter.
		$this->assertSame( 2, $a->get_call_count() );

		$expected = array(
			// 'filter2' is called first because it has priority 9.
			array(
				'filter'    => 'filter2',
				'hook_name' => $hook_name,
				'tag'       => $hook_name, // Back compat.
				'args'      => array( $val ),
			),
			// 'filter' is called second.
			array(
				'filter'    => 'filter',
				'hook_name' => $hook_name,
				'tag'       => $hook_name, // Back compat.
				'args'      => array( $val ),
			),
		);

		$this->assertSame( $expected, $a->get_events() );
	}

	/**
	 * @covers ::did_filter
	 */
	public function test_did_filter() {
		$hook_name1 = 'filter1';
		$hook_name2 = 'filter2';
		$val        = __FUNCTION__ . '_val';

		// Apply filter $hook_name1 but not $hook_name2.
		apply_filters( $hook_name1, $val );
		$this->assertSame( 1, did_filter( $hook_name1 ) );
		$this->assertSame( 0, did_filter( $hook_name2 ) );

		// Apply filter $hook_name2 10 times.
		$count = 10;
		for ( $i = 0; $i < $count; $i++ ) {
			apply_filters( $hook_name2, $val );
		}

		// $hook_name1's count hasn't changed, $hook_name2 should be correct.
		$this->assertSame( 1, did_filter( $hook_name1 ) );
		$this->assertSame( $count, did_filter( $hook_name2 ) );

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

	public function test_remove_all_filter() {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;
		$val       = __FUNCTION__ . '_val';

		add_filter( 'all', array( $a, 'filterall' ) );
		$this->assertTrue( has_filter( 'all' ) );
		$this->assertSame( 10, has_filter( 'all', array( $a, 'filterall' ) ) );
		$this->assertSame( $val, apply_filters( $hook_name, $val ) );

		// Make sure our hook was called correctly.
		$this->assertSame( 1, $a->get_call_count() );
		$this->assertSame( array( $hook_name ), $a->get_hook_names() );

		// Now remove the filter, do it again, and make sure it's not called this time.
		remove_filter( 'all', array( $a, 'filterall' ) );
		$this->assertFalse( has_filter( 'all', array( $a, 'filterall' ) ) );
		$this->assertFalse( has_filter( 'all' ) );
		$this->assertSame( $val, apply_filters( $hook_name, $val ) );
		// Call cound should remain at 1.
		$this->assertSame( 1, $a->get_call_count() );
		$this->assertSame( array( $hook_name ), $a->get_hook_names() );
	}

	/**
	 * @ticket 20920
	 */
	public function test_remove_all_filters_should_respect_the_priority_argument() {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;

		add_filter( $hook_name, array( $a, 'filter' ), 12 );
		$this->assertTrue( has_filter( $hook_name ) );

		// Should not be removed.
		remove_all_filters( $hook_name, 11 );
		$this->assertTrue( has_filter( $hook_name ) );

		remove_all_filters( $hook_name, 12 );
		$this->assertFalse( has_filter( $hook_name ) );
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
	 * @ticket 9886
	 */
	public function test_filter_ref_array() {
		$obj       = new stdClass();
		$a         = new MockAction();
		$hook_name = __FUNCTION__;

		add_action( $hook_name, array( $a, 'filter' ) );

		apply_filters_ref_array( $hook_name, array( &$obj ) );

		$args = $a->get_args();
		$this->assertSame( $args[0][0], $obj );
		// Just in case we don't trust assertSame().
		$obj->foo = true;
		$this->assertNotEmpty( $args[0][0]->foo );
	}

	/**
	 * @ticket 12723
	 */
	public function test_filter_ref_array_result() {
		$obj       = new stdClass();
		$a         = new MockAction();
		$b         = new MockAction();
		$hook_name = __FUNCTION__;

		add_action( $hook_name, array( $a, 'filter_append' ), 10, 2 );
		add_action( $hook_name, array( $b, 'filter_append' ), 10, 2 );

		$result = apply_filters_ref_array( $hook_name, array( 'string', &$obj ) );

		$this->assertSame( $result, 'string_append_append' );

		$args = $a->get_args();
		$this->assertSame( $args[0][1], $obj );
		// Just in case we don't trust assertSame().
		$obj->foo = true;
		$this->assertNotEmpty( $args[0][1]->foo );

		$args = $b->get_args();
		$this->assertSame( $args[0][1], $obj );
		// Just in case we don't trust assertSame().
		$obj->foo = true;
		$this->assertNotEmpty( $args[0][1]->foo );

	}

	/**
	 * @ticket 29070
	 */
	public function test_has_filter_after_remove_all_filters() {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;

		// No priority.
		add_filter( $hook_name, array( $a, 'filter' ), 11 );
		add_filter( $hook_name, array( $a, 'filter' ), 12 );
		$this->assertTrue( has_filter( $hook_name ) );

		remove_all_filters( $hook_name );
		$this->assertFalse( has_filter( $hook_name ) );

		// Remove priorities one at a time.
		add_filter( $hook_name, array( $a, 'filter' ), 11 );
		add_filter( $hook_name, array( $a, 'filter' ), 12 );
		$this->assertTrue( has_filter( $hook_name ) );

		remove_all_filters( $hook_name, 11 );
		remove_all_filters( $hook_name, 12 );
		$this->assertFalse( has_filter( $hook_name ) );
	}

	/**
	 * @ticket 10441
	 * @expectedDeprecated tests_apply_filters_deprecated
	 */
	public function test_apply_filters_deprecated() {
		$p = 'Foo';

		add_filter( 'tests_apply_filters_deprecated', array( __CLASS__, 'deprecated_filter_callback' ) );
		$p = apply_filters_deprecated( 'tests_apply_filters_deprecated', array( $p ), '4.6.0' );
		remove_filter( 'tests_apply_filters_deprecated', array( __CLASS__, 'deprecated_filter_callback' ) );

		$this->assertSame( 'Bar', $p );
	}

	public static function deprecated_filter_callback( $p ) {
		$p = 'Bar';
		return $p;
	}

	/**
	 * @ticket 10441
	 * @expectedDeprecated tests_apply_filters_deprecated
	 */
	public function test_apply_filters_deprecated_with_multiple_params() {
		$p1 = 'Foo1';
		$p2 = 'Foo2';

		add_filter( 'tests_apply_filters_deprecated', array( __CLASS__, 'deprecated_filter_callback_multiple_params' ), 10, 2 );
		$p1 = apply_filters_deprecated( 'tests_apply_filters_deprecated', array( $p1, $p2 ), '4.6.0' );
		remove_filter( 'tests_apply_filters_deprecated', array( __CLASS__, 'deprecated_filter_callback_multiple_params' ), 10, 2 );

		$this->assertSame( 'Bar1', $p1 );

		// Not passed by reference, so not modified.
		$this->assertSame( 'Foo2', $p2 );
	}

	public static function deprecated_filter_callback_multiple_params( $p1, $p2 ) {
		$p1 = 'Bar1';
		$p2 = 'Bar2';

		return $p1;
	}

	/**
	 * @ticket 10441
	 */
	public function test_apply_filters_deprecated_without_filter() {
		$val = 'Foobar';

		$this->assertSame( $val, apply_filters_deprecated( 'tests_apply_filters_deprecated', array( $val ), '4.6.0' ) );
	}

	private $current_priority;

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
