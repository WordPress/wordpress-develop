<?php

/**
 * @group comment
 *
 * @covers ::comment_author_url
 */
class Tests_Comment_CommentAuthorUrl extends WP_UnitTestCase {
	protected static $comments = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		unset( $GLOBALS['comment'] );

		$comment_ids    = $factory->comment->create_post_comments( 0, 1 );
		self::$comments = array_map( 'get_comment', $comment_ids );

	}

	public function test_no_comment() {
		comment_author_url();
		$this->expectOutputString( '' );
	}

	public function test_invalid_comment() {
		$comment            = end( self::$comments );
		$invalid_comment_id = $comment->comment_ID + 1;
		comment_author_url( $invalid_comment_id );
		$this->expectOutputString( '' );
	}

	public function test_global_comment() {
		$comment            = reset( self::$comments );
		$GLOBALS['comment'] = $comment;
		comment_author_url();
		$this->expectOutputString( $comment->comment_author_url );
		unset( $GLOBALS['comment'] );
	}

	public function test_comment_arg() {
		$comment = reset( self::$comments );
		comment_author_url( $comment );
		$this->expectOutputString( $comment->comment_author_url );
	}

}
