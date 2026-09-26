<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_wp_getTaxonomies extends WP_XMLRPC_UnitTestCase {

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_getTaxonomies( array( 1, 'username', 'password' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_taxonomy_validated() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getTaxonomies( array( 1, 'editor', 'editor' ) );
		$this->assertNotIXRError( $result );
	}

	/**
	 * Ensure a non-array `$fields` argument is rejected instead of causing a fatal error.
	 *
	 * @ticket 65983
	 */
	public function test_non_array_fields_returns_error(): void {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getTaxonomies( array( 1, 'editor', 'editor', array(), 'labels' ) );

		$this->assertIXRError( $result );
		$this->assertSame( 400, $result->code );
	}
}
