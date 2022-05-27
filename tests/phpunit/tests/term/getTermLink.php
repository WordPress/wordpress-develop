<?php

/**
 * @group taxonomy
 * @covers ::get_term_link
 */
class Tests_Term_GetTermLink extends WP_UnitTestCase {

	public static $terms;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::register_custom_taxonomy();

		$taxonomies = array( 'category', 'post_tag', 'wptests_tax' );
		foreach ( $taxonomies as $taxonomy ) {
			self::$terms[ $taxonomy ] = $factory->term->create_and_get( array( 'taxonomy' => $taxonomy ) );
		}
	}

	public function set_up() {
		parent::set_up();
		self::register_custom_taxonomy();
	}

	public function test_integer_should_be_interpreted_as_term_id() {
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'name'     => 'foo',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'slug'     => $t1,
			)
		);

		$term = (int) $t1;

		$actual = get_term_link( $term, 'wptests_tax' );
		$this->assertStringContainsString( 'wptests_tax=foo', $actual );
	}

	public function test_numeric_string_should_be_interpreted_as_term_slug() {
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'name'     => 'foo',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'slug'     => $t1,
			)
		);

		$term = (string) $t1;

		$actual = get_term_link( $term, 'wptests_tax' );
		$this->assertStringContainsString( 'wptests_tax=' . $term, $actual );
	}

	public function test_invalid_term_should_return_wp_error() {
		$actual = get_term_link( 'foo', 'wptests_tax' );
		$this->assertWPError( $actual );
	}

	public function test_category_should_use_cat_query_var_with_term_id() {
		$c = self::factory()->category->create();

		$actual = get_term_link( $c, 'category' );
		$this->assertStringContainsString( 'cat=' . $c, $actual );
	}

	public function test_taxonomy_with_query_var_should_use_that_query_var_with_term_slug() {
		register_taxonomy(
			'wptests_tax2',
			'post',
			array(
				'query_var' => 'foo',
			)
		);

		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'slug'     => 'bar',
			)
		);

		$actual = get_term_link( $t, 'wptests_tax2' );
		$this->assertStringContainsString( 'foo=bar', $actual );
	}

	public function test_taxonomy_without_query_var_should_use_taxonomy_query_var_and_term_query_var_with_term_slug() {
		register_taxonomy(
			'wptests_tax2',
			'post',
			array(
				'query_var' => false,
			)
		);

		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'slug'     => 'bar',
			)
		);

		$actual = get_term_link( $t, 'wptests_tax2' );
		$this->assertStringContainsString( 'taxonomy=wptests_tax2', $actual );
		$this->assertStringContainsString( 'term=bar', $actual );
	}

	/**
	 * @ticket 52882
	 */
	public function test_taxonomy_with_rewrite_false_and_custom_permalink_structure() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		register_taxonomy(
			'wptests_tax2',
			'post',
			array(
				'rewrite' => false,
			)
		);

		add_permastruct( 'wptests_tax2', 'foo/%wptests_tax2%' );

		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'slug'     => 'bar',
			)
		);

		$actual = get_term_link( $t, 'wptests_tax2' );

		remove_permastruct( 'wptests_tax2' );

		$this->assertStringContainsString( '/foo/bar/', $actual );
	}

	public function test_taxonomy_permastruct_with_hierarchical_rewrite_should_put_term_ancestors_in_link() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		register_taxonomy(
			'wptests_tax2',
			'post',
			array(
				'hierarchical' => true,
				'rewrite'      => array(
					'slug'         => 'foo',
					'hierarchical' => true,
				),
			)
		);

		flush_rewrite_rules();

		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'slug'     => 'term1',
			)
		);

		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'slug'     => 'term2',
				'parent'   => $t1,
			)
		);

		$actual = get_term_link( $t2, 'wptests_tax2' );

		$this->assertStringContainsString( '/foo/term1/term2/', $actual );
	}

	public function test_taxonomy_permastruct_with_nonhierarchical_rewrite_should_not_put_term_ancestors_in_link() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		register_taxonomy(
			'wptests_tax2',
			'post',
			array(
				'hierarchical' => true,
				'rewrite'      => array(
					'slug'         => 'foo',
					'hierarchical' => false,
				),
			)
		);

		flush_rewrite_rules();

		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'slug'     => 'term1',
			)
		);

		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax2',
				'slug'     => 'term2',
				'parent'   => $t1,
			)
		);

		$actual = get_term_link( $t2, 'wptests_tax2' );

		$this->assertStringContainsString( '/foo/term2/', $actual );
	}

	/**
	 * @dataProvider data_get_term_link
	 *
	 * @ticket 50225
	 *
	 * @param string $taxonomy Taxonomy being tested (used for index of term keys).
	 * @param bool   $use_id   When true, pass term ID. Else, pass term object.
	 */
	public function test_get_term_link_filter_is_object_by_term_id( $taxonomy, $use_id ) {
		$term = $this->get_term( $taxonomy, $use_id );

		add_filter(
			'term_link',
			function( $location, $term ) {
				$this->assertInstanceOf( 'WP_Term', $term );
			},
			10,
			2
		);

		get_term_link( $term, $taxonomy );
	}

	/**
	 * @dataProvider data_get_term_link
	 *
	 * @ticket 50225
	 *
	 * @param string $taxonomy Taxonomy being tested (used for index of term keys).
	 * @param bool   $use_id   When true, pass term ID. Else, pass term object.
	 */
	public function test_get_term_link_filter_is_object_by_term_object( $taxonomy, $use_id ) {
		$term = $this->get_term( $taxonomy, $use_id );

		add_filter(
			'term_link',
			function( $location, $term ) {
				$this->assertInstanceOf( 'WP_Term', $term );
			},
			10,
			2
		);

		get_term_link( get_term( $term, $taxonomy ), $taxonomy );
	}

	/**
	 * @dataProvider data_get_term_link
	 *
	 * @ticket 50225
	 *
	 * @param string $taxonomy Taxonomy being tested (used for index of term keys).
	 * @param bool   $use_id   When true, pass term ID. Else, skip the test.
	 */
	public function test_get_term_feed_link_backward_compatibility( $taxonomy, $use_id ) {
		if ( $use_id ) {
			$term = $this->get_term( $taxonomy, $use_id );

			$term_feed_link = get_term_feed_link( $term, $taxonomy );
			$this->assertIsString( $term_feed_link );

			$term_feed_link = get_term_feed_link( $term, '' );
			$this->assertIsString( $term_feed_link );
		} else {
			$this->markTestSkipped( 'This test requires to pass an ID to get_term_feed_link()' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_term_link() {
		return array(
			'category passing term_id'          => array(
				'taxonomy' => 'category',
				'use_id'   => false,
			),
			'category passing term object'      => array(
				'taxonomy' => 'category',
				'use_id'   => true,
			),
			'post_tag passing term_id'          => array(
				'taxonomy' => 'post_tag',
				'use_id'   => false,
			),
			'post_tag passing term object'      => array(
				'taxonomy' => 'post_tag',
				'use_id'   => true,
			),
			'a custom taxonomy passing term_id' => array(
				'taxonomy' => 'wptests_tax',
				'use_id'   => false,
			),
			'a custom taxonomy passing term_id' => array(
				'taxonomy' => 'wptests_tax',
				'use_id'   => true,
				'expected' => 'term.php?taxonomy=custom_taxonomy&tag_ID=%ID%&post_type=post',
			),
		);
	}

	/**
	 * Helper to register a custom taxonomy for use in tests.
	 *
	 * @since 5.9.0
	 */
	private static function register_custom_taxonomy() {
		register_taxonomy( 'wptests_tax', 'post' );
	}

	/**
	 * Helper to get the term for the given taxonomy.
	 *
	 * @since 5.9.0
	 *
	 * @param string $taxonomy Taxonomy being tested (used for index of term keys).
	 * @param bool   $use_id   When true, pass term ID. Else, pass term object.
	 * @return WP_Term|int If $use_id is true, term ID is returned; else instance of WP_Term.
	 */
	private function get_term( $taxonomy, $use_id ) {
		$term = self::$terms[ $taxonomy ];
		if ( $use_id ) {
			$term = $term->term_id;
		}

		return $term;
	}
}
