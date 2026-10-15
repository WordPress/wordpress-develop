<?php

/**
 * @group admin
 * @group bookmark
 *
 * @covers ::wp_get_link_cats
 */
class Tests_Admin_Includes_Bookmark_WpGetLinkCats_Test extends WP_UnitTestCase {

	/**
	 * @ticket 66019
	 */
	public function test_should_return_the_link_category_ids() {
		$alpha = self::factory()->term->create(
			array(
				'taxonomy' => 'link_category',
				'name'     => 'Alpha',
			)
		);
		$beta  = self::factory()->term->create(
			array(
				'taxonomy' => 'link_category',
				'name'     => 'Beta',
			)
		);

		$link_id = self::factory()->bookmark->create( array( 'link_category' => array( $alpha, $beta ) ) );

		$this->assertSame( array( $alpha, $beta ), wp_get_link_cats( $link_id ) );
	}
}
