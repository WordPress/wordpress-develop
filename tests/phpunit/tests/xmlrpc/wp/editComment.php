<?php

/**
 * @group xmlrpc
 *
 * @covers wp_xmlrpc_server::wp_editComment
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
}
