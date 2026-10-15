<?php

/**
 * Test the `wp_set_link_cats()` function.
 *
 * @group bookmark
 * @covers ::wp_set_link_cats
 */
class Tests_Admin_Includes_Bookmark_wpSetLinkCats extends WP_UnitTestCase {

	/**
	 * Tests that wp_set_link_cats() assigns unique category IDs to a link.
	 *
	 * @ticket 66019
	 */
	public function test_wp_set_link_cats_assigns_unique_categories() {
		$link_id      = self::factory()->bookmark->create();
		$category_ids = self::factory()->term->create_many( 2, array( 'taxonomy' => 'link_category' ) );

		wp_set_link_cats( $link_id, array( $category_ids[0], $category_ids[1], $category_ids[0] ) );

		$this->assertSameSets( $category_ids, wp_get_link_cats( $link_id ) );
	}
}
