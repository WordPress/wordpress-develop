<?php

/**
 * Tests for wp_trash_post_comments().
 *
 * @group post
 *
 * @covers ::wp_trash_post_comments
 */
class Tests_Post_WpTrashPostComments extends WP_UnitTestCase {

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
		add_action( 'trash_post_comments', array( $action, 'action' ) );

		$this->assertNull( wp_trash_post_comments( 987654321 ) );
		$this->assertSame( 0, $action->get_call_count() );
	}

	/**
	 * Tests that the first action fires, and the second does not, for a post without comments.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_null_for_a_post_without_comments() {
		$post_id = self::factory()->post->create();

		$before = new MockAction();
		$after  = new MockAction();
		add_action( 'trash_post_comments', array( $before, 'action' ) );
		add_action( 'trashed_post_comments', array( $after, 'action' ) );

		$this->assertNull( wp_trash_post_comments( $post_id ) );
		$this->assertSame( 1, $before->get_call_count() );
		$this->assertSame( 0, $after->get_call_count() );
		$this->assertSame( array(), get_post_meta( $post_id, '_wp_trash_meta_comments_status' ) );
	}

	/**
	 * Tests that the comments are marked as trashed with the post, and that the number of comments is returned.
	 *
	 * @ticket 66495
	 */
	public function test_should_mark_the_comments_as_trashed_with_the_post() {
		$post_id     = self::factory()->post->create();
		$comment_ids = array(
			$this->create_comment( $post_id, '1' ),
			$this->create_comment( $post_id, '0' ),
			$this->create_comment( $post_id, 'spam' ),
		);

		$this->assertSame( 3, wp_trash_post_comments( $post_id ) );

		foreach ( $comment_ids as $comment_id ) {
			$this->assertSame( 'post-trashed', get_comment( $comment_id )->comment_approved );
		}
	}

	/**
	 * Tests that the status of each comment is stored, to restore them later.
	 *
	 * @ticket 66495
	 */
	public function test_should_store_the_status_of_each_comment() {
		$post_id    = self::factory()->post->create();
		$approved   = $this->create_comment( $post_id, '1' );
		$unapproved = $this->create_comment( $post_id, '0' );
		$spam       = $this->create_comment( $post_id, 'spam' );

		wp_trash_post_comments( $post_id );

		$this->assertSame(
			array(
				$approved   => '1',
				$unapproved => '0',
				$spam       => 'spam',
			),
			get_post_meta( $post_id, '_wp_trash_meta_comments_status', true )
		);
	}

	/**
	 * Tests that the comments of other posts are not changed.
	 *
	 * @ticket 66495
	 */
	public function test_should_not_change_the_comments_of_other_posts() {
		$post_id       = self::factory()->post->create();
		$other_post_id = self::factory()->post->create();
		$this->create_comment( $post_id, '1' );
		$other_comment = $this->create_comment( $other_post_id, '1' );

		wp_trash_post_comments( $post_id );

		$this->assertSame( '1', get_comment( $other_comment )->comment_approved );
		$this->assertSame( array(), get_post_meta( $other_post_id, '_wp_trash_meta_comments_status' ) );
	}

	/**
	 * Tests that the actions fire with their arguments.
	 *
	 * @ticket 66495
	 */
	public function test_should_fire_the_actions_with_the_post_id_and_the_statuses() {
		$post_id    = self::factory()->post->create();
		$comment_id = $this->create_comment( $post_id, '1' );

		$calls = array();
		add_action(
			'trash_post_comments',
			static function () use ( &$calls ) {
				$calls[] = array_merge( array( 'trash_post_comments' ), func_get_args() );
			}
		);
		add_action(
			'trashed_post_comments',
			static function () use ( &$calls ) {
				$calls[] = array_merge( array( 'trashed_post_comments' ), func_get_args() );
			},
			10,
			2
		);

		wp_trash_post_comments( $post_id );

		$this->assertSame(
			array(
				array( 'trash_post_comments', $post_id ),
				array( 'trashed_post_comments', $post_id, array( $comment_id => '1' ) ),
			),
			$calls
		);
	}

	/**
	 * Tests that a post object can be passed, and that the post is used when it is not passed.
	 *
	 * @ticket 66495
	 */
	public function test_should_accept_a_post_object_and_the_current_post() {
		$post_id    = self::factory()->post->create();
		$comment_id = $this->create_comment( $post_id, '1' );

		$this->assertSame( 1, wp_trash_post_comments( get_post( $post_id ) ) );
		$this->assertSame( 'post-trashed', get_comment( $comment_id )->comment_approved );

		$GLOBALS['post'] = get_post( self::factory()->post->create() );
		$other_comment   = $this->create_comment( $GLOBALS['post']->ID, '1' );

		$this->assertSame( 1, wp_trash_post_comments() );
		$this->assertSame( 'post-trashed', get_comment( $other_comment )->comment_approved );

		unset( $GLOBALS['post'] );
	}

	/**
	 * Tests that the comments of a post are trashed when the post is trashed.
	 *
	 * @ticket 66495
	 */
	public function test_should_be_used_when_a_post_is_trashed() {
		$post_id    = self::factory()->post->create();
		$comment_id = $this->create_comment( $post_id, '1' );

		wp_trash_post( $post_id );

		$this->assertSame( 'post-trashed', get_comment( $comment_id )->comment_approved );
	}
}
