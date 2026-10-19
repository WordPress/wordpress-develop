<?php

/**
 * Tests for get_the_category().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::get_the_category
 */
class Tests_Category_GetTheCategory extends WP_UnitTestCase {

	/**
	 * Parent category ID.
	 *
	 * @var int
	 */
	private static $parent_id;

	/**
	 * Child category object.
	 *
	 * @var WP_Term
	 */
	private static $category;

	/**
	 * Post ID assigned to the child category.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Creates shared category and post fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$parent_id = $factory->category->create(
			array(
				'name' => 'Parent Category',
				'slug' => 'parent-category',
			)
		);

		self::$category = $factory->category->create_and_get(
			array(
				'name'        => 'Child Category',
				'slug'        => 'child-category',
				'description' => 'Child category description',
				'parent'      => self::$parent_id,
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_category' => array( self::$category->term_id ),
			)
		);
	}

	/**
	 * Tests that get_the_category() returns a zero-indexed array of WP_Term objects
	 * with backwards-compatible properties for a given post ID.
	 *
	 * @ticket 65819
	 */
	public function test_returns_categories_with_compat_properties_for_post_id() {
		$categories = get_the_category( self::$post_id );

		$this->assertCount( 1, $categories );
		$this->assertTrue( array_is_list( $categories ) );
		$this->assertInstanceOf( WP_Term::class, $categories[0] );
		$this->assertSame( self::$category->term_id, $categories[0]->term_id );
		$this->assertSame( self::$category->term_id, $categories[0]->cat_ID );
		$this->assertSame( 1, $categories[0]->category_count );
		$this->assertSame( 'Child category description', $categories[0]->category_description );
		$this->assertSame( 'Child Category', $categories[0]->cat_name );
		$this->assertSame( 'child-category', $categories[0]->category_nicename );
		$this->assertSame( self::$parent_id, $categories[0]->category_parent );
	}

	/**
	 * Tests that get_the_category() defaults to the global post when no post ID is passed.
	 *
	 * @ticket 65819
	 */
	public function test_defaults_to_global_post_when_post_id_is_omitted() {
		$GLOBALS['post'] = get_post( self::$post_id );

		$categories = get_the_category();

		$this->assertCount( 1, $categories );
		$this->assertSame( self::$category->term_id, $categories[0]->term_id );
		$this->assertSame( self::$category->term_id, $categories[0]->cat_ID );
	}

	/**
	 * Tests that get_the_category() returns an empty array when a post has no categories
	 * or when the post does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_returns_empty_array_when_no_categories_or_invalid_post() {
		$unassigned_post_id = self::factory()->post->create();
		wp_set_object_terms( $unassigned_post_id, array(), 'category' );

		$this->assertSame( array(), get_the_category( $unassigned_post_id ) );
		$this->assertSame( array(), get_the_category( 99999999 ) );
		$this->assertSame( array(), get_the_category( 0 ) );
	}

	/**
	 * Tests that get_the_category() applies the 'get_the_categories' filter
	 * with the categories array and post ID.
	 *
	 * @ticket 65819
	 */
	public function test_applies_get_the_categories_filter() {
		$filter = new MockAction();
		add_filter( 'get_the_categories', array( $filter, 'filter' ), 10, 2 );

		$categories = get_the_category( self::$post_id );

		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( $categories, self::$post_id ), $filter->get_args()[0] );
	}
}
