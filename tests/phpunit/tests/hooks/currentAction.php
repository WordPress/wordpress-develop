<?php

/**
 * Test current_action().
 *
 * @group hooks
 * @covers ::current_action
 */
class Tests_Hooks_CurrentAction extends WP_UnitTestCase {

	/**
	 * @ticket 14994
	 */
	public function test_behaves_as_current_filter() {
		global $wp_current_filter;

		$wp_current_filter[] = 'first';
		$wp_current_filter[] = 'second'; // Let's say a second action was invoked.

		$this->assertSame( 'second', current_action() );
	}
}
