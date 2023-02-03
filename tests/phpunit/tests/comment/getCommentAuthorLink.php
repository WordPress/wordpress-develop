<?php

/**
 * @group comment
 *
 * @covers ::get_comment_author_link
 */
class Tests_Comment_GetCommentAuthorLink extends WP_UnitTestCase {
	protected static $comments = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		unset( $GLOBALS['comment'] );

		$comment_id_with_url    = $factory->comment->create_post_comments( 0, 1 );
		$comment_id_without_url = $factory->comment->create_post_comments( 0, 1, array(
			'comment_author_url' => '',
		) );
		self::$comments          = array_map(
			'get_comment',
			array_merge(
				$comment_id_with_url,
				$comment_id_without_url,
			)
		);

	}

	public function test_no_comment() {
		$link = get_comment_author_link();
		$this->assertSame( 'Anonymous', $link );
	}

	public function test_invalid_comment() {
		$comment            = end( self::$comments );
		$invalid_comment_id = $comment->comment_ID + 1;
		$link               = get_comment_author_link( $invalid_comment_id );
		$this->assertSame( 'Anonymous', $link );
	}

	public function test_global_comment() {
		$comment            = reset( self::$comments );
		$GLOBALS['comment'] = $comment;
		$expect             = "<a href='$comment->comment_author_url' rel='external nofollow ugc' class='url'>$comment->comment_author</a>";
		$link               = get_comment_author_link();
		$this->assertSame( $expect, $link );
		unset( $GLOBALS['comment'] );
	}

	public function test_comment_arg() {
		$comment = reset( self::$comments );
		$expect  = "<a href='$comment->comment_author_url' rel='external nofollow ugc' class='url'>$comment->comment_author</a>";
		$link    = get_comment_author_link( $comment );
		$this->assertSame( $expect, $link );
	}

	public function test_comment_with_empty_comment_author_url() {
		$comment = end( self::$comments );
		$link    = get_comment_author_link( $comment );
		$this->assertSame( $comment->comment_author, $link );
	}

}
