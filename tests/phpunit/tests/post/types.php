<?php

/**
 * @group post
 */
class Tests_Post_Types extends WP_UnitTestCase {

	/**
	 * Post type.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	public $post_type;

	/**
	 * Set up.
	 *
	 * @since 4.5.0
	 */
	public function set_up() {
		parent::set_up();

		$this->post_type = 'foo1';
	}

	public function test_register_post_type() {
		$this->assertNull( get_post_type_object( 'foo' ) );
		register_post_type( 'foo' );

		$pobj = get_post_type_object( 'foo' );
		$this->assertInstanceOf( 'WP_Post_Type', $pobj );
		$this->assertSame( 'foo', $pobj->name );

		// Test some defaults.
		$this->assertFalse( is_post_type_hierarchical( 'foo' ) );
		$this->assertSame( array(), get_object_taxonomies( 'foo' ) );

		_unregister_post_type( 'foo' );
	}

	/**
	 * @ticket 48558
	 */
	public function test_register_post_type_return_value() {
		$this->assertInstanceOf( 'WP_Post_Type', register_post_type( 'foo' ) );
	}

	/**
	 * @ticket 31134
	 *
	 * @expectedIncorrectUsage register_post_type
	 */
	public function test_register_post_type_with_too_long_name() {
		// Post type too long.
		$this->assertInstanceOf( 'WP_Error', register_post_type( 'abcdefghijklmnopqrstuvwxyz0123456789' ) );
	}

	/**
	 * @ticket 31134
	 *
	 * @expectedIncorrectUsage register_post_type
	 */
	public function test_register_post_type_with_empty_name() {
		// Post type too short.
		$this->assertInstanceOf( 'WP_Error', register_post_type( '' ) );
	}

	/**
	 * @ticket 35985
	 * @covers ::register_post_type
	 */
	public function test_register_post_type_exclude_from_search_should_default_to_opposite_value_of_public() {
		/*
		 * 'public'              Default is false
		 * 'exclude_from_search' Default is null (opposite 'public')
		 */
		$args = register_post_type( $this->post_type, array( 'public' => $public = false ) );

		$this->assertNotEquals( $public, $args->exclude_from_search );
	}

	/**
	 * @ticket 35985
	 * @covers ::register_post_type
	 */
	public function test_register_post_type_publicly_queryable_should_default_to_value_of_public() {
		/*
		 * 'public'             Default is false
		 * 'publicly_queryable' Default is null ('public')
		 */
		$args = register_post_type( $this->post_type, array( 'public' => $public = false ) );

		$this->assertSame( $public, $args->publicly_queryable );
	}

	/**
	 * @ticket 35985
	 * @covers ::register_post_type
	 */
	public function test_register_post_type_show_ui_should_default_to_value_of_public() {
		/*
		 * 'public'  Default is false
		 * 'show_ui' Default is null ('public')
		 */
		$args = register_post_type( $this->post_type, array( 'public' => $public = false ) );

		$this->assertSame( $public, $args->show_ui );
	}

	/**
	 * @ticket 35985
	 * @covers ::register_post_type
	 */
	public function test_register_post_type_show_in_menu_should_default_to_value_of_show_ui() {
		/*
		 * 'public'      Default is false
		 * 'show_ui'     Default is null ('public')
		 * 'show_in_menu Default is null ('show_ui' > 'public')
		 */
		$args = register_post_type( $this->post_type, array( 'public' => $public = false ) );

		// Should fall back to 'show_ui'.
		$this->assertSame( $args->show_ui, $args->show_in_menu );

		// Should fall back to 'show_ui', then 'public'.
		$this->assertSame( $public, $args->show_in_menu );
	}

	/**
	 * @ticket 35985
	 * @covers ::register_post_type
	 */
	public function test_register_post_type_show_in_nav_menus_should_default_to_value_of_public() {
		/*
		 * 'public'            Default is false
		 * 'show_in_nav_menus' Default is null ('public')
		 */
		$args = register_post_type( $this->post_type, array( 'public' => $public = false ) );

		$this->assertSame( $public, $args->show_in_nav_menus );
	}

