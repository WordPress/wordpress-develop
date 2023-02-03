<?php

/**
 * @group comment
 *
 * @covers ::comment_author
 */
class Tests_Comment_CommentAuthor extends WP_UnitTestCase {
	protected static $comment;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		unset( $GLOBALS['comment'] );
		self::$comment = $factory->comment->create_and_get();
	}

	public function test_no_comment() {
		comment_author();
		$this->expectOutputString( 'Anonymous' );
	}

	public function test_invalid_comment() {
		$invalid_comment_id = self::$comment->comment_ID + 1;
		comment_author( $invalid_comment_id );
		$this->expectOutputString( 'Anonymous' );
	}

	public function test_global_comment() {
		$GLOBALS['comment'] = self::$comment;
		comment_author();
		$this->expectOutputString( self::$comment->comment_author );
		unset( $GLOBALS['comment'] );
	}

	public function test_comment_arg() {
		comment_author( self::$comment );
		$this->expectOutputString( self::$comment->comment_author );
	}

}
