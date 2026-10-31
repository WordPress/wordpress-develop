<?php

/**
 * Tests for comment_author_url() and get_comment_author_url().
 *
 * @group comment
 */
class Tests_Comment_CommentAuthorUrl extends WP_UnitTestCase {

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
				'comment_post_ID'    => self::$post_id,
				'comment_author_url' => 'https://example.com/author',
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
	 * @covers ::get_comment_author_url
	 */
	public function test_returns_comment_author_url() {
		$this->assertSame( 'https://example.com/author', get_comment_author_url( self::$comment->comment_ID ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_author_url
	 */
	public function test_accepts_comment_object() {
		$this->assertSame( 'https://example.com/author', get_comment_author_url( self::$comment ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_author_url
	 */
	public function test_uses_global_comment_when_unspecified() {
		$GLOBALS['comment'] = self::$comment;

		$this->assertSame( 'https://example.com/author', get_comment_author_url() );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_author_url
	 */
	public function test_returns_empty_string_when_url_missing() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'    => self::$post_id,
				'comment_author_url' => '',
			)
		);

		$this->assertSame( '', get_comment_author_url( $comment_id ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_author_url
	 */
	public function test_filter_can_modify_url() {
		add_filter(
			'get_comment_author_url',
			static function () {
				return 'https://filtered.example.com';
			}
		);

		$this->assertSame( 'https://filtered.example.com', get_comment_author_url( self::$comment->comment_ID ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::comment_author_url
	 * @covers ::get_comment_author_url
	 */
	public function test_comment_author_url_echoes_value() {
		add_filter(
			'comment_url',
			function ( $url ) {
				$this->assertSame( 'https://example.com/author', $url );
				return 'https://display.example.com';
			}
		);

		$this->expectOutputString( 'https://display.example.com' );
		comment_author_url( self::$comment->comment_ID );
	}
}
