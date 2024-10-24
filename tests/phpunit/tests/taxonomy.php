<?php

/**
 * @group taxonomy
 */
class Tests_Taxonomy extends WP_UnitTestCase {

	/**
	 * Number of times full count callback has been called.
	 *
	 * @var int
	 */
	public $full_count_cb_called = 0;

	/**
	 * Number of times partial count callback has been called.
	 *
	 * @var int
	 */
	public $partial_count_cb_called = 0;

	public function test_get_post_taxonomies() {
		$this->assertSame( array( 'category', 'post_tag', 'post_format' ), get_object_taxonomies( 'post' ) );
	}

	public function test_get_link_taxonomies() {
		$this->assertSame( array( 'link_category' ), get_object_taxonomies( 'link' ) );
	}

	public function test_get_block_taxonomies() {
		$this->assertSame( array( 'wp_pattern_category' ), get_object_taxonomies( 'wp_block' ) );
	}

	/**
	 * @ticket 5417
	 */
	public function test_get_unknown_taxonomies() {
		// Taxonomies for an unknown object type.
		$this->assertSame( array(), get_object_taxonomies( 'unknown' ) );
		$this->assertSame( array(), get_object_taxonomies( '' ) );
		$this->assertSame( array(), get_object_taxonomies( 0 ) );
		$this->assertSame( array(), get_object_taxonomies( null ) );
	}

	public function test_get_post_taxonomy() {
		foreach ( get_object_taxonomies( 'post' ) as $taxonomy ) {
			$tax = get_taxonomy( $taxonomy );
			// Should return an object with the correct taxonomy object type.
			$this->assertIsObject( $tax );
			$this->assertIsArray( $tax->object_type );
			$this->assertSame( array( 'post' ), $tax->object_type );
		}
	}

	public function test_get_the_taxonomies() {
		$post_id = self::factory()->post->create();

		$taxes = get_the_taxonomies( $post_id );
		$this->assertNotEmpty( $taxes );
		$this->assertSame( array( 'category' ), array_keys( $taxes ) );

		$id = self::factory()->tag->create();
		wp_set_post_tags( $post_id, array( $id ) );

		$taxes = get_the_taxonomies( $post_id );
		$this->assertNotEmpty( $taxes );
		$this->assertCount( 2, $taxes );
		$this->assertSame( array( 'category', 'post_tag' ), array_keys( $taxes ) );
	}

	/**
	 * @ticket 27238
	 */
	public function test_get_the_taxonomies_term_template() {
		$post_id = self::factory()->post->create();

		$taxes = get_the_taxonomies( $post_id, array( 'term_template' => '%2$s' ) );
		$this->assertSame( 'Categories: Uncategorized.', $taxes['category'] );

		$taxes = get_the_taxonomies( $post_id, array( 'term_template' => '<span class="foo"><a href="%1$s">%2$s</a></span>' ) );
		$link  = get_category_link( 1 );
		$this->assertSame( 'Categories: <span class="foo"><a href="' . $link . '">Uncategorized</a></span>.', $taxes['category'] );
	}

	public function test_the_taxonomies() {
		$post_id = self::factory()->post->create();

		$this->expectOutputString(
			sprintf(
				'Categories: <a href="%s">Uncategorized</a>.',
				get_category_link( 1 )
			)
		);
		the_taxonomies( array( 'post' => $post_id ) );
	}

	/**
	 * @ticket 27238
	 */
	public function test_the_taxonomies_term_template() {
		$post_id = self::factory()->post->create();

		$output = get_echo(
			'the_taxonomies',
			array(
				array(
					'post'          => $post_id,
					'term_template' => '%2$s',
				),
			)
		);
		$this->assertSame( 'Categories: Uncategorized.', $output );

		$output = get_echo(
			'the_taxonomies',
			array(
				array(
					'post'          => $post_id,
					'term_template' => '<span class="foo"><a href="%1$s">%2$s</a></span>',
				),
			)
		);
		$link   = get_category_link( 1 );
		$this->assertSame( 'Categories: <span class="foo"><a href="' . $link . '">Uncategorized</a></span>.', $output );
	}

	public function test_get_link_taxonomy() {
		foreach ( get_object_taxonomies( 'link' ) as $taxonomy ) {
			$tax = get_taxonomy( $taxonomy );
			// Should return an object with the correct taxonomy object type.
			$this->assertIsObject( $tax );
			$this->assertIsArray( $tax->object_type );
			$this->assertSame( array( 'link' ), $tax->object_type );
		}
	}

