<?php
/**
 * Sitemaps: Tests_Sitemaps_Sitemaps class
 *
 * Main test class.
 *
 * @package   Sitemaps
 * @copyright 2019 The Core Sitemaps Contributors
 * @license   GNU General Public License, version 2
 * @link      https://github.com/GoogleChromeLabs/wp-sitemaps
 */

/**
 * Core sitemaps test cases.
 *
 * @group sitemaps
 */
class Tests_Sitemaps_Sitemaps extends WP_UnitTestCase {

	/**
	 * List of user IDs.
	 *
	 * @var array
	 */
	public static $users;

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
	 * List of post type post IDs.
	 *
	 * @var array
	 */
	public static $posts;

	/**
	 * List of post type page IDs.
	 *
	 * @var array
	 */
	public static $pages;

	/**
	 * Editor ID for use in some tests.
	 *
	 * @var int
	 */
	public static $editor_id;

	/**
	 * Test sitemap provider.
	 *
	 * @var WP_Sitemaps_Test_Provider
	 */
	public static $test_provider;

	/**
	 * Set up fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory A WP_UnitTest_Factory object.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$users     = $factory->user->create_many( 10 );
		self::$post_tags = $factory->term->create_many( 10 );
		self::$cats      = $factory->term->create_many( 10, array( 'taxonomy' => 'category' ) );
		self::$pages     = $factory->post->create_many( 10, array( 'post_type' => 'page' ) );

		// Create a set of posts pre-assigned to tags and authors.
		self::$posts = $factory->post->create_many(
			10,
			array(
				'tags_input'  => self::$post_tags,
				'post_author' => reset( self::$users ),
			)
		);

		// Create a user with an editor role to complete some tests.
		self::$editor_id = $factory->user->create( array( 'role' => 'editor' ) );

		self::$test_provider = new WP_Sitemaps_Test_Provider();
	}

	/**
	 * Helper function to get all sitemap entries data.
	 *
	 * @return array A list of sitemap entries.
	 */
	public function _get_sitemap_entries() {
		$entries = array();

		$providers = wp_get_sitemap_providers();

		foreach ( $providers as $provider ) {
			// Using `array_push` is more efficient than `array_merge` in the loop.
			array_push( $entries, ...$provider->get_sitemap_entries() );
		}

		return $entries;
	}

	/**
	 * Test default sitemap entries.
	 */
	public function test_get_sitemap_entries() {
		$entries = $this->_get_sitemap_entries();

		$expected = array(
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/?sitemap=posts&sitemap-subtype=post&paged=1',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/?sitemap=posts&sitemap-subtype=page&paged=1',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/?sitemap=taxonomies&sitemap-subtype=category&paged=1',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/?sitemap=taxonomies&sitemap-subtype=post_tag&paged=1',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/?sitemap=users&paged=1',
			),
		);

