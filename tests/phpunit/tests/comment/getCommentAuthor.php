<?php

/**
 * @group comment
 *
 * @covers ::get_comment_author
 */
class Tests_Comment_GetCommentAuthor extends WP_UnitTestCase {
	protected static $comments = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		unset( $GLOBALS['comment'] );

		$comment_ids                = $factory->comment->create_post_comments( 0, 1 );
		$user_generated_comment_ids = $factory->comment->create_post_comments( 0, 1, array(
			'user_id'        => $factory->user->create(),
			'comment_author' => '',
		) );
		self::$comments = array_map(
			'get_comment',
			array_merge(
				$comment_ids,
				$user_generated_comment_ids
			)
		);
	}

	public function test_no_comment() {
		$author = get_comment_author();
		$this->assertSame( 'Anonymous', $author );
	}

	public function test_invalid_comment() {
		$comment            = end( self::$comments );
		$invalid_comment_id = $comment->comment_ID + 1;
		$author             = get_comment_author( $invalid_comment_id );
		$this->assertSame( 'Anonymous', $author );
	}

	public function test_global_comment() {
		$comment            = reset( self::$comments );
		$GLOBALS['comment'] = $comment;
		$author             = get_comment_author();
		$this->assertSame( $comment->comment_author, $author );
		unset( $GLOBALS['comment'] );
	}

	public function test_comment_arg() {
		$comment = reset( self::$comments );
		$author  = get_comment_author( $comment );
		$this->assertSame( $comment->comment_author, $author );
	}

	public function test_comment_by_registered_user_with_empty_comment_author_name() {
		$comment = end( self::$comments );
		$user    = get_userdata( $comment->user_id );
		$author  = get_comment_author( $comment );
		$this->assertSame( $user->display_name, $author );
	}

}
