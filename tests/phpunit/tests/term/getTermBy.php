<?php

/**
 * @group taxonomy
 */
class Tests_Term_GetTermBy extends WP_UnitTestCase {

	protected $query = '';

	public function test_get_term_by_slug() {
		$term1 = wp_insert_term( 'Foo', 'category', array( 'slug' => 'foo' ) );
		$term2 = get_term_by( 'slug', 'foo', 'category' );
		$this->assertEquals( get_term( $term1['term_id'], 'category' ), $term2 );
	}

	public function test_get_term_by_name() {
		$term1 = wp_insert_term( 'Foo', 'category', array( 'slug' => 'foo' ) );
		$term2 = get_term_by( 'name', 'Foo', 'category' );
		$this->assertEquals( get_term( $term1['term_id'], 'category' ), $term2 );
	}

	public function test_get_term_by_id() {
		$term1 = wp_insert_term( 'Foo', 'category', array( 'slug' => 'foo' ) );
		$term2 = get_term_by( 'id', $term1['term_id'], 'category' );
		$this->assertEquals( get_term( $term1['term_id'], 'category' ), $term2 );
	}

	/**
	 * 'term_id' is an alias of 'id'.
	 */
	public function test_get_term_by_term_id() {
		$term1 = wp_insert_term( 'Foo', 'category', array( 'slug' => 'foo' ) );
		$term2 = get_term_by( 'term_id', $term1['term_id'], 'category' );
		$this->assertEquals( get_term( $term1['term_id'], 'category' ), $term2 );
	}

	/**
	 * @ticket 45163
	 */
	public function test_get_term_by_uppercase_id() {
		$term1 = wp_insert_term( 'Foo', 'category', array( 'slug' => 'foo' ) );
		$term2 = get_term_by( 'ID', $term1['term_id'], 'category' );
		$this->assertEquals( get_term( $term1['term_id'], 'category' ), $term2 );
	}

	/**
	 * @ticket 21651
	 */
	public function test_get_term_by_tt_id() {
		$term1 = wp_insert_term( 'Foo', 'category' );
		$term2 = get_term_by( 'term_taxonomy_id', $term1['term_taxonomy_id'], 'category' );
		$this->assertEquals( get_term( $term1['term_id'], 'category' ), $term2 );
	}

	public function test_get_term_by_unknown() {
		wp_insert_term( 'Foo', 'category', array( 'slug' => 'foo' ) );
		$term2 = get_term_by( 'unknown', 'foo', 'category' );
		$this->assertFalse( $term2 );
	}

	/**
	 * @ticket 33281
	 */
	public function test_get_term_by_with_nonexistent_id_should_return_false() {
		$term = get_term_by( 'id', 123456, 'category' );
		$this->assertFalse( $term );
	}

	/**
	 * @ticket 16282
	 */
	public function test_get_term_by_slug_should_match_nonaccented_equivalents() {
		register_taxonomy( 'wptests_tax', 'post' );

		$slug = 'ńaș';
		$t    = self::factory()->term->create(
			array(
				'slug'     => $slug,
				'taxonomy' => 'wptests_tax',
			)
		);

		$found = get_term_by( 'slug', 'nas', 'wptests_tax' );
		$this->assertSame( $t, $found->term_id );
	}

	/**
	 * @ticket 30620
	 */
	public function test_taxonomy_should_be_ignored_if_matching_by_term_taxonomy_id() {
		global $wpdb;

		register_taxonomy( 'wptests_tax', 'post' );
		$t    = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax' ) );
		$term = get_term( $t, 'wptests_tax' );

		$new_ttid = $term->term_taxonomy_id + 1;

		// Offset just to be sure.
		$wpdb->update(
			$wpdb->term_taxonomy,
			array( 'term_taxonomy_id' => $new_ttid ),
			array( 'term_id' => $t )
		);

		$found = get_term_by( 'term_taxonomy_id', $new_ttid, 'foo' );
		$this->assertSame( $t, $found->term_id );
	}

