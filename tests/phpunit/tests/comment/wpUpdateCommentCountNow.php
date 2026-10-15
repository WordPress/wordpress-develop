<?php

/**
 * @group comment
 *
 * @covers ::wp_update_comment_count_now
 */
class Tests_Comment_wpUpdateCommentCountNow extends WP_UnitTestCase {

	public function test_invalid_post_bails_early() {
		$this->assertFalse( wp_update_comment_count_now( 100 ) );
		$this->assertFalse( wp_update_comment_count_now( null ) );
		$this->assertFalse( wp_update_comment_count_now( 0 ) );
	}

	public function test_regular_post_updates_comment_count() {
		$post_id = self::factory()->post->create();

		self::factory()->comment->create_post_comments( $post_id, 1 );
		$this->assertSame( '1', get_comments_number( $post_id ) );

		$num_queries = get_num_queries();
		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		$this->assertSame( $num_queries + 2, get_num_queries() );

		$this->assertSame( '1', get_comments_number( $post_id ) );
	}

	public function test_using_filter_adjusts_comment_count_without_an_additional_database_query() {
		global $wpdb;

		add_filter( 'pre_wp_update_comment_count_now', array( $this, '_return_100' ) );

		$post_id = self::factory()->post->create();

		self::factory()->comment->create_post_comments( $post_id, 1 );
		$this->assertSame( '100', get_comments_number( $post_id ) );

		$num_queries = get_num_queries();
		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		// Only one query is made instead of two.
		$this->assertSame( $num_queries + 1, get_num_queries() );

		$this->assertSame( '100', get_comments_number( $post_id ) );

		remove_filter( 'pre_wp_update_comment_count_now', array( $this, '_return_100' ) );
	}

