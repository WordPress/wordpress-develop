<?php

/**
 * @group xmlrpc
 *
 * @covers wp_xmlrpc_server::wp_deleteTerm
 */
class Tests_XMLRPC_wp_deleteTerm extends WP_XMLRPC_UnitTestCase {
	protected static $term_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$term_id = $factory->term->create(
			array(
				'taxonomy' => 'category',
			)
		);
	}

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_deleteTerm( array( 1, 'username', 'password', 'category', 0 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_empty_taxonomy() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_deleteTerm( array( 1, 'subscriber', 'subscriber', '', 0 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Invalid taxonomy.' ), $result->message );
	}

	public function test_invalid_taxonomy() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_deleteTerm( array( 1, 'subscriber', 'subscriber', 'not_existing', 0 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Invalid taxonomy.' ), $result->message );
	}

	public function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_deleteTerm( array( 1, 'subscriber', 'subscriber', 'category', self::$term_id ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
		$this->assertSame( __( 'Sorry, you are not allowed to delete this term.' ), $result->message );
	}

	public function test_empty_term() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_deleteTerm( array( 1, 'editor', 'editor', 'category', '' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 500, $result->code );
		$this->assertSame( __( 'Empty Term.' ), $result->message );
	}

	public function test_invalid_term() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_deleteTerm( array( 1, 'editor', 'editor', 'category', 9999 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 404, $result->code );
		$this->assertSame( __( 'Invalid term ID.' ), $result->message );
	}

	public function test_term_deleted() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_deleteTerm( array( 1, 'editor', 'editor', 'category', self::$term_id ) );
		$this->assertNotIXRError( $result );
		$this->assertIsBool( $result );
	}
}
