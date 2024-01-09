<?php

/**
 * Test remove_filter().
 *
 * @group hooks
 * @covers ::remove_filter
 */
class Tests_Hooks_RemoveFilter extends WP_UnitTestCase {

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
}
