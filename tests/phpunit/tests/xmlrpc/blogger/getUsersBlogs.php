<?php

/**
 * @group xmlrpc
 * @group user
 */
class Tests_XMLRPC_blogger_getUsersBlogs extends WP_XMLRPC_UnitTestCase {

	/**
	 * @ticket 65536
	 */
	public function test_multisite_argument_parsing() {
		$subscriber_id = $this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->blogger_getUsersBlogs( array( 1, 'subscriber', 'subscriber' ) );

		$this->assertNotIXRError( $result );
		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );

		$blog = $result[0];
		$this->assertArrayHasKey( 'url', $blog );
		$this->assertArrayHasKey( 'blogid', $blog );
		$this->assertArrayHasKey( 'blogName', $blog );
	}
}
