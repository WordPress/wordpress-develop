<?php

/**
 * @group hooks
 */
class Tests_Actions_Callbacks extends WP_UnitTestCase {

	/**
	 * @ticket 23265
	 *
	 * @covers ::add_action
	 */
	public function test_callback_representations() {
		$hook_name = __FUNCTION__;

		$this->assertFalse( has_action( $hook_name ) );

		add_action( $hook_name, array( 'Class', 'method' ) );

		$this->assertSame( 10, has_action( $hook_name, array( 'Class', 'method' ) ) );

		$this->assertSame( 10, has_action( $hook_name, 'Class::method' ) );
	}
}
