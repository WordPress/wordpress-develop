<?php

/**
 * @group comment
 *
 * @covers ::get_comment_type
 */
class Tests_Comment_GetCommentType extends WP_UnitTestCase {
	protected static $comments;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		unset( $GLOBALS['comment'] );

		$comment_types = array( 'comment', 'custom' );
		foreach ( $comment_types as $comment_type ) {
			self::$comments[] = $factory->comment->create_and_get( compact( 'comment_type' ) );
		}
	}

	public function test_no_comment() {
		$type = get_comment_type();
		$this->assertSame( 'comment', $type );
	}

	public function test_invalid_comment() {
		$comment            = end( self::$comments );
		$invalid_comment_id = $comment->comment_ID + 1;
		$type               = get_comment_type( $invalid_comment_id );
		$this->assertSame( 'comment', $type );
	}

	public function test_global_comment() {
		$comment = end( self::$comments );
		$GLOBALS['comment'] = $comment;
		$type = get_comment_type();
		$this->assertSame( $comment->comment_type, $type );
		unset( $GLOBALS['comment'] );
	}

	public function test_comment_arg() {
		$comment = reset( self::$comments );
		$type    = get_comment_type( $comment );
		$this->assertSame( $comment->comment_type, $type );
	}

}
