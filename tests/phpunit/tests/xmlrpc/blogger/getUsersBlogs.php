<?php

/**
 * @group xmlrpc
 * @group user
 */
class Tests_XMLRPC_blogger_getUsersBlogs extends WP_XMLRPC_UnitTestCase {

	/**
	 * @ticket 65536
	 * @group ms-required
	 * @group multisite
	 */
	public function test_multisite_argument_parsing() {
		$subscriber_id = $this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->blogger_getUsersBlogs( array( 1, 'subscriber', 'subscriber' ) );

		$this->assertNotIXRError( $result, 'The result should not be an instance of IXR_Error.' );
		$this->assertIsArray( $result, 'The result should be an array.' );
		$this->assertNotEmpty( $result, 'The result should not be empty.' );

		$blog = $result[0];
		$this->assertArrayHasKey( 'url', $blog, 'The result should include the url field.' );
		$this->assertArrayHasKey( 'blogid', $blog, 'The result should include the blogid field.' );
		$this->assertArrayHasKey( 'blogName', $blog, 'The result should include the blogName field.' );
	}
}
