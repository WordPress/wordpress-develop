<?php

/**
 * @group xmlrpc
 */
class Tests_XMLRPC_wp_getPostType extends WP_XMLRPC_UnitTestCase {
	var $cpt_name;
	var $cpt_args;

	function setUp(): void {
		parent::setUp();

		$this->cpt_name = 'post_type_test';
		$this->cpt_args = array(
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 7,
			'menu_icon'     => 'cpt_icon.png',
			'taxonomies'    => array( 'category', 'post_tag' ),
			'hierarchical'  => true,
		);
		register_post_type( $this->cpt_name, $this->cpt_args );
	}

	function tearDown(): void {
		_unregister_post_type( $this->cpt_name );

		parent::tearDown();
	}

	function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_getPostType( array( 1, 'username', 'password', 'post' ) );
		$this->assertIXRError( $result );
		$this->assertEquals( 403, $result->code );
	}

	function test_invalid_post_type_name() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getPostType( array( 1, 'editor', 'editor', 'foobar' ) );
		$this->assertIXRError( $result );
		$this->assertEquals( 403, $result->code );
	}

	function test_valid_post_type_name() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getPostType( array( 1, 'editor', 'editor', 'post' ) );
		$this->assertNotIXRError( $result );
	}

	function test_incapable_user() {
		$this->make_user_by_role( 'subscriber' );

		$result = $this->myxmlrpcserver->wp_getPostType( array( 1, 'subscriber', 'subscriber', 'post' ) );
		$this->assertIXRError( $result );
		$this->assertEquals( 401, $result->code );
	}

	function test_valid_type() {
		$this->make_user_by_role( 'editor' );

		$result = $this->myxmlrpcserver->wp_getPostType( array( 1, 'editor', 'editor', $this->cpt_name, array( 'labels', 'cap', 'menu', 'taxonomies' ) ) );
		$this->assertNotIXRError( $result );

		// Check data types.
		$this->assertInternalType( 'string', $result['name'] );
		$this->assertInternalType( 'string', $result['label'] );
		$this->assertIsBool( $result['hierarchical'] );
		$this->assertIsBool( $result['public'] );
		$this->assertIsBool( $result['_builtin'] );
		$this->assertIsBool( $result['map_meta_cap'] );
		$this->assertIsBool( $result['has_archive'] );
		$this->assertIsBool( $result['show_ui'] );
		$this->assertIsInt( $result['menu_position'] );
		$this->assertInternalType( 'string', $result['menu_icon'] );
		$this->assertIsArray( $result['labels'] );
		$this->assertIsArray( $result['cap'] );
		$this->assertIsArray( $result['taxonomies'] );
		$this->assertIsArray( $result['supports'] );

		// Check label data types.
		$this->assertInternalType( 'string', $result['labels']['name'] );
		$this->assertInternalType( 'string', $result['labels']['singular_name'] );
		$this->assertInternalType( 'string', $result['labels']['add_new'] );
		$this->assertInternalType( 'string', $result['labels']['add_new_item'] );
		$this->assertInternalType( 'string', $result['labels']['edit_item'] );
		$this->assertInternalType( 'string', $result['labels']['new_item'] );
		$this->assertInternalType( 'string', $result['labels']['view_item'] );
		$this->assertInternalType( 'string', $result['labels']['search_items'] );
		$this->assertInternalType( 'string', $result['labels']['not_found'] );
		$this->assertInternalType( 'string', $result['labels']['not_found_in_trash'] );
		$this->assertInternalType( 'string', $result['labels']['parent_item_colon'] );
		$this->assertInternalType( 'string', $result['labels']['all_items'] );
		$this->assertInternalType( 'string', $result['labels']['menu_name'] );
		$this->assertInternalType( 'string', $result['labels']['name_admin_bar'] );

		// Check cap data types.
		$this->assertInternalType( 'string', $result['cap']['edit_post'] );
		$this->assertInternalType( 'string', $result['cap']['read_post'] );
		$this->assertInternalType( 'string', $result['cap']['delete_post'] );
		$this->assertInternalType( 'string', $result['cap']['edit_posts'] );
		$this->assertInternalType( 'string', $result['cap']['edit_others_posts'] );
		$this->assertInternalType( 'string', $result['cap']['publish_posts'] );
		$this->assertInternalType( 'string', $result['cap']['read_private_posts'] );
		$this->assertInternalType( 'string', $result['cap']['read'] );
		$this->assertInternalType( 'string', $result['cap']['delete_posts'] );
		$this->assertInternalType( 'string', $result['cap']['delete_private_posts'] );
		$this->assertInternalType( 'string', $result['cap']['delete_published_posts'] );
		$this->assertInternalType( 'string', $result['cap']['delete_others_posts'] );
		$this->assertInternalType( 'string', $result['cap']['edit_private_posts'] );
		$this->assertInternalType( 'string', $result['cap']['edit_published_posts'] );

		// Check taxonomy data types.
		foreach ( $result['taxonomies'] as $taxonomy ) {
			$this->assertInternalType( 'string', $taxonomy );
		}

		// Check support data types.
		foreach ( $result['supports'] as $key => $value ) {
			$this->assertInternalType( 'string', $key );
			$this->assertIsBool( $value );
		}

		// Check expected values.
		$this->assertEquals( $this->cpt_name, $result['name'] );
		foreach ( $this->cpt_args as $key => $value ) {
			$this->assertEquals( $value, $result[ $key ] );
		}
	}
}
