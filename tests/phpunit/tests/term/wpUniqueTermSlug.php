<?php

/**
 * @group taxonomy
 */
class Tests_Term_WpUniqueTermSlug extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();
		register_taxonomy( 'wptests_tax1', 'post', array( 'hierarchical' => false ) );
		register_taxonomy( 'wptests_tax2', 'post', array( 'hierarchical' => true ) );
	}

	public function test_unique_slug_should_be_unchanged() {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wptests_tax1',
				'name'     => 'foo',
				'slug'     => 'foo',
			)
		);

		$actual = wp_unique_term_slug( 'bar', $term );
		$this->assertSame( 'bar', $actual );
	}

	public function test_nonunique_slug_in_different_taxonomy_should_be_unchanged() {
		$term1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'bar',
				'slug'     => 'bar',
			)
		);

		$term2        = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax1',
				'name'     => 'foo',
				'slug'     => 'foo',
			)
		);
		$term2_object = get_term( $term2, 'wptests_tax1' );

		$actual = wp_unique_term_slug( 'bar', $term2_object );
		$this->assertSame( 'bar', $actual );
	}

	public function test_nonunique_slug_in_same_nonhierarchical_taxonomy_should_be_changed() {
		$term1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax1',
				'name'     => 'bar',
				'slug'     => 'bar',
			)
		);

		$term2        = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax1',
				'name'     => 'foo',
				'slug'     => 'foo',
			)
		);
		$term2_object = get_term( $term2, 'wptests_tax1' );

		$actual = wp_unique_term_slug( 'bar', $term2_object );
		$this->assertSame( 'bar-2', $actual );
	}

	public function test_nonunique_slug_in_same_hierarchical_taxonomy_with_same_parent_should_be_suffixed_with_parent_slug() {
		$parent = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'slug'     => 'parent-term',
			)
		);

		$term1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'bar',
				'slug'     => 'bar',
				'parent'   => $parent,
			)
		);

		$term2        = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'foo',
				'slug'     => 'foo',
				'parent'   => $parent,
			)
		);
		$term2_object = get_term( $term2, 'wptests_tax2' );

		$actual = wp_unique_term_slug( 'bar', $term2_object );
		$this->assertSame( 'bar-parent-term', $actual );
	}

	public function test_nonunique_slug_in_same_hierarchical_taxonomy_at_different_level_of_hierarchy_should_be_suffixed_with_number() {
		$parent = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'slug'     => 'parent-term',
			)
		);

		$term1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'bar',
				'slug'     => 'bar',
				'parent'   => $parent,
			)
		);

		$term2        = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'foo',
				'slug'     => 'foo',
			)
		);
		$term2_object = get_term( $term2, 'wptests_tax2' );

		$actual = wp_unique_term_slug( 'bar', $term2_object );
		$this->assertSame( 'bar-2', $actual );
	}

	/**
	 * @ticket 46431
	 */
	public function test_duplicate_parent_suffixed_slug_should_get_numeric_suffix() {
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'Animal',
				'slug'     => 'animal',
			)
		);

		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'Dog',
				'slug'     => 'dog',
			)
		);

		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'Cat',
				'slug'     => 'dog-animal',
				'parent'   => $t1,
			)
		);

		$t4 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'name'     => 'Giraffe',
				'slug'     => 'giraffe',
				'parent'   => $t1,
			)
		);

		$term = get_term( $t4 );

		$slug = wp_unique_term_slug( 'dog', $term );

		$this->assertSame( 'dog-animal-2', $slug );
	}
}
