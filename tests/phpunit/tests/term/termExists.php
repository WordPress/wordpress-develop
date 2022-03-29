<?php

/**
 * @group taxonomy
 */
class Tests_TermExists extends WP_UnitTestCase {
	public function test_term_exists_term_0() {
		$this->assertSame( 0, term_exists( 0 ) );
	}

	public function test_term_exists_term_int_taxonomy_nonempty_term_exists() {
		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
			)
		);

		$found = term_exists( (int) $t, 'post_tag' );
		$this->assertEquals( $t, $found['term_id'] );
	}

	public function test_term_exists_term_int_taxonomy_nonempty_term_does_not_exist() {
		$this->assertNull( term_exists( 54321, 'post_tag' ) );
	}

	public function test_term_exists_term_int_taxonomy_nonempty_wrong_taxonomy() {
		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
			)
		);

		$this->assertNull( term_exists( (int) $t, 'foo' ) );
	}

	public function test_term_exists_term_int_taxonomy_empty_term_exists() {
		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
			)
		);

		$found = term_exists( (int) $t, 'post_tag' );
		$this->assertEquals( $t, $found['term_id'] );
	}

	public function test_term_exists_term_int_taxonomy_empty_term_does_not_exist() {
		$this->assertNull( term_exists( 54321 ) );
	}

	public function test_term_exists_unslash_term() {
		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'I "love" WordPress\'s taxonomy system',
			)
		);

		$found = term_exists( 'I \"love\" WordPress\\\'s taxonomy system' );
		$this->assertEquals( $t, $found );
	}

	public function test_term_exists_trim_term() {
		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => 'foo',
			)
		);

		$found = term_exists( '  foo  ' );
		$this->assertEquals( $t, $found );
	}

	public function test_term_exists_term_trimmed_to_empty_string() {
		$this->assertNull( term_exists( '   ' ) );
	}

	/**
	 * @ticket 29589
	 */
	public function test_term_exists_existing_term_that_sanitizes_to_empty() {
		wp_insert_term( '//', 'category' );
		$this->assertNotEmpty( term_exists( '//' ) );
		$this->assertNotEmpty( term_exists( '//', 'category' ) );

		wp_insert_term( '&gt;&gt;', 'category' );
		$this->assertNotEmpty( term_exists( '&gt;&gt;' ) );
		$this->assertNotEmpty( term_exists( '&gt;&gt;', 'category' ) );
	}

	public function test_term_exists_taxonomy_nonempty_parent_nonempty_match_slug() {
		register_taxonomy(
			'foo',
			'post',
			array(
				'hierarchical' => true,
			)
		);

		$parent_term = self::factory()->term->create(
			array(
				'taxonomy' => 'foo',
			)
		);

		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'foo',
				'parent'   => $parent_term,
				'slug'     => 'child-term',
			)
		);

		$found = term_exists( 'child-term', 'foo', $parent_term );

		_unregister_taxonomy( 'foo' );

		$this->assertIsArray( $found );
		$this->assertEquals( $t, $found['term_id'] );
	}

	/**
	 * @ticket 29851
	 */
	public function test_term_exists_taxonomy_nonempty_parent_0_should_return_false_for_child_term() {
		register_taxonomy(
			'foo',
			'post',
			array(
				'hierarchical' => true,
			)
		);

		$parent_term = self::factory()->term->create(
			array(
				'taxonomy' => 'foo',
			)
		);

		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'foo',
				'parent'   => $parent_term,
				'slug'     => 'child-term',
			)
		);

		$found = term_exists( 'child-term', 'foo', 0 );

		_unregister_taxonomy( 'foo' );

		$this->assertNull( $found );
	}

	public function test_term_exists_taxonomy_nonempty_parent_nonempty_match_name() {
		register_taxonomy(
			'foo',
			'post',
			array(
				'hierarchical' => true,
			)
		);

		$parent_term = self::factory()->term->create(
			array(
				'taxonomy' => 'foo',
			)
		);

		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'foo',
				'parent'   => $parent_term,
				'name'     => 'Child Term',
			)
		);

		$found = term_exists( 'Child Term', 'foo', $parent_term );

		_unregister_taxonomy( 'foo' );

		$this->assertIsArray( $found );
		$this->assertEquals( $t, $found['term_id'] );
	}

	public function test_term_exists_taxonomy_nonempty_parent_empty_match_slug() {
		register_taxonomy( 'foo', 'post', array() );

		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'foo',
				'slug'     => 'kewl-dudez',
			)
		);

		$found = term_exists( 'kewl-dudez', 'foo' );

		_unregister_taxonomy( 'foo' );

		$this->assertIsArray( $found );
		$this->assertEquals( $t, $found['term_id'] );
	}

	public function test_term_exists_taxonomy_nonempty_parent_empty_match_name() {
		register_taxonomy( 'foo', 'post', array() );

		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'foo',
				'name'     => 'Kewl Dudez',
			)
		);

		$found = term_exists( 'Kewl Dudez', 'foo' );

		_unregister_taxonomy( 'foo' );

		$this->assertIsArray( $found );
		$this->assertEquals( $t, $found['term_id'] );
	}

	public function test_term_exists_taxonomy_empty_parent_empty_match_slug() {
		register_taxonomy( 'foo', 'post', array() );

		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'foo',
				'name'     => 'juicy-fruit',
			)
		);

		$found = term_exists( 'juicy-fruit' );

		_unregister_taxonomy( 'foo' );

		$this->assertIsString( $found );
		$this->assertEquals( $t, $found );
	}

	public function test_term_exists_taxonomy_empty_parent_empty_match_name() {
		register_taxonomy( 'foo', 'post', array() );

		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'foo',
				'name'     => 'Juicy Fruit',
			)
		);

		$found = term_exists( 'Juicy Fruit' );

		_unregister_taxonomy( 'foo' );

		$this->assertIsString( $found );
		$this->assertEquals( $t, $found );
	}

	public function test_term_exists_known() {
		register_taxonomy( 'wptests_tax', 'post' );

		// Insert a term.
		$term = __FUNCTION__;
		$t    = wp_insert_term( $term, 'wptests_tax' );
		$this->assertIsArray( $t );
		$this->assertEquals( $t['term_id'], term_exists( $t['term_id'] ) );
		$this->assertEquals( $t['term_id'], term_exists( $term ) );

		// Clean up.
		$this->assertTrue( wp_delete_term( $t['term_id'], 'wptests_tax' ) );
		_unregister_taxonomy( 'wptests_tax' );
	}

	/**
	 * @ticket 36949
	 * @covers ::term_exists()
	 */
	public function test_term_lookup_by_id_and_update() {
		register_taxonomy( 'wptests_tax', 'post' );

		$slug = __FUNCTION__;
		$t    = self::factory()->term->create(
			array(
				'slug'     => $slug,
				'taxonomy' => 'wptests_tax',
			)
		);
		$this->assertEquals( $t, term_exists( $t ) );
		$this->assertTrue( wp_delete_term( $t, 'wptests_tax' ) );
		$this->assertNull( term_exists( $t ) );

		// Clean up.
		_unregister_taxonomy( 'wptests_tax' );
	}

	/**
	 * @ticket 36949
	 * @covers ::term_exists()
	 */
	public function test_term_lookup_by_slug_and_update() {
		register_taxonomy( 'wptests_tax', 'post' );

		$slug = __FUNCTION__;
		$t    = self::factory()->term->create(
			array(
				'slug'     => $slug,
				'taxonomy' => 'wptests_tax',
			)
		);
		$this->assertEquals( $t, term_exists( $slug ) );
		$this->assertTrue( wp_delete_term( $t, 'wptests_tax' ) );
		$this->assertNull( term_exists( $slug ) );

		// Clean up.
		_unregister_taxonomy( 'wptests_tax' );
	}

	/**
	 * @ticket 36949
	 * @covers ::term_exists()
	 */
	public function test_term_exists_caching() {
		global $wpdb;
		register_taxonomy( 'wptests_tax', 'post' );

		$slug = __FUNCTION__;
		$t    = self::factory()->term->create(
			array(
				'slug'     => $slug,
				'taxonomy' => 'wptests_tax',
			)
		);
		$this->assertEquals( $t, term_exists( $slug ) );
		$num_queries = $wpdb->num_queries;
		$this->assertEquals( $t, term_exists( $slug ) );
		$this->assertSame( $num_queries, $wpdb->num_queries );

		$this->assertTrue( wp_delete_term( $t, 'wptests_tax' ) );
		$num_queries = $wpdb->num_queries;
		$this->assertNull( term_exists( $slug ) );
		$this->assertSame( $num_queries + 2, $wpdb->num_queries );

		// Clean up.
		_unregister_taxonomy( 'wptests_tax' );
	}

	/**
	 * @ticket 36949
	 * @covers ::term_exists()
	 */
	public function test_term_exists_caching_suspend_cache_invalidation() {
		global $wpdb;
		register_taxonomy( 'wptests_tax', 'post' );

		wp_suspend_cache_invalidation( true );
		$slug = __FUNCTION__;
		$t    = self::factory()->term->create(
			array(
				'slug'     => $slug,
				'taxonomy' => 'wptests_tax',
			)
		);

		$this->assertEquals( $t, term_exists( $slug ) );
		$num_queries = $wpdb->num_queries;
		$this->assertEquals( $t, term_exists( $slug ) );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries );
		wp_suspend_cache_invalidation( false );

		// Clean up.
		_unregister_taxonomy( 'wptests_tax' );
	}

	/**
	 * @ticket 36949
	 * @covers ::term_exists()
	 */
	public function test_term_exists_caching_by_int_suspend_cache_invalidation() {
		register_taxonomy( 'wptests_tax', 'post' );

		$slug = __FUNCTION__;
		$t    = self::factory()->term->create(
			array(
				'slug'     => $slug,
				'taxonomy' => 'wptests_tax',
			)
		);

		// Warm cache in get_term() via term_exists().
		term_exists( $t );
		wp_suspend_cache_invalidation( true );
		wp_delete_term( $t, 'wptests_tax' );
		$this->assertNull( term_exists( $t ) );

		// Reneable cache invalidation.
		wp_suspend_cache_invalidation( false );
		_unregister_taxonomy( 'wptests_tax' );
	}

	public function test_term_exists_unknown() {
		$this->assertNull( term_exists( rand_str() ) );
		$this->assertSame( 0, term_exists( 0 ) );
		$this->assertNull( term_exists( '' ) );
		$this->assertNull( term_exists( null ) );
	}
}
