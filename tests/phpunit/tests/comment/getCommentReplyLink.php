<?php

/**
 * @group comment
 */
class Tests_Comment_GetCommentReplyLink extends WP_UnitTestCase {
	/**
	 * @ticket 38170
	 */
	public function test_should_return_null_when_max_depth_is_less_than_depth() {
		$args = array(
			'depth'     => 5,
			'max_depth' => 4,
		);

		$this->assertNull( get_comment_reply_link( $args ) );
	}

	/**
	 * @ticket 38170
	 */
	public function test_should_return_null_when_default_max_depth_is_less_than_depth() {
		$args = array(
			'depth' => 5,
		);

		$this->assertNull( get_comment_reply_link( $args ) );
	}

	/**
	 * Ensure comment reply links include post permalink.
	 *
	 * @ticket 47174
	 */
	public function test_get_comment_reply_link_should_include_post_permalink() {
		// Create a sample post.
		$post_id = self::factory()->post->create();

		// Insert comment.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'user_id'         => 1,
			)
		);

		// `depth` and `max_depth` required for reply links to display.
		$comment_reply_link = get_comment_reply_link(
			array(
				'depth'     => 1,
				'max_depth' => 5,
			),
			$comment_id,
			$post_id
		);

		$expected_url = esc_url(
			add_query_arg(
				array(
					'p'          => $post_id,
					'replytocom' => $comment_id,
				),
				home_url( '/#respond' )
			)
		);

		$this->assertContains( $expected_url, $comment_reply_link );
	}

	/**
	 * @ticket 41846
	 */
	public function test_should_return_null_when_depth_less_than_max_depth_and_comment_null_and_no_current_global_comment() {

		// Let max depth be greater than depth and depth be non-zero.
		$args = array(
			'depth'     => 1,
			'max_depth' => 2,
		);

		// Make sure there's no global comment object.
		add_filter( 'get_comment', '__return_null' );

		$actual = get_comment_reply_link( $args );

		$this->assertNull( $actual );
	}

}
