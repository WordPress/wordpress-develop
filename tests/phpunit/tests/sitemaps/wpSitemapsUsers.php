<?php

/**
 * @group sitemaps
 *
 * @coversDefaultClass WP_Sitemaps_Users
 */
class Tests_Sitemaps_wpSitemapsUsers extends WP_UnitTestCase {

	/**
	 * List of user IDs.
	 *
	 * @var array
	 */
	private static $users;

	/**
	 * Editor ID for use in some tests.
	 *
	 * @var int
	 */
	private static $editor_id;

	/**
	 * Set up fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory A WP_UnitTest_Factory object.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$users     = $factory->user->create_many( 10, array( 'role' => 'editor' ) );
		self::$editor_id = self::$users[0];
	}

	/**
	 * Test getting a URL list for a users sitemap page via
	 * WP_Sitemaps_Users::get_url_list().
	 *
	 * @covers ::get_url_list
	 */
	public function test_get_url_list_users() {
		// Set up the user to an editor to assign posts to other users.
		wp_set_current_user( self::$editor_id );

		// Create a set of posts for each user and generate the expected URL list data.
		$expected = array_map(
			static function ( $user_id ) {
				self::factory()->post->create( array( 'post_author' => $user_id ) );

				return array(
					'loc' => get_author_posts_url( $user_id ),
				);
			},
			self::$users
		);

		$user_provider = new WP_Sitemaps_Users();

		$url_list = $user_provider->get_url_list( 1 );

		$this->assertSameSets( $expected, $url_list );
	}

	/**
	 * @covers ::get_url_list
	 * @covers ::get_users_query_args
	 */
	public function test_get_url_list_skips_users_with_only_attachments_and_pages() {
		// Set up the user to an editor to assign posts to other users.
		wp_set_current_user( self::$editor_id );

		foreach ( self::$users as $user_id ) {
			self::factory()->post->create(
				array(
					'post_author' => $user_id,
					'post_type'   => 'attachment',
				)
			);
			self::factory()->post->create(
				array(
					'post_author' => $user_id,
					'post_type'   => 'page',
				)
			);
		}

		$user_provider = new WP_Sitemaps_Users();

		$url_list = $user_provider->get_url_list( 1 );

		$this->assertEmpty( $url_list );
	}

