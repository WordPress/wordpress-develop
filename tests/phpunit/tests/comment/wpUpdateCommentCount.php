<?php

/**
 * Tests for wp_update_comment_count().
 *
 * @group comment
 *
 * @covers ::wp_update_comment_count
 */
class Tests_Comment_WpUpdateCommentCount extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// The state is kept in a static variable, so start from the default and do not leave deferring on.
		wp_defer_comment_counting( false );
	}

	public function tear_down() {
		wp_defer_comment_counting( false );

		parent::tear_down();
	}

	/**
	 * Gives a post a comment count that is not right.
	 *
	 * @param int $post_id ID of the post.
	 * @param int $count   Count to store.
	 */
	private function set_comment_count( $post_id, $count ) {
		global $wpdb;

		$wpdb->update( $wpdb->posts, array( 'comment_count' => $count ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
	}

	/**
	 * Tests that nothing is done without a post ID.
	 *
	 * @ticket 66494
	 *
	 * @dataProvider data_empty_post_ids
	 *
	 * @param mixed $post_id Empty post ID.
	 */
	public function test_should_return_false_without_a_post_id( $post_id ) {
		$this->assertFalse( wp_update_comment_count( $post_id ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_post_ids() {
		return array(
			'null'         => array( null ),
			'zero'         => array( 0 ),
			'empty string' => array( '' ),
			'false'        => array( false ),
		);
	}

	/**
	 * Tests that the count is updated right away when counting is not deferred.
	 *
	 * @ticket 66494
	 */
	public function test_should_update_the_count_when_not_deferred() {
		$post_id = self::factory()->post->create();
		self::factory()->comment->create_post_comments( $post_id, 3 );
		$this->set_comment_count( $post_id, 99 );

		$this->assertTrue( wp_update_comment_count( $post_id ) );
		$this->assertSame( 3, (int) get_post( $post_id )->comment_count );
	}

	/**
	 * Tests that false is returned for a post that does not exist, when counting is not deferred.
	 *
	 * @ticket 66494
	 */
	public function test_should_return_false_for_a_post_that_does_not_exist() {
		$this->assertFalse( wp_update_comment_count( 987654321 ) );
	}

	/**
	 * Tests that the post is queued, and the count is not updated, when counting is deferred.
	 *
	 * @ticket 66494
	 */
	public function test_should_queue_the_post_when_deferred() {
		$post_id = self::factory()->post->create();
		self::factory()->comment->create_post_comments( $post_id, 3 );
		$this->set_comment_count( $post_id, 99 );

		wp_defer_comment_counting( true );

		$this->assertTrue( wp_update_comment_count( $post_id ), 'True should be returned for a post that was queued.' );
		$this->assertSame( 99, (int) get_post( $post_id )->comment_count, 'The count should not change while counting is deferred.' );
	}

	/**
	 * Tests that the queued posts are updated once, and only once, when the queue is flushed.
	 *
	 * @ticket 66494
	 */
	public function test_should_update_each_queued_post_once_when_flushed() {
		$post_1 = self::factory()->post->create();
		$post_2 = self::factory()->post->create();
		self::factory()->comment->create_post_comments( $post_1, 1 );
		self::factory()->comment->create_post_comments( $post_2, 2 );
		$this->set_comment_count( $post_1, 99 );
		$this->set_comment_count( $post_2, 99 );

		wp_defer_comment_counting( true );

		wp_update_comment_count( $post_1 );
		wp_update_comment_count( $post_2 );
		wp_update_comment_count( $post_1 );

		$updated = array();
		add_action(
			'wp_update_comment_count',
			static function ( $post_id ) use ( &$updated ) {
				$updated[] = $post_id;
			}
		);

		wp_defer_comment_counting( false );

		$this->assertSame( array( $post_1, $post_2 ), $updated, 'Each post should be updated once, in the order it was queued.' );
		$this->assertSame( 1, (int) get_post( $post_1 )->comment_count );
		$this->assertSame( 2, (int) get_post( $post_2 )->comment_count );
	}

	/**
	 * Tests that flushing the queue again does not update the posts again.
	 *
	 * @ticket 66494
	 */
	public function test_should_empty_the_queue_when_flushed() {
		$post_id = self::factory()->post->create();

		wp_defer_comment_counting( true );
		wp_update_comment_count( $post_id );
		wp_defer_comment_counting( false );

		$action = new MockAction();
		add_action( 'wp_update_comment_count', array( $action, 'action' ), 10, 3 );

		$this->assertNull( wp_update_comment_count( null, true ) );
		$this->assertSame( 0, $action->get_call_count() );
	}

	/**
	 * Tests that the queued posts are updated when the deferred counts are requested.
	 *
	 * @ticket 66494
	 */
	public function test_should_update_queued_posts_when_deferred_counts_are_requested() {
		$post_id = self::factory()->post->create();
		self::factory()->comment->create_post_comments( $post_id, 2 );
		$this->set_comment_count( $post_id, 99 );

		wp_defer_comment_counting( true );
		wp_update_comment_count( $post_id );

		wp_update_comment_count( null, true );

		$this->assertSame( 2, (int) get_post( $post_id )->comment_count );
	}
}
