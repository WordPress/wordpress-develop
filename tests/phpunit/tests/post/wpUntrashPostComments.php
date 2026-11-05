<?php

/**
 * Tests for wp_untrash_post_comments().
 *
 * @group post
 *
 * @covers ::wp_untrash_post_comments
 */
class Tests_Post_WpUntrashPostComments extends WP_UnitTestCase {

	/**
	 * Creates a comment with a status.
	 *
	 * @param int    $post_id ID of the post.
	 * @param string $status  Value of `comment_approved`.
	 * @return int ID of the comment.
	 */
	private function create_comment( $post_id, $status ) {
		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => $status,
			)
		);
	}

	/**
	 * Tests that nothing is done for a post that does not exist.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_null_for_a_post_that_does_not_exist() {
		$action = new MockAction();
		add_action( 'untrash_post_comments', array( $action, 'action' ) );

		$this->assertNull( wp_untrash_post_comments( 987654321 ) );
		$this->assertSame( 0, $action->get_call_count() );
	}

	/**
	 * Tests that true is returned, and no action fires, when no statuses were stored.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_true_when_there_are_no_stored_statuses() {
		$post_id = self::factory()->post->create();
		$this->create_comment( $post_id, '1' );

		$action = new MockAction();
		add_action( 'untrash_post_comments', array( $action, 'action' ) );
		add_action( 'untrashed_post_comments', array( $action, 'action' ) );

		$this->assertTrue( wp_untrash_post_comments( $post_id ) );
		$this->assertSame( 0, $action->get_call_count() );
	}

	/**
	 * Tests that each comment gets its status back, and the stored statuses are deleted.
	 *
	 * @ticket 66495
	 */
	public function test_should_restore_the_status_of_each_comment() {
		$post_id    = self::factory()->post->create();
		$approved   = $this->create_comment( $post_id, '1' );
		$unapproved = $this->create_comment( $post_id, '0' );
		$spam       = $this->create_comment( $post_id, 'spam' );
		$also_ok    = $this->create_comment( $post_id, '1' );

		wp_trash_post_comments( $post_id );

		$this->assertSame( 'post-trashed', get_comment( $approved )->comment_approved );

		wp_untrash_post_comments( $post_id );

		$this->assertSame( '1', get_comment( $approved )->comment_approved );
		$this->assertSame( '0', get_comment( $unapproved )->comment_approved );
		$this->assertSame( 'spam', get_comment( $spam )->comment_approved );
		$this->assertSame( '1', get_comment( $also_ok )->comment_approved );
		$this->assertSame( array(), get_post_meta( $post_id, '_wp_trash_meta_comments_status' ) );
	}

	/**
	 * Tests that null is returned after the comments were restored.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_null_after_restoring_the_comments() {
		$post_id = self::factory()->post->create();
		$this->create_comment( $post_id, '1' );

		wp_trash_post_comments( $post_id );

		$this->assertNull( wp_untrash_post_comments( $post_id ) );
	}

	/**
	 * Tests that a comment that was stored as trashed with its post becomes unapproved.
	 *
	 * @ticket 66495
	 */
	public function test_should_restore_a_comment_stored_as_post_trashed_as_unapproved() {
		$post_id    = self::factory()->post->create();
		$comment_id = $this->create_comment( $post_id, 'post-trashed' );

		add_post_meta( $post_id, '_wp_trash_meta_comments_status', array( $comment_id => 'post-trashed' ) );

		wp_untrash_post_comments( $post_id );

		$this->assertSame( '0', get_comment( $comment_id )->comment_approved );
	}

	/**
	 * Tests that the actions fire.
	 *
	 * @ticket 66495
	 */
	public function test_should_fire_the_actions() {
		$post_id = self::factory()->post->create();
		$this->create_comment( $post_id, '1' );

		wp_trash_post_comments( $post_id );

		$calls = array();
		foreach ( array( 'untrash_post_comments', 'untrashed_post_comments' ) as $hook ) {
			add_action(
				$hook,
				static function () use ( &$calls, $hook ) {
					$calls[] = array_merge( array( $hook ), func_get_args() );
				}
			);
		}

		wp_untrash_post_comments( $post_id );

		$this->assertSame(
			array(
				array( 'untrash_post_comments', $post_id ),
				array( 'untrashed_post_comments', $post_id ),
			),
			$calls
		);
	}

	/**
	 * Tests that the comments of a post are restored when the post is restored from the trash.
	 *
	 * @ticket 66495
	 */
	public function test_should_be_used_when_a_post_is_restored_from_the_trash() {
		$post_id    = self::factory()->post->create();
		$comment_id = $this->create_comment( $post_id, '1' );

		wp_trash_post( $post_id );
		wp_untrash_post( $post_id );

		$this->assertSame( '1', get_comment( $comment_id )->comment_approved );
	}
}