	public function test_taxonomy_exists_known() {
		$this->assertTrue( taxonomy_exists( 'category' ) );
		$this->assertTrue( taxonomy_exists( 'post_tag' ) );
		$this->assertTrue( taxonomy_exists( 'link_category' ) );
		$this->assertTrue( taxonomy_exists( 'wp_pattern_category' ) );
	}

	public function test_taxonomy_exists_unknown() {
		$this->assertFalse( taxonomy_exists( rand_str() ) );
		$this->assertFalse( taxonomy_exists( '' ) );
		$this->assertFalse( taxonomy_exists( 0 ) );
		$this->assertFalse( taxonomy_exists( null ) );
	}

	/**
	 * Tests that `taxonomy_exists()` returns `false` when the `$taxonomy`
	 * argument is not a string.
	 *
	 * @ticket 56338
	 *
	 * @covers ::taxonomy_exists
	 *
	 * @dataProvider data_taxonomy_exists_should_return_false_with_non_string_taxonomy
	 *
	 * @param mixed $taxonomy The non-string taxonomy.
	 */
	public function test_taxonomy_exists_should_return_false_with_non_string_taxonomy( $taxonomy ) {
		$this->assertFalse( taxonomy_exists( $taxonomy ) );
	}

	/**
	 * Data provider with non-string values.
	 *
	 * @return array
	 */
	public function data_taxonomy_exists_should_return_false_with_non_string_taxonomy() {
		return array(
			'array'        => array( array() ),
			'object'       => array( new stdClass() ),
			'bool (true)'  => array( true ),
			'bool (false)' => array( false ),
			'null'         => array( null ),
			'integer (0)'  => array( 0 ),
			'integer (1)'  => array( 1 ),
			'float (0.0)'  => array( 0.0 ),
			'float (1.1)'  => array( 1.1 ),
		);
	}

	public function test_is_taxonomy_hierarchical() {
		$this->assertTrue( is_taxonomy_hierarchical( 'category' ) );
		$this->assertFalse( is_taxonomy_hierarchical( 'post_tag' ) );
		$this->assertFalse( is_taxonomy_hierarchical( 'link_category' ) );
	}

	public function test_is_taxonomy_hierarchical_unknown() {
		$this->assertFalse( is_taxonomy_hierarchical( rand_str() ) );
		$this->assertFalse( is_taxonomy_hierarchical( '' ) );
		$this->assertFalse( is_taxonomy_hierarchical( 0 ) );
		$this->assertFalse( is_taxonomy_hierarchical( null ) );
	}

	public function test_register_taxonomy() {

		// Make up a new taxonomy name, and ensure it's unused.
		$tax = 'tax_new';
		$this->assertFalse( taxonomy_exists( $tax ) );

		register_taxonomy( $tax, 'post' );
		$this->assertTrue( taxonomy_exists( $tax ) );
		$this->assertFalse( is_taxonomy_hierarchical( $tax ) );

		// Clean up.
		unset( $GLOBALS['wp_taxonomies'][ $tax ] );
	}

	public function test_register_hierarchical_taxonomy() {

		// Make up a new taxonomy name, and ensure it's unused.
		$tax = 'tax_new';
		$this->assertFalse( taxonomy_exists( $tax ) );

		register_taxonomy( $tax, 'post', array( 'hierarchical' => true ) );
		$this->assertTrue( taxonomy_exists( $tax ) );
		$this->assertTrue( is_taxonomy_hierarchical( $tax ) );

		// Clean up.
		unset( $GLOBALS['wp_taxonomies'][ $tax ] );
	}

	/**
	 * @ticket 48558
	 */
	public function test_register_taxonomy_return_value() {
		$this->assertInstanceOf( 'WP_Taxonomy', register_taxonomy( 'foo', 'post' ) );
	}

	/**
	 * @ticket 21593
	 *
	 * @expectedIncorrectUsage register_taxonomy
	 */
	public function test_register_taxonomy_with_too_long_name() {
		$this->assertInstanceOf( 'WP_Error', register_taxonomy( 'abcdefghijklmnopqrstuvwxyz0123456789', 'post', array() ) );
	}

	/**
	 * @ticket 31135
	 *
	 * @expectedIncorrectUsage register_taxonomy
	 */
	public function test_register_taxonomy_with_empty_name() {
		$this->assertInstanceOf( 'WP_Error', register_taxonomy( '', 'post', array() ) );
	}

	/**
	 * @ticket 26948
	 */
	public function test_register_taxonomy_show_in_quick_edit_should_default_to_value_of_show_ui() {
		register_taxonomy(
			'wptests_tax_1',
			'post',
			array(
				'show_ui' => true,
			)
		);

		register_taxonomy(
			'wptests_tax_2',
			'post',
			array(
				'show_ui' => false,
			)
		);

		$tax_1 = get_taxonomy( 'wptests_tax_1' );
		$this->assertTrue( $tax_1->show_in_quick_edit );

		$tax_2 = get_taxonomy( 'wptests_tax_2' );
		$this->assertFalse( $tax_2->show_in_quick_edit );
	}

