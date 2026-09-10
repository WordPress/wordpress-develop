<?php

/**
 * @group admin
 * @group bookmark
 *
 * @covers ::add_link
 */
class Tests_Admin_Includes_Bookmark_AddLink_Test extends WP_UnitTestCase {

	/**
	 * @ticket 66019
	 */
	public function test_should_insert_a_link_from_the_post_data() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		add_filter( 'pre_option_link_manager_enabled', '__return_true' );

		$_POST = array(
			'link_url'   => 'https://example.com/',
			'link_name'  => 'Example',
			'link_image' => '',
			'link_rss'   => '',
		);

		$link_id = add_link();

		$this->assertIsInt( $link_id, 'The link ID should be an integer.' );
		$this->assertSame( 'Example', get_bookmark( $link_id )->link_name, 'The link name was not stored.' );
	}
}
