<?php

/**
 * Tests for the XML-RPC wp.getUsersBlogs method.
 *
 * @group xmlrpc
 * @group user
 *
 * @covers wp_xmlrpc_server::wp_getUsersBlogs
 */
class Tests_XMLRPC_wp_getUsersBlogs extends WP_XMLRPC_UnitTestCase {

	/**
	 * Tests that non-scalar credentials return an error rather than reaching
	 * wp_authenticate(), where a non-scalar password triggers a fatal error and
	 * a non-scalar username an E_USER_WARNING.
	 *
	 * @ticket 65600
	 *
	 * @dataProvider data_non_scalar_credentials
	 *
	 * @param mixed $username Username argument.
	 * @param mixed $password Password argument.
	 */
	public function test_non_scalar_credentials_should_return_error( $username, $password ): void {
		$result = $this->myxmlrpcserver->wp_getUsersBlogs( array( $username, $password ) );

		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( 'Incorrect username or password.', $result->message );
	}

	/**
	 * Data provider for test_non_scalar_credentials_should_return_error.
	 *
	 * @return array[]
	 */
	public static function data_non_scalar_credentials(): array {
		return array(
			'an array as password'  => array( 'subscriber', array() ),
			'an array as username'  => array( array(), 'subscriber' ),
			'an object as password' => array( 'subscriber', new IXR_Date( '20260806T00:00:00' ) ),
			'an object as username' => array( new IXR_Date( '20260806T00:00:00' ), 'subscriber' ),
			'null as password'      => array( 'subscriber', null ),
			'null as username'      => array( null, 'subscriber' ),
		);
	}

	/**
	 * Tests that valid string credentials are still delegated to blogger_getUsersBlogs().
	 *
	 * @ticket 65600
	 * @group ms-excluded
	 */
	public function test_valid_credentials_should_return_blogs(): void {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_getUsersBlogs( array( 'subscriber', 'subscriber' ) );

		$this->assertNotIXRError( $result, 'The result should not be an instance of IXR_Error.' );
		$this->assertIsArray( $result, 'The result should be an array.' );
		$this->assertCount( 1, $result, 'The result should contain a single blog.' );

		$blog = $result[0];
		$this->assertSame( '1', $blog['blogid'], 'The blogid should be that of the only blog.' );
		$this->assertSame( get_option( 'blogname' ), $blog['blogName'], 'The blogName should match the site name.' );
		$this->assertFalse( $blog['isAdmin'], 'A subscriber should not be flagged as an administrator.' );
	}

	/**
	 * Tests that valid string credentials still return blogs on multisite, where
	 * wp_getUsersBlogs() handles the request itself rather than delegating.
	 *
	 * @ticket 65600
	 * @group ms-required
	 * @group multisite
	 */
	public function test_valid_credentials_should_return_blogs_on_multisite(): void {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_getUsersBlogs( array( 'subscriber', 'subscriber' ) );

		$this->assertNotIXRError( $result, 'The result should not be an instance of IXR_Error.' );
		$this->assertIsArray( $result, 'The result should be an array.' );
		$this->assertNotEmpty( $result, 'The result should not be empty.' );

		$blog = $result[0];
		$this->assertArrayHasKey( 'url', $blog, 'The result should include the url field.' );
		$this->assertArrayHasKey( 'blogid', $blog, 'The result should include the blogid field.' );
		$this->assertArrayHasKey( 'blogName', $blog, 'The result should include the blogName field.' );
		$this->assertArrayHasKey( 'isPrimary', $blog, 'The result should include the isPrimary field.' );
	}
}
