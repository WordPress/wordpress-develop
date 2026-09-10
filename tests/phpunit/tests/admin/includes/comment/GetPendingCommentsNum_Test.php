<?php

/**
 * @group admin
 * @group comment
 *
 * @covers ::get_pending_comments_num
 */
class Admin_Includes_Comment_GetPendingCommentsNum_Test extends WP_UnitTestCase {

	/**
	 * Creates a pending (unapproved) comment of a given type on a post.
	 *
	 * @param int    $post_id      Post to attach the comment to.
	 * @param string $comment_type Comment type slug.
	 * @return int The new comment ID.
	 */
	private function make_pending( $post_id, $comment_type = 'comment' ) {
		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => $comment_type,
				'comment_approved' => '0',
			)
		);
	}

	/**
	 * @ticket 65537
	 */
	public function test_counts_only_pending_comments() {
		$post_id = self::factory()->post->create();
		$this->make_pending( $post_id );
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
			)
		);

		$this->assertSame( 1, get_pending_comments_num( $post_id ) );
	}

	/**
	 * @ticket 65537
	 */
	public function test_excludes_note_type_by_default() {
		$post_id = self::factory()->post->create();
		$this->make_pending( $post_id );
		$this->make_pending( $post_id, 'note' );

		$this->assertSame( 1, get_pending_comments_num( $post_id ) );
	}

	/**
	 * A type added to the excluded set must drop out of the pending count.
	 *
	 * @ticket 65537
	 */
	public function test_excludes_a_filtered_type() {
		$post_id = self::factory()->post->create();
		$this->make_pending( $post_id );
		$this->make_pending( $post_id, 'review' );

		// 'review' is counted by default.
		$this->assertSame( 2, get_pending_comments_num( $post_id ) );

		$filter = static function ( $types ) {
			$types[] = 'review';
			return $types;
		};
		add_filter( 'default_excluded_comment_types', $filter );
		$num = get_pending_comments_num( $post_id );
		remove_filter( 'default_excluded_comment_types', $filter );

		$this->assertSame( 1, $num );
	}

	/**
	 * The exclusion is filter-driven, not a hard-coded 'note' literal.
	 *
	 * @ticket 65537
	 */
	public function test_emptying_filter_counts_note_type() {
		$post_id = self::factory()->post->create();
		$this->make_pending( $post_id, 'note' );

		$this->assertSame( 0, get_pending_comments_num( $post_id ) );

		add_filter( 'default_excluded_comment_types', '__return_empty_array' );
		$num = get_pending_comments_num( $post_id );
		remove_filter( 'default_excluded_comment_types', '__return_empty_array' );

		$this->assertSame( 1, $num );
	}

	/**
	 * A filter callback returning false degrades gracefully to no exclusions.
	 *
	 * Scalar returns are cast to an array and treated as a single excluded type;
	 * only values that normalize to an empty set disable the exclusions.
	 *
	 * @ticket 65537
	 */
	public function test_false_filter_return_counts_all_types() {
		$post_id = self::factory()->post->create();
		$this->make_pending( $post_id, 'note' );

		add_filter( 'default_excluded_comment_types', '__return_false' );
		$num = get_pending_comments_num( $post_id );
		remove_filter( 'default_excluded_comment_types', '__return_false' );

		$this->assertSame( 1, $num );
	}

	/**
	 * @ticket 65537
	 */
	public function test_array_input_returns_counts_keyed_by_post() {
		$post_a = self::factory()->post->create();
		$post_b = self::factory()->post->create();
		$this->make_pending( $post_a );
		$this->make_pending( $post_a, 'note' );
		$this->make_pending( $post_b );
		$this->make_pending( $post_b );

		$counts = get_pending_comments_num( array( $post_a, $post_b ) );

		$this->assertSame(
			array(
				$post_a => 1,
				$post_b => 2,
			),
			$counts
		);
	}
}
