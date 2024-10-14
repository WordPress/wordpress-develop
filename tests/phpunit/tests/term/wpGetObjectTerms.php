<?php

/**
 * @group taxonomy
 * @covers ::wp_get_object_terms
 */
class Tests_Term_WpGetObjectTerms extends WP_UnitTestCase {
	private $taxonomy = 'wptests_tax';

	/**
	 * Temporary storage for taxonomies for tests using filter callbacks.
	 *
	 * Used in the `test_taxonomies_passed_to_wp_get_object_terms_filter_should_be_quoted()` method.
	 *
	 * @var array
	 */
	private $taxonomies;

	public function set_up() {
		parent::set_up();
		register_taxonomy( 'wptests_tax', 'post' );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		unset( $this->taxonomies );

		parent::tear_down();
	}

	public function test_get_object_terms_by_slug() {
		$post_id = self::factory()->post->create();

		$terms_1       = array( 'Foo', 'Bar', 'Baz' );
		$terms_1_slugs = array( 'foo', 'bar', 'baz' );

		// Set the initial terms.
		$tt_1 = wp_set_object_terms( $post_id, $terms_1, $this->taxonomy );
		$this->assertCount( 3, $tt_1 );

		// Make sure they're correct.
		$terms = wp_get_object_terms(
			$post_id,
			$this->taxonomy,
			array(
				'fields'  => 'slugs',
				'orderby' => 'term_id',
			)
		);
		$this->assertSame( $terms_1_slugs, $terms );
	}

	/**
	 * @ticket 11003
	 */
	public function test_should_not_filter_out_duplicate_terms_associated_with_different_objects() {
		$post_id1 = self::factory()->post->create();
		$post_id2 = self::factory()->post->create();
		$cat_id   = self::factory()->category->create();
		$cat_id2  = self::factory()->category->create();
		wp_set_post_categories( $post_id1, array( $cat_id, $cat_id2 ) );
		wp_set_post_categories( $post_id2, $cat_id );

		$terms = wp_get_object_terms( array( $post_id1, $post_id2 ), 'category' );
		$this->assertCount( 2, $terms );
		$this->assertSame( array( $cat_id, $cat_id2 ), wp_list_pluck( $terms, 'term_id' ) );

		$terms2 = wp_get_object_terms(
			array( $post_id1, $post_id2 ),
			'category',
			array(
				'fields' => 'all_with_object_id',
			)
		);

		$this->assertCount( 3, $terms2 );
		$this->assertSame( array( $cat_id, $cat_id, $cat_id2 ), wp_list_pluck( $terms2, 'term_id' ) );
	}

	/**
	 * @ticket 17646
	 */
	public function test_should_return_objects_with_int_properties() {
		$post_id = self::factory()->post->create();
		$term    = wp_insert_term( 'one', $this->taxonomy );
		wp_set_object_terms( $post_id, $term, $this->taxonomy );

		$terms      = wp_get_object_terms( $post_id, $this->taxonomy, array( 'fields' => 'all_with_object_id' ) );
		$term       = array_shift( $terms );
		$int_fields = array( 'parent', 'term_id', 'count', 'term_group', 'term_taxonomy_id', 'object_id' );
		foreach ( $int_fields as $field ) {
			$this->assertIsInt( $term->$field, $field );
		}

		$terms = wp_get_object_terms( $post_id, $this->taxonomy, array( 'fields' => 'ids' ) );
		$term  = array_shift( $terms );
		$this->assertIsInt( $term, 'term' );
	}

	/**
	 * @ticket 26339
	 */
	public function test_references_should_be_reset_after_wp_get_object_terms_filter() {
		$post_id = self::factory()->post->create();
		$terms_1 = array( 'foo', 'bar', 'baz' );

		wp_set_object_terms( $post_id, $terms_1, $this->taxonomy );
		add_filter( 'wp_get_object_terms', array( $this, 'filter_get_object_terms' ) );
		$terms = wp_get_object_terms( $post_id, $this->taxonomy );
		remove_filter( 'wp_get_object_terms', array( $this, 'filter_get_object_terms' ) );
		foreach ( $terms as $term ) {
			$this->assertIsObject( $term );
		}
	}

