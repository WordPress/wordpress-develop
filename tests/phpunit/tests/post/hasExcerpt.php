<?php

/**
 * Tests for has_excerpt().
 *
 * @group post
 *
 * @covers ::has_excerpt
 */
class Tests_Post_HasExcerpt extends WP_UnitTestCase {

	/**
	 * Test post ID with excerpt.
	 *
	 * @var int
	 */
	protected static $post_with_excerpt;

	/**
	 * Test post ID without excerpt.
	 *
	 * @var int
	 */
	protected static $post_without_excerpt;

	/**
	 * Sets up test fixtures before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_with_excerpt = $factory->post->create(
			array(
				'post_title'   => 'Post With Excerpt',
				'post_excerpt' => 'This is an explicit excerpt.',
			)
		);

		self::$post_without_excerpt = $factory->post->create(
			array(
				'post_title'   => 'Post Without Excerpt',
				'post_excerpt' => '',
			)
		);
	}

	/**
	 * Tests that has_excerpt() returns true when passing a post with an excerpt.
	 *
	 * @ticket 65819
	 */
	public function test_has_excerpt_returns_true_with_excerpt() {
		$this->assertTrue( has_excerpt( self::$post_with_excerpt ) );
	}

	/**
	 * Tests that has_excerpt() returns false when a post has no excerpt.
	 *
	 * @ticket 65819
	 */
	public function test_has_excerpt_returns_false_when_empty() {
		$this->assertFalse( has_excerpt( self::$post_without_excerpt ) );
	}

	/**
	 * Tests that has_excerpt() returns false for a non-existent post ID.
	 *
	 * @ticket 65819
	 */
	public function test_has_excerpt_returns_false_for_non_existent_post() {
		$this->assertFalse( has_excerpt( 999999 ) );
	}
}