	/**
	 * @ticket 14162
	 */
	public function test_should_prime_term_cache() {
		global $wpdb;

		register_taxonomy( 'wptests_tax', 'post' );
		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'slug'     => 'foo',
			)
		);

		clean_term_cache( $t, 'wptests_tax' );

		$num_queries = $wpdb->num_queries;
		$found       = get_term_by( 'slug', 'foo', 'wptests_tax' );
		$num_queries = $num_queries + 2;

		$this->assertInstanceOf( 'WP_Term', $found );
		$this->assertSame( $t, $found->term_id );
		$this->assertSame( $num_queries, $wpdb->num_queries );

		// Calls to `get_term()` should now hit cache.
		$found2 = get_term( $t );
		$this->assertSame( $t, $found->term_id );
		$this->assertSame( $num_queries, $wpdb->num_queries );
	}

	/**
	 * @ticket 21760
	 */
	public function test_should_unslash_name() {
		register_taxonomy( 'wptests_tax', 'post' );
		$term_name         = 'Foo " \o/';
		$term_name_slashed = wp_slash( $term_name );
		$t                 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'name'     => $term_name_slashed,
			)
		);

		$found = get_term_by( 'name', $term_name_slashed, 'wptests_tax' );

		$this->assertInstanceOf( 'WP_Term', $found );
		$this->assertSame( $t, $found->term_id );
		$this->assertSame( $term_name, $found->name );
	}

	/**
	 * @ticket 21760
	 */
	public function test_should_sanitize_slug() {
		register_taxonomy( 'wptests_tax', 'post' );
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'slug'     => 'foo-foo',
			)
		);

		// Whitespace should get replaced by a '-'.
		$found1 = get_term_by( 'slug', 'foo foo', 'wptests_tax' );

		$this->assertInstanceOf( 'WP_Term', $found1 );
		$this->assertSame( $t1, $found1->term_id );

		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'slug'     => '%e4%bb%aa%e8%a1%a8%e7%9b%98',
			)
		);

		// Slug should get urlencoded.
		$found2 = get_term_by( 'slug', '仪表盘', 'wptests_tax' );

		$this->assertInstanceOf( 'WP_Term', $found2 );
		$this->assertSame( $t2, $found2->term_id );
	}

	/**
	 * @ticket 21760
	 */
	public function test_query_should_not_contain_order_by_clause() {
		global $wpdb;

		$term_id = $this->factory->term->create(
			array(
				'name'     => 'burrito',
				'taxonomy' => 'post_tag',
			)
		);
		$found   = get_term_by( 'name', 'burrito', 'post_tag' );
		$this->assertSame( $term_id, $found->term_id );
		$this->assertStringNotContainsString( 'ORDER BY', $wpdb->last_query );
	}

	/**
	 * @ticket 21760
	 */
	public function test_query_should_contain_limit_clause() {
		$term_id = $this->factory->term->create(
			array(
				'name'     => 'burrito',
				'taxonomy' => 'post_tag',
			)
		);
		add_filter( 'terms_pre_query', array( $this, 'get_query_from_filter' ), 10, 2 );
		$found = get_term_by( 'name', 'burrito', 'post_tag' );
		$this->assertSame( $term_id, $found->term_id );
		$this->assertStringContainsString( 'LIMIT 1', $this->query );
	}

	/**
	 * @ticket 21760
	 */
	public function test_prevent_recursion_by_get_terms_filter() {
		$action = new MockAction();

		add_filter( 'get_terms', array( $action, 'filter' ) );
		get_term_by( 'name', 'burrito', 'post_tag' );
		remove_filter( 'get_terms', array( $action, 'filter' ) );

		$this->assertSame( 0, $action->get_call_count() );
	}

	/**
	 * @ticket 21760
	 */
	public function test_get_term_by_name_with_string_0() {
		register_taxonomy( 'wptests_tax', 'post', array( 'hierarchical' => true ) );

		$term_id = $this->factory->term->create(
			array(
				'name'     => '0',
				'taxonomy' => 'wptests_tax',
			)
		);

		$found = get_term_by( 'name', '0', 'wptests_tax' );
		$this->assertSame( $term_id, $found->term_id );
	}

	/**
	 * @ticket 21760
	 */
	public function test_get_term_by_slug_with_string_0() {
		register_taxonomy( 'wptests_tax', 'post', array( 'hierarchical' => true ) );

		$term_id = $this->factory->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'name'     => '0',
				'slug'     => '0',
			)
		);

		$found = get_term_by( 'slug', '0', 'wptests_tax' );
		$this->assertSame( $term_id, $found->term_id );
	}

	/**
	 * @ticket 21760
	 */
	public function test_get_term_by_with_empty_string() {
		register_taxonomy( 'wptests_tax', 'post', array( 'hierarchical' => true ) );

		$found_by_slug = get_term_by( 'slug', '', 'wptests_tax' );
		$found_by_name = get_term_by( 'name', '', 'wptests_tax' );

		$this->assertFalse( $found_by_slug );
		$this->assertFalse( $found_by_name );
	}

	public function get_query_from_filter( $terms, $wp_term_query ) {
		$this->query = $wp_term_query->request;

		return $terms;
	}
}
