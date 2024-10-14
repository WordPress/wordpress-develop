<?php

/**
 * @group xmlrpc
 *
 * @covers wp_xmlrpc_server::wp_editTerm
 */
class Tests_XMLRPC_wp_editTerm extends WP_XMLRPC_UnitTestCase {
	protected static $parent_term;
	protected static $child_term;
	protected static $post_tag;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$parent_term = $factory->term->create(
			array(
				'taxonomy' => 'category',
			)
		);
		self::$child_term  = $factory->term->create(
			array(
				'taxonomy' => 'category',
			)
		);
		self::$post_tag    = $factory->term->create(
			array(
				'taxonomy' => 'post_tag',
			)
		);
	}

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_editTerm( array( 1, 'username', 'password', 'category', 1 ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_empty_taxonomy() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_editTerm( array( 1, 'subscriber', 'subscriber', '', array( 'taxonomy' => '' ) ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Invalid taxonomy.' ), $result->message );
	}

	public function test_invalid_taxonomy() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_editTerm( array( 1, 'subscriber', 'subscriber', self::$parent_term, array( 'taxonomy' => 'not_existing' ) ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Invalid taxonomy.' ), $result->message );
	}

	public function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_editTerm( array( 1, 'subscriber', 'subscriber', self::$parent_term, array( 'taxonomy' => 'category' ) ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
		$this->assertSame( __( 'Sorry, you are not allowed to edit this term.' ), $result->message );
	}

	public function test_term_not_exists() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_editTerm( array( 1, 'editor', 'editor', 9999, array( 'taxonomy' => 'category' ) ) );
		$this->assertIXRError( $result );
		$this->assertSame( 404, $result->code );
		$this->assertSame( __( 'Invalid term ID.' ), $result->message );
	}

	public function test_empty_term() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_editTerm( array( 1, 'editor', 'editor', '', array( 'taxonomy' => 'category' ) ) );
		$this->assertIXRError( $result );
		$this->assertSame( 500, $result->code );
		$this->assertSame( __( 'Empty Term.' ), $result->message );
	}

	public function test_empty_term_name() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_editTerm(
			array(
				1,
				'editor',
				'editor',
				self::$parent_term,
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

		$result = $this->myxmlrpcserver->wp_editTerm(
			array(
				1,
				'editor',
				'editor',
				self::$post_tag,
				array(
					'taxonomy' => 'post_tag',
					'parent'   => self::$parent_term,
				),
			)
		);
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
		$this->assertSame( __( 'Cannot set parent term, taxonomy is not hierarchical.' ), $result->message );
	}

	public function test_parent_empty() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_editTerm(
			array(
				1,
				'editor',
				'editor',
				self::$child_term,
				array(
					'taxonomy' => 'category',
					'parent'   => '',
					'name'     => 'test',
				),
			)
		);
		$this->assertNotIXRError( $result );
		$this->assertTrue( $result );
	}

	public function test_parent_null() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_editTerm(
			array(
				1,
				'editor',
				'editor',
				self::$child_term,
				array(
					'taxonomy' => 'category',
					'parent'   => null,
					'name'     => 'test',
				),
			)
		);

		$this->assertNotIXRError( $result );
		$this->assertIsBool( $result );

		$term = get_term( self::$child_term, 'category' );
		$this->assertEquals( '0', $term->parent );
	}

	public function test_parent_invalid() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_editTerm(
			array(
				1,
				'editor',
				'editor',
				self::$child_term,
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

		$result = $this->myxmlrpcserver->wp_editTerm(
			array(
				1,
				'editor',
				'editor',
				self::$child_term,
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

	public function test_parent_duplicate_slug() {
		$this->make_user_by_role( 'editor' );

		$parent_term = get_term_by( 'id', self::$parent_term, 'category' );
		$result      = $this->myxmlrpcserver->wp_editTerm(
			array(
				1,
				'editor',
				'editor',
				self::$child_term,
				array(
					'taxonomy' => 'category',
					'slug'     => $parent_term->slug,
				),
			)
		);
		$this->assertIXRError( $result );
		$this->assertSame( 500, $result->code );
		$this->assertSame( htmlspecialchars( sprintf( __( 'The slug &#8220;%s&#8221; is already in use by another term.' ), $parent_term->slug ) ), $result->message );
	}

	public function test_edit_all_fields() {
		$this->make_user_by_role( 'editor' );

		$fields = array(
			'taxonomy'    => 'category',
			'name'        => 'Child 2',
			'parent'      => self::$parent_term,
			'description' => 'Child term',
			'slug'        => 'child_2',
		);
		$result = $this->myxmlrpcserver->wp_editTerm( array( 1, 'editor', 'editor', self::$child_term, $fields ) );

		$this->assertNotIXRError( $result );
		$this->assertIsBool( $result );
	}

	/**
	 * @ticket 35991
	 */
	public function test_update_term_meta() {
		register_taxonomy( 'wptests_tax', 'post' );

		$t       = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);
		$meta_id = add_term_meta( $t, 'foo', 'bar' );

		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_editTerm(
			array(
				1,
				'editor',
				'editor',
				$t,
				array(
					'taxonomy'      => 'wptests_tax',
					'custom_fields' => array(
						array(
							'id'    => $meta_id,
							'key'   => 'foo',
							'value' => 'baz',
						),
					),
				),
			)
		);

		$this->assertNotIXRError( $result );

		$found = get_term_meta( $t, 'foo', true );
		$this->assertSame( 'baz', $found );
	}

	/**
	 * @ticket 35991
	 */
	public function test_delete_term_meta() {
		register_taxonomy( 'wptests_tax', 'post' );

		$t       = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);
		$meta_id = add_term_meta( $t, 'foo', 'bar' );

		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_editTerm(
			array(
				1,
				'editor',
				'editor',
				$t,
				array(
					'taxonomy'      => 'wptests_tax',
					'custom_fields' => array(
						array(
							'id' => $meta_id,
						),
					),
				),
			)
		);

		$this->assertNotIXRError( $result );

		$found = get_term_meta( $t, 'foo' );
		$this->assertSame( array(), $found );
	}
}
