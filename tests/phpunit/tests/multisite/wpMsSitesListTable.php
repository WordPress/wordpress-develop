<?php

/**
 * @group admin
 * @group ms-required
 * @group network-admin
 */
class Tests_Multisite_wpMsSitesListTable extends WP_UnitTestCase {

	protected static $site_ids;

	/**
	 * @var WP_MS_Sites_List_Table
	 */
	public $table = false;

	public function set_up() {
		parent::set_up();
		// Keep the main site's title from matching URL search terms such as "test".
		update_option( 'blogname', 'Main Site' );
		$this->table = _get_list_table( 'WP_MS_Sites_List_Table', array( 'screen' => 'ms-sites' ) );
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$site_ids = array(
			'wordpress.org/'          => array(
				'domain' => 'wordpress.org',
				'path'   => '/',
			),
			'wordpress.org/foo/'      => array(
				'domain' => 'wordpress.org',
				'path'   => '/foo/',
			),
			'wordpress.org/foo/bar/'  => array(
				'domain' => 'wordpress.org',
				'path'   => '/foo/bar/',
			),
			'wordpress.org/afoo/'     => array(
				'domain' => 'wordpress.org',
				'path'   => '/afoo/',
			),
			'make.wordpress.org/'     => array(
				'domain' => 'make.wordpress.org',
				'path'   => '/',
			),
			'make.wordpress.org/foo/' => array(
				'domain' => 'make.wordpress.org',
				'path'   => '/foo/',
			),
			'www.w.org/'              => array(
				'domain' => 'www.w.org',
				'path'   => '/',
			),
			'www.w.org/foo/'          => array(
				'domain' => 'www.w.org',
				'path'   => '/foo/',
			),
			'www.w.org/foo/bar/'      => array(
				'domain' => 'www.w.org',
				'path'   => '/foo/bar/',
			),
			'test.example.org/'       => array(
				'domain' => 'test.example.org',
				'path'   => '/',
			),
			'test2.example.org/'      => array(
				'domain' => 'test2.example.org',
				'path'   => '/',
			),
			'test3.example.org/zig/'  => array(
				'domain' => 'test3.example.org',
				'path'   => '/zig/',
			),
			'atest.example.org/'      => array(
				'domain' => 'atest.example.org',
				'path'   => '/',
			),
		);

		foreach ( self::$site_ids as &$id ) {
			$id = $factory->blog->create( $id );
		}
		unset( $id );
	}

	public static function wpTearDownAfterClass() {
		foreach ( self::$site_ids as $site_id ) {
			wp_delete_site( $site_id );
		}
	}

	public function test_ms_sites_list_table_default_items() {
		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		$this->assertSameSets( array( 1 ) + self::$site_ids, $items );
	}

