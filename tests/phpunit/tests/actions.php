<?php

/**
 * Test do_action() and related functions
 *
 * @group hooks
 */
class Tests_Actions extends WP_UnitTestCase {

	/**
	 * Flag to keep track whether a certain filter has been applied.
	 *
	 * Used in the `test_doing_filter_real()` test method.
	 *
	 * @var bool
	 */
	private $apply_testing_filter = false;

	/**
	 * Flag to keep track whether a certain filter has been applied.
	 *
	 * Used in the `test_doing_filter_real()` test method.
	 *
	 * @var bool
	 */
	private $apply_testing_nested_filter = false;

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		// Make sure potentially changed properties are reverted to their default value.
		$this->apply_testing_filter        = false;
		$this->apply_testing_nested_filter = false;

		parent::tear_down();
	}

	/**
	 * @covers ::do_action
	 */
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

	/**
	 * @covers ::remove_action
	 */
	public function test_remove_action() {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;

		add_action( $hook_name, array( &$a, 'action' ) );
		do_action( $hook_name );

		// Make sure our hook was called correctly.
		$this->assertSame( 1, $a->get_call_count() );
		$this->assertSame( array( $hook_name ), $a->get_hook_names() );

		// Now remove the action, do it again, and make sure it's not called this time.
		remove_action( $hook_name, array( &$a, 'action' ) );
		do_action( $hook_name );
		$this->assertSame( 1, $a->get_call_count() );
		$this->assertSame( array( $hook_name ), $a->get_hook_names() );

	}

	/**
	 * @covers ::has_action
	 */
	public function test_has_action() {
		$hook_name = __FUNCTION__;
		$callback  = __FUNCTION__ . '_func';

		$this->assertFalse( has_action( $hook_name, $callback ) );
		$this->assertFalse( has_action( $hook_name ) );

		add_action( $hook_name, $callback );
		$this->assertSame( 10, has_action( $hook_name, $callback ) );
		$this->assertTrue( has_action( $hook_name ) );

		remove_action( $hook_name, $callback );
		$this->assertFalse( has_action( $hook_name, $callback ) );
		$this->assertFalse( has_action( $hook_name ) );
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

	public function test_action_priority() {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;

		add_action( $hook_name, array( &$a, 'action' ), 10 );
		add_action( $hook_name, array( &$a, 'action2' ), 9 );
		do_action( $hook_name );

		// Two events, one per action.
		$this->assertSame( 2, $a->get_call_count() );

		$expected = array(
			// 'action2' is called first because it has priority 9.
			array(
				'action'    => 'action2',
				'hook_name' => $hook_name,
				'tag'       => $hook_name, // Back compat.
				'args'      => array( '' ),
			),
			// 'action' is called second.
			array(
				'action'    => 'action',
				'hook_name' => $hook_name,
				'tag'       => $hook_name, // Back compat.
				'args'      => array( '' ),
			),
		);

		$this->assertSame( $expected, $a->get_events() );
	}

	/**
	 * @covers ::did_action
	 */
	public function test_did_action() {
		$hook_name1 = 'action1';
		$hook_name2 = 'action2';

		// Do action $hook_name1 but not $hook_name2.
		do_action( $hook_name1 );
		$this->assertSame( 1, did_action( $hook_name1 ) );
		$this->assertSame( 0, did_action( $hook_name2 ) );

		// Do action $hook_name2 10 times.
		$count = 10;
		for ( $i = 0; $i < $count; $i++ ) {
			do_action( $hook_name2 );
		}

		// $hook_name1's count hasn't changed, $hook_name2 should be correct.
		$this->assertSame( 1, did_action( $hook_name1 ) );
		$this->assertSame( $count, did_action( $hook_name2 ) );

	}

	/**
	 * @covers ::do_action
	 */
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
	 * @covers ::remove_action
	 */
	public function test_remove_all_action() {
		$a         = new MockAction();
		$hook_name = __FUNCTION__;

		add_action( 'all', array( &$a, 'action' ) );
		$this->assertSame( 10, has_filter( 'all', array( &$a, 'action' ) ) );
		do_action( $hook_name );

		// Make sure our hook was called correctly.
		$this->assertSame( 1, $a->get_call_count() );
		$this->assertSame( array( $hook_name ), $a->get_hook_names() );

		// Now remove the action, do it again, and make sure it's not called this time.
		remove_action( 'all', array( &$a, 'action' ) );
		$this->assertFalse( has_filter( 'all', array( &$a, 'action' ) ) );
		do_action( $hook_name );
		$this->assertSame( 1, $a->get_call_count() );
		$this->assertSame( array( $hook_name ), $a->get_hook_names() );
	}

	/**
	 * @covers ::do_action_ref_array
	 */
	public function test_action_ref_array() {
		$obj       = new stdClass();
		$a         = new MockAction();
		$hook_name = __FUNCTION__;

		add_action( $hook_name, array( &$a, 'action' ) );

		do_action_ref_array( $hook_name, array( &$obj ) );

		$args = $a->get_args();
		$this->assertSame( $args[0][0], $obj );
		// Just in case we don't trust assertSame().
		$obj->foo = true;
		$this->assertNotEmpty( $args[0][0]->foo );
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
		$closure   = static function( $a, $b ) {
			$GLOBALS[ $a ] = $b;
		};
		add_action( $hook_name, $closure, 10, 2 );

		$this->assertSame( 10, has_action( $hook_name, $closure ) );

		$context = array( 'val1', 'val2' );
		do_action( $hook_name, $context[0], $context[1] );

		$this->assertSame( $GLOBALS[ $context[0] ], $context[1] );

		$hook_name2 = __FUNCTION__ . '_2';
		$closure2   = static function() {
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
	 * @ticket 23265
	 *
	 * @covers ::add_action
	 */
	public function test_action_callback_representations() {
		$hook_name = __FUNCTION__;

		$this->assertFalse( has_action( $hook_name ) );

		add_action( $hook_name, array( 'Class', 'method' ) );

		$this->assertSame( 10, has_action( $hook_name, array( 'Class', 'method' ) ) );

		$this->assertSame( 10, has_action( $hook_name, 'Class::method' ) );
	}

	/**
	 * @covers ::remove_action
	 */
	public function test_action_self_removal() {
		add_action( 'test_action_self_removal', array( $this, 'action_self_removal' ) );
		do_action( 'test_action_self_removal' );
		$this->assertSame( 1, did_action( 'test_action_self_removal' ) );
	}

	public function action_self_removal() {
		remove_action( 'test_action_self_removal', array( $this, 'action_self_removal' ) );
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

	/**
	 * @ticket 17817
	 *
	 * This specificaly addresses the concern raised at
	 * https://core.trac.wordpress.org/ticket/17817#comment:52
	 *
	 * @covers ::remove_filter
	 */
	public function test_remove_anonymous_callback() {
		$hook_name = __FUNCTION__;
		$a         = new MockAction();
		add_action( $hook_name, array( $a, 'action' ), 12, 1 );
		$this->assertTrue( has_action( $hook_name ) );

		$hook = $GLOBALS['wp_filter'][ $hook_name ];

		// From http://wordpress.stackexchange.com/a/57088/6445
		foreach ( $hook as $priority => $filter ) {
			foreach ( $filter as $identifier => $function ) {
				if ( is_array( $function )
					&& is_a( $function['function'][0], 'MockAction' )
					&& 'action' === $function['function'][1]
				) {
					remove_filter(
						$hook_name,
						array( $function['function'][0], 'action' ),
						$priority
					);
				}
			}
		}

		$this->assertFalse( has_action( $hook_name ) );
	}


	/**
	 * Test the ArrayAccess methods of WP_Hook
	 *
	 * @ticket 17817
	 *
	 * @covers WP_Hook::offsetGet
	 * @covers WP_Hook::offsetSet
	 * @covers WP_Hook::offsetUnset
	 */
	public function test_array_access_of_wp_filter_global() {
		global $wp_filter;

		$hook_name = __FUNCTION__;

		add_action( $hook_name, '__return_null', 11, 1 );

		$this->assertArrayHasKey( 11, $wp_filter[ $hook_name ] );
		$this->assertArrayHasKey( '__return_null', $wp_filter[ $hook_name ][11] );

		unset( $wp_filter[ $hook_name ][11] );
		$this->assertFalse( has_action( $hook_name, '__return_null' ) );

		$wp_filter[ $hook_name ][11] = array(
			'__return_null' => array(
				'function'      => '__return_null',
				'accepted_args' => 1,
			),
		);
		$this->assertSame( 11, has_action( $hook_name, '__return_null' ) );
	}

	/**
	 * Make sure current_action() behaves as current_filter()
	 *
	 * @ticket 14994
	 *
	 * @covers ::current_action
	 */
	public function test_current_action() {
		global $wp_current_filter;

		$wp_current_filter[] = 'first';
		$wp_current_filter[] = 'second'; // Let's say a second action was invoked.

		$this->assertSame( 'second', current_action() );
	}

	/**
	 * @ticket 14994
	 *
	 * @covers ::doing_filter
	 */
	public function test_doing_filter() {
		global $wp_current_filter;

		$wp_current_filter = array(); // Set to an empty array first.

		$this->assertFalse( doing_filter() );            // No filter is passed in, and no filter is being processed.
		$this->assertFalse( doing_filter( 'testing' ) ); // Filter is passed in but not being processed.

		$wp_current_filter[] = 'testing';

		$this->assertTrue( doing_filter() );                    // No action is passed in, and a filter is being processed.
		$this->assertTrue( doing_filter( 'testing' ) );         // Filter is passed in and is being processed.
		$this->assertFalse( doing_filter( 'something_else' ) ); // Filter is passed in but not being processed.

		$wp_current_filter = array();
	}

	/**
	 * @ticket 14994
	 *
	 * @covers ::doing_filter
	 */
	public function test_doing_action() {
		global $wp_current_filter;

		$wp_current_filter = array(); // Set to an empty array first.

		$this->assertFalse( doing_action() );            // No action is passed in, and no filter is being processed.
		$this->assertFalse( doing_action( 'testing' ) ); // Action is passed in but not being processed.

		$wp_current_filter[] = 'testing';

		$this->assertTrue( doing_action() );                    // No action is passed in, and a filter is being processed.
		$this->assertTrue( doing_action( 'testing' ) );         // Action is passed in and is being processed.
		$this->assertFalse( doing_action( 'something_else' ) ); // Action is passed in but not being processed.

		$wp_current_filter = array();
	}

	/**
	 * @ticket 14994
	 *
	 * @covers ::doing_filter
	 */
	public function test_doing_filter_real() {
		$this->assertFalse( doing_filter() );            // No filter is passed in, and no filter is being processed.
		$this->assertFalse( doing_filter( 'testing' ) ); // Filter is passed in but not being processed.

		add_filter( 'testing', array( $this, 'apply_testing_filter' ) );
		$this->assertTrue( has_action( 'testing' ) );
		$this->assertSame( 10, has_action( 'testing', array( $this, 'apply_testing_filter' ) ) );

		apply_filters( 'testing', '' );

		// Make sure it ran.
		$this->assertTrue( $this->apply_testing_filter );

		$this->assertFalse( doing_filter() );            // No longer doing any filters.
		$this->assertFalse( doing_filter( 'testing' ) ); // No longer doing this filter.
	}

	public function apply_testing_filter() {
		$this->apply_testing_filter = true;

		$this->assertTrue( doing_filter() );
		$this->assertTrue( doing_filter( 'testing' ) );
		$this->assertFalse( doing_filter( 'something_else' ) );
		$this->assertFalse( doing_filter( 'testing_nested' ) );

		add_filter( 'testing_nested', array( $this, 'apply_testing_nested_filter' ) );
		$this->assertTrue( has_action( 'testing_nested' ) );
		$this->assertSame( 10, has_action( 'testing_nested', array( $this, 'apply_testing_nested_filter' ) ) );

		apply_filters( 'testing_nested', '' );

		// Make sure it ran.
		$this->assertTrue( $this->apply_testing_nested_filter );

		$this->assertFalse( doing_filter( 'testing_nested' ) );
		$this->assertFalse( doing_filter( 'testing_nested' ) );
	}

	public function apply_testing_nested_filter() {
		$this->apply_testing_nested_filter = true;
		$this->assertTrue( doing_filter() );
		$this->assertTrue( doing_filter( 'testing' ) );
		$this->assertTrue( doing_filter( 'testing_nested' ) );
		$this->assertFalse( doing_filter( 'something_else' ) );
	}

	/**
	 * @ticket 10441
	 * @expectedDeprecated tests_do_action_deprecated
	 *
	 * @covers ::do_action_deprecated
	 */
	public function test_do_action_deprecated() {
		$p = new WP_Post( (object) array( 'post_title' => 'Foo' ) );

		add_action( 'tests_do_action_deprecated', array( __CLASS__, 'deprecated_action_callback' ) );
		do_action_deprecated( 'tests_do_action_deprecated', array( $p ), '4.6.0' );
		remove_action( 'tests_do_action_deprecated', array( __CLASS__, 'deprecated_action_callback' ) );

		$this->assertSame( 'Bar', $p->post_title );
	}

	public static function deprecated_action_callback( $p ) {
		$p->post_title = 'Bar';
	}

	/**
	 * @ticket 10441
	 * @expectedDeprecated tests_do_action_deprecated
	 *
	 * @covers ::do_action_deprecated
	 */
	public function test_do_action_deprecated_with_multiple_params() {
		$p1 = new WP_Post( (object) array( 'post_title' => 'Foo1' ) );
		$p2 = new WP_Post( (object) array( 'post_title' => 'Foo2' ) );

		add_action( 'tests_do_action_deprecated', array( __CLASS__, 'deprecated_action_callback_multiple_params' ), 10, 2 );
		do_action_deprecated( 'tests_do_action_deprecated', array( $p1, $p2 ), '4.6.0' );
		remove_action( 'tests_do_action_deprecated', array( __CLASS__, 'deprecated_action_callback_multiple_params' ), 10, 2 );

		$this->assertSame( 'Bar1', $p1->post_title );
		$this->assertSame( 'Bar2', $p2->post_title );
	}

	public static function deprecated_action_callback_multiple_params( $p1, $p2 ) {
		$p1->post_title = 'Bar1';
		$p2->post_title = 'Bar2';
	}
}
