<?php

/**
 * Tests for the _wp_check_reaction_removal_permission() function.
 *
 * @group comment
 *
 * @covers ::_wp_check_reaction_removal_permission
 */
class Tests_Comment_wpCheckReactionRemovalPermission extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * @var int
	 */
	protected static $reactor_id;

	/**
	 * @var int
	 */
	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id  = $factory->user->create( array( 'role' => 'editor' ) );
		self::$reactor_id = $factory->user->create( array( 'role' => 'editor' ) );
		self::$post_id    = $factory->post->create( array( 'post_author' => self::$editor_id ) );
	}

	/**
	 * Creates a note with a reaction from self::$reactor_id.
	 *
	 * @param int $note_status    The note's comment_approved value. 1 means the thread is resolved.
	 * @param int $note_parent    Optional. Parent of the note. Default 0.
	 * @return int Reaction comment ID.
	 */
	private function create_reaction( $note_status = 0, $note_parent = 0 ) {
		$note_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_type'     => 'note',
				'comment_approved' => $note_status,
				'comment_parent'   => $note_parent,
				'user_id'          => self::$editor_id,
				'comment_content'  => 'Note',
			)
		);

		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_type'     => 'reaction',
				'comment_parent'   => $note_id,
				'comment_approved' => 1,
				'user_id'          => self::$reactor_id,
				'comment_content'  => '2764',
			)
		);
	}

	/**
	 * @ticket 66807
	 */
	public function test_non_reaction_comment_is_allowed() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$post_id ) );

		wp_set_current_user( self::$editor_id );

		$this->assertTrue( _wp_check_reaction_removal_permission( $comment_id ) );
	}

	/**
	 * @ticket 66807
	 */
	public function test_author_can_remove_own_reaction() {
		$reaction_id = $this->create_reaction();

		wp_set_current_user( self::$reactor_id );

		$this->assertTrue( _wp_check_reaction_removal_permission( $reaction_id ) );
	}

	/**
	 * @ticket 66807
	 */
	public function test_other_user_cannot_remove_reaction() {
		$reaction_id = $this->create_reaction();

		wp_set_current_user( self::$editor_id );

		$result = _wp_check_reaction_removal_permission( $reaction_id );

		$this->assertWPError( $result );
		$this->assertSame( 'reaction_not_author', $result->get_error_code() );
	}

	/**
	 * @ticket 66807
	 */
	public function test_logged_out_user_cannot_remove_reaction() {
		$reaction_id = $this->create_reaction();

		wp_set_current_user( 0 );

		$this->assertWPError( _wp_check_reaction_removal_permission( $reaction_id ) );
	}

	/**
	 * @ticket 66807
	 */
	public function test_author_cannot_remove_reaction_on_resolved_note() {
		$reaction_id = $this->create_reaction( 1 );

		wp_set_current_user( self::$reactor_id );

		$result = _wp_check_reaction_removal_permission( $reaction_id );

		$this->assertWPError( $result );
		$this->assertSame( 'reaction_thread_resolved', $result->get_error_code() );
	}

	/**
	 * @ticket 66807
	 */
	public function test_author_cannot_remove_reaction_on_reply_in_resolved_thread() {
		$root_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => self::$editor_id,
			)
		);

		$reaction_id = $this->create_reaction( 0, $root_id );

		wp_set_current_user( self::$reactor_id );

		$result = _wp_check_reaction_removal_permission( $reaction_id );

		$this->assertWPError( $result );
		$this->assertSame( 'reaction_thread_resolved', $result->get_error_code() );
	}
}