	/**
	 * Creates one published post for each of the given users.
	 *
	 * @param int[] $user_ids User IDs.
	 */
	private function create_posts_for_users( array $user_ids ) {
		foreach ( $user_ids as $user_id ) {
			self::factory()->post->create( array( 'post_author' => $user_id ) );
		}
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
	 * Tests that the URL list is split into pages.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_url_list
	 * @covers ::get_users_query_args
	 */
	public function test_get_url_list_is_paginated() {
		$authors = array_slice( self::$users, 0, 3 );

		$this->create_posts_for_users( $authors );
		$this->limit_sitemaps_to_two_urls();

		$user_provider = new WP_Sitemaps_Users();

		$this->assertSame(
			array(
				array( 'loc' => 'http://' . WP_TESTS_DOMAIN . '/?author=' . $authors[0] ),
				array( 'loc' => 'http://' . WP_TESTS_DOMAIN . '/?author=' . $authors[1] ),
			),
			$user_provider->get_url_list( 1 ),
			'The first page should list the first two authors.'
		);
		$this->assertSame(
			array(
				array( 'loc' => 'http://' . WP_TESTS_DOMAIN . '/?author=' . $authors[2] ),
			),
			$user_provider->get_url_list( 2 ),
			'The second page should list the remaining author.'
		);
		$this->assertSame( array(), $user_provider->get_url_list( 3 ), 'The third page should be empty.' );
	}

	/**
	 * Tests that authors of custom public post types are listed.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_url_list
	 * @covers ::get_users_query_args
	 */
	public function test_get_url_list_includes_authors_of_public_custom_post_types() {
		register_post_type( 'public_cpt', array( 'public' => true ) );
		register_post_type( 'private_cpt', array( 'public' => false ) );

		self::factory()->post->create(
			array(
				'post_author' => self::$users[1],
				'post_type'   => 'public_cpt',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => self::$users[2],
				'post_type'   => 'private_cpt',
			)
		);

		$user_provider = new WP_Sitemaps_Users();

		$this->assertSame(
			array(
				array( 'loc' => 'http://' . WP_TESTS_DOMAIN . '/?author=' . self::$users[1] ),
			),
			$user_provider->get_url_list( 1 )
		);
	}

	/**
	 * Tests that authors without published posts are not listed.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_url_list
	 * @covers ::get_users_query_args
	 */
	public function test_get_url_list_skips_users_without_published_posts() {
		self::factory()->post->create(
			array(
				'post_author' => self::$users[1],
				'post_status' => 'draft',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => self::$users[2],
				'post_status' => 'private',
			)
		);

		$user_provider = new WP_Sitemaps_Users();

		$this->assertSame( array(), $user_provider->get_url_list( 1 ) );
	}

	/**
	 * Tests that the URL list can be short-circuited.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_url_list
	 *
	 * @dataProvider data_pre_url_lists
	 *
	 * @param array $pre_url_list URL list returned by the filter.
	 */
	public function test_get_url_list_can_be_short_circuited( $pre_url_list ) {
		$this->create_posts_for_users( array( self::$users[0] ) );

		$filter = new MockAction();
		add_filter( 'wp_sitemaps_users_pre_url_list', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'wp_sitemaps_users_pre_url_list',
			static function () use ( $pre_url_list ) {
				return $pre_url_list;
			},
			20
		);

		$query_args_filter = new MockAction();
		add_filter( 'wp_sitemaps_users_query_args', array( $query_args_filter, 'filter' ) );

		$user_provider = new WP_Sitemaps_Users();

		$this->assertSame( $pre_url_list, $user_provider->get_url_list( 3 ) );
		$this->assertSame( array( array( null, 3 ) ), $filter->get_args(), 'The filter should receive null and the page number.' );
		$this->assertSame( 0, $query_args_filter->get_call_count(), 'No user query should be prepared.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_pre_url_lists() {
		return array(
			'custom URL list' => array( array( array( 'loc' => 'http://example.org/custom-author/' ) ) ),
			'empty URL list'  => array( array() ),
		);
	}

	/**
	 * Tests that each sitemap entry is filtered.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_url_list
	 */
	public function test_get_url_list_applies_entry_filter() {
		$this->create_posts_for_users( array( self::$users[0] ) );

		$filter = new MockAction();
		add_filter( 'wp_sitemaps_users_entry', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'wp_sitemaps_users_entry',
			static function ( $sitemap_entry ) {
				$sitemap_entry['lastmod'] = '2026-01-02T03:04:05+00:00';

				return $sitemap_entry;
			},
			20
		);

		$user_provider = new WP_Sitemaps_Users();

		$this->assertSame(
			array(
				array(
					'loc'     => 'http://' . WP_TESTS_DOMAIN . '/?author=' . self::$users[0],
					'lastmod' => '2026-01-02T03:04:05+00:00',
				),
			),
			$user_provider->get_url_list( 1 ),
			'The filtered entry should be returned.'
		);

		$args = $filter->get_args();

		$this->assertCount( 1, $args, 'The filter should run once.' );
		$this->assertSame( array( 'loc' => 'http://' . WP_TESTS_DOMAIN . '/?author=' . self::$users[0] ), $args[0][0], 'The filter should receive the entry.' );
		$this->assertInstanceOf( 'WP_User', $args[0][1], 'The filter should receive the user.' );
		$this->assertSame( self::$users[0], $args[0][1]->ID, 'The filter should receive the listed user.' );
	}

	/**
	 * Tests the default user query arguments and that they are filterable.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_url_list
	 * @covers ::get_users_query_args
	 */
	public function test_get_url_list_uses_filtered_query_args() {
		$this->create_posts_for_users( array( self::$users[0] ) );

		self::factory()->post->create(
			array(
				'post_author' => self::$users[1],
				'post_type'   => 'page',
			)
		);

		$filter = new MockAction();
		add_filter( 'wp_sitemaps_users_query_args', array( $filter, 'filter' ) );
		add_filter(
			'wp_sitemaps_users_query_args',
			static function ( $args ) {
				$args['has_published_posts'] = array( 'page' );

				return $args;
			},
			20
		);

		$user_provider = new WP_Sitemaps_Users();

		$this->assertSame(
			array(
				array( 'loc' => 'http://' . WP_TESTS_DOMAIN . '/?author=' . self::$users[1] ),
			),
			$user_provider->get_url_list( 1 ),
			'Only the page author should be listed.'
		);
		$this->assertSame(
			array(
				array(
					array(
						'has_published_posts' => array( 'post' ),
						'number'              => 2000,
					),
				),
			),
			$filter->get_args(),
			'The filter should receive the default query arguments.'
		);
	}

	/**
	 * Tests the number of sitemap pages.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_max_num_pages
	 *
	 * @dataProvider data_get_max_num_pages
	 *
	 * @param int $authors  Number of users with a published post.
	 * @param int $expected Expected number of pages with two URLs per page.
	 */
	public function test_get_max_num_pages( $authors, $expected ) {
		$this->create_posts_for_users( array_slice( self::$users, 0, $authors ) );
		$this->limit_sitemaps_to_two_urls();

		$user_provider = new WP_Sitemaps_Users();

		$this->assertSame( $expected, $user_provider->get_max_num_pages() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_get_max_num_pages() {
		return array(
			'no authors'    => array( 0, 0 ),
			'one author'    => array( 1, 1 ),
			'two authors'   => array( 2, 1 ),
			'three authors' => array( 3, 2 ),
			'five authors'  => array( 5, 3 ),
		);
	}

	/**
	 * Tests that the number of sitemap pages can be short-circuited.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_max_num_pages
	 *
	 * @dataProvider data_pre_max_num_pages
	 *
	 * @param int $pre_max_num_pages Number of pages returned by the filter.
	 */
	public function test_get_max_num_pages_can_be_short_circuited( $pre_max_num_pages ) {
		$this->create_posts_for_users( array( self::$users[0] ) );

		$filter = new MockAction();
		add_filter( 'wp_sitemaps_users_pre_max_num_pages', array( $filter, 'filter' ) );
		add_filter(
			'wp_sitemaps_users_pre_max_num_pages',
			static function () use ( $pre_max_num_pages ) {
				return $pre_max_num_pages;
			},
			20
		);

		$query_args_filter = new MockAction();
		add_filter( 'wp_sitemaps_users_query_args', array( $query_args_filter, 'filter' ) );

		$user_provider = new WP_Sitemaps_Users();

		$this->assertSame( $pre_max_num_pages, $user_provider->get_max_num_pages() );
		$this->assertSame( array( array( null ) ), $filter->get_args(), 'The filter should receive null.' );
		$this->assertSame( 0, $query_args_filter->get_call_count(), 'No user query should be prepared.' );
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
