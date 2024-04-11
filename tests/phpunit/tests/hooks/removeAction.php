<?php

/**
 * Test remove_action().
 *
 * @group hooks
 * @covers ::remove_action
 */
class Tests_Hooks_RemoveAction extends WP_UnitTestCase {

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

	public function test_action_self_removal() {
		add_action( 'test_action_self_removal', array( $this, 'action_self_removal' ) );
		do_action( 'test_action_self_removal' );

		$this->assertSame( 1, did_action( 'test_action_self_removal' ) );
	}

	public function action_self_removal() {
		remove_action( 'test_action_self_removal', array( $this, 'action_self_removal' ) );
	}

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
}
