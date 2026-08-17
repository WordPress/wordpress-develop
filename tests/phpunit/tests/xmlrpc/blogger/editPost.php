<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_blogger_editPost extends WP_XMLRPC_UnitTestCase {

	public function test_cannot_publish_draft_without_publish_posts() {
		$contributor_id = $this->make_user_by_role( 'contributor' );
		$post_id        = self::factory()->post->create(
			array(
				'post_author' => $contributor_id,
				'post_status' => 'draft',
			)
		);

		$result = $this->myxmlrpcserver->blogger_editPost(
			array(
				1,
				$post_id,
				'contributor',
				'contributor',
				'<title>Updated through Blogger</title>Updated content',
				1,
			)
		);

		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
		$this->assertSame( 'draft', get_post_status( $post_id ) );
	}
}
