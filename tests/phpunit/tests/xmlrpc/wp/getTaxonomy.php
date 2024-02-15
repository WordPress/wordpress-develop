<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_wp_getTaxonomy extends WP_XMLRPC_UnitTestCase {

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_getTaxonomy( array( 1, 'username', 'password', 'category' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_empty_taxonomy() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getTaxonomy( array( 1, 'editor', 'editor', '' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Invalid taxonomy.' ), $result->message );
	}

	public function test_invalid_taxonomy() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getTaxonomy( array( 1, 'editor', 'editor', 'not_existing' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Invalid taxonomy.' ), $result->message );
	}

	public function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_getTaxonomy( array( 1, 'subscriber', 'subscriber', 'category' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
		$this->assertSame( __( 'Sorry, you are not allowed to assign terms in this taxonomy.' ), $result->message );
	}

	public function test_taxonomy_validated() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getTaxonomy( array( 1, 'editor', 'editor', 'category' ) );
		$this->assertNotIXRError( $result );
	}

	public function test_prepare_taxonomy() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getTaxonomy( array( 1, 'editor', 'editor', 'category' ) );
		$this->assertNotIXRError( $result );
		$taxonomy = get_taxonomy( 'category' );
		$this->assertSame( 'category', $result['name'], 'name' );
		$this->assertTrue( $result['_builtin'], '_builtin' );
		$this->assertSame( $taxonomy->show_ui, $result['show_ui'], 'show_ui' );
		$this->assertSame( $taxonomy->public, $result['public'], 'public' );
		$this->assertSame( $taxonomy->hierarchical, $result['hierarchical'], 'hierarchical' );
		$this->assertSame( (array) $taxonomy->labels, $result['labels'], 'labels' );
		$this->assertSame( (array) $taxonomy->cap, $result['cap'], 'capabilities' );
		$this->assertSame( (array) $taxonomy->object_type, $result['object_type'], 'object_types' );
	}

	/**
	 * @ticket 51493
	 */
	public function test_taxonomy_with_menu_field_specified() {
		$this->make_user_by_role( 'editor' );

		$fields = array(
			'menu',
		);

		$result = $this->myxmlrpcserver->wp_getTaxonomy( array( 1, 'editor', 'editor', 'category', $fields ) );
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result['show_in_menu'] );
	}
}
