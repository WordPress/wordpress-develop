<?php

/**
 * Test the `wp_link_manager_disabled_message()` function.
 *
 * @group bookmark
 * @covers ::wp_link_manager_disabled_message
 */
class Tests_Admin_Includes_Bookmark_wpLinkManagerDisabledMessage extends WP_UnitTestCase {

	/**
	 * Tests that wp_link_manager_disabled_message() does nothing on other screens.
	 *
	 * @ticket 66019
	 */
	public function test_wp_link_manager_disabled_message_ignores_other_screens() {
		global $pagenow;

		$pagenow = 'index.php';

		$this->assertNull( wp_link_manager_disabled_message() );
	}
}
