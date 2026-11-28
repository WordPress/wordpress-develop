<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_wp_editComment extends WP_XMLRPC_UnitTestCase {

	public function test_author_can_edit_own_comment() {
		$author_id = $this->make_user_by_role( 'author' );
		$post_id   = self::factory()->post->create(
			array(
				'post_title'  => 'Post test by author',
				'post_author' => $author_id,
			)
		);

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'    => $post_id,
				'comment_author'     => 'Commenter 1',
				'comment_author_url' => 'http://example.com/1/',
				'comment_approved'   => 1,
			)
		);

		$result = $this->myxmlrpcserver->wp_editComment(
			array(
				1,
				'author',
				'author',
				$comment_id,
				array(
					'status' => 'hold',
				),
			)
		);
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );
	}

	public function test_author_cannot_edit_others_comment() {
		$this->make_user_by_role( 'author' );
		$editor_id = $this->make_user_by_role( 'editor' );
		$post_id   = self::factory()->post->create(
			array(
				'post_title'  => 'Post test by editor',
				'post_author' => $editor_id,
			)
		);

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'    => $post_id,
				'comment_author'     => 'Commenter 2',
				'comment_author_url' => 'http://example.com/2/',
				'comment_approved'   => 0,
			)
		);

		$result = $this->myxmlrpcserver->wp_editComment( array( 1, 'author', 'author', $comment_id, array( 'status' => 'hold' ) ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Sorry, you are not allowed to moderate or edit this comment.' ), $result->message );
	}

	public function test_trash_comment() {
		$this->make_user_by_role( 'administrator' );
		$post_id = self::factory()->post->create();

		$comment_data = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => 'Test commenter',
			'comment_author_url'   => 'http://example.com/',
			'comment_author_email' => 'example@example.com',
			'comment_content'      => 'Comment content',
			'comment_approved'     => '1',
		);
		$comment_id   = wp_insert_comment( $comment_data );

		$this->assertSame( '1', get_comment( $comment_id )->comment_approved );

		$this->myxmlrpcserver->wp_editComment(
			array(
				1,
				'administrator',
				'administrator',
				$comment_id,
				array(
					'status' => 'trash',
				),
			)
		);

		$this->assertSame( 'trash', get_comment( $comment_id )->comment_approved );
	}

	/**
	 * Reactions are immutable, whoever asks: XML-RPC needs no nonce, so it
	 * must not be the one writer that lets a reaction be rewritten.
	 *
	 * @ticket 66635
	 */
	public function test_cannot_edit_reaction(): void {
		$contributor_id = $this->make_user_by_role( 'contributor' );
		$admin_id       = $this->make_user_by_role( 'administrator' );
		$draft_id       = self::factory()->post->create(
			array(
				'post_author' => $contributor_id,
				'post_status' => 'draft',
			)
		);
		$note_id        = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $draft_id,
				'comment_type'     => 'note',
				'comment_approved' => 0,
				'user_id'          => $admin_id,
				'comment_content'  => 'Editorial note',
			)
		);
		$reaction_id    = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $draft_id,
				'comment_type'     => 'reaction',
				'comment_parent'   => $note_id,
				'comment_approved' => 1,
				'user_id'          => $admin_id,
				'comment_content'  => '2764',
			)
		);

		$result = $this->myxmlrpcserver->wp_editComment(
			array(
				1,
				'contributor',
				'contributor',
				$reaction_id,
				array(
					'content' => 'rewritten',
					'author'  => 'Forged',
				),
			)
		);

		$this->assertIXRError( $result );

		$reaction = get_comment( $reaction_id );
		$this->assertInstanceOf( WP_Comment::class, $reaction );
		$this->assertSame( '2764', $reaction->comment_content );
		$this->assertSame( $admin_id, (int) $reaction->user_id );
	}
}
