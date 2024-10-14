<?php

/**
 * @group xmlrpc
 *
 * @covers wp_xmlrpc_server::wp_getRevisions
 */
class Tests_XMLRPC_wp_getRevisions extends WP_XMLRPC_UnitTestCase {

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_getRevisions( array( 1, 'username', 'password', 0 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );

		$post_id = self::factory()->post->create();

		$result = $this->myxmlrpcserver->wp_getRevisions( array( 1, 'subscriber', 'subscriber', $post_id ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
	}

	public function test_capable_user() {
		$this->make_user_by_role( 'editor' );

		$post_id = self::factory()->post->create();
		$result  = $this->myxmlrpcserver->wp_getRevisions( array( 1, 'editor', 'editor', $post_id ) );
		$this->assertNotIXRError( $result );
	}

	public function test_revision_count() {
		$this->make_user_by_role( 'editor' );

		$post_id = self::factory()->post->create();
		wp_insert_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Edit 1',
			)
		); // Create the initial revision.

		$result = $this->myxmlrpcserver->wp_getRevisions( array( 1, 'editor', 'editor', $post_id ) );
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );

		wp_insert_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Edit 2',
			)
		);

		$result = $this->myxmlrpcserver->wp_getRevisions( array( 1, 'editor', 'editor', $post_id ) );
		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
	}

	/**
	 * @ticket 22687
	 */
	public function test_revision_count_for_auto_draft_post_creation() {
		$this->make_user_by_role( 'editor' );

		$post_id = $this->myxmlrpcserver->wp_newPost(
			array(
				1,
				'editor',
				'editor',
				array(
					'post_title'   => 'Original title',
					'post_content' => 'Test',
				),
			)
		);

		$result = $this->myxmlrpcserver->wp_getRevisions( array( 1, 'editor', 'editor', $post_id ) );
		$this->assertCount( 1, $result );
	}
}
