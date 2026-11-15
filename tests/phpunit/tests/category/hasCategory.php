<?php

/**
 * Tests for has_category().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::has_category
 */
class Tests_Category_HasCategory extends WP_UnitTestCase {

	/**
	 * Category object.
	 *
	 * @var WP_Term
	 */
	private static $category;

	/**
	 * Unassigned category object.
	 *
	 * @var WP_Term
	 */
	private static $other_category;

	/**
	 * Post object assigned to self::$category.
	 *
	 * @var WP_Post
	 */
	private static $post;

	/**
	 * Creates shared category and post fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$category = $factory->category->create_and_get(
			array(
				'name' => 'Assigned Category',
				'slug' => 'assigned-category',
			)
		);

		self::$other_category = $factory->category->create_and_get(
			array(
				'name' => 'Other Category',
				'slug' => 'other-category',
			)
		);

		self::$post = $factory->post->create_and_get(
			array(
				'post_category' => array( self::$category->term_id ),
			)
		);
	}

	/**
	 * Tests that has_category() returns true when the post has the specified category
	 * by ID, name, slug, or an array of values, and accepts a post ID or WP_Post object.
	 *
	 * @ticket 65819
	 */
	public function test_returns_true_when_post_has_matching_category() {
		$this->assertTrue( has_category( self::$category->term_id, self::$post->ID ) );
		$this->assertTrue( has_category( self::$category->name, self::$post->ID ) );
		$this->assertTrue( has_category( self::$category->slug, self::$post->ID ) );
		$this->assertTrue( has_category( array( self::$other_category->slug, self::$category->slug ), self::$post->ID ) );
		$this->assertTrue( has_category( self::$category->term_id, self::$post ) );
	}

	/**
	 * Tests that has_category() with an empty category check returns true when the post
	 * has at least one category and false when it has no categories.
	 *
	 * @ticket 65819
	 */
	public function test_empty_category_checks_if_post_has_any_category() {
		$this->assertTrue( has_category( '', self::$post->ID ) );

		$unassigned_post_id = self::factory()->post->create();
		wp_set_object_terms( $unassigned_post_id, array(), 'category' );

		$this->assertFalse( has_category( '', $unassigned_post_id ) );
	}

	/**
	 * Tests that has_category() defaults to the global post when no post parameter is passed.
	 *
	 * @ticket 65819
	 */
	public function test_defaults_to_global_post_when_post_is_omitted() {
		$GLOBALS['post'] = self::$post;

		$this->assertTrue( has_category( self::$category->term_id ) );
		$this->assertFalse( has_category( self::$other_category->term_id ) );
	}

	/**
	 * Tests that has_category() returns false when the post does not have the category
	 * or when the post does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_non_matching_category_or_invalid_post() {
		$this->assertFalse( has_category( self::$other_category->term_id, self::$post->ID ) );
		$this->assertFalse( has_category( self::$other_category->name, self::$post->ID ) );
		$this->assertFalse( has_category( self::$other_category->slug, self::$post->ID ) );
		$this->assertFalse( has_category( self::$category->term_id, 99999999 ) );
	}
}