	/**
	 * @ticket 35985
	 * @covers ::register_post_type
	 */
	public function test_register_post_type_show_in_admin_bar_should_default_to_value_of_show_in_menu() {
		/*
		 * 'public'            Default is false
		 * 'show_in_menu'      Default is null ('show_ui' > 'public')
		 * 'show_in_admin_bar' Default is null ('show_in_menu' > 'show_ui' > 'public')
		 */
		$args = register_post_type( $this->post_type, array( 'public' => $public = false ) );

		// Should fall back to 'show_in_menu'.
		$this->assertSame( $args->show_in_menu, $args->show_in_admin_bar );

		// Should fall back to 'show_ui'.
		$this->assertSame( $args->show_ui, $args->show_in_admin_bar );

		// Should fall back to 'public'.
		$this->assertSame( $public, $args->show_in_admin_bar );
	}

	/**
	 * @ticket 53212
	 * @covers ::register_post_type
	 */
	public function test_fires_registered_post_type_actions() {
		$post_type = 'cpt';
		$action    = new MockAction();

		add_action( 'registered_post_type', array( $action, 'action' ) );
		add_action( "registered_post_type_{$post_type}", array( $action, 'action' ) );

		register_post_type( $post_type );
		register_post_type( $this->post_type );

		$this->assertSame( 3, $action->get_call_count() );
	}

	public function test_register_taxonomy_for_object_type() {
		global $wp_taxonomies;

		register_post_type( 'bar' );
		register_taxonomy_for_object_type( 'post_tag', 'bar' );
		$this->assertSame( array( 'post_tag' ), get_object_taxonomies( 'bar' ) );
		register_taxonomy_for_object_type( 'category', 'bar' );
		$this->assertSame( array( 'category', 'post_tag' ), get_object_taxonomies( 'bar' ) );

		$this->assertTrue( is_object_in_taxonomy( 'bar', 'post_tag' ) );
		$this->assertTrue( is_object_in_taxonomy( 'bar', 'post_tag' ) );

		// Clean up. Remove the 'bar' post type from these taxonomies.
		$GLOBALS['wp_taxonomies']['post_tag']->object_type = array( 'post' );
		$GLOBALS['wp_taxonomies']['category']->object_type = array( 'post' );

		$this->assertFalse( is_object_in_taxonomy( 'bar', 'post_tag' ) );
		$this->assertFalse( is_object_in_taxonomy( 'bar', 'post_tag' ) );

		_unregister_post_type( 'bar' );
	}

	public function test_post_type_exists() {
		$this->assertFalse( post_type_exists( 'notaposttype' ) );
		$this->assertTrue( post_type_exists( 'post' ) );
	}

	public function test_post_type_supports() {
		$this->assertTrue( post_type_supports( 'post', 'post-formats' ) );
		$this->assertFalse( post_type_supports( 'page', 'post-formats' ) );
		$this->assertFalse( post_type_supports( 'notaposttype', 'post-formats' ) );
		$this->assertFalse( post_type_supports( 'post', 'notafeature' ) );
		$this->assertFalse( post_type_supports( 'notaposttype', 'notafeature' ) );
	}

	/**
	 * @ticket 21586
	 */
	public function test_post_type_with_no_support() {
		register_post_type( 'foo', array( 'supports' => array() ) );
		$this->assertTrue( post_type_supports( 'foo', 'editor' ) );
		$this->assertTrue( post_type_supports( 'foo', 'title' ) );
		_unregister_post_type( 'foo' );

		register_post_type( 'foo', array( 'supports' => false ) );
		$this->assertFalse( post_type_supports( 'foo', 'editor' ) );
		$this->assertFalse( post_type_supports( 'foo', 'title' ) );
		_unregister_post_type( 'foo' );
	}

