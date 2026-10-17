<?php

/**
 * Tests for in_category().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::in_category
 */
class Tests_Category_InCategory extends WP_UnitTestCase {

	/**
	 * Assigned category object.
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
	 * Tests that in_category() returns true when the post is in the specified category
	 * by ID, name, slug, or an array of values, and accepts a post ID or WP_Post object.
	 *
	 * @ticket 65819
	 */
	public function test_returns_true_when_post_is_in_matching_category() {
		$this->assertTrue( in_category( self::$category->term_id, self::$post->ID ) );
		$this->assertTrue( in_category( self::$category->name, self::$post->ID ) );
		$this->assertTrue( in_category( self::$category->slug, self::$post->ID ) );
		$this->assertTrue( in_category( array( self::$other_category->slug, self::$category->slug ), self::$post->ID ) );
		$this->assertTrue( in_category( self::$category->term_id, self::$post ) );
	}

	/**
	 * Tests that in_category() returns false when the category parameter is empty,
	 * even if the post has categories assigned.
	 *
	 * @ticket 25706
	 * @ticket 65819
	 *
	 * @dataProvider data_empty_categories
	 *
	 * @param mixed $empty_category Empty category value.
	 */
	public function test_returns_false_when_category_is_empty( $empty_category ) {
		$this->assertFalse( in_category( $empty_category, self::$post ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_categories() {
		return array(
			'empty string' => array( '' ),
			'zero int'     => array( 0 ),
			'zero string'  => array( '0' ),
			'false'        => array( false ),
			'null'         => array( null ),
			'empty array'  => array( array() ),
		);
	}

	/**
	 * Tests that in_category() defaults to the global post when no post parameter is passed.
	 *
	 * @ticket 65819
	 */
	public function test_defaults_to_global_post_when_post_is_omitted() {
		$GLOBALS['post'] = self::$post;

		$this->assertTrue( in_category( self::$category->term_id ) );
		$this->assertFalse( in_category( self::$other_category->term_id ) );
	}

	/**
	 * Tests that in_category() returns false when the post is not in the category
	 * or when the post does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_non_matching_category_or_invalid_post() {
		$this->assertFalse( in_category( self::$other_category->term_id, self::$post->ID ) );
		$this->assertFalse( in_category( self::$other_category->name, self::$post->ID ) );
		$this->assertFalse( in_category( self::$other_category->slug, self::$post->ID ) );
		$this->assertFalse( in_category( self::$category->term_id, 99999999 ) );
	}
}
