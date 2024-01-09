<?php

/**
 * Test do_action_ref_array().
 *
 * @group hooks
 * @covers ::do_action_ref_array
 */
class Tests_Hooks_DoActionRefArray extends WP_UnitTestCase {

	public function test_action_ref_array() {
		$obj       = new stdClass();
		$a         = new MockAction();
		$hook_name = __FUNCTION__;

		add_action( $hook_name, array( &$a, 'action' ) );

		do_action_ref_array( $hook_name, array( &$obj ) );

		$args = $a->get_args();
		$this->assertSame( $args[0][0], $obj );

		// Just in case we don't trust assertSame().
		$obj->foo = true;
		$this->assertNotEmpty( $args[0][0]->foo );
	}
}
