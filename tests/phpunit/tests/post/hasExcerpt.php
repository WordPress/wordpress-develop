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
	 * Tears down test state after each test.
	 */
	public function tear_down() {
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * Tests that has_excerpt() returns true when passing a post ID with an excerpt.
	 *
	 * @ticket 65819
	 */
	public function test_has_excerpt_returns_true_with_post_id() {
		$this->assertTrue( has_excerpt( self::$post_with_excerpt ) );
	}

	/**
	 * Tests that has_excerpt() returns true when passing a post object with an excerpt.
	 *
	 * @ticket 65819
	 */
	public function test_has_excerpt_returns_true_with_post_object() {
		$post = get_post( self::$post_with_excerpt );

		$this->assertTrue( has_excerpt( $post ) );
	}

	/**
	 * Tests that has_excerpt() falls back to global $post when no argument is passed.
	 *
	 * @ticket 65819
	 */
	public function test_has_excerpt_fallback_to_global_post() {
		$GLOBALS['post'] = get_post( self::$post_with_excerpt );

		$this->assertTrue( has_excerpt() );
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

	/**
	 * Tests that has_excerpt() returns false when no post is in scope.
	 *
	 * @ticket 65819
	 */
	public function test_has_excerpt_returns_false_when_no_post_in_scope() {
		$this->assertFalse( has_excerpt() );
	}
}
