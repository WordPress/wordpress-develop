<?php

/**
 * @group comment
 *
 * @covers ::get_comment_author_IP
 */
class Tests_Comment_GetCommentAuthorIP extends WP_UnitTestCase {
	protected static $comments = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		unset( $GLOBALS['comment'] );

		$comment_id_with_ip    = $factory->comment->create_post_comments(
			0,
			1,
			array(
				'comment_author_IP' => '0.0.0.0',
			)
		);
		$comment_id_without_ip = $factory->comment->create_post_comments( 0, 1 );
		self::$comments        = array_map(
			'get_comment',
			array_merge(
				$comment_id_with_ip,
				$comment_id_without_ip
			)
		);
	}

	public function test_no_comment() {
		$ip = get_comment_author_IP();
		$this->assertSame( '', $ip );
	}

	public function test_invalid_comment() {
		$comment            = end( self::$comments );
		$invalid_comment_id = $comment->comment_ID + 1;
		$ip                 = get_comment_author_IP( $invalid_comment_id );
		$this->assertSame( '', $ip );
	}

	public function test_global_comment() {
		$comment            = reset( self::$comments );
		$GLOBALS['comment'] = $comment;
		$ip                 = get_comment_author_IP();
		$this->assertSame( '0.0.0.0', $ip );
		unset( $GLOBALS['comment'] );
	}

	public function test_comment_arg() {
		$comment = reset( self::$comments );
		$ip      = get_comment_author_IP( $comment );
		$this->assertSame( '0.0.0.0', $ip );
	}

	public function test_comment_with_empty_comment_author_IP() {
		$comment = end( self::$comments );
		$ip      = get_comment_author_IP( $comment );
		$this->assertSame( '', $ip );
	}

}