	/**
	 * @ticket 40154
	 */
	public function test_taxonomies_passed_to_wp_get_object_terms_filter_should_be_quoted() {
		register_taxonomy( 'wptests_tax', 'post' );
		register_taxonomy( 'wptests_tax_2', 'post' );

		add_filter( 'wp_get_object_terms', array( $this, 'wp_get_object_terms_callback' ), 10, 3 );
		$terms = wp_get_object_terms( 1, array( 'wptests_tax', 'wptests_tax_2' ) );
		remove_filter( 'wp_get_object_terms', array( $this, 'wp_get_object_terms_callback' ), 10, 3 );

		$this->assertSame( "'wptests_tax', 'wptests_tax_2'", $this->taxonomies );
	}

	public function wp_get_object_terms_callback( $terms, $object_ids, $taxonomies ) {
		$this->taxonomies = $taxonomies;
		return $terms;
	}

	public function test_orderby_name() {
		$p = self::factory()->post->create();

		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'name'     => 'AAA',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'name'     => 'ZZZ',
			)
		);
		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'name'     => 'JJJ',
			)
		);

		wp_set_object_terms( $p, array( $t1, $t2, $t3 ), $this->taxonomy );

		$found = wp_get_object_terms(
			$p,
			$this->taxonomy,
			array(
				'orderby' => 'name',
				'fields'  => 'ids',
			)
		);

		$this->assertSame( array( $t1, $t3, $t2 ), $found );
	}

	public function test_orderby_count() {
		$posts = self::factory()->post->create_many( 3 );

		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'name'     => 'AAA',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'name'     => 'ZZZ',
			)
		);
		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'name'     => 'JJJ',
			)
		);

		wp_set_object_terms( $posts[0], array( $t3, $t2, $t1 ), $this->taxonomy );
		wp_set_object_terms( $posts[1], array( $t3, $t1 ), $this->taxonomy );
		wp_set_object_terms( $posts[2], array( $t3 ), $this->taxonomy );

		$found = wp_get_object_terms(
			$posts[0],
			$this->taxonomy,
			array(
				'orderby' => 'count',
				'fields'  => 'ids',
			)
		);

		$this->assertSame( array( $t2, $t1, $t3 ), $found );
	}

	public function test_orderby_slug() {
		$p = self::factory()->post->create();

		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'slug'     => 'aaa',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'slug'     => 'zzz',
			)
		);
		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'slug'     => 'jjj',
			)
		);

		wp_set_object_terms( $p, array( $t1, $t2, $t3 ), $this->taxonomy );

		$found = wp_get_object_terms(
			$p,
			$this->taxonomy,
			array(
				'orderby' => 'slug',
				'fields'  => 'ids',
			)
		);

		$this->assertSame( array( $t1, $t3, $t2 ), $found );
	}

	public function test_orderby_term_group() {
		$p = self::factory()->post->create();

		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);
		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);

		// No great way to do this in the API.
		global $wpdb;
		$wpdb->update( $wpdb->terms, array( 'term_group' => 1 ), array( 'term_id' => $t1 ) );
		$wpdb->update( $wpdb->terms, array( 'term_group' => 3 ), array( 'term_id' => $t2 ) );
		$wpdb->update( $wpdb->terms, array( 'term_group' => 2 ), array( 'term_id' => $t3 ) );

		wp_set_object_terms( $p, array( $t1, $t2, $t3 ), $this->taxonomy );

		$found = wp_get_object_terms(
			$p,
			$this->taxonomy,
			array(
				'orderby' => 'term_group',
				'fields'  => 'ids',
			)
		);

		$this->assertSame( array( $t1, $t3, $t2 ), $found );
	}

	public function test_orderby_term_order() {
		$p = self::factory()->post->create();

		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);
		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);

		$set = wp_set_object_terms( $p, array( $t1, $t2, $t3 ), $this->taxonomy );

		// No great way to do this in the API.
		$term_1 = get_term( $t1, $this->taxonomy );
		$term_2 = get_term( $t2, $this->taxonomy );
		$term_3 = get_term( $t3, $this->taxonomy );

		global $wpdb;
		$wpdb->update(
			$wpdb->term_relationships,
			array( 'term_order' => 1 ),
			array(
				'term_taxonomy_id' => $term_1->term_taxonomy_id,
				'object_id'        => $p,
			)
		);
		$wpdb->update(
			$wpdb->term_relationships,
			array( 'term_order' => 3 ),
			array(
				'term_taxonomy_id' => $term_2->term_taxonomy_id,
				'object_id'        => $p,
			)
		);
		$wpdb->update(
			$wpdb->term_relationships,
			array( 'term_order' => 2 ),
			array(
				'term_taxonomy_id' => $term_3->term_taxonomy_id,
				'object_id'        => $p,
			)
		);

		$found = wp_get_object_terms(
			$p,
			$this->taxonomy,
			array(
				'orderby' => 'term_order',
				'fields'  => 'ids',
			)
		);

		$this->assertSame( array( $t1, $t3, $t2 ), $found );
	}

	/**
	 * @ticket 28688
	 */
	public function test_orderby_parent() {
		$p = self::factory()->post->create();

		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);
		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);

		$set = wp_set_object_terms( $p, array( $t1, $t2, $t3 ), $this->taxonomy );

		$term_1 = get_term( $t1, $this->taxonomy );
		$term_2 = get_term( $t2, $this->taxonomy );
		$term_3 = get_term( $t3, $this->taxonomy );

		global $wpdb;
		$wpdb->update( $wpdb->term_taxonomy, array( 'parent' => 1 ), array( 'term_taxonomy_id' => $term_1->term_taxonomy_id ) );
		$wpdb->update( $wpdb->term_taxonomy, array( 'parent' => 3 ), array( 'term_taxonomy_id' => $term_2->term_taxonomy_id ) );
		$wpdb->update( $wpdb->term_taxonomy, array( 'parent' => 2 ), array( 'term_taxonomy_id' => $term_3->term_taxonomy_id ) );

		$found = wp_get_object_terms(
			$p,
			$this->taxonomy,
			array(
				'orderby' => 'parent',
				'fields'  => 'ids',
			)
		);

		$this->assertSame( array( $t1, $t3, $t2 ), $found );
	}

	/**
	 * @ticket 28688
	 */
	public function test_orderby_taxonomy() {
		register_taxonomy( 'wptests_tax_2', 'post' );
		register_taxonomy( 'wptests_tax_3', 'post' );

		$p = self::factory()->post->create();

		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax_3',
			)
		);
		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax_2',
			)
		);

		wp_set_object_terms( $p, $t1, $this->taxonomy );
		wp_set_object_terms( $p, $t2, 'wptests_tax_3' );
		wp_set_object_terms( $p, $t3, 'wptests_tax_2' );

		$found = wp_get_object_terms(
			$p,
			array( $this->taxonomy, 'wptests_tax_2', 'wptests_tax_3' ),
			array(
				'orderby' => 'taxonomy',
				'fields'  => 'ids',
			)
		);

		$this->assertSame( array( $t1, $t3, $t2 ), $found );
	}

	/**
	 * @ticket 28688
	 */
	public function test_orderby_tt_id() {
		$p = self::factory()->post->create();

		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);
		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);

		// term_taxonomy_id will only have a different order from term_id in legacy situations.
		$term_1 = get_term( $t1, $this->taxonomy );
		$term_2 = get_term( $t2, $this->taxonomy );
		$term_3 = get_term( $t3, $this->taxonomy );

		global $wpdb;
		$wpdb->update( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => 100004 ), array( 'term_taxonomy_id' => $term_1->term_taxonomy_id ) );
		$wpdb->update( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => 100006 ), array( 'term_taxonomy_id' => $term_2->term_taxonomy_id ) );
		$wpdb->update( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => 100005 ), array( 'term_taxonomy_id' => $term_3->term_taxonomy_id ) );

		clean_term_cache( array( $t1, $t2, $t3 ), $this->taxonomy );

		$set = wp_set_object_terms( $p, array( $t1, $t2, $t3 ), $this->taxonomy );

		$found = wp_get_object_terms(
			$p,
			$this->taxonomy,
			array(
				'orderby' => 'term_taxonomy_id',
				'fields'  => 'ids',
			)
		);

		$this->assertSame( array( $t1, $t3, $t2 ), $found );
	}

	public function test_order_desc() {
		$p = self::factory()->post->create();

		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'name'     => 'AAA',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'name'     => 'ZZZ',
			)
		);
		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'name'     => 'JJJ',
			)
		);

		wp_set_object_terms( $p, array( $t1, $t2, $t3 ), $this->taxonomy );

		$found = wp_get_object_terms(
			$p,
			$this->taxonomy,
			array(
				'orderby' => 'name',
				'order'   => 'DESC',
				'fields'  => 'ids',
			)
		);

		$this->assertSame( array( $t2, $t3, $t1 ), $found );
	}

	/**
	 * @ticket 15675
	 */
	public function test_parent() {
		register_taxonomy(
			'wptests_tax2',
			'post',
			array(
				'hierarchical' => true,
			)
		);

		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
			)
		);
		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'parent'   => $t1,
			)
		);
		$t4 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'parent'   => $t2,
			)
		);

		$p = self::factory()->post->create();

		wp_set_object_terms( $p, array( $t1, $t2, $t3, $t3 ), 'wptests_tax2' );

		$found = wp_get_object_terms(
			$p,
			'wptests_tax2',
			array(
				'parent' => $t1,
				'fields' => 'ids',
			)
		);

		$this->assertSame( array( $t3 ), $found );
	}

	/**
	 * @ticket 15675
	 */
	public function test_parent_0() {
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
			)
		);
		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'parent'   => $t1,
			)
		);
		$t4 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'parent'   => $t2,
			)
		);

		$p = self::factory()->post->create();

		wp_set_object_terms( $p, array( $t1, $t2, $t3, $t3 ), $this->taxonomy );

		$found = wp_get_object_terms(
			$p,
			$this->taxonomy,
			array(
				'parent' => 0,
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( $t1, $t2 ), $found );
	}

	/**
	 * @ticket 10142
	 * @ticket 57701
	 */
	public function test_termmeta_cache_should_not_be_lazy_loaded_by_default() {
		register_taxonomy( 'wptests_tax', 'post' );
		$terms = self::factory()->term->create_many( 3, array( 'taxonomy' => 'wptests_tax' ) );
		add_term_meta( $terms[0], 'foo', 'bar' );
		add_term_meta( $terms[1], 'foo', 'bar' );
		add_term_meta( $terms[2], 'foo', 'bar' );

		$p = self::factory()->post->create();
		wp_set_object_terms( $p, $terms, 'wptests_tax' );

		$found = wp_get_object_terms( $p, 'wptests_tax' );

		$num_queries = get_num_queries();

		foreach ( $terms as $t ) {
			$this->assertSame( 'bar', get_term_meta( $t, 'foo', true ) );
		}

		// Here we had extra queries as the term meta cache was not primed by default.
		$this->assertSame( 3, get_num_queries() - $num_queries );
	}

	/**
	 * @ticket 10142
	 */
	public function test_termmeta_cache_should_not_be_primed_when_update_term_meta_cache_is_false() {
		register_taxonomy( 'wptests_tax', 'post' );
		$terms = self::factory()->term->create_many( 3, array( 'taxonomy' => 'wptests_tax' ) );
		add_term_meta( $terms[0], 'foo', 'bar' );
		add_term_meta( $terms[1], 'foo', 'bar' );
		add_term_meta( $terms[2], 'foo', 'bar' );

		$p = self::factory()->post->create();
		wp_set_object_terms( $p, $terms, 'wptests_tax' );

		$found = wp_get_object_terms(
			$p,
			'wptests_tax',
			array(
				'update_term_meta_cache' => false,
			)
		);

		$num_queries = get_num_queries();

		foreach ( $terms as $t ) {
			$this->assertSame( 'bar', get_term_meta( $t, 'foo', true ) );
		}

		$this->assertSame( $num_queries + 3, get_num_queries() );
	}

	/**
	 * @ticket 36932
	 */
	public function test_termmeta_cache_should_be_primed_when_fields_is_all_with_object_id() {
		register_taxonomy( 'wptests_tax', 'post' );
		$terms = self::factory()->term->create_many( 3, array( 'taxonomy' => 'wptests_tax' ) );
		add_term_meta( $terms[0], 'foo', 'bar' );
		add_term_meta( $terms[1], 'foo', 'bar' );
		add_term_meta( $terms[2], 'foo', 'bar' );

		$p = self::factory()->post->create();
		wp_set_object_terms( $p, $terms, 'wptests_tax' );

		$found = wp_get_object_terms(
			$p,
			'wptests_tax',
			array(
				'update_term_meta_cache' => true,
				'fields'                 => 'all_with_object_id',
			)
		);

		$num_queries = get_num_queries();

		foreach ( $terms as $t ) {
			$this->assertSame( 'bar', get_term_meta( $t, 'foo', true ) );
		}

		$this->assertSame( $num_queries + 1, get_num_queries() );
	}

	/**
	 * @ticket 36932
	 */
	public function test_termmeta_cache_should_be_primed_when_fields_is_ids() {
		register_taxonomy( 'wptests_tax', 'post' );
		$terms = self::factory()->term->create_many( 3, array( 'taxonomy' => 'wptests_tax' ) );
		add_term_meta( $terms[0], 'foo', 'bar' );
		add_term_meta( $terms[1], 'foo', 'bar' );
		add_term_meta( $terms[2], 'foo', 'bar' );

		$p = self::factory()->post->create();
		wp_set_object_terms( $p, $terms, 'wptests_tax' );

		$found = wp_get_object_terms(
			$p,
			'wptests_tax',
			array(
				'update_term_meta_cache' => true,
				'fields'                 => 'ids',
			)
		);

		$num_queries = get_num_queries();

		foreach ( $terms as $t ) {
			$this->assertSame( 'bar', get_term_meta( $t, 'foo', true ) );
		}

		$this->assertSame( $num_queries + 1, get_num_queries() );
	}

	/**
	 * @ticket 10142
	 */
	public function test_meta_query() {
		register_taxonomy( 'wptests_tax', 'post' );
		$terms = self::factory()->term->create_many( 5, array( 'taxonomy' => 'wptests_tax' ) );
		add_term_meta( $terms[0], 'foo', 'bar' );
		add_term_meta( $terms[1], 'foo', 'bar' );
		add_term_meta( $terms[2], 'foo', 'baz' );
		add_term_meta( $terms[3], 'foob', 'ar' );

		$p = self::factory()->post->create();
		wp_set_object_terms( $p, $terms, 'wptests_tax' );

		$found = wp_get_object_terms(
			$p,
			'wptests_tax',
			array(
				'meta_query' => array(
					array(
						'key'   => 'foo',
						'value' => 'bar',
					),
				),
			)
		);

		$this->assertSameSets( array( $terms[0], $terms[1] ), wp_list_pluck( $found, 'term_id' ) );
	}

	/**
	 * @ticket 14162
	 */
	public function test_should_return_wp_term_objects_for_fields_all() {
		register_taxonomy( 'wptests_tax', 'post' );
		$p = self::factory()->post->create();
		$t = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax' ) );
		wp_set_object_terms( $p, $t, 'wptests_tax' );

		$found = wp_get_object_terms(
			$p,
			'wptests_tax',
			array(
				'fields' => 'all',
			)
		);

		$this->assertNotEmpty( $found );
		foreach ( $found as $f ) {
			$this->assertInstanceOf( 'WP_Term', $f );
		}
	}

	/**
	 * @ticket 14162
	 */
	public function test_should_return_wp_term_objects_for_fields_all_with_object_id() {
		register_taxonomy( 'wptests_tax', 'post' );
		$p = self::factory()->post->create();
		$t = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax' ) );
		wp_set_object_terms( $p, $t, 'wptests_tax' );

		$found = wp_get_object_terms(
			$p,
			'wptests_tax',
			array(
				'fields' => 'all_with_object_id',
			)
		);

		$this->assertNotEmpty( $found );
		foreach ( $found as $f ) {
			$this->assertInstanceOf( 'WP_Term', $f );
		}
	}

	/**
	 * @ticket 14162
	 */
	public function test_should_prime_cache_for_found_terms() {
		register_taxonomy( 'wptests_tax', 'post' );
		$p = self::factory()->post->create();
		$t = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax' ) );
		wp_set_object_terms( $p, $t, 'wptests_tax' );

		$found = wp_get_object_terms(
			$p,
			'wptests_tax',
			array(
				'fields' => 'all_with_object_id',
			)
		);

		$num_queries = get_num_queries();
		$term        = get_term( $t );
		$this->assertSame( $num_queries, get_num_queries() );
	}

	/**
	 * @ticket 14162
	 */
	public function test_object_id_should_not_be_cached_with_term_object() {
		register_taxonomy( 'wptests_tax', 'post' );
		$p = self::factory()->post->create();
		$t = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax' ) );
		wp_set_object_terms( $p, $t, 'wptests_tax' );

		$found = wp_get_object_terms(
			$p,
			'wptests_tax',
			array(
				'fields' => 'all_with_object_id',
			)
		);

		foreach ( $found as $f ) {
			$this->assertSame( $p, $f->object_id );
		}

		$term = get_term( $t );
		$this->assertNull( $term->object_id, 'object_id should not be cached along with the term object.' );
	}

	/**
	 * @ticket 14162
	 */
	public function test_term_cache_should_be_primed_for_all_taxonomies() {
		register_taxonomy( 'wptests_tax1', 'post' );
		register_taxonomy( 'wptests_tax2', 'post' );
		$p  = self::factory()->post->create();
		$t1 = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax1' ) );
		$t2 = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax2' ) );
		wp_set_object_terms( $p, $t1, 'wptests_tax1' );
		wp_set_object_terms( $p, $t2, 'wptests_tax2' );

		$found = wp_get_object_terms(
			$p,
			array(
				'wptests_tax1',
				'wptests_tax2',
			),
			array(
				'fields' => 'all_with_object_id',
			)
		);

		$this->assertSameSets( array( $t1, $t2 ), wp_list_pluck( $found, 'term_id' ) );

		$num_queries = get_num_queries();
		$term1       = get_term( $t1 );
		$term2       = get_term( $t2 );
		$this->assertSame( $num_queries, get_num_queries() );
	}

	/**
	 * @ticket 14162
	 */
	public function test_object_id_should_be_set_on_objects_that_share_terms() {
		register_taxonomy( 'wptests_tax', 'post' );
		$posts = self::factory()->post->create_many( 2 );
		$t     = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax' ) );
		wp_set_object_terms( $posts[0], $t, 'wptests_tax' );
		wp_set_object_terms( $posts[1], $t, 'wptests_tax' );

		$found = wp_get_object_terms(
			$posts,
			'wptests_tax',
			array(
				'fields' => 'all_with_object_id',
			)
		);

		$this->assertSameSets( $posts, wp_list_pluck( $found, 'object_id' ) );
	}

	public function filter_get_object_terms( $terms ) {
		$term_ids = wp_list_pluck( $terms, 'term_id' );
		// All terms should still be objects.
		return $terms;
	}

	public function test_verify_args_parameter_can_be_string() {
		$p = self::factory()->post->create();

		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'name'     => 'AAA',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'name'     => 'ZZZ',
			)
		);
		$t3 = self::factory()->term->create(
			array(
				'taxonomy' => $this->taxonomy,
				'name'     => 'JJJ',
			)
		);

		wp_set_object_terms( $p, array( $t1, $t2, $t3 ), $this->taxonomy );

		$found = wp_get_object_terms( $p, $this->taxonomy, 'orderby=name&fields=ids' );

		$this->assertSame( array( $t1, $t3, $t2 ), $found );
	}

	/**
	 * @ticket 35925
	 */
	public function test_wp_get_object_terms_args_filter() {
		$taxonomy = 'wptests_tax_4';

		register_taxonomy( $taxonomy, 'post', array( 'sort' => 'true' ) );
		$post_id = self::factory()->post->create();
		$terms   = array( 'foo', 'bar', 'baz' );
		$set     = wp_set_object_terms( $post_id, $terms, $taxonomy );

		// Filter for maintaining term order.
		add_filter( 'wp_get_object_terms_args', array( $this, 'filter_wp_get_object_terms_args' ), 10, 3 );

		// Test directly.
		$get_object_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
		$this->assertSame( $terms, $get_object_terms );

		// Test metabox taxonomy (admin advanced edit).
		$terms_to_edit = get_terms_to_edit( $post_id, $taxonomy );
		$this->assertSame( implode( ',', $terms ), $terms_to_edit );
	}

	public function filter_wp_get_object_terms_args( $args, $object_ids, $taxonomies ) {
		$args['orderby'] = 'term_order';
		return $args;
	}

	/**
	 * @ticket 41010
	 */
	public function test_duplicate_terms_should_not_be_returned_when_passed_multiple_taxonomies_registered_with_args_array() {
		$taxonomy1 = 'wptests_tax';
		$taxonomy2 = 'wptests_tax_2';

		// Any non-empty 'args' array triggers the bug.
		$taxonomy_arguments = array(
			'args' => array( 0 ),
		);

		register_taxonomy( $taxonomy1, 'post', $taxonomy_arguments );
		register_taxonomy( $taxonomy2, 'post', $taxonomy_arguments );

		$post_id   = self::factory()->post->create();
		$term_1_id = self::factory()->term->create(
			array(
				'taxonomy' => $taxonomy1,
			)
		);
		$term_2_id = self::factory()->term->create(
			array(
				'taxonomy' => $taxonomy2,
			)
		);

		wp_set_object_terms( $post_id, $term_1_id, $taxonomy1 );
		wp_set_object_terms( $post_id, $term_2_id, $taxonomy2 );

		$expected = array( $term_1_id, $term_2_id );

		$actual = wp_get_object_terms(
			$post_id,
			array( $taxonomy1, $taxonomy2 ),
			array(
				'orderby' => 'term_id',
				'fields'  => 'ids',
			)
		);

		$this->assertSameSets( $expected, $actual );
	}
}
