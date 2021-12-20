<?php

/**
 * Test the remove_all_filters method of WP_Hook
 *
 * @group hooks
 * @covers WP_Hook::remove_all_filters
 */
class Tests_Hooks_RemoveAllFilters extends WP_UnitTestCase {

	public function test_remove_all_filters() {
		$callback      = '__return_null';
		$hook          = new WP_Hook();
		$tag           = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $tag, $callback, $priority, $accepted_args );

		$hook->remove_all_filters();

		$this->assertFalse( $hook->has_filters() );
	}

	public function test_remove_all_filters_with_priority() {
		$callback_one  = '__return_null';
		$callback_two  = '__return_false';
		$hook          = new WP_Hook();
		$tag           = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $tag, $callback_one, $priority, $accepted_args );
		$hook->add_filter( $tag, $callback_two, $priority + 1, $accepted_args );

		$hook->remove_all_filters( $priority );

		$this->assertFalse( $hook->has_filter( $tag, $callback_one ) );
		$this->assertTrue( $hook->has_filters() );
		$this->assertSame( $priority + 1, $hook->has_filter( $tag, $callback_two ) );
	}
}
