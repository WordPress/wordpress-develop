<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_wp_getPostTypes extends WP_XMLRPC_UnitTestCase {
	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_getPostTypes( array( 1, 'username', 'password', 'post' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_getPostTypes( array( 1, 'subscriber', 'subscriber' ) );
		$this->assertNotIXRError( $result );
		$this->assertIsArray( $result );
		$this->assertCount( 0, $result );
	}

	public function test_capable_user() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getPostTypes( array( 1, 'editor', 'editor' ) );
		$this->assertNotIXRError( $result );
		$this->assertIsArray( $result );
		$this->assertGreaterThan( 0, count( $result ) );
	}

	public function test_simple_filter() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getPostTypes( array( 1, 'editor', 'editor', array( 'hierarchical' => true ) ) );
		$this->assertNotIXRError( $result );
		$this->assertIsArray( $result );

		// Verify that page is in the result, and post is not.
		$result_names = wp_list_pluck( $result, 'name' );
		$this->assertContains( 'page', $result_names );
		$this->assertNotContains( 'post', $result_names );
	}
}
