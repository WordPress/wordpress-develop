<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_mt_publishPost extends WP_XMLRPC_UnitTestCase {

	public function test_cannot_publish_page_with_post_capability() {
		$role = 'xmlrpc_page_publisher';
		add_role(
			$role,
			'XML-RPC page publisher',
			array(
				'read'                => true,
				'edit_pages'          => true,
				'edit_published_pages' => true,
				'publish_posts'        => true,
			)
		);

		try {
			$user_id = $this->make_user_by_role( $role );
			$page_id = self::factory()->post->create(
				array(
					'post_author' => $user_id,
					'post_type'   => 'page',
					'post_status' => 'draft',
				)
			);

			$result = $this->myxmlrpcserver->mt_publishPost( array( $page_id, $role, $role ) );

			$this->assertIXRError( $result );
			$this->assertSame( 401, $result->code );
			$this->assertSame( 'draft', get_post_status( $page_id ) );
		} finally {
			remove_role( $role );
		}
	}
}