	/**
	 * @ticket 53212
	 */
	public function test_register_taxonomy_fires_registered_actions() {
		$taxonomy = 'taxonomy53212';
		$action   = new MockAction();

		add_action( 'registered_taxonomy', array( $action, 'action' ) );
		add_action( "registered_taxonomy_{$taxonomy}", array( $action, 'action' ) );

		register_taxonomy( $taxonomy, 'post' );
		register_taxonomy( 'random', 'post' );

		$this->assertSame( 3, $action->get_call_count() );
	}

	/**
	 * @ticket 11058
	 */
	public function test_registering_taxonomies_to_object_types() {
		// Create a taxonomy to test with.
		$tax = 'test_tax';
		$this->assertFalse( taxonomy_exists( $tax ) );
		register_taxonomy( $tax, 'post', array( 'hierarchical' => true ) );

		// Create a post type to test with.
		$post_type = 'test_cpt';
		$this->assertFalse( get_post_type( $post_type ) );
		$this->assertObjectHasProperty( 'name', register_post_type( $post_type ) );

		// Core taxonomy, core post type.
		$this->assertTrue( unregister_taxonomy_for_object_type( 'category', 'post' ) );
		$this->assertFalse( unregister_taxonomy_for_object_type( 'category', 'post' ) );
		$this->assertTrue( register_taxonomy_for_object_type( 'category', 'post' ) );

		// Core taxonomy, non-core post type.
		$this->assertTrue( register_taxonomy_for_object_type( 'category', $post_type ) );
		$this->assertTrue( unregister_taxonomy_for_object_type( 'category', $post_type ) );
		$this->assertFalse( unregister_taxonomy_for_object_type( 'category', $post_type ) );
		$this->assertTrue( register_taxonomy_for_object_type( 'category', $post_type ) );

		// Core taxonomies, non-post object types.
		$this->assertFalse( register_taxonomy_for_object_type( 'category', 'user' ) );
		$this->assertFalse( unregister_taxonomy_for_object_type( 'category', 'user' ) );

		// Non-core taxonomy, core post type.
		$this->assertTrue( unregister_taxonomy_for_object_type( $tax, 'post' ) );
		$this->assertFalse( unregister_taxonomy_for_object_type( $tax, 'post' ) );
		$this->assertTrue( register_taxonomy_for_object_type( $tax, 'post' ) );

		// Non-core taxonomy, non-core post type.
		$this->assertTrue( register_taxonomy_for_object_type( $tax, $post_type ) );
		$this->assertTrue( unregister_taxonomy_for_object_type( $tax, $post_type ) );
		$this->assertFalse( unregister_taxonomy_for_object_type( $tax, $post_type ) );
		$this->assertTrue( register_taxonomy_for_object_type( $tax, $post_type ) );

		// Non-core taxonomies, non-post object types.
		$this->assertFalse( register_taxonomy_for_object_type( $tax, 'user' ) );
		$this->assertFalse( unregister_taxonomy_for_object_type( $tax, 'user' ) );

		unset( $GLOBALS['wp_taxonomies'][ $tax ] );
		_unregister_post_type( $post_type );
	}

	/**
	 * @ticket 32590
	 */
	public function test_register_taxonomy_for_post_type_for_taxonomy_with_no_object_type_should_filter_out_empty_object_types() {
		register_taxonomy( 'wptests_tax', '' );
		register_taxonomy_for_object_type( 'wptests_tax', 'post' );
		$tax = get_taxonomy( 'wptests_tax' );

		$expected = array( 'post' );
		$this->assertSameSets( $expected, $tax->object_type );
	}

	public function test_get_objects_in_term_should_return_invalid_taxonomy_error() {
		$terms = get_objects_in_term( 1, 'invalid_taxonomy' );
		$this->assertInstanceOf( 'WP_Error', $terms );
		$this->assertSame( 'invalid_taxonomy', $terms->get_error_code() );
	}

	public function test_get_objects_in_term_should_return_empty_array() {
		$this->assertSame( array(), get_objects_in_term( 1, 'post_tag' ) );
	}