		$this->assertSame( $expected, $entries );
	}

	/**
	 * Test default sitemap entries with permalinks on.
	 */
	public function test_get_sitemap_entries_post_with_permalinks() {
		$this->set_permalink_structure( '/%year%/%postname%/' );

		$entries = $this->_get_sitemap_entries();

		$expected = array(
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-post-1.xml',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-page-1.xml',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-taxonomies-category-1.xml',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-taxonomies-post_tag-1.xml',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-users-1.xml',
			),
		);

		// Clean up permalinks.
		$this->set_permalink_structure();

		$this->assertSame( $expected, $entries );
	}

	/**
	 * Test sitemap index entries with public and private custom post types.
	 */
	public function test_get_sitemap_entries_custom_post_types() {
		// Register and create a public post type post.
		register_post_type( 'public_cpt', array( 'public' => true ) );
		self::factory()->post->create( array( 'post_type' => 'public_cpt' ) );

		// Register and create a private post type post.
		register_post_type( 'private_cpt', array( 'public' => false ) );
		self::factory()->post->create( array( 'post_type' => 'private_cpt' ) );

		$entries = wp_list_pluck( $this->_get_sitemap_entries(), 'loc' );

		// Clean up.
		unregister_post_type( 'public_cpt' );
		unregister_post_type( 'private_cpt' );

		$this->assertContains( 'http://' . WP_TESTS_DOMAIN . '/?sitemap=posts&sitemap-subtype=public_cpt&paged=1', $entries, 'Public CPTs are not in the index.' );
		$this->assertNotContains( 'http://' . WP_TESTS_DOMAIN . '/?sitemap=posts&sitemap-subtype=private_cpt&paged=1', $entries, 'Private CPTs are visible in the index.' );
	}

	/**
	 * Test sitemap index entries with public and private custom post types.
	 *
	 * @ticket 50607
	 */
	public function test_get_sitemap_entries_not_publicly_queryable_post_types() {
		register_post_type(
			'non_queryable_cpt',
			array(
				'public'             => true,
				'publicly_queryable' => false,
			)
		);
		self::factory()->post->create( array( 'post_type' => 'non_queryable_cpt' ) );

		$entries = wp_list_pluck( $this->_get_sitemap_entries(), 'loc' );

		// Clean up.
		unregister_post_type( 'non_queryable_cpt' );

		$this->assertNotContains( 'http://' . WP_TESTS_DOMAIN . '/?sitemap=posts&sitemap-subtype=non_queryable_cpt&paged=1', $entries, 'Non-publicly queryable CPTs are visible in the index.' );
	}

	/**
	 * Tests getting a URL list for post type post.
	 */
	public function test_get_url_list_post() {
		$providers = wp_get_sitemap_providers();

		$post_list = $providers['posts']->get_url_list( 1, 'post' );

		$expected = $this->_get_expected_url_list( 'post', self::$posts );

		$this->assertSame( $expected, $post_list );
	}

	/**
	 * Tests getting a URL list for post type page.
	 */
	public function test_get_url_list_page() {
		// Short circuit the show on front option.
		add_filter( 'pre_option_show_on_front', '__return_true' );

		$providers = wp_get_sitemap_providers();

		$post_list = $providers['posts']->get_url_list( 1, 'page' );

		$expected = $this->_get_expected_url_list( 'page', self::$pages );

		$this->assertSame( $expected, $post_list );
	}

	/**
	 * Tests getting a URL list for post type page with included home page.
	 */
	public function test_get_url_list_page_with_home() {
		$providers = wp_get_sitemap_providers();

		$post_list = $providers['posts']->get_url_list( 1, 'page' );

		$post_list_sorted = wp_list_sort( $post_list, 'lastmod', 'DESC' );

		$expected = $this->_get_expected_url_list( 'page', self::$pages );

		// Add the homepage to the front of the URL list.
		array_unshift(
			$expected,
			array(
				'loc'     => home_url( '/' ),
				'lastmod' => $post_list_sorted[0]['lastmod'],
			)
		);

		$this->assertSame( $expected, $post_list );
	}

	/**
	 * Tests getting a URL list for post with private post.
	 */
	public function test_get_url_list_private_post() {
		wp_set_current_user( self::$editor_id );

		$providers = wp_get_sitemap_providers();

		$post_list_before = $providers['posts']->get_url_list( 1, 'post' );

		$private_post_id = self::factory()->post->create( array( 'post_status' => 'private' ) );

		$post_list_after = $providers['posts']->get_url_list( 1, 'post' );

		$private_post = array(
			'loc' => get_permalink( $private_post_id ),
		);

		$this->assertNotContains( $private_post, $post_list_after );
		$this->assertSameSets( $post_list_before, $post_list_after );
	}

	/**
	 * Tests getting a URL list for a custom post type.
	 */
	public function test_get_url_list_cpt() {
		$post_type = 'custom_type';

		// Registered post types are private unless explicitly set to public.
		register_post_type( $post_type, array( 'public' => true ) );

		$ids = self::factory()->post->create_many( 10, array( 'post_type' => $post_type ) );

		$providers = wp_get_sitemap_providers();

		$post_list = $providers['posts']->get_url_list( 1, $post_type );

		$expected = $this->_get_expected_url_list( $post_type, $ids );

		// Clean up.
		unregister_post_type( $post_type );

		$this->assertSame( $expected, $post_list, 'Custom post type posts are not visible.' );
	}

	/**
	 * Tests getting a URL list for a private custom post type.
	 */
	public function test_get_url_list_cpt_private() {
		$post_type = 'private_type';

		// Create a private post type for testing against data leaking.
		register_post_type( $post_type, array( 'public' => false ) );

		self::factory()->post->create_many( 10, array( 'post_type' => $post_type ) );

		$providers = wp_get_sitemap_providers();

		$post_list = $providers['posts']->get_url_list( 1, $post_type );

		// Clean up.
		unregister_post_type( $post_type );

		$this->assertEmpty( $post_list, 'Private post types may be returned by the post provider.' );
	}

	/**
	 * Tests getting a URL list for a private custom post type.
	 *
	 * @ticket 50607
	 */
	public function test_get_url_list_cpt_not_publicly_queryable() {
		$post_type = 'non_queryable_cpt';

		register_post_type(
			$post_type,
			array(
				'public'             => true,
				'publicly_queryable' => false,
			)
		);

		self::factory()->post->create_many( 10, array( 'post_type' => $post_type ) );

		$providers = wp_get_sitemap_providers();

		$post_list = $providers['posts']->get_url_list( 1, $post_type );

		// Clean up.
		unregister_post_type( $post_type );

		$this->assertEmpty( $post_list, 'Non-publicly queryable post types may be returned by the post provider.' );
	}

	/**
	 * Helper function for building an expected url list.
	 *
	 * @param string $type An object sub type, e.g., post type.
	 * @param array  $ids  Array of object IDs.
	 * @return array A formed URL list.
	 */
	public function _get_expected_url_list( $type, $ids ) {
		$posts = get_posts(
			array(
				'include'   => $ids,
				'orderby'   => 'ID',
				'order'     => 'ASC',
				'post_type' => $type,
			)
		);

		return array_map(
			static function ( $post ) {
				return array(
					'loc'     => get_permalink( $post ),
					'lastmod' => get_post_modified_time( DATE_W3C, true, $post ),
				);
			},
			$posts
		);
	}

	/**
	 * Test functionality that adds a new sitemap provider to the registry.
	 */
	public function test_register_sitemap_provider() {
		wp_register_sitemap_provider( 'test_sitemap', self::$test_provider );

		$sitemaps = wp_get_sitemap_providers();

		$this->assertSame( $sitemaps['test_sitemap'], self::$test_provider, 'Can not confirm sitemap registration is working.' );
	}

	/**
	 * Test robots.txt output.
	 */
	public function test_robots_text() {
		// Get the text added to the default robots text output.
		$robots_text    = apply_filters( 'robots_txt', '', true );
		$sitemap_string = 'Sitemap: http://' . WP_TESTS_DOMAIN . '/?sitemap=index';

		$this->assertStringContainsString( $sitemap_string, $robots_text, 'Sitemap URL not included in robots text.' );
	}

	/**
	 * Test robots.txt output for a private site.
	 */
	public function test_robots_text_private_site() {
		$robots_text    = apply_filters( 'robots_txt', '', false );
		$sitemap_string = 'Sitemap: http://' . WP_TESTS_DOMAIN . '/?sitemap=index';

		$this->assertStringNotContainsString( $sitemap_string, $robots_text );
	}

	/**
	 * Test robots.txt output with permalinks set.
	 */
	public function test_robots_text_with_permalinks() {
		// Set permalinks for testing.
		$this->set_permalink_structure( '/%year%/%postname%/' );

		// Get the text added to the default robots text output.
		$robots_text    = apply_filters( 'robots_txt', '', true );
		$sitemap_string = 'Sitemap: http://' . WP_TESTS_DOMAIN . '/wp-sitemap.xml';

		// Clean up permalinks.
		$this->set_permalink_structure();

		$this->assertStringContainsString( $sitemap_string, $robots_text, 'Sitemap URL not included in robots text.' );
	}

	/**
	 * Test robots.txt output with line feed prefix.
	 */
	public function test_robots_text_prefixed_with_line_feed() {
		// Get the text added to the default robots text output.
		$robots_text    = apply_filters( 'robots_txt', '', true );
		$sitemap_string = "\nSitemap: ";

		$this->assertStringContainsString( $sitemap_string, $robots_text, 'Sitemap URL not prefixed with "\n".' );
	}

	/**
	 * @ticket 50643
	 */
	public function test_sitemaps_enabled() {
		$before = wp_sitemaps_get_server()->sitemaps_enabled();
		add_filter( 'wp_sitemaps_enabled', '__return_false' );
		$after = wp_sitemaps_get_server()->sitemaps_enabled();
		remove_filter( 'wp_sitemaps_enabled', '__return_false' );

		$this->assertTrue( $before );
		$this->assertFalse( $after );
	}

	/**
	 * @ticket 50643
	 */
	public function test_disable_sitemap_should_return_404() {
		add_filter( 'wp_sitemaps_enabled', '__return_false' );

		// Instantiate the server before navigating: registering the sitemap
		// rewrite tags is what adds `sitemap` to `$wp->public_query_vars`.
		$sitemaps = wp_sitemaps_get_server();

		$this->go_to( home_url( '/?sitemap=index' ) );

		$sitemaps->render_sitemaps();

		remove_filter( 'wp_sitemaps_enabled', '__return_false' );

		$this->assertTrue( is_404() );
	}

	/**
	 * @ticket 50643
	 */
	public function test_empty_url_list_should_return_404() {
		wp_register_sitemap_provider( 'foo', new WP_Sitemaps_Empty_Test_Provider( 'foo' ) );

		$this->go_to( home_url( '/?sitemap=foo' ) );

		wp_sitemaps_get_server()->render_sitemaps();

		$this->assertTrue( is_404() );
	}

	/**
	 * Ensures a populated, paginated sitemap page is not flagged as a 404 just
	 * because its page number exceeds the number of pages of regular posts.
	 *
	 * A sitemap URL is routed with the `sitemap` query variable but no
	 * `post_type`, so the main query defaults to `post`. Before the fix, on a
	 * request for a custom post type sitemap page numbered higher than the
	 * `post` sitemap's last page, the main query returned no posts and
	 * WP::handle_404() flagged the request as a 404 even though the sitemap
	 * provider had URLs to render for that page.
	 *
	 * @ticket 65375
	 *
	 * @covers WP_Sitemaps::pre_handle_404
	 */
	public function test_populated_paged_sitemap_should_not_return_404() {
		register_post_type(
			'sitemap_cpt',
			array(
				'public'      => true,
				'has_archive' => true,
			)
		);

		// Two custom post type posts. With one URL per sitemap page (filtered
		// below), the custom post type sitemap spans two pages, so page 2 is a
		// real, populated page.
		self::factory()->post->create_many( 2, array( 'post_type' => 'sitemap_cpt' ) );

		// Make the dummy main query (which defaults to the `post` post type)
		// have a single page: all of the regular posts created in
		// wpSetUpBeforeClass fit on page 1, so any paged > 1 request returns no
		// posts. This is the condition that used to make WP::handle_404() flag a
		// 404 for sitemap pages numbered beyond the post sitemap's last page.
		update_option( 'posts_per_page', 100 );
		add_filter( 'wp_sitemaps_max_urls', array( $this, 'filter_max_urls_to_one' ) );

		// Ensure the sitemaps server is bootstrapped so its pre_handle_404 and
		// rewrite registrations are in place before the request is simulated.
		// In a real request this happens on the 'init' hook; the test harness
		// resets the server between tests, so trigger it explicitly.
		wp_sitemaps_get_server();

		// Request page 2 of the custom post type sitemap. Its page number (2)
		// exceeds the post sitemap's single page, but the page itself has a URL
		// to render and must not be a 404.
		$this->go_to( home_url( '/?sitemap=posts&sitemap-subtype=sitemap_cpt&paged=2' ) );

		remove_filter( 'wp_sitemaps_max_urls', array( $this, 'filter_max_urls_to_one' ) );

		$this->assertFalse( is_404(), 'A populated paginated sitemap page should not be a 404.' );

		unregister_post_type( 'sitemap_cpt' );
	}

	/**
	 * Ensures a sitemap page that genuinely has no URLs still returns a 404.
	 *
	 * Guards against the pre_handle_404 short-circuit suppressing the 404 that
	 * WP_Sitemaps::render_sitemaps() issues for an out-of-range sitemap page.
	 *
	 * @ticket 65375
	 *
	 * @covers WP_Sitemaps::pre_handle_404
	 * @covers WP_Sitemaps::render_sitemaps
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_out_of_range_paged_sitemap_should_still_return_404() {
		$this->go_to( home_url( '/?sitemap=posts&sitemap-subtype=post&paged=9999' ) );

		wp_sitemaps_get_server()->render_sitemaps();

		$this->assertTrue( is_404(), 'An out-of-range sitemap page with no URLs should be a 404.' );
	}

	/**
	 * Forces the sitemap per-page URL limit to one for pagination testing.
	 *
	 * @return int
	 */
	public function filter_max_urls_to_one() {
		return 1;
	}
}
