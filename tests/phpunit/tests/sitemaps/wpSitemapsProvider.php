<?php

/**
 * Tests for the WP_Sitemaps_Provider base class.
 *
 * @group sitemaps
 *
 * @coversDefaultClass WP_Sitemaps_Provider
 */
class Tests_Sitemaps_wpSitemapsProvider extends WP_UnitTestCase {

	/**
	 * Creates a provider that only implements the abstract methods.
	 *
	 * @param array $pages Number of sitemap pages, keyed by object subtype name. The '' key is used without subtypes.
	 * @return WP_Sitemaps_Provider Provider instance.
	 */
	private function get_provider( array $pages ) {
		return new class( $pages ) extends WP_Sitemaps_Provider {

			/**
			 * Number of sitemap pages, keyed by object subtype name.
			 *
			 * @var array
			 */
			private $pages;

			/**
			 * Object subtypes passed to get_max_num_pages().
			 *
			 * @var array
			 */
			public $max_num_pages_calls = array();

			public function __construct( array $pages ) {
				$this->name        = 'tests';
				$this->object_type = 'test';
				$this->pages       = $pages;
			}

			public function get_url_list( $page_num, $object_subtype = '' ) {
				return array();
			}

			public function get_max_num_pages( $object_subtype = '' ) {
				$this->max_num_pages_calls[] = $object_subtype;

				return $this->pages[ $object_subtype ];
			}
		};
	}

	/**
	 * Creates a provider with object subtypes.
	 *
	 * @param array $pages Number of sitemap pages, keyed by object subtype name.
	 * @return WP_Sitemaps_Provider Provider instance.
	 */
	private function get_provider_with_subtypes( array $pages ) {
		return new class( $pages ) extends WP_Sitemaps_Provider {

			/**
			 * Number of sitemap pages, keyed by object subtype name.
			 *
			 * @var array
			 */
			private $pages;

			public function __construct( array $pages ) {
				$this->name        = 'tests';
				$this->object_type = 'test';
				$this->pages       = $pages;
			}

			public function get_object_subtypes() {
				$subtypes = array();

				foreach ( array_keys( $this->pages ) as $name ) {
					$subtypes[ $name ] = (object) array( 'name' => $name );
				}

				return $subtypes;
			}

			public function get_url_list( $page_num, $object_subtype = '' ) {
				return array();
			}

			public function get_max_num_pages( $object_subtype = '' ) {
				return $this->pages[ $object_subtype ];
			}
		};
	}

	/**
	 * Tests that a provider has no object subtypes by default.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_object_subtypes
	 */
	public function test_get_object_subtypes_returns_empty_array_by_default() {
		$this->assertSame( array(), $this->get_provider( array( '' => 1 ) )->get_object_subtypes() );
	}

	/**
	 * Tests the sitemap type data of a provider without object subtypes.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_sitemap_type_data
	 */
	public function test_get_sitemap_type_data_without_subtypes() {
		$provider = $this->get_provider( array( '' => 3 ) );

		$this->assertSame(
			array(
				array(
					'name'  => '',
					'pages' => 3,
				),
			),
			$provider->get_sitemap_type_data()
		);
		$this->assertSame( array( '' ), $provider->max_num_pages_calls, 'The number of pages should be requested once, without a subtype.' );
	}

	/**
	 * Tests the sitemap type data of a provider with object subtypes.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_sitemap_type_data
	 */
	public function test_get_sitemap_type_data_with_subtypes() {
		$provider = $this->get_provider_with_subtypes(
			array(
				'type-1' => 2,
				'type-2' => 0,
				123      => 1,
			)
		);

		$this->assertSame(
			array(
				array(
					'name'  => 'type-1',
					'pages' => 2,
				),
				array(
					'name'  => 'type-2',
					'pages' => 0,
				),
				array(
					'name'  => '123',
					'pages' => 1,
				),
			),
			$provider->get_sitemap_type_data()
		);
	}

	/**
	 * Tests that one sitemap entry is listed for each page of each subtype.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_sitemap_entries
	 * @covers ::get_sitemap_url
	 */
	public function test_get_sitemap_entries_lists_each_page_of_each_subtype() {
		$provider = $this->get_provider_with_subtypes(
			array(
				'type-1' => 2,
				'type-2' => 0,
				'type-3' => 1,
			)
		);

		$this->assertSame(
			array(
				array( 'loc' => 'http://' . WP_TESTS_DOMAIN . '/?sitemap=tests&sitemap-subtype=type-1&paged=1' ),
				array( 'loc' => 'http://' . WP_TESTS_DOMAIN . '/?sitemap=tests&sitemap-subtype=type-1&paged=2' ),
				array( 'loc' => 'http://' . WP_TESTS_DOMAIN . '/?sitemap=tests&sitemap-subtype=type-3&paged=1' ),
			),
			$provider->get_sitemap_entries()
		);
	}

