<?php

/**
 * @group xmlrpc
 * @group user
 */
class Tests_XMLRPC_wp_getUsersBlogs extends WP_XMLRPC_UnitTestCase {

	/**
	 * Tests that non-string username or password arguments return an error
	 * instead of triggering a fatal error.
	 *
	 * @ticket 65600
	 *
	 * @dataProvider data_non_string_credentials
	 *
	 * @param mixed $username Username argument.
	 * @param mixed $password Password argument.
	 */
	public function test_non_string_credentials_should_return_error( $username, $password ) {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_getUsersBlogs( array( $username, $password ) );

		$this->assertIXRError( $result );
		$this->assertSame( 400, $result->code );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_non_string_credentials() {
		return array(
			'an array as password'            => array( 'subscriber', array() ),
			'an array as username'            => array( array(), 'subscriber' ),
			'arrays as username and password' => array( array(), array() ),
			'an integer as password'          => array( 'subscriber', 12345 ),
		);
	}
}
