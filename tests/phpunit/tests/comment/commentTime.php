<?php

/**
 * Tests for the comment_time() function.
 *
 * @group comment
 *
 * @covers ::comment_time
 */
class Tests_Comment_CommentTime extends WP_UnitTestCase {

	/**
	 * A post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * A comment ID.
	 *
	 * @var int
	 */
	protected static $comment_id;

	/**
	 * Sets the post ID and comment ID property values before any tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Post title for comment_time() tests',
				'post_content' => 'Post content for comment_time() tests',
			)
		);

		self::$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'user_id'         => 1,
			)
		);
	}

	/**
	 * Tests that comment_time() displays the same value that get_comment_time() returns.
	 *
	 * @ticket 58064
	 *
	 * @dataProvider data_should_output_the_same_value_that_get_comment_time_returns
	 *
	 * @param string $format PHP date format.
	 */
	public function test_should_output_the_same_value_that_get_comment_time_returns( $format ) {
		$expected = get_comment_time( $format, false, true, self::$comment_id );

		ob_start();
		comment_time( $format, self::$comment_id );
		$actual = ob_get_clean();

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_output_the_same_value_that_get_comment_time_returns() {
		return array(
			'an empty format'   => array(
				'format' => '',
			),
			'a PHP date format' => array(
				'format' => 'h:i:s A',
			),
		);
	}

	/**
	 * Tests that comment_time() defaults to the global comment when comment ID
	 * is not provided.
	 *
	 * @ticket 58064
	 */
	public function test_should_default_to_the_global_comment_when_comment_id_is_not_provided() {
		global $comment;

		// Back up the global comment before setting the value.
		$comment_backup = $comment;
		$comment        = self::$comment_id;

		$expected = get_comment_time();

		ob_start();
		comment_time();
		$actual = ob_get_clean();

		// Restore the global comment value.
		$comment = $comment_backup;

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that comment_time() displays an empty string when global comment is not set
	 * and comment ID is not provided.
	 *
	 * @ticket 58064
	 */
	public function test_should_output_an_empty_string_when_global_comment_is_not_set_and_comment_id_is_not_provided() {
		global $comment;

		// Back up the global comment before setting the value.
		$comment_backup = $comment;
		$comment        = null;

		ob_start();
		comment_time();
		$actual = ob_get_clean();

		// Restore the global comment value.
		$comment = $comment_backup;

		$this->assertSame( '', $actual );
	}

}
