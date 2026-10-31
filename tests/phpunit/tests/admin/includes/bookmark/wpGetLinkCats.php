<?php

/**
 * Test the `wp_get_link_cats()` function.
 *
 * @group bookmark
 * @covers ::wp_get_link_cats
 */
class Tests_Admin_Includes_Bookmark_wpGetLinkCats extends WP_UnitTestCase {

	/**
	 * Tests that wp_get_link_cats() returns the IDs of a link's categories.
	 *
	 * @ticket 66019
	 */
	public function test_wp_get_link_cats_returns_category_ids() {
		$link_id      = self::factory()->bookmark->create();
		$category_ids = self::factory()->term->create_many( 2, array( 'taxonomy' => 'link_category' ) );
		wp_set_object_terms( $link_id, $category_ids, 'link_category' );

		$this->assertSameSets( $category_ids, wp_get_link_cats( $link_id ) );
	}
}
