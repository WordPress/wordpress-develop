<?php

/**
 * @group admin
 * @group bookmark
 *
 * @covers ::wp_update_link
 */
class Tests_Admin_Includes_Bookmark_WpUpdateLink_Test extends WP_UnitTestCase {

	/**
	 * @ticket 66019
	 */
	public function test_should_update_the_link_and_return_its_id() {
		$link_id = self::factory()->bookmark->create( array( 'link_name' => 'Original' ) );

		$result = wp_update_link(
			array(
				'link_id'   => $link_id,
				'link_name' => 'Updated',
			)
		);

		$this->assertSame( $link_id, $result, 'The link ID should be returned.' );
		$this->assertSame( 'Updated', get_bookmark( $link_id )->link_name, 'The link name was not updated.' );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_keep_the_fields_that_are_not_passed() {
		$link_id = self::factory()->bookmark->create(
			array(
				'link_url'         => 'https://example.com/',
				'link_description' => 'A description.',
			)
		);

		wp_update_link(
			array(
				'link_id'   => $link_id,
				'link_name' => 'Updated',
			)
		);

		$link = get_bookmark( $link_id );

		$this->assertSame( 'https://example.com/', $link->link_url, 'The link URL was not kept.' );
		$this->assertSame( 'A description.', $link->link_description, 'The link description was not kept.' );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_keep_the_existing_categories_when_none_are_passed() {
		$term_id = self::factory()->term->create( array( 'taxonomy' => 'link_category' ) );
		$link_id = self::factory()->bookmark->create( array( 'link_category' => array( $term_id ) ) );

		wp_update_link(
			array(
				'link_id'   => $link_id,
				'link_name' => 'Updated',
			)
		);

		$this->assertSame( array( $term_id ), wp_get_link_cats( $link_id ) );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_replace_the_categories_when_they_are_passed() {
		$term_ids = self::factory()->term->create_many( 2, array( 'taxonomy' => 'link_category' ) );
		$link_id  = self::factory()->bookmark->create( array( 'link_category' => array( $term_ids[0] ) ) );

		wp_update_link(
			array(
				'link_id'       => $link_id,
				'link_category' => array( $term_ids[1] ),
			)
		);

		$this->assertSame( array( $term_ids[1] ), wp_get_link_cats( $link_id ) );
	}
}
