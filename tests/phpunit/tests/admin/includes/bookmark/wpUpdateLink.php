<?php

/**
 * Test the `wp_update_link()` function.
 *
 * @group bookmark
 * @covers ::wp_update_link
 */
class Tests_Admin_Includes_Bookmark_wpUpdateLink extends WP_UnitTestCase {

	/**
	 * Tests that wp_update_link() updates link fields and categories.
	 *
	 * @ticket 66019
	 */
	public function test_wp_update_link_updates_link_data() {
		$link_id     = self::factory()->bookmark->create(
			array(
				'link_name' => 'Original link',
				'link_url'  => 'https://old.example.com',
			)
		);
		$category_id = self::factory()->term->create( array( 'taxonomy' => 'link_category' ) );

		$this->assertSame(
			$link_id,
			wp_update_link(
				array(
					'link_id'       => $link_id,
					'link_name'     => 'Updated link',
					'link_url'      => 'https://new.example.com',
					'link_category' => array( $category_id ),
				)
			)
		);

		$link = get_bookmark( $link_id );
		$this->assertSame( 'Updated link', $link->link_name );
		$this->assertSame( 'https://new.example.com', $link->link_url );
		$this->assertSame( array( $category_id ), wp_get_link_cats( $link_id ) );
	}
}
