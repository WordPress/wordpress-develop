<?php

/**
 * Test add_action().
 *
 * @group hooks
 * @covers ::add_action
 */
class Tests_Hooks_AddAction extends WP_UnitTestCase {
	/**
	 * @ticket 23265
	 */
	public function test_action_callback_representations() {
		$hook_name = __FUNCTION__;

		$this->assertFalse( has_action( $hook_name ) );

		add_action( $hook_name, array( 'Class', 'method' ) );

		$this->assertSame( 10, has_action( $hook_name, array( 'Class', 'method' ) ) );

		$this->assertSame( 10, has_action( $hook_name, 'Class::method' ) );
	}
}
