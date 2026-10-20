<?php

/**
 * Tests for cat_is_ancestor_of().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::cat_is_ancestor_of
 */
class Tests_Category_CatIsAncestorOf extends WP_UnitTestCase {

	/**
	 * Grandparent category object.
	 *
	 * @var WP_Term
	 */
	private static $grandparent;

	/**
	 * Parent category object.
	 *
	 * @var WP_Term
	 */
	private static $parent;

	/**
	 * Child category object.
	 *
	 * @var WP_Term
	 */
	private static $child;

	/**
	 * Unrelated category object.
	 *
	 * @var WP_Term
	 */
	private static $unrelated;

	/**
	 * Creates shared category hierarchy fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$grandparent = $factory->category->create_and_get(
			array(
				'name' => 'Grandparent Category',
			)
		);

		self::$parent = $factory->category->create_and_get(
			array(
				'name'   => 'Parent Category',
				'parent' => self::$grandparent->term_id,
			)
		);

		self::$child = $factory->category->create_and_get(
			array(
				'name'   => 'Child Category',
				'parent' => self::$parent->term_id,
			)
		);

		self::$unrelated = $factory->category->create_and_get(
			array(
				'name' => 'Unrelated Category',
			)
		);
	}

	/**
	 * Tests that cat_is_ancestor_of() returns true for direct and indirect descendants
	 * when given category IDs, WP_Term objects, or a mix of both.
	 *
	 * @ticket 65819
	 */
	public function test_returns_true_for_direct_and_indirect_descendants() {
		// Category IDs.
		$this->assertTrue( cat_is_ancestor_of( self::$parent->term_id, self::$child->term_id ) );
		$this->assertTrue( cat_is_ancestor_of( self::$grandparent->term_id, self::$child->term_id ) );
		$this->assertTrue( cat_is_ancestor_of( self::$grandparent->term_id, self::$parent->term_id ) );

		// Category objects.
		$this->assertTrue( cat_is_ancestor_of( self::$parent, self::$child ) );
		$this->assertTrue( cat_is_ancestor_of( self::$grandparent, self::$child ) );

		// Mixed ID and object inputs.
		$this->assertTrue( cat_is_ancestor_of( self::$parent->term_id, self::$child ) );
		$this->assertTrue( cat_is_ancestor_of( self::$grandparent, self::$child->term_id ) );
	}

	/**
	 * Tests that cat_is_ancestor_of() returns false when the first category is not an ancestor.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_non_ancestors() {
		// Child is not an ancestor of its parent or grandparent.
		$this->assertFalse( cat_is_ancestor_of( self::$child->term_id, self::$parent->term_id ) );
		$this->assertFalse( cat_is_ancestor_of( self::$child->term_id, self::$grandparent->term_id ) );
		$this->assertFalse( cat_is_ancestor_of( self::$child, self::$parent ) );

		// A category is not an ancestor of itself.
		$this->assertFalse( cat_is_ancestor_of( self::$parent->term_id, self::$parent->term_id ) );
		$this->assertFalse( cat_is_ancestor_of( self::$parent, self::$parent ) );

		// Unrelated categories.
		$this->assertFalse( cat_is_ancestor_of( self::$unrelated->term_id, self::$child->term_id ) );
		$this->assertFalse( cat_is_ancestor_of( self::$parent->term_id, self::$unrelated->term_id ) );

		// Non-existent category IDs.
		$this->assertFalse( cat_is_ancestor_of( 99999999, self::$child->term_id ) );
		$this->assertFalse( cat_is_ancestor_of( self::$parent->term_id, 99999999 ) );
	}

	/**
	 * Tests that cat_is_ancestor_of() returns false for hierarchical term IDs from another taxonomy.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_term_ids_from_another_taxonomy() {
		register_taxonomy( 'wptests_tax', 'post', array( 'hierarchical' => true ) );

		$parent_term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);
		$child_term_id  = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'parent'   => $parent_term_id,
			)
		);

		$this->assertFalse( cat_is_ancestor_of( $parent_term_id, $child_term_id ) );
	}
}
