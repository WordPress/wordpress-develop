<?php

/**
 * @group comment
 *
 * @covers ::get_comment_text
 */
class Tests_Comment_GetCommentText extends WP_UnitTestCase {
	protected static $comment = array();

	protected static $comment_content = 'Lorem ipsum dolor sit.';

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		unset( $GLOBALS['comment'] );

		self::$comment = $factory->comment->create_and_get(
			array(
				'comment_content' => self::$comment_content,
			)
		);
	}

	public function test_no_comment() {
		$text = get_comment_text();
		$this->assertSame( '', $text );
	}

	public function test_invalid_comment_id_nonexistent_int() {
		$invalid_comment_id = self::$comment->comment_id + 1;
		$text               = get_comment_text( $invalid_comment_id );
		$this->assertSame( '', $text );
	}

	/**
	 * @ticket 40143
	 */
	public function test_invalid_comment_id_true() {
		$text = get_comment_text( true );
		$this->assertSame( '', $text );
	}

	public function test_global_comment() {
		$GLOBALS['comment'] = self::$comment;
		$text               = get_comment_text();
		$this->assertSame( self::$comment_content, $text );
	}

	public function test_comment_arg() {
		$text = get_comment_text( self::$comment );
		$this->assertSame( self::$comment_content, $text );
	}

	public function test_comment_feed_reply_to() {

		$comment_post_ID = self::factory()->post->create();
		$parent_comment  = self::factory()->comment->create_and_get( compact( 'comment_post_ID' ) );
		$comment         = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => $comment_post_ID,
				'comment_parent'  => $parent_comment->comment_ID,
				'comment_content' => self::$comment_content,
			)
		);

		$this->go_to( get_post_comments_feed_link( $comment_post_ID ) );

		$expected = sprintf(
			'In reply to <a href="%1$s">%2$s</a>.',
			esc_url( get_comment_link( $parent_comment ) ),
			get_comment_author( $parent_comment )
		) . "\n\n" . self::$comment_content;
		$text     = get_comment_text( $comment );
		$this->assertSame( $expected, $text );
	}

}
