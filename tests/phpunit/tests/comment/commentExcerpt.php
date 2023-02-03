<?php

/**
 * @group comment
 *
 * @covers ::comment_excerpt
 */
class Tests_Comment_CommentExcerpt extends WP_UnitTestCase {
	protected static $comment_content = 'Bacon ipsum dolor amet porchetta capicola sirloin prosciutto brisket shankle jerky.

Ham hock filet mignon boudin ground round, prosciutto alcatra spare ribs meatball turducken pork beef ribs ham beef.

Bacon pastrami short loin, venison tri-tip ham short ribs doner swine. Tenderloin pig tongue pork jowl doner.';

	protected static $expected_comment_excerpt = 'Bacon ipsum dolor amet porchetta capicola sirloin prosciutto brisket shankle jerky. Ham hock filet mignon boudin ground round, prosciutto alcatra&hellip;';

	protected static $comment;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		unset( $GLOBALS['comment'] );

		self::$comment = $factory->comment->create_and_get( array(
			'comment_content' => self::$comment_content,
		) );
	}

	public function test_no_comment() {
		comment_excerpt();
		$this->expectOutputString( '' );
	}

	public function test_invalid_comment() {
		$invalid_comment_id = self::$comment->comment_ID + 1;
		comment_excerpt( $invalid_comment_id );
		$this->expectOutputString( '' );
	}

	public function test_global_comment() {
		$GLOBALS['comment'] = self::$comment;
		comment_excerpt();
		$this->expectOutputString( self::$expected_comment_excerpt );
		unset( $GLOBALS['comment'] );
	}

	public function test_comment_arg() {
		comment_excerpt( self::$comment );
		$this->expectOutputString( self::$expected_comment_excerpt );
	}

}
