<?php

/**
 * @group sitemaps
 */
class Tests_Sitemaps_wpSitemapsTaxonomies extends WP_UnitTestCase {

	/**
	 * List of post_tag IDs.
	 *
	 * @var array
	 */
	public static $post_tags;

	/**
	 * List of category IDs.
	 *
	 * @var array
	 */
	public static $cats;

	/**
	 * Editor ID for use in some tests.
	 *
	 * @var int
	 */
	public static $editor_id;

	/**
	 * Set up fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory A WP_UnitTest_Factory object.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$cats      = $factory->term->create_many( 10, array( 'taxonomy' => 'category' ) );
		self::$post_tags = $factory->term->create_many( 10 );
		self::$editor_id = $factory->user->create( array( 'role' => 'editor' ) );
	}

	public function tear_down() {
		unregister_taxonomy( 'test_taxonomy' );
		unregister_taxonomy( 'private_taxonomy' );
		unregister_taxonomy( 'non_queryable_tax' );
		unregister_taxonomy( 'public_taxonomy' );
		unregister_taxonomy( 'non_queryable_taxonomy' );

		parent::tear_down();
	}

	/**
	 * Test getting a URL list for default taxonomies via
	 * WP_Sitemaps_Taxonomies::get_url_list().
	 */
	public function test_get_url_list_taxonomies() {
		// Add the default category to the list of categories we're testing.
		$categories = array_merge( array( 1 ), self::$cats );

		// Create a test post so the test terms are assigned and counted.
		self::factory()->post->create(
			array(
				'tags_input'    => self::$post_tags,
				'post_category' => $categories,
			)
		);

		$tax_provider = new WP_Sitemaps_Taxonomies();

		$cat_list = $tax_provider->get_url_list( 1, 'category' );

		$expected_cats = array_map(
			static function ( $id ) {
				return array(
					'loc' => get_term_link( $id, 'category' ),
				);
			},
			$categories
		);

		$this->assertSame( $expected_cats, $cat_list, 'Category URL list does not match.' );

		$tag_list = $tax_provider->get_url_list( 1, 'post_tag' );

		$expected_tags = array_map(
			static function ( $id ) {
				return array(
					'loc' => get_term_link( $id, 'post_tag' ),
				);
			},
			self::$post_tags
		);

		$this->assertSame( $expected_tags, $tag_list, 'Post Tags URL list does not match.' );
	}

	/**
	 * Test getting a URL list for a custom taxonomy via
	 * WP_Sitemaps_Taxonomies::get_url_list().
	 */
	public function test_get_url_list_custom_taxonomy() {
		wp_set_current_user( self::$editor_id );

		// Create a custom taxonomy for this test.
		$taxonomy = 'test_taxonomy';
		register_taxonomy( $taxonomy, 'post' );

		// Create test terms in the custom taxonomy.
		$terms = self::factory()->term->create_many( 10, array( 'taxonomy' => $taxonomy ) );

		// Create a test post applied to all test terms.
		self::factory()->post->create( array( 'tax_input' => array( $taxonomy => $terms ) ) );

		$expected = array_map(
			static function ( $id ) use ( $taxonomy ) {
				return array(
					'loc' => get_term_link( $id, $taxonomy ),
				);
			},
			$terms
		);

		$tax_provider = new WP_Sitemaps_Taxonomies();

		$post_list = $tax_provider->get_url_list( 1, $taxonomy );

		// Clean up.
		unregister_taxonomy_for_object_type( $taxonomy, 'post' );

		$this->assertSame( $expected, $post_list, 'Custom taxonomy term links are not visible.' );
	}

	/**
	 * Test getting a URL list for a private custom taxonomy via
	 * WP_Sitemaps_Taxonomies::get_url_list().
	 */
	public function test_get_url_list_custom_taxonomy_private() {
		// Create a custom taxonomy for this test.
		$taxonomy = 'private_taxonomy';
		register_taxonomy( $taxonomy, 'post', array( 'public' => false ) );

		// Create test terms in the custom taxonomy.
		$terms = self::factory()->term->create_many( 10, array( 'taxonomy' => $taxonomy ) );

		// Create a test post applied to all test terms.
		$post_id = self::factory()->post->create();
		wp_add_object_terms( $post_id, $terms, $taxonomy );

		$tax_provider = new WP_Sitemaps_Taxonomies();

		$post_list = $tax_provider->get_url_list( 1, $taxonomy );

		// Clean up.
		unregister_taxonomy_for_object_type( $taxonomy, 'post' );

		$this->assertEmpty( $post_list, 'Private taxonomy term links are visible.' );
	}

