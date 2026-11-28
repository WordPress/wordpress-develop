<?php

/**
 * Test the `wp_insert_link()` function.
 *
 * @group bookmark
 * @covers ::wp_insert_link
 */
class Tests_Admin_Includes_Bookmark_wpInsertLink extends WP_UnitTestCase {

	/**
	 * Tests that wp_insert_link() inserts a link and assigns its category.
	 *
	 * @ticket 66019
	 */
	public function test_wp_insert_link_inserts_link_with_category() {
		$category_id = self::factory()->term->create( array( 'taxonomy' => 'link_category' ) );

		$link_id = wp_insert_link(
			array(
				'link_name'     => 'Inserted link',
				'link_url'      => 'https://example.com',
				'link_category' => array( $category_id ),
			)
		);

		$this->assertIsInt( $link_id );
		$this->assertSame( 'Inserted link', get_bookmark( $link_id )->link_name );
		$this->assertSame( array( $category_id ), wp_get_link_cats( $link_id ) );
	}

	/**
	 * Tests that wp_insert_link() rejects links without a URL.
	 *
	 * @ticket 66019
	 * @dataProvider data_invalid_link_data
	 *
	 * @param array $link_data Invalid link data.
	 */
	public function test_wp_insert_link_rejects_invalid_data( $link_data ) {
		$this->assertSame( 0, wp_insert_link( $link_data ) );
	}

	/**
	 * Data provider for test_wp_insert_link_rejects_invalid_data().
	 *
	 * @return array<string, array{
	 *     link_data: array<string, string>,
	 * }>
	 */
	public function data_invalid_link_data(): array {
		return array(
			'no URL'         => array(
				'link_data' => array( 'link_name' => 'Missing URL' ),
			),
			'no name or URL' => array(
				'link_data' => array(),
			),
		);
	}
}
