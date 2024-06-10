<?php

/**
 * @group comment
 *
 * @covers ::get_comment_author
 */
class Tests_Comment_GetCommentAuthor extends WP_UnitTestCase {

	private static $comment;
	private static $non_existent_comment_id;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => 0,
			)
		);
	}

	public function get_comment_author_filter( $comment_author, $comment_id, $comment ) {
		$this->assertSame( $comment_id, self::$comment->comment_ID, 'Comment IDs do not match.' );
		$this->assertTrue( is_string( $comment_id ), '$comment_id parameter is not a string.' );

		return $comment_author;
	}

	public function test_comment_author_passes_correct_comment_id_for_comment_object() {
		add_filter( 'get_comment_author', array( $this, 'get_comment_author_filter' ), 99, 3 );

		get_comment_author( self::$comment );
	}

	public function test_comment_author_passes_correct_comment_id_for_int() {
		add_filter( 'get_comment_author', array( $this, 'get_comment_author_filter' ), 99, 3 );

		get_comment_author( (int) self::$comment->comment_ID );
	}

	public function get_comment_author_filter_non_existent_id( $comment_author, $comment_id, $comment ) {
		$this->assertSame( $comment_id, (string) self::$non_existent_comment_id, 'Comment IDs do not match.' );
		$this->assertTrue( is_string( $comment_id ), '$comment_id parameter is not a string.' );

		return $comment_author;
	}

	/**
	 * @ticket 60475
	 */
	public function test_comment_author_passes_correct_comment_id_for_non_existent_comment() {
		add_filter( 'get_comment_author', array( $this, 'get_comment_author_filter_non_existent_id' ), 99, 3 );

		self::$non_existent_comment_id = self::$comment->comment_ID + 1;

		get_comment_author( self::$non_existent_comment_id ); // Non-existent comment ID.
	}
}