	/**
	 * Test getting a sitemap for a custom taxonomy that is not publicly queryable.
	 *
	 * @ticket 50607
	 * @ticket 61293
	 */
	public function test_get_url_list_custom_taxonomy_not_publicly_queryable() {
		// Create a custom taxonomy for this test.
		$taxonomy = 'non_queryable_tax';
		register_taxonomy( $taxonomy, 'post', array( 'publicly_queryable' => false ) );

		// Create test terms in the custom taxonomy.
		$terms = self::factory()->term->create_many( 10, array( 'taxonomy' => $taxonomy ) );

		// Create a test post applied to all test terms.
		$post_id = self::factory()->post->create();
		wp_add_object_terms( $post_id, $terms, 'non_queryable_tax' );

		$sitemaps     = wp_sitemaps_get_server();
		$tax_provider = new WP_Sitemaps_Taxonomies();

		$post_list = $tax_provider->get_url_list( 1, $taxonomy );

		$this->go_to( home_url( "/?sitemap=taxonomies&sitemap-subtype={$taxonomy}" ) );
		// Wrap in get_echo to prevent unexpected test suite output.
		get_echo( array( $sitemaps, 'render_sitemaps' ) );
		$this->assertTrue( is_404(), 'Private taxonomy sitemaps should 404' );

		// Clean up.
		unregister_taxonomy_for_object_type( $taxonomy, 'post' );

		$this->assertEmpty( $post_list, 'Non-publicly queryable taxonomy term links are visible.' );
	}

	/**
	 * Test sitemap index entries with public and private taxonomies.
	 *
	 * @ticket #50117
	 * @ticket #50607
	 */
	public function test_get_sitemap_entries_custom_taxonomies() {
		wp_set_current_user( self::$editor_id );

		// Create a custom public and private taxonomies for this test.
		register_taxonomy( 'public_taxonomy', 'post' );
		register_taxonomy( 'non_queryable_taxonomy', 'post', array( 'publicly_queryable' => false ) );
		register_taxonomy( 'private_taxonomy', 'post', array( 'public' => false ) );

		// Create test terms in the custom taxonomy.
		$public_term        = self::factory()->term->create( array( 'taxonomy' => 'public_taxonomy' ) );
		$non_queryable_term = self::factory()->term->create( array( 'taxonomy' => 'non_queryable_taxonomy' ) );
		$private_term       = self::factory()->term->create( array( 'taxonomy' => 'private_taxonomy' ) );

		// Create a test post applied to all test terms.
		self::factory()->post->create_and_get(
			array(
				'tax_input' => array(
					'public_taxonomy'        => array( $public_term ),
					'non_queryable_taxonomy' => array( $non_queryable_term ),
					'private_taxonomy'       => array( $private_term ),
				),
			)
		);

		$tax_provider = new WP_Sitemaps_Taxonomies();
		$entries      = wp_list_pluck( $tax_provider->get_sitemap_entries(), 'loc' );

		// Clean up.
		unregister_taxonomy_for_object_type( 'public_taxonomy', 'post' );
		unregister_taxonomy_for_object_type( 'non_queryable_taxonomy', 'post' );
		unregister_taxonomy_for_object_type( 'private_taxonomy', 'post' );

		$this->assertContains( 'http://' . WP_TESTS_DOMAIN . '/?sitemap=taxonomies&sitemap-subtype=public_taxonomy&paged=1', $entries, 'Public Taxonomies are not in the index.' );
		$this->assertNotContains( 'http://' . WP_TESTS_DOMAIN . '/?sitemap=taxonomies&sitemap-subtype=non_queryable_taxonomy&paged=1', $entries, 'Private Taxonomies are visible in the index.' );
		$this->assertNotContains( 'http://' . WP_TESTS_DOMAIN . '/?sitemap=taxonomies&sitemap-subtype=private_taxonomy&paged=1', $entries, 'Private Taxonomies are visible in the index.' );
	}

