<?php

/**
 * @group admin
 * @group comment
 */
class Tests_Admin_IncludesComment extends WP_UnitTestCase {
	/**
	 * Post ID to add comments to.
	 *
	 * @var int
	 */
	public static $post_id;

	/**
	 * Comment IDs.
	 *
	 * @var array
	 */
	public static $comment_ids = array();

	/**
	 * Create the post and comments for the tests.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();

		self::$comment_ids[] = $factory->comment->create(
			array(
				'comment_author'   => 1,
				'comment_date'     => '2014-05-06 12:00:00',
				'comment_date_gmt' => '2014-05-06 07:00:00',
				'comment_post_ID'  => self::$post_id,
			)
		);

		self::$comment_ids[] = $factory->comment->create(
			array(
				'comment_author'  => 2,
				'comment_date'    => '2004-01-02 12:00:00',
				'comment_post_ID' => self::$post_id,
			)
		);
	}

	/**
	 * Verify that both the comment date and author must match for a comment to exist.
	 *
	 * @covers ::comment_exists
	 */
	public function test_must_match_date_and_author() {
		$this->assertNull( comment_exists( 1, '2004-01-02 12:00:00' ) );
		$this->assertSame( (string) self::$post_id, comment_exists( 1, '2014-05-06 12:00:00' ) );
	}

	/**
	 * @ticket 33871
	 *
	 * @covers ::comment_exists
	 */
	public function test_default_value_of_timezone_should_be_blog() {
		$this->assertSame( (string) self::$post_id, comment_exists( 1, '2014-05-06 12:00:00' ) );
	}

	/**
	 * @ticket 33871
	 *
	 * @covers ::comment_exists
	 */
	public function test_should_respect_timezone_blog() {
		$this->assertSame( (string) self::$post_id, comment_exists( 1, '2014-05-06 12:00:00', 'blog' ) );
	}

	/**
	 * @ticket 33871
	 *
	 * @covers ::comment_exists
	 */
	public function test_should_respect_timezone_gmt() {
		$this->assertSame( (string) self::$post_id, comment_exists( 1, '2014-05-06 07:00:00', 'gmt' ) );
	}

	/**
	 * @ticket 33871
	 *
	 * @covers ::comment_exists
	 */
	public function test_invalid_timezone_should_fall_back_on_blog() {
		$this->assertSame( (string) self::$post_id, comment_exists( 1, '2014-05-06 12:00:00', 'not_a_valid_value' ) );
	}
	/**
	 * Internal comment types are not awaiting moderation, so they must not be
	 * counted as pending.
	 *
	 * @ticket 63191
	 *
	 * @covers ::get_pending_comments_num
	 */
	public function test_get_pending_comments_num_excludes_internal_comment_types() {
		$post_id = self::factory()->post->create();

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '0',
			)
		);

		foreach ( wp_get_internal_comment_types() as $internal_type ) {
			self::factory()->comment->create(
				array(
					'comment_post_ID'  => $post_id,
					'comment_approved' => '0',
					'comment_type'     => $internal_type,
				)
			);
		}

		$this->assertSame( 1, (int) get_pending_comments_num( $post_id ) );
	}

	/**
	 * The array form of the count excludes internal comment types too.
	 *
	 * @ticket 63191
	 *
	 * @covers ::get_pending_comments_num
	 */
	public function test_get_pending_comments_num_for_multiple_posts_excludes_internal_comment_types() {
		$with_comment = self::factory()->post->create();
		$notes_only   = self::factory()->post->create();

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $with_comment,
				'comment_approved' => '0',
			)
		);

		foreach ( wp_get_internal_comment_types() as $internal_type ) {
			self::factory()->comment->create(
				array(
					'comment_post_ID'  => $notes_only,
					'comment_approved' => '0',
					'comment_type'     => $internal_type,
				)
			);
		}

		$counts = get_pending_comments_num( array( $with_comment, $notes_only ) );

		$this->assertSame( 1, (int) $counts[ $with_comment ] );
		$this->assertSame( 0, (int) $counts[ $notes_only ] );
	}
}