	/**
	 * @ticket 23302
	 */
	public function test_post_type_with_no_feed() {
		global $wp_rewrite;
		$old_permastruct = get_option( 'permalink_structure' );
		update_option( 'permalink_structure', '%postname%' );
		register_post_type( 'foo', array( 'rewrite' => array( 'feeds' => false ) ) );
		$this->assertFalse( $wp_rewrite->extra_permastructs['foo']['feed'] );
		update_option( 'permalink_structure', $old_permastruct );
		_unregister_post_type( 'foo' );
	}

	/**
	 * @ticket 30013
	 */
	public function test_get_post_type_object_with_non_scalar_values() {
		$this->assertFalse( post_type_exists( 'foo' ) );

		register_post_type( 'foo' );

		$this->assertTrue( post_type_exists( 'foo' ) );

		$this->assertNotNull( get_post_type_object( 'foo' ) );
		$this->assertNull( get_post_type_object( array() ) );
		$this->assertNull( get_post_type_object( array( 'foo' ) ) );
		$this->assertNull( get_post_type_object( new stdClass() ) );

		_unregister_post_type( 'foo' );

		$this->assertFalse( post_type_exists( 'foo' ) );
	}

	/**
	 * @ticket 33023
	 */
	public function test_get_post_type_object_casting() {
		register_post_type( 'foo' );

		$before = get_post_type_object( 'foo' )->labels;

		get_post_type_labels( get_post_type_object( 'foo' ) );

		$after = get_post_type_object( 'foo' )->labels;

		$this->assertEquals( $before, $after );

		_unregister_post_type( 'foo' );
	}

	/**
	 * @ticket 38844
	 */
	public function test_get_post_type_object_includes_menu_icon_for_builtin_post_types() {
		$this->assertSame( 'dashicons-admin-post', get_post_type_object( 'post' )->menu_icon );
		$this->assertSame( 'dashicons-admin-page', get_post_type_object( 'page' )->menu_icon );
		$this->assertSame( 'dashicons-admin-media', get_post_type_object( 'attachment' )->menu_icon );
	}

	/**
	 * @ticket 14761
	 */
	public function test_unregister_post_type() {
		register_post_type( 'foo' );
		$this->assertTrue( unregister_post_type( 'foo' ) );
	}

	/**
	 * @ticket 14761
	 */
	public function test_unregister_post_type_unknown_post_type() {
		$this->assertWPError( unregister_post_type( 'foo' ) );
	}

	/**
	 * @ticket 14761
	 */
	public function test_unregister_post_type_twice() {
		register_post_type( 'foo' );
		$this->assertTrue( unregister_post_type( 'foo' ) );
		$this->assertWPError( unregister_post_type( 'foo' ) );
	}

	/**
	 * @ticket 14761
	 */
	public function test_unregister_post_type_disallow_builtin_post_type() {
		$this->assertWPError( unregister_post_type( 'post' ) );
		$this->assertWPError( unregister_post_type( 'page' ) );
		$this->assertWPError( unregister_post_type( 'attachment' ) );
		$this->assertWPError( unregister_post_type( 'revision' ) );
		$this->assertWPError( unregister_post_type( 'nav_menu_item' ) );
	}

	/**
	 * @ticket 14761
	 */
	public function test_unregister_post_type_removes_query_vars() {
		global $wp;

		register_post_type(
			'foo',
			array(
				'public'    => true,
				'query_var' => 'bar',
			)
		);

		$this->assertIsInt( array_search( 'bar', $wp->public_query_vars, true ) );
		$this->assertTrue( unregister_post_type( 'foo' ) );
		$this->assertFalse( array_search( 'bar', $wp->public_query_vars, true ) );
	}

