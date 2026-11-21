<?php

/**
 * Test the has_filter method of WP_Hook
 *
 * @group hooks
 * @covers WP_Hook::has_filter
 */
class Tests_Hooks_HasFilter extends WP_UnitTestCase {

	/**
	 * @ticket 64186
	 */
	public function test_has_filter_with_function() {
		$callback      = '__return_null';
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority_a    = 1;
		$priority_b    = 10;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback, $priority_a, $accepted_args );
		$hook->add_filter( $hook_name, $callback, $priority_b, $accepted_args );

		$this->assertSame( $priority_a, $hook->has_filter( $hook_name, $callback ) );
		$this->assertTrue( $hook->has_filter( $hook_name, $callback, $priority_a ) );
		$this->assertTrue( $hook->has_filter( $hook_name, $callback, $priority_b ) );
		$hook->remove_filter( $hook_name, $callback, $priority_a );
		$this->assertSame( $priority_b, $hook->has_filter( $hook_name, $callback ) );
	}

	public function test_has_filter_with_object() {
		$a             = new MockAction();
		$callback      = array( $a, 'action' );
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );

		$this->assertSame( $priority, $hook->has_filter( $hook_name, $callback ) );
	}

	public function test_has_filter_with_static_method() {
		$callback      = array( 'MockAction', 'action' );
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );

		$this->assertSame( $priority, $hook->has_filter( $hook_name, $callback ) );
	}

	public function test_has_filter_without_callback() {
		$callback      = '__return_null';
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );

		$this->assertTrue( $hook->has_filter() );
	}

	public function test_not_has_filter_without_callback() {
		$hook = new WP_Hook();
		$this->assertFalse( $hook->has_filter() );
	}

	public function test_not_has_filter_with_callback() {
		$callback  = '__return_null';
		$hook      = new WP_Hook();
		$hook_name = __FUNCTION__;

		$this->assertFalse( $hook->has_filter( $hook_name, $callback ) );
	}

	public function test_has_filter_with_wrong_callback() {
		$callback      = '__return_null';
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );

		$this->assertFalse( $hook->has_filter( $hook_name, '__return_false' ) );
	}
}
