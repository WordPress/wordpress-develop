<?php

/**
 * @group xmlrpc
 *
 * @covers wp_xmlrpc_server::wp_deleteComment
 */
class Tests_XMLRPC_wp_deleteComment extends WP_XMLRPC_UnitTestCase {

	/**
	 * Creates a reaction by the given user on a note on a post by the given author.
	 *
	 * @param int $post_author Post author ID.
	 * @param int $reactor_id  Reaction author ID.
	 * @return int Reaction comment ID.
	 */
	private function create_reaction( $post_author, $reactor_id ) {
		$post_id = self::factory()->post->create( array( 'post_author' => $post_author ) );
		$note_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'note',
				'comment_approved' => 0,
				'user_id'          => $post_author,
			)
		);

		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'reaction',
				'comment_parent'   => $note_id,
				'comment_approved' => 1,
				'user_id'          => $reactor_id,
				'comment_content'  => '2764',
			)
		);
	}

	/**
	 * @ticket 66807
	 */
	public function test_cannot_delete_another_users_reaction() {
		$editor_id   = $this->make_user_by_role( 'editor' );
		$reactor_id  = self::factory()->user->create( array( 'role' => 'author' ) );
		$reaction_id = $this->create_reaction( $editor_id, $reactor_id );

		$result = $this->myxmlrpcserver->wp_deleteComment( array( 1, 'editor', 'editor', $reaction_id ) );

		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( 'approved', wp_get_comment_status( $reaction_id ) );
	}

	/**
	 * @ticket 66807
	 */
	public function test_can_delete_own_reaction() {
		$editor_id   = $this->make_user_by_role( 'editor' );
		$reaction_id = $this->create_reaction( $editor_id, $editor_id );

		$result = $this->myxmlrpcserver->wp_deleteComment( array( 1, 'editor', 'editor', $reaction_id ) );

		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );
		$this->assertSame( 'trash', wp_get_comment_status( $reaction_id ) );
	}
}
