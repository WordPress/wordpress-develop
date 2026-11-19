<?php

/**
 * Tests for get_comment_author_email() and comment_author_email().
 *
 * @group comment
 */
class Tests_Comment_GetCommentAuthorEmail extends WP_UnitTestCase {

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
				'comment_post_ID'      => self::$post_id,
				'comment_author_email' => 'author@example.com',
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
	 * @covers ::get_comment_author_email
	 */
	public function test_returns_comment_author_email() {
		$this->assertSame( 'author@example.com', get_comment_author_email( self::$comment->comment_ID ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_author_email
	 */
	public function test_accepts_comment_object() {
		$this->assertSame( 'author@example.com', get_comment_author_email( self::$comment ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_author_email
	 */
	public function test_uses_global_comment_when_unspecified() {
		$GLOBALS['comment'] = self::$comment;

		$this->assertSame( 'author@example.com', get_comment_author_email() );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_author_email
	 */
	public function test_returns_empty_string_when_email_missing() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_author_email' => '',
			)
		);

		$this->assertSame( '', get_comment_author_email( $comment_id ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_author_email
	 */
	public function test_filter_can_modify_email() {
		add_filter(
			'get_comment_author_email',
			static function () {
				return 'filtered@example.com';
			}
		);

		$this->assertSame( 'filtered@example.com', get_comment_author_email( self::$comment->comment_ID ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::comment_author_email
	 * @covers ::get_comment_author_email
	 */
	public function test_comment_author_email_echoes_value() {
		add_filter(
			'author_email',
			function ( $email ) {
				$this->assertSame( 'author@example.com', $email );

				return 'display@example.com';
			}
		);

		$this->expectOutputString( 'display@example.com' );
		comment_author_email( self::$comment->comment_ID );
	}
}
