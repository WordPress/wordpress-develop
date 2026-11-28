<?php

/**
 * @group post
 *
 * @covers ::wp_untrash_post
 */
class Tests_Post_WpUntrashPost extends WP_UnitTestCase {
	/**
	 * @var WP_Post
	 */
	protected $trashed_post;

	public function set_up() {
		parent::set_up();

		$this->trashed_post = wp_trash_post(
			$this->factory()->post->create(
				array(
					'post_status' => 'draft',
				)
			)
		);
	}

	/**
	 * Tests that wp_untrash_post() returns a WP_Post object,
	 * removes post meta for an untrashed post and sets it to a 'Draft'.
	 *
	 * @ticket 58392
	 *
	 * @covers ::wp_untrash_post
	 */
	public function test_untrash_post() {
		$result = wp_untrash_post( $this->trashed_post->ID );

		$this->assertInstanceOf( 'WP_Post', $result, 'wp_untrash_post returned value should be an instance of WP_Post.' );

		$trashed = get_posts(
			array(
				'post_status' => 'trash',
				'fields'      => 'ids',
			)
		);

		$this->assertNotContains( $this->trashed_post->ID, $trashed, 'Untrashed post should not belong to trashed posts anymore.' );

		$untrashed_post_metas = get_post_meta( $this->trashed_post->ID );

		$this->assertArrayNotHasKey( '_wp_trash_meta_status', $untrashed_post_metas, 'Untrashed post should not have _wp_trash_meta_status meta anymore.' );
		$this->assertArrayNotHasKey( '_wp_trash_meta_time', $untrashed_post_metas, 'Untrashed post should not have _wp_trash_meta_time meta anymore.' );

		$post = get_post( $this->trashed_post->ID );

		$this->assertSame( 'draft', $post->post_status, 'Untrashed post should have its previous status set correctly.' );
	}

	/**
	 * Tests that wp_untrash_post() applies 'pre_untrash_post' filters
	 * and passes the expected values to callbacks.
	 *
	 * @ticket 58392
	 *
	 * @covers ::wp_untrash_post
	 */
	public function test_pre_untrash_post_hook() {
		add_filter(
			'pre_untrash_post',
			function ( $trash, $post, $previous_status ) {
				$this->assertNull( $trash, 'pre_untrash_post first parameter should be null.' );
				$this->assertSame( $this->trashed_post->ID, $post->ID, 'pre_untrash_post second parameter should be the trashed post ID.' );
				$this->assertSame( $this->trashed_post->post_status, $previous_status, 'pre_untrash_post third parameter should be the previous trashed post status.' );

				return $trash;
			},
			10,
			3
		);

		wp_untrash_post( $this->trashed_post->ID );

		$this->assertGreaterThan( 0, did_filter( 'pre_untrash_post' ), 'pre_untrash_post filter was not called.' );
	}

	/**
	 * Tests that wp_untrash_post() triggers the 'untrash_post' action
	 * and passes the expected values to callbacks.
	 *
	 * @ticket 58392
	 *
	 * @covers ::wp_untrash_post
	 */
	public function test_untrash_post_hook() {
		add_action(
			'untrash_post',
			function ( $post_id, $previous_status ) {
				$this->assertSame( $this->trashed_post->ID, $post_id, 'untrash_post first parameter should be the trashed post ID.' );
				$this->assertSame( $this->trashed_post->post_status, $previous_status, 'untrash_post second parameter should be the previous trashed post status.' );
			},
			10,
			2
		);

		wp_untrash_post( $this->trashed_post->ID );

		$this->assertGreaterThan( 0, did_action( 'untrash_post' ), 'untrash_post action was not called.' );
	}

	/**
	 * Tests that wp_untrash_post() triggers the 'untrashed_post' action
	 * and passes the expected values to callbacks.
	 *
	 * @ticket 58392
	 *
	 * @covers ::wp_untrash_post
	 */
	public function test_untrashed_post_hook() {
		add_action(
			'untrashed_post',
			function ( $post_id, $previous_status ) {
				$this->assertSame( $this->trashed_post->ID, $post_id, 'untrashed_post first parameter should be the trashed post ID.' );
				$this->assertSame( $this->trashed_post->post_status, $previous_status, 'untrashed_post second parameter should be the previous trashed post status.' );
			},
			10,
			2
		);

		wp_untrash_post( $this->trashed_post->ID );

		$this->assertGreaterThan( 0, did_action( 'untrashed_post' ), 'untrashed_post action was not called.' );
	}

	/**
	 * Tests that wp_untrash_post() restores the comments trashed with the post once,
	 * after the post leaves the Trash.
	 *
	 * @ticket 66809
	 * @since 7.3.0
	 */
	public function test_untrash_post_restores_comments_once() {
		$post_id    = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
			)
		);

		wp_trash_post( $post_id );

		$statuses_when_restoring_comments = array();
		add_action(
			'untrash_post_comments',
			static function ( $post_id ) use ( &$statuses_when_restoring_comments ) {
				$statuses_when_restoring_comments[] = get_post_status( $post_id );
			}
		);

		$result = wp_untrash_post( $post_id );

		$this->assertInstanceOf( 'WP_Post', $result, 'wp_untrash_post returned value should be an instance of WP_Post.' );
		$this->assertSame( array( 'draft' ), $statuses_when_restoring_comments, 'Comments should be restored once, after the post left the Trash.' );
		$this->assertSame( 'draft', get_post_status( $post_id ), 'Untrashed post should be a draft.' );
		$this->assertSame( '1', get_comment( $comment_id )->comment_approved, 'The comment should be approved again.' );
		$this->assertSame( array(), get_post_meta( $post_id, '_wp_trash_meta_status' ), 'Untrashed post should not have _wp_trash_meta_status meta anymore.' );
		$this->assertSame( array(), get_post_meta( $post_id, '_wp_trash_meta_time' ), 'Untrashed post should not have _wp_trash_meta_time meta anymore.' );
		$this->assertSame( array(), get_post_meta( $post_id, '_wp_trash_meta_comments_status' ), 'Untrashed post should not have _wp_trash_meta_comments_status meta anymore.' );
	}
}
