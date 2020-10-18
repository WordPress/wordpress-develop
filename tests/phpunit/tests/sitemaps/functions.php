<?php

/**
 * @group sitemaps
 */
class Test_Sitemaps_Functions extends WP_UnitTestCase {
	/**
	 * Test getting the correct number of URLs for a sitemap.
	 */
	public function test_wp_sitemaps_get_max_urls() {
		// Apply a filter to test filterable values.
		add_filter( 'wp_sitemaps_max_urls', array( $this, '_filter_max_url_value' ), 10, 2 );

		$expected_posts      = wp_sitemaps_get_max_urls( 'post' );
		$expected_taxonomies = wp_sitemaps_get_max_urls( 'term' );
		$expected_users      = wp_sitemaps_get_max_urls( 'user' );

		$this->assertSame( $expected_posts, 300, 'Can not confirm max URL number for posts.' );
		$this->assertSame( $expected_taxonomies, 50, 'Can not confirm max URL number for taxonomies.' );
		$this->assertSame( $expected_users, 1, 'Can not confirm max URL number for users.' );
	}

	/**
	 * Callback function for testing the `sitemaps_max_urls` filter.
	 *
	 * @param int    $max_urls The maximum number of URLs included in a sitemap. Default 2000.
	 * @param string $type     Optional. The type of sitemap to be filtered. Default empty.
	 * @return int The maximum number of URLs.
	 */
	public function _filter_max_url_value( $max_urls, $type ) {
		switch ( $type ) {
			case 'post':
				return 300;
			case 'term':
				return 50;
			case 'user':
				return 1;
			default:
				return $max_urls;
		}
	}

	/**
	 * Test wp_get_sitemap_providers default functionality.
	 */
	public function test_wp_get_sitemap_providers() {
		$sitemaps = wp_get_sitemap_providers();

		$expected = array(
			'posts'      => 'WP_Sitemaps_Posts',
			'taxonomies' => 'WP_Sitemaps_Taxonomies',
			'users'      => 'WP_Sitemaps_Users',
		);

		$this->assertSame( array_keys( $expected ), array_keys( $sitemaps ), 'Unable to confirm default sitemap types are registered.' );

		foreach ( $expected as $name => $provider ) {
			$this->assertTrue( is_a( $sitemaps[ $name ], $provider ), "Default $name sitemap is not a $provider object." );
		}
	}

	/**
	 * Test get_sitemap_url() with ugly permalinks.
	 *
	 * @dataProvider ugly_permalinks_provider
	 */
	public function test_get_sitemap_url_ugly_permalinks( $name, $subtype_name, $page, $expected ) {
		$actual = get_sitemap_url( $name, $subtype_name, $page );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test get_sitemap_url() with pretty permalinks.
	 *
	 * @dataProvider pretty_permalinks_provider
	 */
	public function test_get_sitemap_url_pretty_permalinks( $name, $subtype_name, $page, $expected ) {
		$this->set_permalink_structure( '/%postname%/' );

		$actual = get_sitemap_url( $name, $subtype_name, $page );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for test_get_sitemap_url_ugly_permalinks.
	 *
	 * @return array[] {
	 *     Data to test with.
	 *
	 *     @type string       $0 Sitemap name.
	 *     @type string       $1 Sitemap subtype name.
	 *     @type int          $3 Sitemap page.
	 *     @type string|false $4 Sitemap URL.
	 * }
	 */
	function ugly_permalinks_provider() {
		return array(
			array( 'posts', 'post', 1, home_url( '/?sitemap=posts&sitemap-subtype=post&paged=1' ) ),
			array( 'posts', 'post', 0, home_url( '/?sitemap=posts&sitemap-subtype=post&paged=1' ) ),
			array( 'posts', 'page', 1, home_url( '/?sitemap=posts&sitemap-subtype=page&paged=1' ) ),
			array( 'posts', 'page', 5, home_url( '/?sitemap=posts&sitemap-subtype=page&paged=5' ) ),
			// post_type doesn't exist.
			array( 'posts', 'foo', 5, false ),
			array( 'taxonomies', 'category', 1, home_url( '/?sitemap=taxonomies&sitemap-subtype=category&paged=1' ) ),
			array( 'taxonomies', 'post_tag', 1, home_url( '/?sitemap=taxonomies&sitemap-subtype=post_tag&paged=1' ) ),
			array( 'taxonomies', 'post_tag', -1, home_url( '/?sitemap=taxonomies&sitemap-subtype=post_tag&paged=1' ) ),
			// negative paged, gets converted to it's absolute value.
			array( 'users', '', 4, home_url( '/?sitemap=users&paged=4' ) ),
			// users provider doesn't allow subtypes.
			array( 'users', 'foo', 4, false ),
			// provider doesn't exist.
			array( 'foo', '', 4, false ),
		);
	}

	/**
	 * Data provider for test_get_sitemap_url_pretty_permalinks
	 *
	 * @return array[] {
	 *     Data to test with.
	 *
	 *     @type string       $0 Sitemap name.
	 *     @type string       $1 Sitemap subtype name.
	 *     @type int          $3 Sitemap page.
	 *     @type string|false $4 Sitemap URL.
	 * }
	 */
	function pretty_permalinks_provider() {
		return array(
			array( 'posts', 'post', 1, home_url( '/wp-sitemap-posts-post-1.xml' ) ),
			array( 'posts', 'post', 0, home_url( '/wp-sitemap-posts-post-1.xml' ) ),
			array( 'posts', 'page', 1, home_url( '/wp-sitemap-posts-page-1.xml' ) ),
			array( 'posts', 'page', 5, home_url( '/wp-sitemap-posts-page-5.xml' ) ),
			// post_type doesn't exist.
			array( 'posts', 'foo', 5, false ),
			array( 'taxonomies', 'category', 1, home_url( '/wp-sitemap-taxonomies-category-1.xml' ) ),
			array( 'taxonomies', 'post_tag', 1, home_url( '/wp-sitemap-taxonomies-post_tag-1.xml' ) ),
			// negative paged, gets converted to it's absolute value.
			array( 'taxonomies', 'post_tag', -1, home_url( '/wp-sitemap-taxonomies-post_tag-1.xml' ) ),
			array( 'users', '', 4, home_url( '/wp-sitemap-users-4.xml' ) ),
			// users provider doesn't allow subtypes.
			array( 'users', 'foo', 4, false ),
			// provider doesn't exist.
			array( 'foo', '', 4, false ),
		);
	}
}
