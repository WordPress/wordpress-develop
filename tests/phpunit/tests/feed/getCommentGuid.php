<?php
/**
 * Tests for get_comment_guid().
 *
 * @group feed
 * @group comment
 *
 * @covers ::get_comment_guid
 */
class Tests_Feed_GetCommentGuid extends WP_UnitTestCase {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Comment ID.
	 *
	 * @var int
	 */
	private static $comment_id;

	/**
	 * Create shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'guid' => 'https://example.org/?p=123',
			)
		);

		self::$comment_id = $factory->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
			)
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_for_invalid_comment() {
		$this->assertFalse( get_comment_guid( PHP_INT_MAX ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_post_guid_with_comment_anchor() {
		$this->assertSame(
			'https://example.org/?p=123#comment-' . self::$comment_id,
			get_comment_guid( self::$comment_id )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_accepts_comment_object() {
		$comment = get_comment( self::$comment_id );

		$this->assertSame(
			'https://example.org/?p=123#comment-' . self::$comment_id,
			get_comment_guid( $comment )
		);
	}
}
