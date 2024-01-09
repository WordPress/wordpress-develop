<?php

/**
 * Test did_action().
 *
 * @group hooks
 * @covers ::did_action
 */
class Tests_Hooks_DidAction extends WP_UnitTestCase {

	public function test_did_action() {
		$hook_name1 = 'action1';
		$hook_name2 = 'action2';

		// Do action $hook_name1 but not $hook_name2.
		do_action( $hook_name1 );
		$this->assertSame( 1, did_action( $hook_name1 ) );
		$this->assertSame( 0, did_action( $hook_name2 ) );

		// Do action $hook_name2 10 times.
		$count = 10;
		for ( $i = 0; $i < $count; $i++ ) {
			do_action( $hook_name2 );
		}

		// $hook_name1's count hasn't changed, $hook_name2 should be correct.
		$this->assertSame( 1, did_action( $hook_name1 ) );
		$this->assertSame( $count, did_action( $hook_name2 ) );
	}
}
