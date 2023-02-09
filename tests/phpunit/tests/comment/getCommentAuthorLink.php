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

		self::$comments = array(
			'external_url' => $factory->comment->create_and_get(),
			'internal_url' => $factory->comment->create_and_get(
				array(
					'comment_author_url' => home_url( 'comment-author-url' ),
				)
			),
			'empty_url'    => $factory->comment->create_and_get(
				array(
					'comment_author_url' => '',
				)
			),
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
		$comment            = self::$comments['external_url'];
		$GLOBALS['comment'] = $comment;
		$expect             = "<a href=\"$comment->comment_author_url\" class=\"url\" rel=\"ugc external nofollow\">$comment->comment_author</a>";
		$link               = get_comment_author_link();
		$this->assertSame( $expect, $link );
		unset( $GLOBALS['comment'] );
	}

	public function test_comment_arg() {
		$comment = self::$comments['external_url'];
		$expect  = "<a href=\"$comment->comment_author_url\" class=\"url\" rel=\"ugc external nofollow\">$comment->comment_author</a>";
		$link    = get_comment_author_link( $comment );
		$this->assertSame( $expect, $link );
	}

	public function test_comment_with_internal_comment_author_url() {
		$comment = self::$comments['internal_url'];
		$expect  = "<a href=\"$comment->comment_author_url\" class=\"url\" rel=\"ugc\">$comment->comment_author</a>";
		$link    = get_comment_author_link( $comment );
		$this->assertSame( $expect, $link );
	}

	public function test_comment_with_empty_comment_author_url() {
		$comment = self::$comments['empty_url'];
		$link    = get_comment_author_link( $comment );
		$this->assertSame( $comment->comment_author, $link );
	}

	public function test_comment_with_no_rel() {
		add_filter( 'comment_author_link_rel', '__return_empty_array' );

		$comment = self::$comments['external_url'];
		$expect  = "<a href=\"$comment->comment_author_url\" class=\"url\">$comment->comment_author</a>";
		$link    = get_comment_author_link( $comment );
		$this->assertSame( $expect, $link );

		remove_filter( 'comment_author_link_rel', '__return_empty_array' );
	}

}
