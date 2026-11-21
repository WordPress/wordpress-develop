<?php

/**
 * Tests for get_comment_ID() and comment_ID().
 *
 * @group comment
 */
class Tests_Comment_GetCommentID extends WP_UnitTestCase {

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
				'comment_post_ID' => self::$post_id,
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
	 * @covers ::get_comment_ID
	 */
	public function test_returns_comment_id_from_global_comment() {
		$GLOBALS['comment'] = self::$comment;

		$this->assertSame( (string) self::$comment->comment_ID, get_comment_ID() );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_ID
	 */
	public function test_returns_zero_when_no_comment() {
		unset( $GLOBALS['comment'] );

		$this->assertSame( '0', get_comment_ID() );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_ID
	 */
	public function test_filter_can_modify_comment_id() {
		$GLOBALS['comment'] = self::$comment;

		add_filter(
			'get_comment_ID',
			static function () {
				return '999';
			}
		);

		$this->assertSame( '999', get_comment_ID() );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::comment_ID
	 * @covers ::get_comment_ID
	 */
	public function test_comment_id_echoes_value() {
		$GLOBALS['comment'] = self::$comment;

		$this->expectOutputString( (string) self::$comment->comment_ID );
		comment_ID();
	}
}
