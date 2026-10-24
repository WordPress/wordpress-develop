<?php

/**
 * @group taxonomy
 * @covers ::get_term_children
 */
class Tests_Term_GetTermChildren extends WP_UnitTestCase {

	/**
	 * @ticket 66242
	 */
	public function test_should_return_direct_children_before_descendants(): void {
		$parent            = self::factory()->category->create();
		$first_child       = self::factory()->category->create( array( 'parent' => $parent ) );
		$second_child      = self::factory()->category->create( array( 'parent' => $parent ) );
		$first_grandchild  = self::factory()->category->create( array( 'parent' => $first_child ) );
		$second_grandchild = self::factory()->category->create( array( 'parent' => $first_child ) );
		$great_grandchild  = self::factory()->category->create( array( 'parent' => $first_grandchild ) );
		$third_grandchild  = self::factory()->category->create( array( 'parent' => $second_child ) );

		$this->assertSame(
			array( $first_child, $second_child, $first_grandchild, $second_grandchild, $great_grandchild, $third_grandchild ),
			get_term_children( $parent, 'category' )
		);
	}

	/**
	 * @ticket 66242
	 */
	public function test_should_return_an_error_for_an_invalid_taxonomy(): void {
		$result = get_term_children( 0, 'wptests_invalid_taxonomy' );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_taxonomy', $result->get_error_code() );
	}

	/**
	 * @ticket 66242
	 */
	public function test_should_return_an_empty_array_for_a_leaf(): void {
		$parent = self::factory()->category->create();
		$child  = self::factory()->category->create( array( 'parent' => $parent ) );

		$this->assertSame( array(), get_term_children( $child, 'category' ) );
	}

	/**
	 * @ticket 66242
	 */
	public function test_should_observe_hierarchy_changes_between_calls(): void {
		$first_parent  = self::factory()->category->create();
		$second_parent = self::factory()->category->create();
		$child         = self::factory()->category->create( array( 'parent' => $first_parent ) );

		$this->assertSame( array( $child ), get_term_children( $first_parent, 'category' ) );
		$this->assertSame( array(), get_term_children( $second_parent, 'category' ) );

		$grandchild = self::factory()->category->create( array( 'parent' => $child ) );

		$this->assertSame( array( $child, $grandchild ), get_term_children( $first_parent, 'category' ) );

		$updated = wp_update_term( $child, 'category', array( 'parent' => $second_parent ) );
		$this->assertNotWPError( $updated );
		$this->assertSame( array(), get_term_children( $first_parent, 'category' ) );
		$this->assertSame( array( $child, $grandchild ), get_term_children( $second_parent, 'category' ) );

		$this->assertTrue( wp_delete_term( $grandchild, 'category' ) );
		$this->assertSame( array( $child ), get_term_children( $second_parent, 'category' ) );
	}

	/**
	 * @ticket 66242
	 */
	public function test_should_read_the_cached_hierarchy_once_for_all_descendants(): void {
		$parent           = self::factory()->category->create();
		$child            = self::factory()->category->create( array( 'parent' => $parent ) );
		$grandchild       = self::factory()->category->create( array( 'parent' => $child ) );
		$great_grandchild = self::factory()->category->create( array( 'parent' => $grandchild ) );

		// Exclude building the hierarchy from the number of reads during traversal.
		_get_term_hierarchy( 'category' );
		$filter = new MockAction();
		add_filter( 'option_category_children', array( $filter, 'filter' ) );

		$found = get_term_children( $parent, 'category' );

		$this->assertSame( array( $child, $grandchild, $great_grandchild ), $found );
		$this->assertSame( 1, $filter->get_call_count(), 'Descendant traversal should not repeatedly deserialize the hierarchy option.' );
	}

	/**
	 * @ticket 66242
	 *
	 * @covers WP_Term_Query::get_terms
	 */
	public function test_hide_empty_should_retain_a_parent_when_its_nonempty_descendant_is_excluded(): void {
		$parent     = self::factory()->category->create();
		$child      = self::factory()->category->create( array( 'parent' => $parent ) );
		$grandchild = self::factory()->category->create( array( 'parent' => $child ) );
		$post_id    = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_set_post_categories( $post_id, array( $grandchild ) );

		$query = new WP_Term_Query(
			array(
				'taxonomy'      => 'category',
				'include'       => array( $parent ),
				'hide_empty'    => true,
				'cache_results' => false,
			)
		);

		$this->assertSame( array( $parent ), wp_list_pluck( $query->terms, 'term_id' ) );
	}

	/**
	 * @ticket 66242
	 *
	 * @covers WP_Term_Query::get_terms
	 */
	public function test_hide_empty_should_read_each_taxonomy_hierarchy_once(): void {
		$taxonomies = array( 'wptests_hierarchy_a', 'wptests_hierarchy_b' );
		$expected   = array();
		$filters    = array();
		$post_id    = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		foreach ( $taxonomies as $taxonomy ) {
			register_taxonomy( $taxonomy, 'post', array( 'hierarchical' => true ) );
			$parent       = self::factory()->term->create( array( 'taxonomy' => $taxonomy ) );
			$child        = self::factory()->term->create(
				array(
					'taxonomy' => $taxonomy,
					'parent'   => $parent,
				)
			);
			$grandchild   = self::factory()->term->create(
				array(
					'taxonomy' => $taxonomy,
					'parent'   => $child,
				)
			);
			$empty_parent = self::factory()->term->create( array( 'taxonomy' => $taxonomy ) );
			self::factory()->term->create(
				array(
					'taxonomy' => $taxonomy,
					'parent'   => $empty_parent,
				)
			);
			wp_set_object_terms( $post_id, array( $grandchild ), $taxonomy );
			$expected = array_merge( $expected, array( $parent, $child, $grandchild ) );

			_get_term_hierarchy( $taxonomy );
		}

		// Count reads only after both hierarchies and term counts have been populated.
		foreach ( $taxonomies as $taxonomy ) {
			$filters[ $taxonomy ] = new MockAction();
			add_filter( "option_{$taxonomy}_children", array( $filters[ $taxonomy ], 'filter' ) );
		}

		$query = new WP_Term_Query(
			array(
				'taxonomy'      => $taxonomies,
				'hide_empty'    => true,
				'cache_results' => false,
			)
		);

		$this->assertSameSets( $expected, wp_list_pluck( $query->terms, 'term_id' ) );
		foreach ( $taxonomies as $taxonomy ) {
			$this->assertSame( 1, $filters[ $taxonomy ]->get_call_count(), "The {$taxonomy} hierarchy should be loaded once for the query." );
		}
	}

	/**
	 * @ticket 66242
	 *
	 * @covers WP_Term_Query::get_terms
	 */
	public function test_hide_empty_should_observe_a_new_descendant_between_queries(): void {
		$parent = self::factory()->category->create();
		$args   = array(
			'taxonomy'      => 'category',
			'include'       => array( $parent ),
			'hide_empty'    => true,
			'cache_results' => false,
		);
		$query  = new WP_Term_Query( $args );
		$this->assertSame( array(), $query->terms );

		$child   = self::factory()->category->create( array( 'parent' => $parent ) );
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_set_post_categories( $post_id, array( $child ) );

		$query = new WP_Term_Query( $args );
		$this->assertSame( array( $parent ), wp_list_pluck( $query->terms, 'term_id' ) );
	}
}
