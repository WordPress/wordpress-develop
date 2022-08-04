<?php

/**
 * Test the remove_filter method of WP_Hook
 *
 * @group hooks
 * @covers WP_Hook::remove_filter
 */
class Tests_Hooks_RemoveFilter extends WP_UnitTestCase {

	public function test_remove_filter_with_function() {
		$callback      = '__return_null';
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );
		$hook->remove_filter( $hook_name, $callback, $priority );

		$this->assertArrayNotHasKey( $priority, $hook->callbacks );
	}

	public function test_remove_filter_with_object() {
		$a             = new MockAction();
		$callback      = array( $a, 'action' );
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );
		$hook->remove_filter( $hook_name, $callback, $priority );

		$this->assertArrayNotHasKey( $priority, $hook->callbacks );
	}

	public function test_remove_filter_with_static_method() {
		$callback      = array( 'MockAction', 'action' );
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );
		$hook->remove_filter( $hook_name, $callback, $priority );

		$this->assertArrayNotHasKey( $priority, $hook->callbacks );
	}

	public function test_remove_filters_with_another_at_same_priority() {
		$callback_one  = '__return_null';
		$callback_two  = '__return_false';
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback_one, $priority, $accepted_args );
		$hook->add_filter( $hook_name, $callback_two, $priority, $accepted_args );

		$hook->remove_filter( $hook_name, $callback_one, $priority );

		$this->assertCount( 1, $hook->callbacks[ $priority ] );
	}

	public function test_remove_filter_with_another_at_different_priority() {
		$callback_one  = '__return_null';
		$callback_two  = '__return_false';
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback_one, $priority, $accepted_args );
		$hook->add_filter( $hook_name, $callback_two, $priority + 1, $accepted_args );

		$hook->remove_filter( $hook_name, $callback_one, $priority );
		$this->assertArrayNotHasKey( $priority, $hook->callbacks );
		$this->assertCount( 1, $hook->callbacks[ $priority + 1 ] );
	}
}
