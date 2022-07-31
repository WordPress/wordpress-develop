<?php

/**
 * Test do_action() and related functions
 *
 * @group hooks
 */
class Tests_Actions_Closures extends WP_UnitTestCase {

	/**
	 * @ticket 10493
	 *
	 * @covers ::add_action
	 * @covers ::has_action
	 * @covers ::do_action
	 */
	public function test_action_closure() {
		$hook_name = 'test_action_closure';
		$closure   = static function( $a, $b ) {
			$GLOBALS[ $a ] = $b;
		};
		add_action( $hook_name, $closure, 10, 2 );

		$this->assertSame( 10, has_action( $hook_name, $closure ) );

		$context = array( 'val1', 'val2' );
		do_action( $hook_name, $context[0], $context[1] );

		$this->assertSame( $GLOBALS[ $context[0] ], $context[1] );

		$hook_name2 = 'test_action_closure_2';
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
}
