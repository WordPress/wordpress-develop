<?php

/**
 * Test the `edit_link()` function.
 *
 * @group bookmark
 * @covers ::edit_link
 */
class Tests_Admin_Includes_Bookmark_editLink extends WP_UnitTestCase {

	/**
	 * Tests that edit_link() updates an existing link from the submitted values.
	 *
	 * @ticket 66019
	 */
	public function test_edit_link_updates_existing_link() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_option( 'link_manager_enabled', 1 );
		wp_set_current_user( $user_id );
		$link_id = self::factory()->bookmark->create(
			array(
				'link_name' => 'Original name',
				'link_url'  => 'https://old.example.com',
			)
		);
		$_POST   = array(
			'link_name'  => 'Updated name',
			'link_url'   => 'https://new.example.com',
			'link_image' => '',
			'link_rss'   => '',
		);

		$this->assertSame( $link_id, edit_link( $link_id ) );
		$this->assertSame( 'Updated name', get_bookmark( $link_id )->link_name );
		$this->assertSame( 'https://new.example.com', get_bookmark( $link_id )->link_url );
	}

	public function tear_down() {
		$_POST = array();
		wp_set_current_user( 0 );
		parent::tear_down();
	}
}
