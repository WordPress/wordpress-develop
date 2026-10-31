<?php

/**
 * Tests for cat_is_ancestor_of().
 *
 * @group category
 * @group taxonomy
 *
 * @covers ::cat_is_ancestor_of
 */
class Tests_Category_CatIsAncestorOf extends WP_UnitTestCase {


	public function tear_down() {

		unregister_taxonomy( 'wptests_tax' );

		parent::tear_down();
	}

	/**
	 * Grandparent category ID.
	 *
	 * @var int
	 */
	private static $grandparent_id;

	/**
	 * Parent category ID.
	 *
	 * @var int
	 */
	private static $parent_id;

	/**
	 * Child category ID.
	 *
	 * @var int
	 */
	private static $child_id;

	/**
	 * Unrelated category ID.
	 *
	 * @var int
	 */
	private static $unrelated_id;

	/**
	 * Post tag ID (different taxonomy).
	 *
	 * @var int
	 */
	private static $tag_id;

	/**
	 * Creates category fixtures before tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$grandparent_id = $factory->category->create(
			array(
				'name' => 'Grandparent Category',
				'slug' => 'grandparent-category',
			)
		);

		self::$parent_id = $factory->category->create(
			array(
				'name'   => 'Parent Category',
				'slug'   => 'parent-category',
				'parent' => self::$grandparent_id,
			)
		);

		self::$child_id = $factory->category->create(
			array(
				'name'   => 'Child Category',
				'slug'   => 'child-category',
				'parent' => self::$parent_id,
			)
		);

		self::$unrelated_id = $factory->category->create(
			array(
				'name' => 'Unrelated Category',
				'slug' => 'unrelated-category',
			)
		);

		self::$tag_id = $factory->tag->create(
			array(
				'name' => 'Test Tag',
				'slug' => 'test-tag',
			)
		);
	}

	/**
	 * Tests that cat_is_ancestor_of() returns true for a direct parent category.
	 *
	 * @ticket 65819
	 */
	public function test_returns_true_for_direct_parent() {
		$this->assertTrue( cat_is_ancestor_of( self::$parent_id, self::$child_id ) );
		$this->assertTrue( cat_is_ancestor_of( self::$grandparent_id, self::$parent_id ) );
	}

	/**
	 * Tests that cat_is_ancestor_of() returns true for a grandparent category.
	 *
	 * @ticket 65819
	 */
	public function test_returns_true_for_grandparent() {
		$this->assertTrue( cat_is_ancestor_of( self::$grandparent_id, self::$child_id ) );
	}

	/**
	 * Tests that cat_is_ancestor_of() returns false when cat1 is not an ancestor of cat2.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_when_not_an_ancestor() {
		// Child is not an ancestor of parent or grandparent.
		$this->assertFalse( cat_is_ancestor_of( self::$child_id, self::$parent_id ) );
		$this->assertFalse( cat_is_ancestor_of( self::$child_id, self::$grandparent_id ) );

		// Unrelated category is not an ancestor.
		$this->assertFalse( cat_is_ancestor_of( self::$unrelated_id, self::$child_id ) );
		$this->assertFalse( cat_is_ancestor_of( self::$child_id, self::$unrelated_id ) );
	}

	/**
	 * Tests that cat_is_ancestor_of() returns false when comparing a category to itself.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_same_category() {
		$this->assertFalse( cat_is_ancestor_of( self::$parent_id, self::$parent_id ) );
	}

	/**
	 * Tests that cat_is_ancestor_of() accepts category IDs and WP_Term objects in any combination.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_category_parameter_types
	 *
	 * @param string $parent_type Parameter type for parent category.
	 * @param string $child_type  Parameter type for child category.
	 */
	public function test_accepts_category_objects_and_ids( $parent_type, $child_type ) {
		$parent = ( 'object' === $parent_type ) ? get_term( self::$parent_id, 'category' ) : self::$parent_id;
		$child  = ( 'object' === $child_type ) ? get_term( self::$child_id, 'category' ) : self::$child_id;

		$this->assertTrue( cat_is_ancestor_of( $parent, $child ) );
	}

	/**
	 * Data provider for test_accepts_category_objects_and_ids().
	 *
	 * @return array[]
	 */
	public static function data_category_parameter_types() {
		return array(
			'both WP_Term objects'               => array( 'object', 'object' ),
			'parent ID and child WP_Term object' => array( 'id', 'object' ),
			'parent WP_Term object and child ID' => array( 'object', 'id' ),
		);
	}

	/**
	 * Tests that cat_is_ancestor_of() returns false when either category does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_non_existent_category() {
		$this->assertFalse( cat_is_ancestor_of( 999999, self::$child_id ) );
		$this->assertFalse( cat_is_ancestor_of( self::$parent_id, 999999 ) );
		$this->assertFalse( cat_is_ancestor_of( 999998, 999999 ) );
	}

	/**
	 * Tests that cat_is_ancestor_of() returns false for invalid or empty inputs.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_invalid_inputs
	 *
	 * @param mixed $cat1 Invalid category 1.
	 * @param mixed $cat2 Invalid category 2.
	 */
	public function test_returns_false_for_invalid_inputs( $cat1, $cat2 ) {
		$this->assertFalse( cat_is_ancestor_of( $cat1, $cat2 ) );
	}

	/**
	 * Data provider for test_returns_false_for_invalid_inputs().
	 *
	 * @return array[]
	 */
	public static function data_invalid_inputs() {
		return array(
			'both integer zeros'          => array( 0, 0 ),
			'empty strings'               => array( '', '' ),
			'null values'                 => array( null, null ),
			'boolean false values'        => array( false, false ),
			'valid parent and zero child' => array( 1, 0 ),
			'zero parent and valid child' => array( 0, 1 ),
		);
	}

	/**
	 * Tests that cat_is_ancestor_of() returns false when passed a term from another taxonomy.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_term_from_different_taxonomy() {
		$this->assertFalse( cat_is_ancestor_of( self::$tag_id, self::$child_id ) );
		$this->assertFalse( cat_is_ancestor_of( self::$parent_id, self::$tag_id ) );
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
