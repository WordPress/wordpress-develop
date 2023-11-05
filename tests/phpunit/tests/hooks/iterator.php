<?php

/**
 * Test the Iterator implementation of WP_Hook
 *
 * @group hooks
 * @covers WP_Hook::add_filter
 */
class Tests_Hooks_Iterator extends WP_UnitTestCase {

	public function test_foreach() {
		$callback_one  = '__return_null';
		$callback_two  = '__return_false';
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback_one, $priority, $accepted_args );
		$hook->add_filter( $hook_name, $callback_two, $priority + 1, $accepted_args );

		$functions  = array();
		$priorities = array();
		foreach ( $hook as $key => $callbacks ) {
			$priorities[] = $key;
			foreach ( $callbacks as $function_index => $the_ ) {
				$functions[] = $the_['function'];
			}
		}
		$this->assertSameSets( array( $priority, $priority + 1 ), $priorities );
		$this->assertSameSets( array( $callback_one, $callback_two ), $functions );
	}
}
