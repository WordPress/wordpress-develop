<?php

/**
 * Test the `wp_delete_link()` function.
 *
 * @group bookmark
 * @covers ::wp_delete_link
 */
class Tests_Admin_Includes_Bookmark_wpDeleteLink extends WP_UnitTestCase {

	/**
	 * Tests that wp_delete_link() removes the link and its category relationships.
	 *
	 * @ticket 66019
	 */
	public function test_wp_delete_link_removes_link_and_categories() {
		$link_id     = self::factory()->bookmark->create();
		$category_id = self::factory()->term->create( array( 'taxonomy' => 'link_category' ) );
		wp_set_object_terms( $link_id, $category_id, 'link_category' );
		add_action( 'delete_link', array( $this, 'record_deleted_link' ) );
		add_action( 'deleted_link', array( $this, 'record_deleted_link' ) );

		$this->assertTrue( wp_delete_link( $link_id ) );
		$this->assertNull( get_bookmark( $link_id ) );
		$this->assertSame( array(), wp_get_link_cats( $link_id ) );
		$this->assertSame( array( $link_id, $link_id ), $this->deleted_link_ids );
	}

	/**
	 * Records a link ID passed to a deletion action.
	 *
	 * @param int $link_id Deleted link ID.
	 */
	public function record_deleted_link( $link_id ) {
		$this->deleted_link_ids[] = $link_id;
	}

	/**
	 * Link IDs recorded by the deletion hooks.
	 *
	 * @var int[]
	 */
	private $deleted_link_ids = array();
}
