<?php

/**
 * Tests for wp_defer_comment_counting().
 *
 * @group comment
 *
 * @covers ::wp_defer_comment_counting
 */
class Tests_Comment_WpDeferCommentCounting extends WP_UnitTestCase {

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
	 * Tests that deferring can be turned on and off, and that the new state is returned.
	 *
	 * @ticket 66494
	 */
	public function test_should_turn_deferring_on_and_off() {
		$this->assertTrue( wp_defer_comment_counting( true ) );
		$this->assertTrue( wp_defer_comment_counting(), 'The state should be kept.' );

		$this->assertFalse( wp_defer_comment_counting( false ) );
		$this->assertFalse( wp_defer_comment_counting(), 'The state should be kept.' );
	}

	/**
	 * Tests that a value that is not a boolean does not change the state.
	 *
	 * @ticket 66494
	 *
	 * @dataProvider data_values_that_are_not_booleans
	 *
	 * @param mixed $value Value that is not a boolean.
	 */
	public function test_should_ignore_values_that_are_not_booleans( $value ) {
		wp_defer_comment_counting( true );

		$this->assertTrue( wp_defer_comment_counting( $value ) );

		wp_defer_comment_counting( false );

		$this->assertFalse( wp_defer_comment_counting( $value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_values_that_are_not_booleans() {
		return array(
			'null'         => array( null ),
			'integer 1'    => array( 1 ),
			'integer 0'    => array( 0 ),
			'string true'  => array( 'true' ),
			'empty string' => array( '' ),
			'empty array'  => array( array() ),
		);
	}

	/**
	 * Tests that the counts of the posts are updated when deferring is turned off.
	 *
	 * @ticket 66494
	 */
	public function test_should_update_the_counts_when_deferring_is_turned_off() {
		global $wpdb;

		$post_id = self::factory()->post->create();
		self::factory()->comment->create_post_comments( $post_id, 2 );
		$wpdb->update( $wpdb->posts, array( 'comment_count' => 99 ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );

		wp_defer_comment_counting( true );
		wp_update_comment_count( $post_id );

		$this->assertSame( 99, (int) get_post( $post_id )->comment_count, 'The count should not change while deferring.' );

		wp_defer_comment_counting( false );

		$this->assertSame( 2, (int) get_post( $post_id )->comment_count, 'The count should be updated when deferring is turned off.' );
	}

	/**
	 * Tests that turning deferring on again does not update the posts that are in the queue.
	 *
	 * @ticket 66494
	 */
	public function test_should_not_update_queued_posts_when_deferring_is_turned_on_again() {
		$post_id = self::factory()->post->create();

		wp_defer_comment_counting( true );
		wp_update_comment_count( $post_id );

		$action = new MockAction();
		add_action( 'wp_update_comment_count', array( $action, 'action' ), 10, 3 );

		wp_defer_comment_counting( true );

		$this->assertSame( 0, $action->get_call_count(), 'The queue should not be flushed while deferring is on.' );

		wp_defer_comment_counting( false );

		$this->assertSame( 1, $action->get_call_count(), 'The post should still be in the queue, and be updated when deferring is turned off.' );
	}
}
