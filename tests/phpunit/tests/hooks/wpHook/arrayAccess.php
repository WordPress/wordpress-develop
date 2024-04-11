<?php

/**
 * Test ArrayAccess methods of WP_Hook.
 *
 * @group hooks
 * @covers WP_Hook::offsetGet
 * @covers WP_Hook::offsetSet
 * @covers WP_Hook::offsetUnset
 */
class Tests_Hooks_WpHook_ArrayAccess extends WP_UnitTestCase {

	/**
	 * @ticket 17817
	 */
	public function test_array_access() {
		global $wp_filter;

		$hook_name = __FUNCTION__;

		add_action( $hook_name, '__return_null', 11, 1 );

		$this->assertArrayHasKey( 11, $wp_filter[ $hook_name ] );
		$this->assertArrayHasKey( '__return_null', $wp_filter[ $hook_name ][11] );

		unset( $wp_filter[ $hook_name ][11] );
		$this->assertFalse( has_action( $hook_name, '__return_null' ) );

		$wp_filter[ $hook_name ][11] = array(
			'__return_null' => array(
				'function'      => '__return_null',
				'accepted_args' => 1,
			),
		);
		$this->assertSame( 11, has_action( $hook_name, '__return_null' ) );
	}
}
