<?php

/**
 * @group sitemaps
 */
class Tests_Sitemaps_wpSitemapsPosts extends WP_UnitTestCase {

	/**
	 * Tests getting sitemap entries for post type page with 'posts' homepage.
	 *
	 * Ensures that an entry is added even if there are no pages.
	 *
	 * @ticket 50571
	 */
	public function test_get_sitemap_entries_homepage() {
		update_option( 'show_on_front', 'posts' );

		$posts_provider = new WP_Sitemaps_Posts();

		$post_list = $posts_provider->get_sitemap_entries();

		$expected = array(
			array(
				'loc' => home_url( '/?sitemap=posts&sitemap-subtype=page&paged=1' ),
			),
		);

		$this->assertSame( $expected, $post_list );
	}

	/**
	 * Tests ability to filter object subtypes.
	 */
	public function test_filter_sitemaps_post_types() {
		$posts_provider = new WP_Sitemaps_Posts();

		// Return an empty array to show that the list of subtypes is filterable.
		add_filter( 'wp_sitemaps_post_types', '__return_empty_array' );
		$subtypes = $posts_provider->get_object_subtypes();

		$this->assertSame( array(), $subtypes, 'Could not filter posts subtypes.' );
	}

	/**
	 * Tests `wp_sitemaps_posts_show_on_front_entry` filter.
	 */
	public function test_posts_show_on_front_entry() {
		$posts_provider = new WP_Sitemaps_Posts();
		update_option( 'show_on_front', 'page' );

		add_filter( 'wp_sitemaps_posts_show_on_front_entry', array( $this, '_show_on_front_entry' ) );

		$url_list = $posts_provider->get_url_list( 1, 'page' );

		$this->assertSame( array(), $url_list );

		update_option( 'show_on_front', 'posts' );

		$url_list      = $posts_provider->get_url_list( 1, 'page' );
		$sitemap_entry = array_shift( $url_list );

		$this->assertEqualSetsWithIndex(
			array(
				'loc'     => home_url( '/' ),
				'lastmod' => '2000-01-01',
			),
			$sitemap_entry
		);
	}

	/**
	 * Callback for 'wp_sitemaps_posts_show_on_front_entry' filter.
	 */
	public function _show_on_front_entry( $sitemap_entry ) {
		$sitemap_entry['lastmod'] = '2000-01-01';

		return $sitemap_entry;
	}

	/**
	 * Tests that sticky posts are not moved to the front of the first page of the post sitemap.
	 *
	 * @ticket 55633
	 */
	public function test_posts_sticky_posts_not_moved_to_front() {
		$factory = self::factory();

		// Create 4 posts, and stick the last one.
		$post_ids     = $factory->post->create_many( 4 );
		$last_post_id = end( $post_ids );
		stick_post( $last_post_id );

		$posts_provider = new WP_Sitemaps_Posts();

		$url_list = $posts_provider->get_url_list( 1, 'post' );

		$this->assertCount( count( $post_ids ), $url_list, 'The post count did not match.' );

		$expected = array();

		foreach ( $post_ids as $post_id ) {
			$expected[] = array(
				'loc'     => home_url( "?p={$post_id}" ),
				'lastmod' => get_post_modified_time( DATE_W3C, true, $post_id ),
			);
		}

		// Check that the URL list is still in the order of the post IDs (i.e., sticky post wasn't moved to the front).
		$this->assertSame( $expected, $url_list, 'The post order did not match.' );
	}

