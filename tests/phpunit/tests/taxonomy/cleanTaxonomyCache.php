<?php

/**
 * @group taxonomy
 *
 * @covers ::clean_taxonomy_cache
 */
class Tests_Taxonomy_CleanTaxonomyCache extends WP_UnitTestCase {
	/**
	 * Ensure clearing the cache of a non-hierarchical taxonomy doesn't attempt to delete the hierarchy cache.
	 *
	 * @ticket 64090
	 */
	public function test_hierarchy_cache_not_cleared_for_non_hierarchical_taxonomy() {
		// Prime the tag cache by inserting terms.
		wp_insert_term( 'foo', 'post_tag' );

		/*
		 * Determine if delete_option( "{$taxonomy}_children" ) is called.
		 *
		 * None of the actions in delete_option() or _get_term_hierarchy() fire for
		 * non-hierarchical taxonomies, so the query filter needs to be used to determine
		 * if an attempt to delete the option was made.
		 */
		$delete_post_tag_children_called = false;
		add_filter(
			'query',
			static function ( $query ) use ( &$delete_post_tag_children_called ) {
				global $wpdb;
				if ( "SELECT autoload FROM $wpdb->options WHERE option_name = 'post_tag_children'" === $query ) {
					$delete_post_tag_children_called = true;
				}
				return $query;
			}
		);

		clean_taxonomy_cache( 'post_tag' );

		$this->assertFalse( $delete_post_tag_children_called, 'An attempt to clear the post_tag_children option should not be made.' );
	}

	/**
	 * Ensure clearing the cache of a hierarchical taxonomy does attempt to delete the hierarchy cache.
	 *
	 * @ticket 64090
	 */
	public function test_hierarchy_cache_cleared_for_hierarchical_taxonomy() {
		// Prime the category cache by inserting terms.
		wp_insert_term( 'foo', 'category' );

		/*
		 * Determine if delete_option( "{$taxonomy}_children" ) is called.
		 *
		 * None of the actions in delete_option() or _get_term_hierarchy() fire for
		 * non-hierarchical taxonomies, so the query filter needs to be used to determine
		 * if an attempt to delete the option was made.
		 */
		$delete_category_children_called = false;
		add_filter(
			'query',
			static function ( $query ) use ( &$delete_category_children_called ) {
				global $wpdb;
				if ( "SELECT autoload FROM $wpdb->options WHERE option_name = 'category_children'" === $query ) {
					$delete_category_children_called = true;
				}
				return $query;
			}
		);

		clean_taxonomy_cache( 'category' );

		$this->assertTrue( $delete_category_children_called, 'An attempt to clear the category_children option should be made.' );
	}
}
