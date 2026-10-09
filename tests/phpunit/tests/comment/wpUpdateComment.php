<?php

/**
 * @group comment
 *
 * @covers ::wp_update_comment
 */
class Tests_Comment_WpUpdateComment extends WP_UnitTestCase {

	protected static $post_id;
	protected static $comment_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id    = $factory->post->create();
		self::$comment_id = $factory->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
			)
		);
	}

	/**
	 * @ticket 36564
	 *
	 * @covers ::wp_update_comment
	 */
	public function test_wp_update_comment_modified_meta_is_valid_mysql_datetime() {
		wp_update_comment(
			array(
				'comment_ID'      => self::$comment_id,
				'comment_content' => 'Check datetime format.',
			)
		);

		$comment_modified     = get_comment_meta( self::$comment_id, 'comment_modified', true );
		$comment_modified_gmt = get_comment_meta( self::$comment_id, 'comment_modified_gmt', true );

		// MySQL datetime format: YYYY-MM-DD HH:MM:SS
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			$comment_modified,
			'comment_modified should be in MySQL datetime format.'
		);
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			$comment_modified_gmt,
			'comment_modified_gmt should be in MySQL datetime format.'
		);
	}

	/**
	 * @ticket 36564
	 *
	 * @covers ::wp_update_comment
	 */
	public function test_wp_update_comment_does_not_store_modified_meta_on_invalid_comment() {
		// Use a non-existent comment ID.
		$result = wp_update_comment(
			array(
				'comment_ID'      => 999999,
				'comment_content' => 'Should not be stored.',
			)
		);

		$this->assertFalse( $result, 'wp_update_comment() should return false for an invalid comment ID.' );

		$comment_modified = get_comment_meta( 999999, 'comment_modified', true );
		$this->assertEmpty( $comment_modified, 'comment_modified meta should not be set for an invalid comment.' );
	}

	/**
	 * @ticket 36564
	 *
	 * @covers ::get_comment_modified_date
	 */
	public function test_get_comment_modified_date_returns_formatted_date() {
		wp_update_comment(
			array(
				'comment_ID'      => self::$comment_id,
				'comment_content' => 'For modified date test.',
			)
		);

		$date = get_comment_modified_date( 'Y-m-d', self::$comment_id );

		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}$/',
			$date,
			'get_comment_modified_date() should return a date formatted as Y-m-d.'
		);
	}

	/**
	 * @ticket 36564
	 *
	 * @covers ::get_comment_modified_date
	 */
	public function test_get_comment_modified_date_returns_empty_for_unmodified_comment() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
			)
		);

		// Do not call wp_update_comment() — no meta should exist.
		$date = get_comment_modified_date( 'Y-m-d', $comment_id );

		$this->assertSame( '', $date, 'get_comment_modified_date() should return empty string when the comment has never been modified.' );
	}

	/**
	 * @ticket 36564
	 *
	 * @covers ::get_comment_modified_time
	 */
	public function test_get_comment_modified_time_returns_formatted_time() {
		wp_update_comment(
			array(
				'comment_ID'      => self::$comment_id,
				'comment_content' => 'For modified time test.',
			)
		);

		$time = get_comment_modified_time( 'H:i:s', false, false, self::$comment_id );

		$this->assertMatchesRegularExpression(
			'/^\d{2}:\d{2}:\d{2}$/',
			$time,
			'get_comment_modified_time() should return a time formatted as H:i:s.'
		);
	}

	/**
	 * @ticket 36564
	 *
	 * @covers ::get_comment_modified_time
	 */
	public function test_get_comment_modified_time_gmt_uses_gmt_meta() {
		wp_update_comment(
			array(
				'comment_ID'      => self::$comment_id,
				'comment_content' => 'For modified time GMT test.',
			)
		);

		$time_gmt   = get_comment_modified_time( 'U', true, false, self::$comment_id );
		$time_local = get_comment_modified_time( 'U', false, false, self::$comment_id );

		// Both should be numeric timestamps.
		$this->assertIsNumeric( $time_gmt, 'GMT timestamp should be numeric.' );
		$this->assertIsNumeric( $time_local, 'Local timestamp should be numeric.' );
	}

	/**
	 * @ticket 36564
	 *
	 * @covers ::get_comment_modified_time
	 */
	public function test_get_comment_modified_time_returns_empty_for_unmodified_comment() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
			)
		);

		// Do not call wp_update_comment() — no meta should exist.
		$time = get_comment_modified_time( 'H:i:s', false, false, $comment_id );

		$this->assertSame( '', $time, 'get_comment_modified_time() should return empty string when the comment has never been modified.' );
	}
}
