<?php

/**
 * @group comment
 *
 * @covers ::get_comment_author_email
 */
class Tests_Comment_GetCommentAuthorEmail extends WP_UnitTestCase {
	protected static $comments = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		unset( $GLOBALS['comment'] );

		$comment_id_with_email    = $factory->comment->create_post_comments( 0, 1, array(
			'comment_author_email' => ( new WP_UnitTest_Generator_Sequence( 'commenter.%s@example.com' ) )->get_template_string(),
		) );
		$comment_id_without_email = $factory->comment->create_post_comments( 0, 1 );
		self::$comments           = array_map(
			'get_comment',
			array_merge(
				$comment_id_with_email,
				$comment_id_without_email,
			)
		);

	}

	public function test_no_comment() {
		$email = get_comment_author_email();
		$this->assertSame( '', $email );
	}

	public function test_invalid_comment() {
		$comment            = end( self::$comments );
		$invalid_comment_id = $comment->comment_ID + 1;
		$email              = get_comment_author_email( $invalid_comment_id );
		$this->assertSame( '', $email );
	}

	public function test_global_comment() {
		$comment            = reset( self::$comments );
		$GLOBALS['comment'] = $comment;
		$email              = get_comment_author_email();
		$this->assertSame( $comment->comment_author_email, $email );
		unset( $GLOBALS['comment'] );
	}

	public function test_comment_arg() {
		$comment = reset( self::$comments );
		$email   = get_comment_author_email( $comment );
		$this->assertSame( $comment->comment_author_email, $email );
	}

	public function test_comment_with_empty_comment_author_email() {
		$comment = end( self::$comments );
		$email   = get_comment_author_email( $comment );
		$this->assertSame( '', $email );
	}

}
