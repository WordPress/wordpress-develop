<?php

/**
 * @group xmlrpc
 *
 * @covers wp_xmlrpc_server::wp_newTerm
 */
class Tests_XMLRPC_wp_newTerm extends WP_XMLRPC_UnitTestCase {

	protected static $parent_term_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$parent_term_id = $factory->term->create(
			array(
				'taxonomy' => 'category',
			)
		);
	}

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_newTerm( array( 1, 'username', 'password', array() ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_empty_taxonomy() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_newTerm( array( 1, 'editor', 'editor', array( 'taxonomy' => '' ) ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Invalid taxonomy.' ), $result->message );
	}

	public function test_invalid_taxonomy() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_newTerm( array( 1, 'editor', 'editor', array( 'taxonomy' => 'not_existing' ) ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Invalid taxonomy.' ), $result->message );
	}

	public function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_newTerm( array( 1, 'subscriber', 'subscriber', array( 'taxonomy' => 'category' ) ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
		$this->assertSame( __( 'Sorry, you are not allowed to create terms in this taxonomy.' ), $result->message );
	}

	public function test_empty_term() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_newTerm(
			array(
				1,
				'editor',
				'editor',
				array(
					'taxonomy' => 'category',
					'name'     => '',
				),
			)
		);
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'The term name cannot be empty.' ), $result->message );
	}

	public function test_parent_for_nonhierarchical() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_newTerm(
			array(
				1,
				'editor',
				'editor',
				array(
					'taxonomy' => 'post_tag',
					'parent'   => self::$parent_term_id,
					'name'     => 'test',
				),
			)
		);
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'This taxonomy is not hierarchical.' ), $result->message );
	}

	public function test_parent_invalid() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_newTerm(
			array(
				1,
				'editor',
				'editor',
				array(
					'taxonomy' => 'category',
					'parent'   => 'dasda',
					'name'     => 'test',
				),
			)
		);
		$this->assertIXRError( $result );
		$this->assertSame( 500, $result->code );
	}

	public function test_parent_not_existing() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_newTerm(
			array(
				1,
				'editor',
				'editor',
				array(
					'taxonomy' => 'category',
					'parent'   => 9999,
					'name'     => 'test',
				),
			)
		);
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Parent term does not exist.' ), $result->message );
	}


	public function test_add_term() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_newTerm(
			array(
				1,
				'editor',
				'editor',
				array(
					'taxonomy' => 'category',
					'name'     => 'test',
				),
			)
		);
		$this->assertNotIXRError( $result );
		$this->assertStringMatchesFormat( '%d', $result );
	}

	public function test_add_term_with_parent() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_newTerm(
			array(
				1,
				'editor',
				'editor',
				array(
					'taxonomy' => 'category',
					'parent'   => self::$parent_term_id,
					'name'     => 'test',
				),
			)
		);
		$this->assertNotIXRError( $result );
		$this->assertStringMatchesFormat( '%d', $result );
	}

	public function test_add_term_with_all() {
		$this->make_user_by_role( 'editor' );

		$taxonomy = array(
			'taxonomy'    => 'category',
			'parent'      => self::$parent_term_id,
			'name'        => 'test_all',
			'description' => 'Test all',
			'slug'        => 'test_all',
		);
		$result   = $this->myxmlrpcserver->wp_newTerm( array( 1, 'editor', 'editor', $taxonomy ) );
		$this->assertNotIXRError( $result );
		$this->assertStringMatchesFormat( '%d', $result );
	}

	/**
	 * @ticket 35991
	 */
	public function test_add_term_meta() {
		$this->make_user_by_role( 'editor' );
		$result = $this->myxmlrpcserver->wp_newTerm(
			array(
				1,
				'editor',
				'editor',
				array(
					'taxonomy'      => 'category',
					'name'          => 'Test meta',
					'custom_fields' => array(
						array(
							'key'   => 'key1',
							'value' => 'value1',
						),
					),
				),
			)
		);
		$this->assertNotIXRError( $result );
		$this->assertStringMatchesFormat( '%d', $result );
	}
}
