<?php

/**
 * @group admin
 * @group bookmark
 *
 * @covers ::wp_set_link_cats
 */
class Tests_Admin_Includes_Bookmark_WpSetLinkCats_Test extends WP_UnitTestCase {

	/**
	 * @ticket 66019
	 */
	public function test_should_replace_the_existing_categories() {
		$term_ids = self::factory()->term->create_many( 2, array( 'taxonomy' => 'link_category' ) );
		$link_id  = self::factory()->bookmark->create( array( 'link_category' => array( $term_ids[0] ) ) );

		wp_set_link_cats( $link_id, array( $term_ids[1] ) );

		$this->assertSame( array( $term_ids[1] ), wp_get_link_cats( $link_id ) );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_cast_category_ids_to_integers() {
		$term_id = self::factory()->term->create( array( 'taxonomy' => 'link_category' ) );
		$link_id = self::factory()->bookmark->create();

		wp_set_link_cats( $link_id, array( (string) $term_id ) );

		$this->assertSame( array( $term_id ), wp_get_link_cats( $link_id ) );
	}

	/**
	 * @ticket 66019
	 *
	 * @dataProvider data_categories_that_fall_back_to_the_default
	 *
	 * @param mixed $link_categories Value passed as the list of link categories.
	 */
	public function test_should_use_the_default_category( $link_categories ) {
		$assigned_id = self::factory()->term->create( array( 'taxonomy' => 'link_category' ) );
		$link_id     = self::factory()->bookmark->create( array( 'link_category' => array( $assigned_id ) ) );

		$default_id = self::factory()->term->create( array( 'taxonomy' => 'link_category' ) );
		update_option( 'default_link_category', $default_id );

		wp_set_link_cats( $link_id, $link_categories );

		$this->assertSame( array( $default_id ), wp_get_link_cats( $link_id ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{link_categories: mixed}>
	 */
	public function data_categories_that_fall_back_to_the_default() {
		return array(
			'an empty array' => array( 'link_categories' => array() ),
			'a string'       => array( 'link_categories' => 'not-an-array' ),
		);
	}
}
