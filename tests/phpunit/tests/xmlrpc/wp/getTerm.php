<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_wp_getTerm extends WP_XMLRPC_UnitTestCase {

	protected static $term_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$term_id = $factory->term->create(
			array(
				'taxonomy' => 'category',
			)
		);
	}

	function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_getTerm( array( 1, 'username', 'password', 'category', 1 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	function test_empty_taxonomy() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getTerm( array( 1, 'editor', 'editor', '', 0 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Invalid taxonomy.' ), $result->message );
	}

	function test_invalid_taxonomy() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getTerm( array( 1, 'editor', 'editor', 'not_existing', 0 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Invalid taxonomy.' ), $result->message );
	}

	function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_getTerm( array( 1, 'subscriber', 'subscriber', 'category', self::$term_id ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
		$this->assertSame( __( 'Sorry, you are not allowed to assign this term.' ), $result->message );
	}


	function test_empty_term() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getTerm( array( 1, 'editor', 'editor', 'category', '' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 500, $result->code );
		$this->assertSame( __( 'Empty Term.' ), $result->message );
	}

	function test_invalid_term() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getTerm( array( 1, 'editor', 'editor', 'category', 9999 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 404, $result->code );
		$this->assertSame( __( 'Invalid term ID.' ), $result->message );
	}

	function test_valid_term() {
		$this->make_user_by_role( 'editor' );

		$term                  = get_term( self::$term_id, 'category', ARRAY_A );
		$term['custom_fields'] = array();

		$result = $this->myxmlrpcserver->wp_getTerm( array( 1, 'editor', 'editor', 'category', self::$term_id ) );

		$this->assertNotIXRError( $result );
		$this->assertEquals( $result, $term );

		// Check data types.
		$this->assertInternalType( 'string', $result['name'] );
		$this->assertInternalType( 'string', $result['slug'] );
		$this->assertInternalType( 'string', $result['taxonomy'] );
		$this->assertInternalType( 'string', $result['description'] );
		$this->assertInternalType( 'int', $result['count'] );

		// We expect all ID's to be strings not integers so we don't return something larger than an XMLRPC integer can describe.
		$this->assertStringMatchesFormat( '%d', $result['term_id'] );
		$this->assertStringMatchesFormat( '%d', $result['term_group'] );
		$this->assertStringMatchesFormat( '%d', $result['term_taxonomy_id'] );
		$this->assertStringMatchesFormat( '%d', $result['parent'] );

		// Check data.
		$this->assertSame( 0, $result['count'] );
		$this->assertSame( $term['name'], $result['name'] );
		$this->assertSame( $term['slug'], $result['slug'] );
		$this->assertSame( 'category', $result['taxonomy'] );
		$this->assertSame( $term['description'], $result['description'] );
	}

	/**
	 * @ticket 35991
	 */
	public function test_get_term_meta() {
		$this->make_user_by_role( 'editor' );

		// Add term meta to test wp.getTerm.
		add_term_meta( self::$term_id, 'foo', 'bar' );

		$term = get_term( self::$term_id, 'category', ARRAY_A );

		$result = $this->myxmlrpcserver->wp_getTerm(
			array(
				1,
				'editor',
				'editor',
				'category',
				self::$term_id,
			)
		);
		$this->assertNotIXRError( $result );

		$this->assertInternalType( 'array', $result['custom_fields'] );
		$term_meta = get_term_meta( self::$term_id, '', true );
		$this->assertSame( $term_meta['foo'][0], $result['custom_fields'][0]['value'] );
	}
}