	/**
	 * Creates a published post with a fixed modified date.
	 *
	 * @param string $post_modified_gmt Modified date in GMT, in 'Y-m-d H:i:s' format.
	 * @return int Post ID.
	 */
	private function create_post_modified_at( $post_modified_gmt ) {
		global $wpdb;

		$post_id = self::factory()->post->create();

		// wp_update_post() always sets the modified date to the current time.
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => $post_modified_gmt,
				'post_modified_gmt' => $post_modified_gmt,
			),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );

		return $post_id;
	}

	/**
	 * Limits sitemaps to two URLs per page.
	 */
	private function limit_sitemaps_to_two_urls() {
		add_filter(
			'wp_sitemaps_max_urls',
			static function () {
				return 2;
			}
		);
	}

	/**
	 * Tests that only viewable public post types other than attachments are object subtypes.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Sitemaps_Posts::get_object_subtypes
	 */
	public function test_get_object_subtypes_returns_viewable_public_post_types() {
		register_post_type( 'public_cpt', array( 'public' => true ) );
		register_post_type( 'private_cpt', array( 'public' => false ) );
		register_post_type(
			'not_viewable_cpt',
			array(
				'public'             => true,
				'publicly_queryable' => false,
			)
		);

		$posts_provider = new WP_Sitemaps_Posts();
		$subtypes       = $posts_provider->get_object_subtypes();

		$this->assertSame( array( 'post', 'page', 'public_cpt' ), array_keys( $subtypes ) );
		$this->assertContainsOnlyInstancesOf( 'WP_Post_Type', $subtypes );
	}

	/**
	 * Tests that the URL list is empty for post types that are not object subtypes.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Sitemaps_Posts::get_url_list
	 *
	 * @dataProvider data_unsupported_post_types
	 *
	 * @param string $post_type Post type.
	 */
	public function test_get_url_list_returns_empty_array_for_unsupported_post_type( $post_type ) {
		register_post_type( 'private_cpt', array( 'public' => false ) );

		self::factory()->post->create( array( 'post_type' => 'private_cpt' ) );
		self::factory()->attachment->create();

		$filter = new MockAction();
		add_filter( 'wp_sitemaps_posts_pre_url_list', array( $filter, 'filter' ) );

		$posts_provider = new WP_Sitemaps_Posts();

		$this->assertSame( array(), $posts_provider->get_url_list( 1, $post_type ) );
		$this->assertSame( 0, $filter->get_call_count(), 'The short-circuit filter should not run.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unsupported_post_types() {
		return array(
			'empty post type'        => array( '' ),
			'attachment'             => array( 'attachment' ),
			'private post type'      => array( 'private_cpt' ),
			'unregistered post type' => array( 'does_not_exist' ),
		);
	}

	/**
	 * Tests the location and last modified date of each entry.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Sitemaps_Posts::get_url_list
	 *
	 * @dataProvider data_timezones
	 *
	 * @param string $timezone Site timezone.
	 * @param string $lastmod  Expected last modified date.
	 */
	public function test_get_url_list_returns_location_and_last_modified_date( $timezone, $lastmod ) {
		update_option( 'timezone_string', $timezone );

		$post_id = $this->create_post_modified_at( '2026-02-03 04:05:06' );

		$posts_provider = new WP_Sitemaps_Posts();

		$this->assertSame(
			array(
				array(
					'loc'     => 'http://' . WP_TESTS_DOMAIN . '/?p=' . $post_id,
					'lastmod' => $lastmod,
				),
			),
			$posts_provider->get_url_list( 1, 'post' )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_timezones() {
		return array(
			'UTC'          => array( 'UTC', '2026-02-03T04:05:06+00:00' ),
			'behind UTC'   => array( 'America/New_York', '2026-02-02T23:05:06-05:00' ),
			'ahead of UTC' => array( 'Asia/Dhaka', '2026-02-03T10:05:06+06:00' ),
		);
	}

	/**
	 * Tests that only published posts of the requested post type are listed.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Sitemaps_Posts::get_url_list
	 * @covers WP_Sitemaps_Posts::get_posts_query_args
	 */
	public function test_get_url_list_only_lists_published_posts_of_post_type() {
		$published_id = $this->create_post_modified_at( '2026-02-03 04:05:06' );

		self::factory()->post->create( array( 'post_status' => 'draft' ) );
		self::factory()->post->create( array( 'post_status' => 'private' ) );
		self::factory()->post->create( array( 'post_status' => 'pending' ) );
		self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_date'   => '2099-01-01 00:00:00',
			)
		);
		self::factory()->post->create( array( 'post_type' => 'page' ) );

		$posts_provider = new WP_Sitemaps_Posts();

		$this->assertSame(
			array(
				array(
					'loc'     => 'http://' . WP_TESTS_DOMAIN . '/?p=' . $published_id,
					'lastmod' => '2026-02-03T04:05:06+00:00',
				),
			),
			$posts_provider->get_url_list( 1, 'post' )
		);
	}

	/**
	 * Tests that the URL list is split into pages.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Sitemaps_Posts::get_url_list
	 * @covers WP_Sitemaps_Posts::get_posts_query_args
	 */
	public function test_get_url_list_is_paginated() {
		$first_id  = $this->create_post_modified_at( '2026-02-01 00:00:00' );
		$second_id = $this->create_post_modified_at( '2026-02-02 00:00:00' );
		$third_id  = $this->create_post_modified_at( '2026-02-03 00:00:00' );

		$this->limit_sitemaps_to_two_urls();

		$posts_provider = new WP_Sitemaps_Posts();

		$this->assertSame(
			array(
				array(
					'loc'     => 'http://' . WP_TESTS_DOMAIN . '/?p=' . $first_id,
					'lastmod' => '2026-02-01T00:00:00+00:00',
				),
				array(
					'loc'     => 'http://' . WP_TESTS_DOMAIN . '/?p=' . $second_id,
					'lastmod' => '2026-02-02T00:00:00+00:00',
				),
			),
			$posts_provider->get_url_list( 1, 'post' ),
			'The first page should list the first two posts.'
		);
		$this->assertSame(
			array(
				array(
					'loc'     => 'http://' . WP_TESTS_DOMAIN . '/?p=' . $third_id,
					'lastmod' => '2026-02-03T00:00:00+00:00',
				),
			),
			$posts_provider->get_url_list( 2, 'post' ),
			'The second page should list the remaining post.'
		);
		$this->assertSame( array(), $posts_provider->get_url_list( 3, 'post' ), 'The third page should be empty.' );
	}

	/**
	 * Tests that the URL list can be short-circuited.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Sitemaps_Posts::get_url_list
	 *
	 * @dataProvider data_pre_url_lists
	 *
	 * @param array $pre_url_list URL list returned by the filter.
	 */
	public function test_get_url_list_can_be_short_circuited( $pre_url_list ) {
		self::factory()->post->create();

		$filter = new MockAction();
		add_filter( 'wp_sitemaps_posts_pre_url_list', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'wp_sitemaps_posts_pre_url_list',
			static function () use ( $pre_url_list ) {
				return $pre_url_list;
			},
			20
		);

		$query_args_filter = new MockAction();
		add_filter( 'wp_sitemaps_posts_query_args', array( $query_args_filter, 'filter' ) );

		$posts_provider = new WP_Sitemaps_Posts();

		$this->assertSame( $pre_url_list, $posts_provider->get_url_list( 3, 'post' ) );
		$this->assertSame( array( array( null, 'post', 3 ) ), $filter->get_args(), 'The filter should receive null, the post type and the page number.' );
		$this->assertSame( 0, $query_args_filter->get_call_count(), 'No post query should be prepared.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_pre_url_lists() {
		return array(
			'custom URL list' => array( array( array( 'loc' => 'http://example.org/custom-post/' ) ) ),
			'empty URL list'  => array( array() ),
		);
	}

	/**
	 * Tests that each sitemap entry is filtered.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Sitemaps_Posts::get_url_list
	 */
	public function test_get_url_list_applies_entry_filter() {
		$post_id = $this->create_post_modified_at( '2026-02-03 04:05:06' );

		$filter = new MockAction();
		add_filter( 'wp_sitemaps_posts_entry', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'wp_sitemaps_posts_entry',
			static function ( $sitemap_entry ) {
				unset( $sitemap_entry['lastmod'] );
				$sitemap_entry['priority'] = 0.5;

				return $sitemap_entry;
			},
			20
		);

		$posts_provider = new WP_Sitemaps_Posts();

		$this->assertSame(
			array(
				array(
					'loc'      => 'http://' . WP_TESTS_DOMAIN . '/?p=' . $post_id,
					'priority' => 0.5,
				),
			),
			$posts_provider->get_url_list( 1, 'post' ),
			'The filtered entry should be returned.'
		);

		$args = $filter->get_args();

		$this->assertCount( 1, $args, 'The filter should run once.' );
		$this->assertSame(
			array(
				'loc'     => 'http://' . WP_TESTS_DOMAIN . '/?p=' . $post_id,
				'lastmod' => '2026-02-03T04:05:06+00:00',
			),
			$args[0][0],
			'The filter should receive the entry.'
		);
		$this->assertInstanceOf( 'WP_Post', $args[0][1], 'The filter should receive the post.' );
		$this->assertSame( $post_id, $args[0][1]->ID, 'The filter should receive the listed post.' );
		$this->assertSame( 'post', $args[0][2], 'The filter should receive the post type.' );
	}

	/**
	 * Tests the default post query arguments and that they are filterable.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Sitemaps_Posts::get_url_list
	 * @covers WP_Sitemaps_Posts::get_posts_query_args
	 */
	public function test_get_url_list_uses_filtered_query_args() {
		$this->create_post_modified_at( '2026-02-01 00:00:00' );
		$private_id = self::factory()->post->create( array( 'post_status' => 'private' ) );

		$filter = new MockAction();
		add_filter( 'wp_sitemaps_posts_query_args', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'wp_sitemaps_posts_query_args',
			static function ( $args ) {
				$args['post_status'] = array( 'private' );

				return $args;
			},
			20
		);

		$posts_provider = new WP_Sitemaps_Posts();

		$this->assertSame(
			array( 'http://' . WP_TESTS_DOMAIN . '/?p=' . $private_id ),
			wp_list_pluck( $posts_provider->get_url_list( 1, 'post' ), 'loc' ),
			'Only the private post should be listed.'
		);
		$this->assertSame(
			array(
				array(
					array(
						'orderby'                => 'ID',
						'order'                  => 'ASC',
						'post_type'              => 'post',
						'posts_per_page'         => 2000,
						'post_status'            => array( 'publish' ),
						'no_found_rows'          => true,
						'update_post_term_cache' => false,
						'update_post_meta_cache' => false,
						'ignore_sticky_posts'    => true,
					),
					'post',
				),
			),
			$filter->get_args(),
			'The filter should receive the default query arguments and the post type.'
		);
	}

	/**
	 * Tests the number of sitemap pages.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Sitemaps_Posts::get_max_num_pages
	 *
	 * @dataProvider data_get_max_num_pages
	 *
	 * @param int $posts    Number of published posts.
	 * @param int $expected Expected number of pages with two URLs per page.
	 */
	public function test_get_max_num_pages( $posts, $expected ) {
		if ( $posts > 0 ) {
			self::factory()->post->create_many( $posts );
		}

		self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->limit_sitemaps_to_two_urls();

		$posts_provider = new WP_Sitemaps_Posts();

		$this->assertSame( $expected, $posts_provider->get_max_num_pages( 'post' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_get_max_num_pages() {
		return array(
			'no posts'    => array( 0, 0 ),
			'one post'    => array( 1, 1 ),
			'two posts'   => array( 2, 1 ),
			'three posts' => array( 3, 2 ),
			'five posts'  => array( 5, 3 ),
		);
	}

	/**
	 * Tests that there are no sitemap pages without an object subtype.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Sitemaps_Posts::get_max_num_pages
	 */
	public function test_get_max_num_pages_returns_zero_without_subtype() {
		self::factory()->post->create();

		$filter = new MockAction();
		add_filter( 'wp_sitemaps_posts_pre_max_num_pages', array( $filter, 'filter' ) );

		$posts_provider = new WP_Sitemaps_Posts();

		$this->assertSame( 0, $posts_provider->get_max_num_pages() );
		$this->assertSame( 0, $filter->get_call_count(), 'The short-circuit filter should not run.' );
	}

	/**
	 * Tests that the page post type always has a sitemap page when the homepage shows the latest posts.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Sitemaps_Posts::get_max_num_pages
	 *
	 * @dataProvider data_show_on_front
	 *
	 * @param string $show_on_front Value of the show_on_front option.
	 * @param string $post_type     Post type.
	 * @param int    $expected      Expected number of pages.
	 */
	public function test_get_max_num_pages_without_posts_depends_on_show_on_front( $show_on_front, $post_type, $expected ) {
		update_option( 'show_on_front', $show_on_front );

		$posts_provider = new WP_Sitemaps_Posts();

		$this->assertSame( $expected, $posts_provider->get_max_num_pages( $post_type ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_show_on_front() {
		return array(
			'pages, homepage shows latest posts' => array( 'posts', 'page', 1 ),
			'pages, homepage shows a page'       => array( 'page', 'page', 0 ),
			'posts, homepage shows latest posts' => array( 'posts', 'post', 0 ),
		);
	}

	/**
	 * Tests that the number of sitemap pages can be short-circuited.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Sitemaps_Posts::get_max_num_pages
	 *
	 * @dataProvider data_pre_max_num_pages
	 *
	 * @param int $pre_max_num_pages Number of pages returned by the filter.
	 */
	public function test_get_max_num_pages_can_be_short_circuited( $pre_max_num_pages ) {
		self::factory()->post->create();

		$filter = new MockAction();
		add_filter( 'wp_sitemaps_posts_pre_max_num_pages', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'wp_sitemaps_posts_pre_max_num_pages',
			static function () use ( $pre_max_num_pages ) {
				return $pre_max_num_pages;
			},
			20
		);

		$query_args_filter = new MockAction();
		add_filter( 'wp_sitemaps_posts_query_args', array( $query_args_filter, 'filter' ) );

		$posts_provider = new WP_Sitemaps_Posts();

		$this->assertSame( $pre_max_num_pages, $posts_provider->get_max_num_pages( 'post' ) );
		$this->assertSame( array( array( null, 'post' ) ), $filter->get_args(), 'The filter should receive null and the post type.' );
		$this->assertSame( 0, $query_args_filter->get_call_count(), 'No post query should be prepared.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_pre_max_num_pages() {
		return array(
			'seven pages' => array( 7 ),
			'zero pages'  => array( 0 ),
		);
	}
}
