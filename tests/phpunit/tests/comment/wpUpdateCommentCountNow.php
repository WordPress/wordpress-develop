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

	/**
	 * A comment type excluded via the shared filter must not inflate the stored count.
	 *
	 * @ticket 65537
	 */
	public function test_filtered_excluded_type_does_not_inflate_count() {
		$post_id = self::factory()->post->create();

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 1,
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'review',
				'comment_approved' => 1,
			)
		);

		// Without exclusion, both approved comments are counted.
		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		$this->assertSame( '2', get_comments_number( $post_id ) );

		// Excluding 'review' through the same filter that hides it from queries drops it from the count.
		$filter = static function ( $types ) {
			$types[] = 'review';
			return $types;
		};
		add_filter( 'default_excluded_comment_types', $filter );
		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		remove_filter( 'default_excluded_comment_types', $filter );

		$this->assertSame( '1', get_comments_number( $post_id ) );
	}

	/**
	 * The count is driven by the filtered set, not a hard-coded 'note' literal.
	 *
	 * Clearing the excluded set causes 'note' comments to be counted, proving the
	 * exclusion comes from the filter rather than an in-query literal.
	 *
	 * @ticket 65537
	 */
	public function test_emptying_filter_counts_otherwise_excluded_types() {
		$post_id = self::factory()->post->create();

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'note',
				'comment_approved' => 1,
			)
		);

		// By default the 'note' type is excluded.
		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		$this->assertSame( '0', get_comments_number( $post_id ) );

		// A plugin that clears the excluded set causes notes to be counted.
		add_filter( 'default_excluded_comment_types', '__return_empty_array' );
		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		remove_filter( 'default_excluded_comment_types', '__return_empty_array' );

		$this->assertSame( '1', get_comments_number( $post_id ) );
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

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'note',
				'comment_approved' => 1,
			)
		);

		add_filter( 'default_excluded_comment_types', '__return_false' );
		$this->assertTrue( wp_update_comment_count_now( $post_id ) );
		remove_filter( 'default_excluded_comment_types', '__return_false' );

		$this->assertSame( '1', get_comments_number( $post_id ) );
	}

	public function _return_100() {
		return 100;
	}
}
