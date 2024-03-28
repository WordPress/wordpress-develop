<?php

/**
 * Test remove_all_filters().
 *
 * @group hooks
 * @covers ::remove_all_filters
 */
class Tests_Hooks_RemoveAllFilters extends WP_UnitTestCase {

	/**
	 * @ticket 20920
	 */
	public function test_should_respect_the_priority_argument() {
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
}
