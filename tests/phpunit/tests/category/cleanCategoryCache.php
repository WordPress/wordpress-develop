<?php

/**
 * Tests for clean_category_cache().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::clean_category_cache
 */
class Tests_Category_CleanCategoryCache extends WP_UnitTestCase {

	/**
	 * Tests that clean_category_cache() removes the category from the terms cache
	 * and fires the clean_term_cache action for the 'category' taxonomy.
	 *
	 * @ticket 65819
	 */
	public function test_cleans_cached_category_and_fires_action() {
		$category_id = self::factory()->category->create();

		// Prime the term cache.
		get_category( $category_id );
		$this->assertNotFalse( wp_cache_get( $category_id, 'terms' ) );

		$action = new MockAction();
		add_action( 'clean_term_cache', array( $action, 'action' ), 10, 3 );

		clean_category_cache( $category_id );

		$this->assertFalse( wp_cache_get( $category_id, 'terms' ) );
		$this->assertSame( 1, $action->get_call_count() );
		$this->assertSame(
			array(
				array( $category_id ),
				'category',
				true,
			),
			$action->get_args()[0]
		);
	}

	/**
	 * Tests that clean_category_cache() regenerates the category_children hierarchy option.
	 *
	 * @ticket 65819
	 */
	public function test_regenerates_category_hierarchy_option() {
		$parent_id = self::factory()->category->create();
		$child_id  = self::factory()->category->create( array( 'parent' => $parent_id ) );

		delete_option( 'category_children' );
		$this->assertFalse( get_option( 'category_children' ) );

		clean_category_cache( $parent_id );

		$hierarchy = get_option( 'category_children' );
		$this->assertIsArray( $hierarchy );
		$this->assertArrayHasKey( $parent_id, $hierarchy );
		$this->assertContains( $child_id, $hierarchy[ $parent_id ] );
	}
}