	/**
	 * Test ability to filter object subtypes.
	 */
	public function test_filter_sitemaps_taxonomies() {
		$taxonomies_provider = new WP_Sitemaps_Taxonomies();

		// Return an empty array to show that the list of subtypes is filterable.
		add_filter( 'wp_sitemaps_taxonomies', '__return_empty_array' );
		$subtypes = $taxonomies_provider->get_object_subtypes();

		$this->assertSame( array(), $subtypes, 'Could not filter taxonomies subtypes.' );
	}

	/**
	 * Ensure unregistered taxonomy sub-type throws a 404.
	 *
	 * @ticket 61293
	 */
	public function test_unregistered_taxonomy_sitemap_404s() {
		$taxonomy = 'unregistered_test_taxonomy';
		$sitemaps = wp_sitemaps_get_server();

		$this->go_to( home_url( "/?sitemap=taxonomies&sitemap-subtype={$taxonomy}" ) );
		// Wrap in get_echo to prevent unexpected test suite output.
		get_echo( array( $sitemaps, 'render_sitemaps' ) );
		$this->assertTrue( is_404(), 'Unregistered taxonomy sitemaps should 404' );
	}

	/**
	 * Ensure in each page for a registered sub-type returns the correct response.
	 *
	 * Tests the two pages that are in range return a 200 and that third, out of
	 * range, page returns a 404.
	 *
	 * @dataProvider data_sitemap_does_not_404_for_valid_sitemap_subtypes
	 *
	 * @param string $taxonomy The taxonomy to test.
	 */
	public function test_sitemap_does_not_404_for_valid_sitemap_subtypes( string $taxonomy ) {
		register_taxonomy( 'valid_custom_taxonomy', 'post', array( 'public' => true ) );

		$post_id = self::factory()->post->create();
		$terms   = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 5,
			)
		);

		$this->assertIsArray( $terms );
		$count = count( $terms );
		if ( $count < 5 ) {
			$terms = array_merge( $terms, self::factory()->term->create_many( 5 - $count, array( 'taxonomy' => $taxonomy ) ) );
		}
		wp_add_object_terms( $post_id, $terms, $taxonomy );

		add_filter(
			'wp_sitemaps_max_urls',
			static function () {
				return 3;
			}
		);

		$this->go_to( home_url( "?sitemap=taxonomies&sitemap-subtype={$taxonomy}" ) );
		$sitemaps = wp_sitemaps_get_server();
		// Wrap in get_echo to prevent unexpected test suite output.
		get_echo( array( $sitemaps, 'render_sitemaps' ) );
		$this->assertFalse( is_404(), 'Sitemap should not 404 when page is unspecified' );

		foreach ( array( 1, 2 ) as $page ) {
			$this->go_to( home_url( "?sitemap=taxonomies&sitemap-subtype={$taxonomy}&paged={$page}" ) );
			$sitemaps = wp_sitemaps_get_server();
			// Wrap in get_echo to prevent unexpected test suite output.
			get_echo( array( $sitemaps, 'render_sitemaps' ) );
			$this->assertFalse( is_404(), "Sitemap should not 404 when page is set to {$page}" );
		}

		$page = 3;
		$this->go_to( home_url( "?sitemap=taxonomies&sitemap-subtype={$taxonomy}&paged={$page}" ) );
		$sitemaps = wp_sitemaps_get_server();
		// Wrap in get_echo to prevent unexpected test suite output.
		get_echo( array( $sitemaps, 'render_sitemaps' ) );
		$this->assertTrue( is_404(), "Sitemap should 404 when page is set to {$page} (out of range)" );
	}

	/**
	 * Data provider for {@see self::test_sitemap_does_not_404_for_valid_sitemap_subtypes()}.
	 *
	 * @return array<non-falsy-string, array{ lowercase-string&truthy-string }>
	 */
	public static function data_sitemap_does_not_404_for_valid_sitemap_subtypes(): array {
		return array(
			'category'              => array( 'category' ),
			'valid custom taxonomy' => array( 'valid_custom_taxonomy' ),
			'tag'                   => array( 'post_tag' ),
		);
	}
}
