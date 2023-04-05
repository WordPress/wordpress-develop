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
	 * Tests that comment_time() returns the same value as get_comment_time().
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
	 * Tests that comment_time() defaults to the global comment when no comment ID
	 * is provided.
	 *
	 * @ticket 58064
	 */
	public function test_should_default_to_the_global_comment_when_no_comment_id_is_provided() {
		global $comment;

		// Backup the global comment before setting the value.
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
	 * Tests that comment_time() returns an empty string when no global comment is set
	 * and no comment ID is provided.
	 *
	 * @ticket 58064
	 */
	public function test_should_return_an_empty_string_when_no_global_comment_is_set_and_no_comment_id_is_provided() {
		global $comment;

		// Backup the global comment before setting the value.
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
