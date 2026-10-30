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

	public function tear_down() {
		unset( $GLOBALS['comment'] );
		parent::tear_down();
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
	public function test_accepts_comment_object() {
		$this->assertSame( '192.0.2.10', get_comment_author_IP( self::$comment ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_author_IP
	 */
	public function test_uses_global_comment_when_unspecified() {
		$GLOBALS['comment'] = self::$comment;

		$this->assertSame( '192.0.2.10', get_comment_author_IP() );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_author_IP
	 */
	public function test_returns_empty_string_when_ip_missing() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'   => self::$post_id,
				'comment_author_IP' => '',
			)
		);

		$this->assertSame( '', get_comment_author_IP( $comment_id ) );
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
		$this->expectOutputString( '192.0.2.10' );
		comment_author_IP( self::$comment->comment_ID );
	}
}
