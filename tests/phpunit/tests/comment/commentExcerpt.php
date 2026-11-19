<?php

/**
 * Tests for comment_excerpt().
 *
 * @group comment
 *
 * @covers ::comment_excerpt
 * @covers ::get_comment_excerpt
 */
class Tests_Comment_CommentExcerpt extends WP_UnitTestCase {

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
				'comment_content' => 'Short comment excerpt text.',
			)
		);
	}

	public function tear_down() {
		unset( $GLOBALS['comment'] );
		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_comment_excerpt_echoes_excerpt() {
		$this->expectOutputString( 'Short comment excerpt text.' );
		comment_excerpt( self::$comment->comment_ID );
	}

	/**
	 * @ticket 65819
	 */
	public function test_comment_excerpt_accepts_comment_object() {
		$this->expectOutputString( 'Short comment excerpt text.' );
		comment_excerpt( self::$comment );
	}

	/**
	 * @ticket 65819
	 */
	public function test_comment_excerpt_uses_global_comment_when_unspecified() {
		$GLOBALS['comment'] = self::$comment;

		$this->expectOutputString( 'Short comment excerpt text.' );
		comment_excerpt();
	}

	/**
	 * @ticket 65819
	 */
	public function test_comment_excerpt_filter_can_modify_output() {
		add_filter(
			'comment_excerpt',
			static function () {
				return 'Filtered excerpt.';
			}
		);

		$this->expectOutputString( 'Filtered excerpt.' );
		comment_excerpt( self::$comment->comment_ID );
	}
}
