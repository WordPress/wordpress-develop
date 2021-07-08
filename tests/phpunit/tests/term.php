<?php

/**
 * @group taxonomy
 */
class Tests_Term extends WP_UnitTestCase {
	protected $taxonomy        = 'category';
	protected static $post_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_ids = $factory->post->create_many( 5 );
	}

	/**
	 * @ticket 29911
	 */
	public function test_wp_delete_term_should_invalidate_cache_for_child_terms() {
		register_taxonomy(
			'wptests_tax',
			'post',
			array(
				'hierarchical' => true,
			)
		);

		$parent = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$child = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'parent'   => $parent,
				'slug'     => 'foo',
			)
		);

		// Prime the cache.
		$child_term = get_term( $child, 'wptests_tax' );
		$this->assertSame( $parent, $child_term->parent );

		wp_delete_term( $parent, 'wptests_tax' );
		$child_term = get_term( $child, 'wptests_tax' );
		$this->assertSame( 0, $child_term->parent );
	}

	/**
	 * @ticket 5381
	 */
	function test_is_term_type() {
		// Insert a term.
		$term = rand_str();
		$t    = wp_insert_term( $term, $this->taxonomy );
		$this->assertIsArray( $t );
		$term_obj = get_term_by( 'name', $term, $this->taxonomy );
		$this->assertEquals( $t['term_id'], term_exists( $term_obj->slug ) );

		// Clean up.
		$this->assertTrue( wp_delete_term( $t['term_id'], $this->taxonomy ) );
	}

	/**
	 * @ticket 15919
	 */
	function test_wp_count_terms() {
		$count = wp_count_terms(
			array(
				'hide_empty' => true,
				'taxonomy'   => 'category',
			)
		);
		// There are 5 posts, all Uncategorized.
		$this->assertEquals( 1, $count );
	}

	/**
	 * @ticket 36399
	 */
	function test_wp_count_terms_legacy_interoperability() {
		self::factory()->tag->create_many( 5 );

		// Counts all terms (1 default category, 5 tags).
		$count = wp_count_terms();
		$this->assertEquals( 6, $count );

		// Counts only tags (5), with both current and legacy signature.
		// Legacy usage should not trigger deprecated notice.
		$count        = wp_count_terms( array( 'taxonomy' => 'post_tag' ) );
		$legacy_count = wp_count_terms( 'post_tag' );
		$this->assertEquals( 5, $count );
		$this->assertEquals( $count, $legacy_count );
	}

	/**
	 * @ticket 15475
	 */
	function test_wp_add_remove_object_terms() {
		$posts = self::$post_ids;
		$tags  = self::factory()->tag->create_many( 5 );

		$tt = wp_add_object_terms( $posts[0], $tags[1], 'post_tag' );
		$this->assertCount( 1, $tt );
		$this->assertSame( array( $tags[1] ), wp_get_object_terms( $posts[0], 'post_tag', array( 'fields' => 'ids' ) ) );

		$three_tags = array( $tags[0], $tags[1], $tags[2] );
		$tt         = wp_add_object_terms( $posts[1], $three_tags, 'post_tag' );
		$this->assertCount( 3, $tt );
		$this->assertSame( $three_tags, wp_get_object_terms( $posts[1], 'post_tag', array( 'fields' => 'ids' ) ) );

		$this->assertTrue( wp_remove_object_terms( $posts[0], $tags[1], 'post_tag' ) );
		$this->assertFalse( wp_remove_object_terms( $posts[0], $tags[0], 'post_tag' ) );
		$this->assertInstanceOf( 'WP_Error', wp_remove_object_terms( $posts[0], $tags[1], 'non_existing_taxonomy' ) );
		$this->assertTrue( wp_remove_object_terms( $posts[1], $three_tags, 'post_tag' ) );
		$this->assertCount( 0, wp_get_object_terms( $posts[1], 'post_tag' ) );

		foreach ( $tags as $term_id ) {
			$this->assertTrue( wp_delete_term( $term_id, 'post_tag' ) );
		}

		foreach ( $posts as $post_id ) {
			$this->assertTrue( (bool) wp_delete_post( $post_id ) );
		}
	}

	/**
	 * @group category.php
	 */
	function test_term_is_ancestor_of() {
		$term  = rand_str();
		$term2 = rand_str();

		$t = wp_insert_term( $term, 'category' );
		$this->assertIsArray( $t );
		$t2 = wp_insert_term( $term, 'category', array( 'parent' => $t['term_id'] ) );
		$this->assertIsArray( $t2 );
		if ( function_exists( 'term_is_ancestor_of' ) ) {
			$this->assertTrue( term_is_ancestor_of( $t['term_id'], $t2['term_id'], 'category' ) );
			$this->assertFalse( term_is_ancestor_of( $t2['term_id'], $t['term_id'], 'category' ) );
		}
		$this->assertTrue( cat_is_ancestor_of( $t['term_id'], $t2['term_id'] ) );
		$this->assertFalse( cat_is_ancestor_of( $t2['term_id'], $t['term_id'] ) );

		wp_delete_term( $t['term_id'], 'category' );
		wp_delete_term( $t2['term_id'], 'category' );
	}

	function test_wp_insert_delete_category() {
		$term = rand_str();
		$this->assertNull( category_exists( $term ) );

		$initial_count = wp_count_terms( array( 'taxonomy' => 'category' ) );

		$t = wp_insert_category( array( 'cat_name' => $term ) );
		$this->assertTrue( is_numeric( $t ) );
		$this->assertNotWPError( $t );
		$this->assertTrue( $t > 0 );
		$this->assertEquals( $initial_count + 1, wp_count_terms( array( 'taxonomy' => 'category' ) ) );

		// Make sure the term exists.
		$this->assertTrue( term_exists( $term ) > 0 );
		$this->assertTrue( term_exists( $t ) > 0 );

		// Now delete it.
		$this->assertTrue( wp_delete_category( $t ) );
		$this->assertNull( term_exists( $term ) );
		$this->assertNull( term_exists( $t ) );
		$this->assertEquals( $initial_count, wp_count_terms( array( 'taxonomy' => 'category' ) ) );
	}

	/**
	 * @ticket 16550
	 */
	function test_wp_set_post_categories() {
		$post_id = self::$post_ids[0];
		$post    = get_post( $post_id );

		$this->assertIsArray( $post->post_category );
		$this->assertCount( 1, $post->post_category );
		$this->assertEquals( get_option( 'default_category' ), $post->post_category[0] );

		$term1 = wp_insert_term( 'Foo', 'category' );
		$term2 = wp_insert_term( 'Bar', 'category' );
		$term3 = wp_insert_term( 'Baz', 'category' );

		wp_set_post_categories( $post_id, array( $term1['term_id'], $term2['term_id'] ) );
		$this->assertCount( 2, $post->post_category );
		$this->assertSame( array( $term2['term_id'], $term1['term_id'] ), $post->post_category );

		wp_set_post_categories( $post_id, $term3['term_id'], true );
		$this->assertSame( array( $term2['term_id'], $term3['term_id'], $term1['term_id'] ), $post->post_category );

		$term4 = wp_insert_term( 'Burrito', 'category' );

		wp_set_post_categories( $post_id, $term4['term_id'] );
		$this->assertSame( array( $term4['term_id'] ), $post->post_category );

		wp_set_post_categories( $post_id, array( $term1['term_id'], $term2['term_id'] ), true );
		$this->assertSame( array( $term2['term_id'], $term4['term_id'], $term1['term_id'] ), $post->post_category );

		wp_set_post_categories( $post_id, array(), true );
		$this->assertCount( 1, $post->post_category );
		$this->assertEquals( get_option( 'default_category' ), $post->post_category[0] );

		wp_set_post_categories( $post_id, array() );
		$this->assertCount( 1, $post->post_category );
		$this->assertEquals( get_option( 'default_category' ), $post->post_category[0] );
	}

	/**
	 * @ticket 43516
	 */
	function test_wp_set_post_categories_sets_default_category_for_custom_post_types() {
		add_filter( 'default_category_post_types', array( $this, 'filter_default_category_post_types' ) );

		register_post_type( 'cpt', array( 'taxonomies' => array( 'category' ) ) );

		$post_id = self::factory()->post->create( array( 'post_type' => 'cpt' ) );
		$post    = get_post( $post_id );

		$this->assertEquals( get_option( 'default_category' ), $post->post_category[0] );

		$term = wp_insert_term( 'Foo', 'category' );

		wp_set_post_categories( $post_id, $term['term_id'] );
		$this->assertSame( $term['term_id'], $post->post_category[0] );

		wp_set_post_categories( $post_id, array() );
		$this->assertEquals( get_option( 'default_category' ), $post->post_category[0] );

		remove_filter( 'default_category_post_types', array( $this, 'filter_default_category_post_types' ) );
	}

	function filter_default_category_post_types( $post_types ) {
		$post_types[] = 'cpt';
		return $post_types;
	}

	/**
	 * @ticket 25852
	 */
	function test_sanitize_term_field() {
		$term = wp_insert_term( 'foo', $this->taxonomy );

		$this->assertSame( 0, sanitize_term_field( 'parent', 0, $term['term_id'], $this->taxonomy, 'raw' ) );
		$this->assertSame( 1, sanitize_term_field( 'parent', 1, $term['term_id'], $this->taxonomy, 'raw' ) );
		$this->assertSame( 0, sanitize_term_field( 'parent', -1, $term['term_id'], $this->taxonomy, 'raw' ) );
		$this->assertSame( 0, sanitize_term_field( 'parent', '', $term['term_id'], $this->taxonomy, 'raw' ) );
	}

	/**
	 * @ticket 19205
	 */
	function test_orphan_category() {
		$cat_id1 = self::factory()->category->create();

		wp_delete_category( $cat_id1 );

		$cat_id2 = self::factory()->category->create( array( 'parent' => $cat_id1 ) );
		$this->assertWPError( $cat_id2 );
	}
}