	/**
	 * @ticket 14761
	 */
	public function test_unregister_post_type_removes_rewrite_tags() {
		$this->set_permalink_structure( '/%postname%' );

		global $wp_rewrite;

		register_post_type(
			'foo',
			array(
				'public'    => true,
				'query_var' => 'bar',
			)
		);

		$count_before = count( $wp_rewrite->rewritereplace );

		$this->assertContains( '%foo%', $wp_rewrite->rewritecode );
		$this->assertContains( 'bar=', $wp_rewrite->queryreplace );
		$this->assertTrue( unregister_post_type( 'foo' ) );
		$this->assertNotContains( '%foo%', $wp_rewrite->rewritecode );
		$this->assertNotContains( 'bar=', $wp_rewrite->queryreplace );
		$this->assertCount( --$count_before, $wp_rewrite->rewritereplace ); // Array was reduced by one value.
	}

	/**
	 * @ticket 14761
	 */
	public function test_unregister_post_type_removes_rewrite_rules() {
		$this->set_permalink_structure( '/%postname%' );

		global $wp_rewrite;

		register_post_type(
			'foo',
			array(
				'public'      => true,
				'has_archive' => true,
			)
		);

		$this->assertContains( 'index.php?post_type=foo', $wp_rewrite->extra_rules_top );
		$this->assertTrue( unregister_post_type( 'foo' ) );
		$this->assertNotContains( 'index.php?post_type=foo', $wp_rewrite->extra_rules_top );
	}

	/**
	 * @ticket 14761
	 */
	public function test_unregister_post_type_removes_custom_meta_capabilities() {
		global $post_type_meta_caps;

		register_post_type(
			'foo',
			array(
				'public'          => true,
				'capability_type' => 'bar',
				'map_meta_cap'    => true,
			)
		);

		$this->assertSame( 'read_post', $post_type_meta_caps['read_bar'] );
		$this->assertSame( 'delete_post', $post_type_meta_caps['delete_bar'] );
		$this->assertSame( 'edit_post', $post_type_meta_caps['edit_bar'] );

		$this->assertTrue( unregister_post_type( 'foo' ) );

		$this->assertArrayNotHasKey( 'read_bar', $post_type_meta_caps );
		$this->assertArrayNotHasKey( 'delete_bar', $post_type_meta_caps );
		$this->assertArrayNotHasKey( 'edit_bar', $post_type_meta_caps );
	}

	/**
	 * @ticket 14761
	 */
	public function test_unregister_post_type_removes_post_type_supports() {
		global $_wp_post_type_features;

		register_post_type(
			'foo',
			array(
				'public'   => true,
				'supports' => array( 'editor', 'author', 'title' ),
			)
		);

		$this->assertSameSetsWithIndex(
			array(
				'editor' => true,
				'author' => true,
				'title'  => true,
			),
			$_wp_post_type_features['foo']
		);
		$this->assertTrue( unregister_post_type( 'foo' ) );
		$this->assertArrayNotHasKey( 'foo', $_wp_post_type_features );
	}

	/**
	 * @ticket 14761
	 */
	public function test_unregister_post_type_removes_post_type_from_taxonomies() {
		global $wp_taxonomies;

		register_post_type(
			'foo',
			array(
				'public'     => true,
				'taxonomies' => array( 'category', 'post_tag' ),
			)
		);

		$this->assertIsInt( array_search( 'foo', $wp_taxonomies['category']->object_type, true ) );
		$this->assertIsInt( array_search( 'foo', $wp_taxonomies['post_tag']->object_type, true ) );
		$this->assertTrue( unregister_post_type( 'foo' ) );
		$this->assertFalse( array_search( 'foo', $wp_taxonomies['category']->object_type, true ) );
		$this->assertFalse( array_search( 'foo', $wp_taxonomies['post_tag']->object_type, true ) );
		$this->assertEmpty( get_object_taxonomies( 'foo' ) );
	}