	public function test_get_objects_in_term_should_return_objects_ids() {
		$tag_id              = self::factory()->tag->create();
		$cat_id              = self::factory()->category->create();
		$posts_with_tag      = array();
		$posts_with_category = array();

		for ( $i = 0; $i < 3; $i++ ) {
			$post_id = self::factory()->post->create();
			wp_set_post_tags( $post_id, array( $tag_id ) );
			$posts_with_tag[] = $post_id;
		}

		for ( $i = 0; $i < 3; $i++ ) {
			$post_id = self::factory()->post->create();
			wp_set_post_categories( $post_id, array( $cat_id ) );
			$posts_with_category[] = $post_id;
		}

		for ( $i = 0; $i < 3; $i++ ) {
			self::factory()->post->create();
		}

		$posts_with_terms = array_merge( $posts_with_tag, $posts_with_category );

		$this->assertEquals( $posts_with_tag, get_objects_in_term( $tag_id, 'post_tag' ) );
		$this->assertEquals( $posts_with_category, get_objects_in_term( $cat_id, 'category' ) );
		$this->assertEquals( $posts_with_terms, get_objects_in_term( array( $tag_id, $cat_id ), array( 'post_tag', 'category' ) ) );
		$this->assertEquals( array_reverse( $posts_with_tag ), get_objects_in_term( $tag_id, 'post_tag', array( 'order' => 'desc' ) ) );
	}

	/**
	 * @ticket 37094
	 */
	public function test_term_assignment_should_invalidate_get_objects_in_term_cache() {
		register_taxonomy( 'wptests_tax', 'post' );

		$posts   = self::factory()->post->create_many( 2 );
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		wp_set_object_terms( $posts[1], $term_id, 'wptests_tax' );

		// Prime cache.
		$before = get_objects_in_term( $term_id, 'wptests_tax' );
		$this->assertEqualSets( array( $posts[1] ), $before );

		wp_set_object_terms( $posts[1], array(), 'wptests_tax' );

		$after = get_objects_in_term( $term_id, 'wptests_tax' );
		$this->assertSame( array(), $after );
	}

	/**
	 * @ticket 37094
	 */
	public function test_term_deletion_should_invalidate_get_objects_in_term_cache() {
		register_taxonomy( 'wptests_tax', 'post' );

		$posts   = self::factory()->post->create_many( 2 );
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		wp_set_object_terms( $posts[1], $term_id, 'wptests_tax' );

		// Prime cache.
		$before = get_objects_in_term( $term_id, 'wptests_tax' );
		$this->assertEqualSets( array( $posts[1] ), $before );

		wp_delete_term( $term_id, 'wptests_tax' );

		$after = get_objects_in_term( $term_id, 'wptests_tax' );
		$this->assertSame( array(), $after );
	}

	/**
	 * @ticket 37094
	 */
	public function test_post_deletion_should_invalidate_get_objects_in_term_cache() {
		register_taxonomy( 'wptests_tax', 'post' );

		$posts   = self::factory()->post->create_many( 2 );
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		wp_set_object_terms( $posts[1], $term_id, 'wptests_tax' );

		// Prime cache.
		$before = get_objects_in_term( $term_id, 'wptests_tax' );
		$this->assertEqualSets( array( $posts[1] ), $before );

		wp_delete_post( $posts[1], true );

		$after = get_objects_in_term( $term_id, 'wptests_tax' );
		$this->assertSame( array(), $after );
	}

	/**
	 * @ticket 25706
	 */
	public function test_in_category() {
		$post = self::factory()->post->create_and_get();

		// in_category() returns false when first parameter is empty().
		$this->assertFalse( in_category( '', $post ) );
		$this->assertFalse( in_category( false, $post ) );
		$this->assertFalse( in_category( null, $post ) );

		// Test expected behavior of in_category().
		$term = wp_insert_term( 'Test', 'category' );
		wp_set_object_terms( $post->ID, $term['term_id'], 'category' );
		$this->assertTrue( in_category( $term['term_id'], $post ) );
	}

	public function test_insert_category_create() {
		$cat = array(
			'cat_ID'   => 0,
			'taxonomy' => 'category',
			'cat_name' => 'test1',
		);
		$this->assertIsNumeric( wp_insert_category( $cat, true ) );
	}

	public function test_insert_category_update() {
		$cat = array(
			'cat_ID'   => 1,
			'taxonomy' => 'category',
			'cat_name' => 'Updated Name',
		);
		$this->assertSame( 1, wp_insert_category( $cat ) );
	}

	public function test_insert_category_force_error_handle() {
		$cat = array(
			'cat_ID'   => 0,
			'taxonomy' => 'force_error',
			'cat_name' => 'Error',
		);
		$this->assertInstanceOf( 'WP_Error', wp_insert_category( $cat, true ) );
	}

	public function test_insert_category_force_error_no_handle() {
		$cat = array(
			'cat_ID'   => 0,
			'taxonomy' => 'force_error',
			'cat_name' => 'Error',
		);
		$this->assertSame( 0, wp_insert_category( $cat, false ) );
	}

