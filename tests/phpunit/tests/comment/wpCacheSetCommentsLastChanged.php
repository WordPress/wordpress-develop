<?php

/**
 * Tests for the wp_cache_set_comments_last_changed() function.
 *
 * @group comment
 * @group cache
 *
 * @covers ::wp_cache_set_comments_last_changed
 */
class Tests_Comment_WpCacheSetCommentsLastChanged extends WP_UnitTestCase {

	/**
	 * A comment used across the tests.
	 *
	 * @var int
	 */
	protected static $comment_id;

	/**
	 * Creates a shared comment before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$comment_id = $factory->comment->create();
	}

	/**
	 * Adding comment meta should update the 'comment' and 'comment-meta'
	 * last changed values but leave 'comment-queries' untouched.
	 *
	 * @ticket 65487
	 */
	public function test_comment_meta_action_updates_comment_and_comment_meta() {
		$comment_before         = wp_cache_get_last_changed( 'comment' );
		$comment_meta_before    = wp_cache_get_last_changed( 'comment-meta' );
		$comment_queries_before = wp_cache_get_last_changed( 'comment-queries' );

		add_comment_meta( self::$comment_id, 'test_key', 'test_value' );

		$this->assertNotSame(
			$comment_before,
			wp_cache_get_last_changed( 'comment' ),
			'The comment last changed value should be updated.'
		);
		$this->assertNotSame(
			$comment_meta_before,
			wp_cache_get_last_changed( 'comment-meta' ),
			'The comment-meta last changed value should be updated.'
		);
		$this->assertSame(
			$comment_queries_before,
			wp_cache_get_last_changed( 'comment-queries' ),
			'The comment-queries last changed value should not be updated.'
		);
	}

	/**
	 * Inserting a comment should update the 'comment' and 'comment-queries'
	 * last changed values but leave 'comment-meta' untouched.
	 *
	 * @ticket 65487
	 */
	public function test_comment_insert_action_updates_comment_and_comment_queries() {
		$comment_before         = wp_cache_get_last_changed( 'comment' );
		$comment_meta_before    = wp_cache_get_last_changed( 'comment-meta' );
		$comment_queries_before = wp_cache_get_last_changed( 'comment-queries' );

		wp_insert_comment(
			array(
				'comment_post_ID' => 0,
				'comment_content' => 'Test comment content.',
			)
		);

		$this->assertNotSame(
			$comment_before,
			wp_cache_get_last_changed( 'comment' ),
			'The comment last changed value should be updated.'
		);
		$this->assertNotSame(
			$comment_queries_before,
			wp_cache_get_last_changed( 'comment-queries' ),
			'The comment-queries last changed value should be updated.'
		);
		$this->assertSame(
			$comment_meta_before,
			wp_cache_get_last_changed( 'comment-meta' ),
			'The comment-meta last changed value should not be updated.'
		);
	}
}
