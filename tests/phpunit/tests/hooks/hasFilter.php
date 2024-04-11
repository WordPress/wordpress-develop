<?php

/**
 * Test has_filter().
 *
 * @group hooks
 * @covers ::has_filter
 */
class Tests_Hooks_HasFilter extends WP_UnitTestCase {

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
}