	public function test_ms_sites_list_table_subdirectory_path_search_items() {
		if ( is_subdomain_install() ) {
			$this->markTestSkipped( 'Path search is not available for subdomain configurations.' );
		}

		$_REQUEST['s'] = 'foo';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['wordpress.org/foo/'],
			self::$site_ids['wordpress.org/foo/bar/'],
			self::$site_ids['wordpress.org/afoo/'],
			self::$site_ids['make.wordpress.org/foo/'],
			self::$site_ids['www.w.org/foo/'],
			self::$site_ids['www.w.org/foo/bar/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	public function test_ms_sites_list_table_subdirectory_multiple_path_search_items() {
		if ( is_subdomain_install() ) {
			$this->markTestSkipped( 'Path search is not available for subdomain configurations.' );
		}

		$_REQUEST['s'] = 'foo/bar';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['wordpress.org/foo/bar/'],
			self::$site_ids['www.w.org/foo/bar/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	public function test_ms_sites_list_table_invalid_path_search_items() {
		$_REQUEST['s'] = 'foobar';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$this->assertEmpty( $items );
	}

	public function test_ms_sites_list_table_subdomain_domain_search_items() {
		if ( ! is_subdomain_install() ) {
			$this->markTestSkipped( 'Domain search is not available for subdirectory configurations.' );
		}

		$_REQUEST['s'] = 'test';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['test.example.org/'],
			self::$site_ids['test2.example.org/'],
			self::$site_ids['test3.example.org/zig/'],
			self::$site_ids['atest.example.org/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	public function test_ms_sites_list_table_subdomain_domain_search_items_with_trailing_wildcard() {
		if ( ! is_subdomain_install() ) {
			$this->markTestSkipped( 'Domain search is not available for subdirectory configurations.' );
		}

		$_REQUEST['s'] = 'test*';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['test.example.org/'],
			self::$site_ids['test2.example.org/'],
			self::$site_ids['test3.example.org/zig/'],
			self::$site_ids['atest.example.org/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	public function test_ms_sites_list_table_subdirectory_path_search_items_with_trailing_wildcard() {
		if ( is_subdomain_install() ) {
			$this->markTestSkipped( 'Path search is not available for subdomain configurations.' );
		}

		$_REQUEST['s'] = 'fo*';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['wordpress.org/foo/'],
			self::$site_ids['wordpress.org/foo/bar/'],
			self::$site_ids['wordpress.org/afoo/'],
			self::$site_ids['make.wordpress.org/foo/'],
			self::$site_ids['www.w.org/foo/'],
			self::$site_ids['www.w.org/foo/bar/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	/**
	 * @ticket 42066
	 */
	public function test_get_views_should_return_views_by_default() {
		$expected = array(
			'all'    => '<a href="sites.php" class="current" aria-current="page">All <span class="count">(14)</span></a>',
			'public' => '<a href="sites.php?status=public">Public <span class="count">(14)</span></a>',
		);

		$this->assertSame( $expected, $this->table->get_views() );
	}

	/**
	 * @ticket 58795
	 */
	public function test_sites_list_searches_updated_title_without_flushing_cache() {
		$site_id = self::factory()->blog->create( array( 'title' => 'Original Catalog 58795' ) );

		$this->assertSame( array( array( $site_id ), 1 ), $this->search_sites( 'Original Catalog 58795' ) );
		$this->assertSame( array( array(), 0 ), $this->search_sites( 'Café Archive 🐱 58795' ) );

		// Named and numeric character references must match the displayed title.
		$this->assertTrue( update_blog_option( $site_id, 'blogname', 'Caf&eacute; Archive &#x1F431; 58795' ) );
		$this->assertSame( array( array( $site_id ), 1 ), $this->search_sites( 'Café Archive 🐱 58795' ) );
		$this->assertSame( array( array(), 0 ), $this->search_sites( 'Original Catalog 58795' ) );
	}

	/**
	 * @ticket 58795
	 */
	public function test_sites_list_matches_url_or_title_and_counts_before_pagination() {
		$title_id = self::factory()->blog->create( array( 'title' => 'Needle58795 Catalog' ) );
		$url_id   = self::factory()->blog->create(
			array(
				'domain' => 'needle58795.example.org',
				'path'   => '/needle58795/',
				'title'  => 'Other Catalog',
			)
		);
		$both_id  = self::factory()->blog->create(
			array(
				'domain' => 'both-needle58795.example.org',
				'path'   => '/both-needle58795/',
				'title'  => 'Another Needle58795 Catalog',
			)
		);

		// Multiple matching metadata rows must not duplicate a site or inflate its count.
		add_site_meta( $both_id, 'blogname', 'Duplicate Needle58795 Catalog' );
		add_filter(
			'sites_network_per_page',
			static function () {
				return 2;
			}
		);

		$this->assertSame(
			array( array( $title_id, $url_id ), 3 ),
			$this->search_sites( 'needle58795', array( 'orderby' => 'blog_id' ) )
		);
		$this->assertSame(
			array( array( $both_id ), 3 ),
			$this->search_sites(
				'needle58795',
				array(
					'orderby' => 'blog_id',
					'paged'   => 2,
				)
			)
		);
	}

	/**
	 * Searches the network sites list and returns the matching IDs and total.
	 *
	 * @param string $search Search term.
	 * @param array  $args   Additional request arguments.
	 * @return array List of site IDs and the total number of matches.
	 */
	private function search_sites( $search, $args = array() ) {
		$request = $_REQUEST;

		try {
			$_REQUEST = array_merge( array( 's' => $search ), $args );
			$this->table->prepare_items();

			return array(
				array_map( 'intval', wp_list_pluck( $this->table->items, 'blog_id' ) ),
				(int) $this->table->get_pagination_arg( 'total_items' ),
			);
		} finally {
			$_REQUEST = $request;
		}
	}
}