	public function test_get_ancestors_taxonomy_non_hierarchical() {
		register_taxonomy( 'wptests_tax', 'post' );
		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$this->assertSame( array(), get_ancestors( $t, 'wptests_tax' ) );
		_unregister_taxonomy( 'wptests_tax' );
	}

	public function test_get_ancestors_taxonomy() {
		register_taxonomy(
			'wptests_tax',
			'post',
			array(
				'hierarchical' => true,
			)
		);
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'parent'   => $t1,
			)
		);
		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'parent'   => $t2,
			)
		);
		$t4 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'parent'   => $t1,
			)
		);

		$this->assertSameSets( array( $t2, $t1 ), get_ancestors( $t3, 'wptests_tax' ) );
		_unregister_taxonomy( 'wptests_tax' );
	}

	public function test_get_ancestors_post_type_non_hierarchical() {
		register_post_type( 'wptests_pt' );
		$p = self::factory()->post->create(
			array(
				'taxonomy' => 'wptests_pt',
			)
		);

		$this->assertSameSets( array(), get_ancestors( $p, 'wptests_tax' ) );
	}

	public function test_get_ancestors_post_type() {
		register_post_type(
			'wptests_pt',
			array(
				'hierarchical' => true,
			)
		);
		$p1 = self::factory()->post->create(
			array(
				'post_type' => 'wptests_pt',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_type'   => 'wptests_pt',
				'post_parent' => $p1,
			)
		);
		$p3 = self::factory()->post->create(
			array(
				'post_type'   => 'wptests_pt',
				'post_parent' => $p2,
			)
		);
		$p4 = self::factory()->post->create(
			array(
				'post_type'   => 'wptests_pt',
				'post_parent' => $p1,
			)
		);

		$this->assertSameSets( array( $p2, $p1 ), get_ancestors( $p3, 'wptests_pt' ) );
		_unregister_post_type( 'wptests_pt' );
	}

	/**
	 * @ticket 15029
	 */
	public function test_get_ancestors_taxonomy_post_type_conflict_resource_type_taxonomy() {
		register_post_type(
			'wptests_conflict',
			array(
				'hierarchical' => true,
			)
		);
		$p1 = self::factory()->post->create(
			array(
				'post_type' => 'wptests_conflict',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_type'   => 'wptests_conflict',
				'post_parent' => $p1,
			)
		);

		register_taxonomy(
			'wptests_conflict',
			'post',
			array(
				'hierarchical' => true,
			)
		);
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_conflict',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_conflict',
				'parent'   => $t1,
			)
		);

		$this->assertSameSets( array( $p1 ), get_ancestors( $p2, 'wptests_conflict', 'post_type' ) );
		$this->assertSameSets( array( $t1 ), get_ancestors( $t2, 'wptests_conflict', 'taxonomy' ) );
		$this->assertSameSets( array( $t1 ), get_ancestors( $t2, 'wptests_conflict' ) );
		_unregister_post_type( 'wptests_pt' );
	}

	/**
	 * @ticket 21949
	 */
	public function test_nonpublicly_queryable_taxonomy_should_not_be_queryable_using_taxname_query_var() {
		register_taxonomy(
			'wptests_tax',
			'post',
			array(
				'publicly_queryable' => false,
			)
		);

		$t = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$p = self::factory()->post->create();
		wp_set_object_terms( $p, $t->slug, 'wptests_tax' );

		$this->go_to( '/?wptests_tax=' . $t->slug );

		$this->assertFalse( is_tax( 'wptests_tax' ) );
	}

	/**
	 * @ticket 21949
	 */
	public function test_it_should_be_possible_to_register_a_query_var_that_matches_the_name_of_a_nonpublicly_queryable_taxonomy() {
		global $wp;

		register_taxonomy(
			'wptests_tax',
			'post',
			array(
				'publicly_queryable' => false,
			)
		);
		$t = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$p = self::factory()->post->create();
		wp_set_object_terms( $p, $t->slug, 'wptests_tax' );

		add_filter( 'do_parse_request', array( $this, 'register_query_var' ) );
		$this->go_to( '/?wptests_tax=foo' );
		remove_filter( 'do_parse_request', array( $this, 'register_query_var' ) );

		// Not a taxonomy...
		$this->assertFalse( is_tax( 'wptests_tax' ) );

		// ...but query var works.
		$this->assertSame( 'foo', $wp->query_vars['wptests_tax'] );
	}

	public static function register_query_var( $r ) {
		global $wp;

		$wp->add_query_var( 'wptests_tax' );

		return $r;
	}

	/**
	 * @ticket 21949
	 */
	public function test_nonpublicly_queryable_taxonomy_should_not_be_queryable_using_taxonomy_and_term_vars() {
		register_taxonomy(
			'wptests_tax',
			'post',
			array(
				'publicly_queryable' => false,
			)
		);

		$t = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$p = self::factory()->post->create();
		wp_set_object_terms( $p, $t->slug, 'wptests_tax' );

		$this->go_to( '/?taxonomy=wptests_tax&term=' . $t->slug );

		$this->assertFalse( is_tax( 'wptests_tax' ) );
	}

	/**
	 * @ticket 34491
	 */
	public function test_public_taxonomy_should_be_publicly_queryable() {
		register_taxonomy(
			'wptests_tax',
			'post',
			array(
				'public' => true,
			)
		);

		$this->assertContains( 'wptests_tax', get_taxonomies( array( 'publicly_queryable' => true ) ) );

		$t = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$p = self::factory()->post->create();
		wp_set_object_terms( $p, $t->slug, 'wptests_tax' );

		$this->go_to( '/?wptests_tax=' . $t->slug );

		$this->assertTrue( is_tax( 'wptests_tax' ) );
	}

	/**
	 * @ticket 34491
	 */
	public function test_private_taxonomy_should_not_be_publicly_queryable() {
		register_taxonomy(
			'wptests_tax',
			'post',
			array(
				'public' => false,
			)
		);

		$this->assertContains( 'wptests_tax', get_taxonomies( array( 'publicly_queryable' => false ) ) );

		$t = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$p = self::factory()->post->create();
		wp_set_object_terms( $p, $t->slug, 'wptests_tax' );

		$this->go_to( '/?wptests_tax=' . $t->slug );

		$this->assertFalse( is_tax( 'wptests_tax' ) );
	}

	/**
	 * @ticket 34491
	 */
	public function test_private_taxonomy_should_be_overridden_by_publicly_queryable() {
		register_taxonomy(
			'wptests_tax',
			'post',
			array(
				'public'             => false,
				'publicly_queryable' => true,
			)
		);

		$this->assertContains( 'wptests_tax', get_taxonomies( array( 'publicly_queryable' => true ) ) );

		$t = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$p = self::factory()->post->create();
		wp_set_object_terms( $p, $t->slug, 'wptests_tax' );

		$this->go_to( '/?wptests_tax=' . $t->slug );

		$this->assertTrue( is_tax( 'wptests_tax' ) );
	}

	/**
	 * @ticket 35089
	 */
	public function test_query_var_should_be_forced_to_false_for_non_public_taxonomy() {
		register_taxonomy(
			'wptests_tax',
			'post',
			array(
				'public'    => false,
				'query_var' => true,
			)
		);

		$tax = get_taxonomy( 'wptests_tax' );
		$this->assertFalse( $tax->query_var );
	}

	/**
	 * @ticket 35227
	 */
	public function test_unregister_taxonomy_unknown_taxonomy() {
		$this->assertWPError( unregister_taxonomy( 'foo' ) );
	}

	/**
	 * @ticket 35227
	 */
	public function test_unregister_taxonomy_twice() {
		register_taxonomy( 'foo', 'post' );
		$this->assertTrue( unregister_taxonomy( 'foo' ) );
		$this->assertWPError( unregister_taxonomy( 'foo' ) );
	}

	/**
	 * @ticket 35227
	 */
	public function test_unregister_taxonomy_disallow_builtin_taxonomy() {
		$this->assertWPError( unregister_taxonomy( 'post_tag' ) );
		$this->assertWPError( unregister_taxonomy( 'category' ) );
	}

	/**
	 * @ticket 35227
	 */
	public function test_unregister_taxonomy_removes_query_vars() {
		global $wp;

		register_taxonomy( 'foo', 'post', array( 'query_var' => 'bar' ) );

		$this->assertIsInt( array_search( 'bar', $wp->public_query_vars, true ) );
		$this->assertTrue( unregister_taxonomy( 'foo' ) );
		$this->assertFalse( array_search( 'bar', $wp->public_query_vars, true ) );
	}

	/**
	 * @ticket 35227
	 */
	public function test_unregister_taxonomy_removes_permastruct() {
		$this->set_permalink_structure( '/%postname%' );

		global $wp_rewrite;

		register_taxonomy(
			'foo',
			'post',
			array(
				'query_var' => 'bar',
				'rewrite'   => true,
			)
		);

		$this->assertIsArray( $wp_rewrite->extra_permastructs['foo'] );
		$this->assertTrue( unregister_taxonomy( 'foo' ) );
		$this->assertArrayNotHasKey( 'foo', $wp_rewrite->extra_permastructs );
	}

	/**
	 * @ticket 35227
	 */
	public function test_unregister_taxonomy_removes_rewrite_rules() {
		$this->set_permalink_structure( '/%postname%' );

		global $wp_rewrite;

		register_taxonomy( 'foo', 'post', array( 'query_var' => 'bar' ) );

		$count_before = count( $wp_rewrite->rewritereplace );

		$this->assertContains( '%foo%', $wp_rewrite->rewritecode );
		$this->assertContains( 'bar=', $wp_rewrite->queryreplace );
		$this->assertTrue( unregister_taxonomy( 'foo' ) );
		$this->assertNotContains( '%foo%', $wp_rewrite->rewritecode );
		$this->assertNotContains( 'bar=', $wp_rewrite->queryreplace );
		$this->assertCount( --$count_before, $wp_rewrite->rewritereplace ); // Array was reduced by one value.
	}

	/**
	 * @ticket 35227
	 */
	public function test_unregister_taxonomy_removes_taxonomy_from_global() {
		global $wp_taxonomies;

		register_taxonomy( 'foo', 'post' );

		$this->assertIsObject( $wp_taxonomies['foo'] );
		$this->assertIsObject( get_taxonomy( 'foo' ) );

		$this->assertTrue( unregister_taxonomy( 'foo' ) );

		$this->assertArrayNotHasKey( 'foo', $wp_taxonomies );
		$this->assertFalse( get_taxonomy( 'foo' ) );
	}

	/**
	 * @ticket 35227
	 */
	public function test_unregister_taxonomy_removes_meta_box_callback() {
		global $wp_filter;

		register_taxonomy( 'foo', 'post' );

		$this->assertArrayHasKey( 'wp_ajax_add-foo', $wp_filter );
		$this->assertCount( 1, $wp_filter['wp_ajax_add-foo']->callbacks );
		$this->assertTrue( unregister_taxonomy( 'foo' ) );
		$this->assertArrayNotHasKey( 'wp_ajax_add-foo', $wp_filter );
	}

	/**
	 * @ticket 35227
	 */
	public function test_taxonomy_does_not_exist_after_unregister_taxonomy() {
		register_taxonomy( 'foo', 'post' );
		$this->assertTrue( taxonomy_exists( 'foo' ) );
		unregister_taxonomy( 'foo' );
		$this->assertFalse( taxonomy_exists( 'foo' ) );
	}

	/**
	 * @ticket 39308
	 */
	public function test_taxonomy_name_property_should_not_get_overridden_by_passed_args() {
		register_taxonomy( 'foo', 'post', array( 'name' => 'bar' ) );

		$taxonomy = get_taxonomy( 'foo' );
		unregister_taxonomy( 'foo' );

		$this->assertSame( 'foo', $taxonomy->name );
	}

	/**
	 * @ticket 36514
	 */
	public function test_edit_post_hierarchical_taxonomy() {

		$taxonomy_name = 'foo';
		$term_name     = 'bar';

		register_taxonomy(
			$taxonomy_name,
			array( 'post' ),
			array(
				'hierarchical' => false,
				'meta_box_cb'  => 'post_categories_meta_box',
			)
		);
		$post = self::factory()->post->create_and_get(
			array(
				'post_type' => 'post',
			)
		);

		$term_id = self::factory()->term->create_object(
			array(
				'name'     => $term_name,
				'taxonomy' => $taxonomy_name,
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$updated_post_id = edit_post(
			array(
				'post_ID'   => $post->ID,
				'post_type' => 'post',
				'tax_input' => array(
					$taxonomy_name => array(
						(string) $term_id, // Cast term_id as string to match what's sent in WP Admin.
					),
				),
			)
		);

		$terms_obj        = get_the_terms( $updated_post_id, $taxonomy_name );
		$problematic_term = current( wp_list_pluck( $terms_obj, 'name' ) );

		$this->assertSame( $problematic_term, $term_name );
	}

	/**
	 * Test default term for custom taxonomy.
	 *
	 * @ticket 43517
	 */
	public function test_default_term_for_custom_taxonomy() {

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$tax = 'custom-tax';

		// Create custom taxonomy to test with.
		register_taxonomy(
			$tax,
			'post',
			array(
				'hierarchical' => true,
				'public'       => true,
				'default_term' => array(
					'name' => 'Default category',
					'slug' => 'default-category',
				),
			)
		);

		// Add post.
		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Foo',
				'post_type'  => 'post',
			)
		);

		// Test default term.
		$term = wp_get_post_terms( $post_id, $tax );
		$this->assertSame( get_option( 'default_term_' . $tax ), $term[0]->term_id );

		// Test default term deletion.
		$this->assertSame( wp_delete_term( $term[0]->term_id, $tax ), 0 );

		// Add custom post type.
		register_post_type(
			'post-custom-tax',
			array(
				'taxonomies' => array( $tax ),
			)
		);
		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Foo',
				'post_type'  => 'post-custom-tax',
			)
		);

		// Test default term.
		$term = wp_get_post_terms( $post_id, $tax );
		$this->assertSame( get_option( 'default_term_' . $tax ), $term[0]->term_id );

		// wp_set_object_terms() should not assign default term.
		wp_set_object_terms( $post_id, array(), $tax );
		$term = wp_get_post_terms( $post_id, $tax );
		$this->assertSame( array(), $term );
	}

	/**
	 * @ticket 51320
	 */
	public function test_default_term_for_post_in_multiple_taxonomies() {
		$post_type = 'test_post_type';
		$tax1      = 'test_tax1';
		$tax2      = 'test_tax2';

		register_post_type( $post_type, array( 'taxonomies' => array( $tax1, $tax2 ) ) );
		register_taxonomy( $tax1, $post_type, array( 'default_term' => 'term_1' ) );
		register_taxonomy( $tax2, $post_type, array( 'default_term' => 'term_2' ) );

		$post_id = self::factory()->post->create( array( 'post_type' => $post_type ) );

		$taxonomies = get_post_taxonomies( $post_id );

		$this->assertContains( $tax1, $taxonomies );
		$this->assertContains( $tax2, $taxonomies );
	}

	/**
	 * Ensure custom callbacks are used when registered.
	 *
	 * @covers ::register_taxonomy
	 * @ticket 40351
	 */
	function test_register_taxonomy_counting_callbacks() {
		$post_id = self::factory()->post->create();

		register_taxonomy(
			'wp_tax_40351_full_only',
			'post',
			array(
				'update_count_callback' => array( $this, 'cb_register_taxonomy_full_count_callback' ),
			)
		);
		$full_term    = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wp_tax_40351_full_only',
			)
		);
		$full_term_id = $full_term->term_id;

		register_taxonomy(
			'wp_tax_40351_partial_only',
			'post',
			array(
				'update_count_by_callback' => array( $this, 'cb_register_taxonomy_partial_count_callback' ),
			)
		);
		$partial_term    = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wp_tax_40351_partial_only',
			)
		);
		$partial_term_id = $partial_term->term_id;

		register_taxonomy(
			'wp_tax_40351_both',
			'post',
			array(
				'update_count_callback'    => array( $this, 'cb_register_taxonomy_full_count_callback' ),
				'update_count_by_callback' => array( $this, 'cb_register_taxonomy_partial_count_callback' ),
			)
		);
		$both_term      = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wp_tax_40351_both',
			)
		);
		$both_term_id   = $both_term->term_id;
		$both_term_ttid = $both_term->term_taxonomy_id;

		wp_set_post_terms( $post_id, $full_term_id, 'wp_tax_40351_full_only' );
		$this->assertSame( 0, $this->partial_count_cb_called );
		$this->assertSame( 1, $this->full_count_cb_called );

		wp_set_post_terms( $post_id, $partial_term_id, 'wp_tax_40351_partial_only' );
		$this->assertSame( 1, $this->partial_count_cb_called );
		$this->assertSame( 1, $this->full_count_cb_called );

		wp_set_post_terms( $post_id, $both_term_id, 'wp_tax_40351_both' );
		$this->assertSame( 2, $this->partial_count_cb_called );
		$this->assertSame( 1, $this->full_count_cb_called );

		// Force a full recount `$both_term` to ensure callback is called.
		wp_update_term_count( $both_term_ttid, 'wp_tax_40351_both' );
		$this->assertSame( 2, $this->full_count_cb_called );
	}

	/**
	 * Custom full count callback for `test_register_taxonomy_counting_callbacks()`.
	 *
	 * For the purpose of this test no database modifications are required, therefore
	 * the parameters passed are unused.
	 *
	 * @param int|array $tt_ids   The term_taxonomy_id of the terms.
	 * @param string    $taxonomy The context of the term.
	 */
	function cb_register_taxonomy_full_count_callback( $tt_ids, $taxonomy ) {
		$this->full_count_cb_called++;
	}

	/**
	 * Custom partial count callback for `test_register_taxonomy_counting_callbacks()`.
	 *
	 * For the purpose of this test no database modifications are required, therefore
	 * the parameters passed are unused.
	 *
	 * @param int|array $tt_ids    The term_taxonomy_id of the terms.
	 * @param string    $taxonomy  The context of the term.
	 * @param int       $modify_by By how many the term count is to be modified.
	 */
	function cb_register_taxonomy_partial_count_callback( $tt_ids, $taxonomy, $modify_by ) {
		$this->partial_count_cb_called++;
	}
}