	/**
	 * Tests the sitemap entries of a provider without object subtypes.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_sitemap_entries
	 * @covers ::get_sitemap_url
	 */
	public function test_get_sitemap_entries_without_subtypes() {
		$this->assertSame(
			array(
				array( 'loc' => 'http://' . WP_TESTS_DOMAIN . '/?sitemap=tests&paged=1' ),
				array( 'loc' => 'http://' . WP_TESTS_DOMAIN . '/?sitemap=tests&paged=2' ),
			),
			$this->get_provider( array( '' => 2 ) )->get_sitemap_entries()
		);
	}

	/**
	 * Tests that a provider without sitemap pages has no sitemap entries.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_sitemap_entries
	 */
	public function test_get_sitemap_entries_returns_empty_array_without_pages() {
		$filter = new MockAction();
		add_filter( 'wp_sitemaps_index_entry', array( $filter, 'filter' ) );

		$this->assertSame( array(), $this->get_provider( array( '' => 0 ) )->get_sitemap_entries() );
		$this->assertSame( 0, $filter->get_call_count(), 'The entry filter should not run without entries.' );
	}

	/**
	 * Tests that each sitemap entry is filtered.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_sitemap_entries
	 */
	public function test_get_sitemap_entries_applies_index_entry_filter() {
		$filter = new MockAction();
		add_filter( 'wp_sitemaps_index_entry', array( $filter, 'filter' ), 10, 4 );
		add_filter(
			'wp_sitemaps_index_entry',
			static function ( $sitemap_entry, $object_type, $object_subtype, $page ) {
				$sitemap_entry['lastmod'] = "2026-01-0{$page}T00:00:00+00:00";

				return $sitemap_entry;
			},
			20,
			4
		);

		$provider = $this->get_provider_with_subtypes( array( 'type-1' => 2 ) );

		$this->assertSame(
			array(
				array(
					'loc'     => 'http://' . WP_TESTS_DOMAIN . '/?sitemap=tests&sitemap-subtype=type-1&paged=1',
					'lastmod' => '2026-01-01T00:00:00+00:00',
				),
				array(
					'loc'     => 'http://' . WP_TESTS_DOMAIN . '/?sitemap=tests&sitemap-subtype=type-1&paged=2',
					'lastmod' => '2026-01-02T00:00:00+00:00',
				),
			),
			$provider->get_sitemap_entries(),
			'The filtered entries should be returned.'
		);
		$this->assertSame(
			array(
				array(
					array( 'loc' => 'http://' . WP_TESTS_DOMAIN . '/?sitemap=tests&sitemap-subtype=type-1&paged=1' ),
					'test',
					'type-1',
					1,
				),
				array(
					array( 'loc' => 'http://' . WP_TESTS_DOMAIN . '/?sitemap=tests&sitemap-subtype=type-1&paged=2' ),
					'test',
					'type-1',
					2,
				),
			),
			$filter->get_args(),
			'The filter should receive the entry, object type, subtype and page.'
		);
	}

	/**
	 * Tests the sitemap URL with plain permalinks.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_sitemap_url
	 *
	 * @dataProvider data_get_sitemap_url
	 *
	 * @param string $name     Object subtype name.
	 * @param int    $page     Page number.
	 * @param string $expected Expected path with plain permalinks.
	 */
	public function test_get_sitemap_url_with_plain_permalinks( $name, $page, $expected ) {
		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . $expected,
			$this->get_provider( array( '' => 1 ) )->get_sitemap_url( $name, $page )
		);
	}

	/**
	 * Tests the sitemap URL with pretty permalinks.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_sitemap_url
	 *
	 * @dataProvider data_get_sitemap_url
	 *
	 * @param string $name     Object subtype name.
	 * @param int    $page     Page number.
	 * @param string $unused   Expected path with plain permalinks.
	 * @param string $expected Expected path with pretty permalinks.
	 */
	public function test_get_sitemap_url_with_pretty_permalinks( $name, $page, $unused, $expected ) {
		$this->set_permalink_structure( '/%year%/%postname%/' );

		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . $expected,
			$this->get_provider( array( '' => 1 ) )->get_sitemap_url( $name, $page )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_get_sitemap_url() {
		return array(
			'first page without subtype'  => array( '', 1, '/?sitemap=tests&paged=1', '/wp-sitemap-tests-1.xml' ),
			'second page without subtype' => array( '', 2, '/?sitemap=tests&paged=2', '/wp-sitemap-tests-2.xml' ),
			'first page of a subtype'     => array( 'type-1', 1, '/?sitemap=tests&sitemap-subtype=type-1&paged=1', '/wp-sitemap-tests-type-1-1.xml' ),
			'later page of a subtype'     => array( 'type-1', 12, '/?sitemap=tests&sitemap-subtype=type-1&paged=12', '/wp-sitemap-tests-type-1-12.xml' ),
			'page zero is omitted'        => array( 'type-1', 0, '/?sitemap=tests&sitemap-subtype=type-1', '/wp-sitemap-tests-type-1.xml' ),
		);
	}
}
