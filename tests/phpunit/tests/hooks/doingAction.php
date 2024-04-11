<?php

/**
 * Test doing_action().
 *
 * @group hooks
 * @covers ::doing_action
 */
class Tests_Hooks_DoingAction extends WP_UnitTestCase {

	/**
	 * @ticket 14994
	 */
	public function test_doing_action() {
		global $wp_current_filter;

		$wp_current_filter = array(); // Set to an empty array first.

		$this->assertFalse( doing_action() );            // No action is passed in, and no filter is being processed.
		$this->assertFalse( doing_action( 'testing' ) ); // Action is passed in but not being processed.

		$wp_current_filter[] = 'testing';

		$this->assertTrue( doing_action() );                    // No action is passed in, and a filter is being processed.
		$this->assertTrue( doing_action( 'testing' ) );         // Action is passed in and is being processed.
		$this->assertFalse( doing_action( 'something_else' ) ); // Action is passed in but not being processed.

		$wp_current_filter = array();
	}
}