	/**
	 * @ticket 64325
	 */
	public function test_only_approved_regular_comments_are_counted() {
		$post_id = self::factory()->post->create();

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 0,
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 1,
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'note',
				'comment_approved' => 0,
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'note',
				'comment_approved' => 1,
			)
		);

		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		$this->assertSame( '1', get_comments_number( $post_id ) );
	}

	public function _return_100() {
		return 100;
	}

	/**
	 * Counts the comments the front end renders, mirroring the query that
	 * comments_template() builds for the current `thread_comments` setting.
	 *
	 * @param int $post_id Post ID.
	 * @return int Number of comments rendered.
	 */
	private function get_rendered_comment_count( $post_id ) {
		$hierarchical = get_option( 'thread_comments' ) ? 'threaded' : false;

		$query = new WP_Comment_Query(
			array(
				'orderby'       => 'comment_date_gmt',
				'order'         => 'ASC',
				'status'        => 'approve',
				'post_id'       => $post_id,
				'no_found_rows' => false,
				'hierarchical'  => $hierarchical,
			)
		);

		if ( ! $hierarchical ) {
			return count( $query->comments );
		}

		// Trees must be flattened before they're passed to the walker.
		$comments_flat = array();

		foreach ( $query->comments as $comment ) {
			$comments_flat[] = $comment;

			$children = $comment->get_children(
				array(
					'format'  => 'flat',
					'status'  => 'approve',
					'orderby' => 'comment_date_gmt',
				)
			);

			foreach ( $children as $child ) {
				$comments_flat[] = $child;
			}
		}

		return count( $comments_flat );
	}

	/**
	 * Creates 2 top-level comments, 2 replies to the first, and a reply to that first reply.
	 *
	 * @param int $post_id Post ID.
	 * @return int The first top-level comment ID.
	 */
	private function create_comment_tree( $post_id ) {
		$parent_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 1,
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 1,
			)
		);

		$child_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_parent'   => $parent_id,
				'comment_approved' => 1,
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_parent'   => $parent_id,
				'comment_approved' => 1,
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_parent'   => $child_id,
				'comment_approved' => 1,
			)
		);

		return $parent_id;
	}

	/**
	 * Resolves a data_detached_parents() case to a parent comment ID.
	 *
	 * @param bool $on_other_post Whether the parent should exist on a different post.
	 * @return int Parent comment ID.
	 */
	private function get_detached_parent_id( $on_other_post ) {
		if ( ! $on_other_post ) {
			return 999999;
		}

		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::factory()->post->create(),
				'comment_approved' => 1,
			)
		);
	}

	/**
	 * Statuses that remove a comment from the rendered list.
	 *
	 * @return array[]
	 */
	public function data_hidden_parent_statuses() {
		return array(
			'an unapproved parent'  => array( '0' ),
			'a trashed parent'      => array( 'trash' ),
			'a spammed parent'      => array( 'spam' ),
			'a post-trashed parent' => array( 'post-trashed' ),
		);
	}

	/**
	 * Parents that a threaded list cannot reach from `comment_parent = 0`.
	 *
	 * wp_delete_comment() re-parents children, so neither is reachable through the
	 * comment API; both arrive by import or by a direct database write.
	 *
	 * @return array[]
	 */
	public function data_detached_parents() {
		return array(
			'a parent that does not exist' => array( false ),
			'a parent on another post'     => array( true ),
		);
	}

	/**
	 * @return array[]
	 */
	public function data_comment_parent_cycles() {
		return array(
			'a comment that is its own parent'    => array( 1 ),
			'two comments parented to each other' => array( 2 ),
		);
	}

	/**
	 * A hidden parent takes its whole subtree out of a threaded list.
	 *
	 * @ticket 36409
	 *
	 * @dataProvider data_hidden_parent_statuses
	 *
	 * @param string $status Status applied to the parent comment.
	 */
	public function test_hidden_parent_excludes_descendants_when_threaded( $status ) {
		update_option( 'thread_comments', 1 );

		$post_id   = self::factory()->post->create();
		$parent_id = $this->create_comment_tree( $post_id );

		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		$this->assertSame( '5', get_comments_number( $post_id ) );

		wp_update_comment(
			array(
				'comment_ID'       => $parent_id,
				'comment_approved' => $status,
			)
		);

		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		$this->assertSame( 1, $this->get_rendered_comment_count( $post_id ) );
		$this->assertSame( '1', get_comments_number( $post_id ) );
	}

	/**
	 * With threading off, comments_template() queries a flat list of every approved
	 * comment, so replies to a hidden parent are still rendered and still count.
	 *
	 * @ticket 36409
	 *
	 * @dataProvider data_hidden_parent_statuses
	 *
	 * @param string $status Status applied to the parent comment.
	 */
	public function test_hidden_parent_keeps_replies_when_threading_is_disabled( $status ) {
		update_option( 'thread_comments', 0 );

		$post_id   = self::factory()->post->create();
		$parent_id = $this->create_comment_tree( $post_id );

		wp_update_comment(
			array(
				'comment_ID'       => $parent_id,
				'comment_approved' => $status,
			)
		);

		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		$this->assertSame( 4, $this->get_rendered_comment_count( $post_id ) );
		$this->assertSame( '4', get_comments_number( $post_id ) );
	}

	/**
	 * @ticket 36409
	 *
	 * @dataProvider data_detached_parents
	 *
	 * @param bool $on_other_post Whether the parent exists on a different post.
	 */
	public function test_detached_comment_is_not_counted_when_threaded( $on_other_post ) {
		update_option( 'thread_comments', 1 );

		$post_id = self::factory()->post->create();

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_parent'   => $this->get_detached_parent_id( $on_other_post ),
				'comment_approved' => 1,
			)
		);

		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		$this->assertSame( 0, $this->get_rendered_comment_count( $post_id ) );
		$this->assertSame( '0', get_comments_number( $post_id ) );
	}

	/**
	 * @ticket 36409
	 *
	 * @dataProvider data_detached_parents
	 *
	 * @param bool $on_other_post Whether the parent exists on a different post.
	 */
	public function test_detached_comment_is_counted_when_threading_is_disabled( $on_other_post ) {
		update_option( 'thread_comments', 0 );

		$post_id = self::factory()->post->create();

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_parent'   => $this->get_detached_parent_id( $on_other_post ),
				'comment_approved' => 1,
			)
		);

		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		$this->assertSame( 1, $this->get_rendered_comment_count( $post_id ) );
		$this->assertSame( '1', get_comments_number( $post_id ) );
	}

	/**
	 * @ticket 36409
	 *
	 * @dataProvider data_comment_parent_cycles
	 *
	 * @param int $size Number of comments in the cycle.
	 */
	public function test_comment_parent_cycles_are_not_counted( $size ) {
		global $wpdb;

		update_option( 'thread_comments', 1 );

		$post_id     = self::factory()->post->create();
		$comment_ids = self::factory()->comment->create_post_comments( $post_id, $size );

		// Only reachable by writing the column directly; no part of the comment API produces a cycle.
		foreach ( $comment_ids as $index => $comment_id ) {
			$wpdb->update(
				$wpdb->comments,
				array( 'comment_parent' => $comment_ids[ ( $index + 1 ) % $size ] ),
				array( 'comment_ID' => $comment_id )
			);
		}

		clean_comment_cache( $comment_ids );

		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		$this->assertSame( 0, $this->get_rendered_comment_count( $post_id ) );
		$this->assertSame( '0', get_comments_number( $post_id ) );
	}

	/**
	 * `thread_comments_depth` caps nesting, not visibility: Walker_Comment re-displays
	 * deeper replies at the capped depth, so every approved comment still counts.
	 *
	 * @ticket 36409
	 */
	public function test_replies_below_thread_comments_depth_are_still_counted() {
		update_option( 'thread_comments', 1 );
		update_option( 'thread_comments_depth', 2 );

		$post_id   = self::factory()->post->create();
		$parent_id = 0;

		for ( $i = 0; $i < 4; $i++ ) {
			$parent_id = self::factory()->comment->create(
				array(
					'comment_post_ID'  => $post_id,
					'comment_parent'   => $parent_id,
					'comment_approved' => 1,
				)
			);
		}

		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		$this->assertSame( 4, $this->get_rendered_comment_count( $post_id ) );
		$this->assertSame( '4', get_comments_number( $post_id ) );
	}
}
