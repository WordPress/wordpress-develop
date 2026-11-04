<?php

/**
 * Tests for get_comment_author_IP() and comment_author_IP().
 *
 * @group comment
 */
class Tests_Comment_GetCommentAuthorIP extends WP_UnitTestCase {

	/**
	 * Post fixture.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Comment fixture.
	 *
	 * @var WP_Comment
	 */
	private static $comment;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
		self::$comment = $factory->comment->create_and_get(
			array(
				'comment_post_ID'   => self::$post_id,
				'comment_author_IP' => '192.0.2.10',
			)
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_author_IP
	 */
	public function test_returns_comment_author_ip() {
		$this->assertSame( '192.0.2.10', get_comment_author_IP( self::$comment->comment_ID ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_author_IP
	 */
	public function test_filter_can_modify_ip() {
		add_filter(
			'get_comment_author_IP',
			static function () {
				return '203.0.113.5';
			}
		);

		$this->assertSame( '203.0.113.5', get_comment_author_IP( self::$comment->comment_ID ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::comment_author_IP
	 * @covers ::get_comment_author_IP
	 */
	public function test_comment_author_ip_echoes_escaped_value() {
		add_filter(
			'get_comment_author_IP',
			static function () {
				return '<b>192.0.2.10</b>';
			}
		);

		$this->expectOutputString( '&lt;b&gt;192.0.2.10&lt;/b&gt;' );
		comment_author_IP( self::$comment->comment_ID );
	}
}
