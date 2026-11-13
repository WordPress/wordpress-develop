<?php

/**
 * Tests for redundant cache invalidation during bulk meta input processing.
 *
 * Verifies that inserting posts, comments, and users with multiple meta entries
 * does not trigger a redundant `last_changed` cache write for every single meta
 * key. The loop should suspend the per-key invalidation and rely on the parent
 * function's existing cache cleanup.
 *
 * @group post
 * @group meta
 * @group cache
 * @ticket 65485
 */
class Tests_Cache_RedundantMetaInvalidation extends WP_UnitTestCase {

	/**
	 * Tracks the number of times the posts "last_changed" cache key is set.
	 *
	 * @var int
	 */
	protected $posts_last_changed_calls = 0;

	/**
	 * Tracks the number of times the comments "last_changed" cache key is set.
	 *
	 * @var int
	 */
	protected $comments_last_changed_calls = 0;

	/**
	 * Tracks the number of times the users "last_changed" cache key is set.
	 *
	 * @var int
	 */
	protected $users_last_changed_calls = 0;

	public function set_up() {
		parent::set_up();

		$this->posts_last_changed_calls    = 0;
		$this->comments_last_changed_calls = 0;
		$this->users_last_changed_calls    = 0;

		// Ensure the last_changed keys are pre-initialized so that reads via
		// wp_cache_get_last_changed() do not trigger a write during the test.
		wp_cache_get_last_changed( 'posts' );
		wp_cache_get_last_changed( 'comment' );
		wp_cache_get_last_changed( 'users' );

		$self = $this;

		add_action(
			'wp_cache_set_last_changed',
			function ( $group ) use ( $self ) {
				switch ( $group ) {
					case 'posts':
						++$self->posts_last_changed_calls;
						break;
					case 'comment':
						++$self->comments_last_changed_calls;
						break;
					case 'users':
						++$self->users_last_changed_calls;
						break;
				}
			}
		);
	}

	/**
	 * Inserting a post with 5 meta_input entries should not trigger a
	 * last_changed cache write for every meta key. Without the fix, this
	 * would fire 5 times during the meta loop.
	 */
	public function test_wp_insert_post_meta_input_avoids_redundant_cache_writes() {
		$meta_count = 5;

		// Insert a baseline post without meta to account for any
		// post-insert side effects that legitimately invalidate the cache.
		$this->posts_last_changed_calls = 0;
		wp_insert_post(
			array(
				'post_title'  => 'Baseline Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);
		$baseline_writes = $this->posts_last_changed_calls;

		$this->posts_last_changed_calls = 0;

		wp_insert_post(
			array(
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
				'meta_input'  => array_fill_keys( array_map( 'strval', range( 1, $meta_count ) ), 'value' ),
			)
		);

		// The number of cache writes should not exceed the baseline (no meta)
		// writes plus a single invalidation for the meta loop.
		$this->assertLessThanOrEqual(
			$baseline_writes + 1,
			$this->posts_last_changed_calls,
			'Bulk meta_input should not trigger a redundant last_changed write per meta key.'
		);
	}

	/**
	 * Inserting a comment with comment_meta should not trigger a redundant
	 * last_changed cache write per meta key.
	 */
	public function test_wp_insert_comment_comment_meta_avoids_redundant_cache_writes() {
		$post_id    = self::factory()->post->create();
		$meta_count = 5;

		// Establish a baseline by inserting a comment without meta.
		$this->comments_last_changed_calls = 0;
		wp_insert_comment(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => 'Baseline comment',
			)
		);
		$baseline_writes = $this->comments_last_changed_calls;

		$this->comments_last_changed_calls = 0;

		wp_insert_comment(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => 'Test comment',
				'comment_meta'    => array_fill_keys( array_map( 'strval', range( 1, $meta_count ) ), 'value' ),
			)
		);

		$this->assertLessThanOrEqual(
			$baseline_writes + 1,
			$this->comments_last_changed_calls,
			'Bulk comment_meta should not trigger a redundant last_changed write per meta key.'
		);
	}

	/**
	 * Inserting a user with meta_input should not trigger a redundant
	 * last_changed cache write per meta key.
	 */
	public function test_wp_insert_user_meta_input_avoids_redundant_cache_writes() {
		$meta_count = 5;

		// Establish a baseline by inserting a user without custom meta_input.
		$this->users_last_changed_calls = 0;
		wp_insert_user(
			array(
				'user_login' => 'baselineuser65485',
				'user_email' => 'baselineuser65485@example.org',
				'user_pass'  => 'password',
				'role'       => 'subscriber',
			)
		);
		$baseline_writes = $this->users_last_changed_calls;

		$this->users_last_changed_calls = 0;

		wp_insert_user(
			array(
				'user_login' => 'testuser65485',
				'user_email' => 'testuser65485@example.org',
				'user_pass'  => 'password',
				'role'       => 'subscriber',
				'meta_input' => array_fill_keys( array_map( 'strval', range( 1, $meta_count ) ), 'value' ),
			)
		);

		$this->assertLessThanOrEqual(
			$baseline_writes + 1,
			$this->users_last_changed_calls,
			'Bulk user meta_input should not trigger a redundant last_changed write per meta key.'
		);
	}

	/**
	 * Updating a comment with comment_meta should not trigger a redundant
	 * last_changed cache write per meta key.
	 */
	public function test_wp_update_comment_comment_meta_avoids_redundant_cache_writes() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => 'Test comment',
			)
		);

		// Establish a baseline by updating the comment without meta.
		$this->comments_last_changed_calls = 0;
		wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => 'Baseline update',
			)
		);
		$baseline_writes = $this->comments_last_changed_calls;

		$this->comments_last_changed_calls = 0;

		wp_update_comment(
			array(
				'comment_ID'   => $comment_id,
				'comment_meta' => array_fill_keys( array_map( 'strval', range( 1, 5 ) ), 'updated_value' ),
			)
		);

		$this->assertLessThanOrEqual(
			$baseline_writes + 1,
			$this->comments_last_changed_calls,
			'Bulk comment_meta updates should not trigger a redundant last_changed write per meta key.'
		);
	}

	/**
	 * Sanity check: without meta_input, a single update_post_meta call still
	 * invalidates the cache. Ensures the suspension is correctly scoped to the
	 * bulk loop only and does not leak.
	 */
	public function test_single_meta_update_still_invalidates_cache() {
		$post_id = self::factory()->post->create();

		$this->posts_last_changed_calls = 0;
		update_post_meta( $post_id, 'solo_key', 'solo_value' );

		$this->assertGreaterThan( 0, $this->posts_last_changed_calls, 'A single meta update should still invalidate the last_changed cache.' );
	}
}