	/**
	 * @ticket 14761
	 */
	public function test_unregister_post_type_removes_the_future_post_hooks() {
		global $wp_filter;

		register_post_type(
			'foo',
			array(
				'public' => true,
			)
		);

		$this->assertArrayHasKey( 'future_foo', $wp_filter );
		$this->assertCount( 1, $wp_filter['future_foo']->callbacks );
		$this->assertTrue( unregister_post_type( 'foo' ) );
		$this->assertArrayNotHasKey( 'future_foo', $wp_filter );
	}

	/**
	 * @ticket 14761
	 */
	public function test_unregister_post_type_removes_meta_box_callback() {
		global $wp_filter;

		register_post_type(
			'foo',
			array(
				'public'               => true,
				'register_meta_box_cb' => '__return_empty_string',
			)
		);

		$this->assertArrayHasKey( 'add_meta_boxes_foo', $wp_filter );
		$this->assertCount( 1, $wp_filter['add_meta_boxes_foo']->callbacks );
		$this->assertTrue( unregister_post_type( 'foo' ) );
		$this->assertArrayNotHasKey( 'add_meta_boxes_foo', $wp_filter );
	}

	/**
	 * @ticket 14761
	 */
	public function test_unregister_post_type_removes_post_type_from_global() {
		global $wp_post_types;

		register_post_type(
			'foo',
			array(
				'public' => true,
			)
		);

		$this->assertIsObject( $wp_post_types['foo'] );
		$this->assertIsObject( get_post_type_object( 'foo' ) );

		$this->assertTrue( unregister_post_type( 'foo' ) );

		$this->assertArrayNotHasKey( 'foo', $wp_post_types );
		$this->assertNull( get_post_type_object( 'foo' ) );
	}

	/**
	 * @ticket 14761
	 */
	public function test_post_type_does_not_exist_after_unregister_post_type() {
		register_post_type(
			'foo',
			array(
				'public' => true,
			)
		);

		$this->assertTrue( unregister_post_type( 'foo' ) );

		$this->assertFalse( post_type_exists( 'foo' ) );
	}

	/**
	 * @ticket 34010
	 */
	public function test_get_post_types_by_support_single_feature() {
		$this->assertContains( 'post', get_post_types_by_support( 'title' ) );
		$this->assertContains( 'page', get_post_types_by_support( 'title' ) );
		$this->assertContains( 'attachment', get_post_types_by_support( 'title' ) );
		$this->assertContains( 'nav_menu_item', get_post_types_by_support( 'title' ) );
	}

	/**
	 * @ticket 34010
	 */
	public function test_get_post_types_by_support_multiple_features() {
		$this->assertContains( 'post', get_post_types_by_support( array( 'thumbnail', 'author' ) ) );
		$this->assertContains( 'page', get_post_types_by_support( array( 'thumbnail', 'author' ) ) );
	}

	/**
	 * @ticket 34010
	 */
	public function test_get_post_types_by_support_or_operator() {
		$this->assertContains( 'post', get_post_types_by_support( array( 'post-formats', 'page-attributes' ), 'or' ) );
		$this->assertContains( 'page', get_post_types_by_support( array( 'post-formats', 'page-attributes' ), 'or' ) );
	}

	/**
	 * @ticket 34010
	 */
	public function test_get_post_types_by_support_not_operator() {
		$this->assertContains( 'attachment', get_post_types_by_support( array( 'thumbnail' ), 'not' ) );
		$this->assertContains( 'revision', get_post_types_by_support( array( 'thumbnail' ), 'not' ) );
		$this->assertContains( 'nav_menu_item', get_post_types_by_support( array( 'thumbnail' ), 'not' ) );
	}

	/**
	 * @ticket 34010
	 */
	public function test_get_post_types_by_support_excluding_features() {
		$this->assertSameSets( array(), get_post_types_by_support( array( 'post-formats', 'page-attributes' ) ) );
	}

	/**
	 * @ticket 34010
	 */
	public function test_get_post_types_by_support_non_existant_feature() {
		$this->assertSameSets( array(), get_post_types_by_support( 'somefeature' ) );
	}
}
