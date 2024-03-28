<?php

/**
 * Test has_action().
 *
 * @group hooks
 * @covers ::has_action
 */
class Tests_Hooks_HasAction extends WP_UnitTestCase {

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
}
