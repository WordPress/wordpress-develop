<?php

/**
 * Test the `add_link()` function.
 *
 * @group bookmark
 * @covers ::add_link
 */
class Tests_Admin_Includes_Bookmark_addLink extends WP_UnitTestCase {

	/**
	 * Tests that add_link() inserts a link from the submitted values.
	 *
	 * @ticket 66019
	 */
	public function test_add_link_inserts_submitted_link() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_option( 'link_manager_enabled', 1 );
		wp_set_current_user( $user_id );
		$_POST = array(
			'link_name'  => 'Example link',
			'link_url'   => 'https://example.com',
			'link_image' => '',
			'link_rss'   => '',
		);

		$link_id = add_link();

		$this->assertIsInt( $link_id );
		$this->assertSame( 'Example link', get_bookmark( $link_id )->link_name );
	}

	public function tear_down() {
		$_POST = array();
		wp_set_current_user( 0 );
		parent::tear_down();
	}
}
