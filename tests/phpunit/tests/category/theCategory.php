<?php

/**
 * Tests for the_category().
 *
 * @group taxonomy
 * @group category
 *
 * @covers ::the_category
 */
class Tests_Category_TheCategory extends WP_UnitTestCase {

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

		self::$child_id = $factory->category->create(
			array(
				'name'   => 'Child Category',
				'slug'   => 'child-category',
				'parent' => self::$parent_id,
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_category' => array( self::$child_id ),
			)
		);
	}

	/**
	 * Tests that the_category() outputs the unordered category list by default for the global post.
	 *
	 * @ticket 65819
	 */
	public function test_outputs_default_unordered_list_for_global_post() {
		$GLOBALS['post'] = get_post( self::$post_id );

		$this->expectOutputString( get_the_category_list() );

		the_category();
	}

	/**
	 * Tests that the_category() forwards the separator, parents, and post_id parameters
	 * to get_the_category_list().
	 *
	 * @ticket 65819
	 */
	public function test_outputs_category_list_with_separator_parents_and_post_id() {
		$expected = get_the_category_list( ' / ', 'multiple', self::$post_id );

		$this->expectOutputString( $expected );

		the_category( ' / ', 'multiple', self::$post_id );
	}
}
