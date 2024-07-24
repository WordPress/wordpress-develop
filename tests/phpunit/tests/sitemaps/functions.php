<?php

/**
 * @group sitemaps
 */
class Tests_Sitemaps_Functions extends WP_UnitTestCase {

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
			$this->assertInstanceOf( $provider, $sitemaps[ $name ], "Default $name sitemap is not a $provider object." );
		}
	}

	/**
	 * Test get_sitemap_url() with plain permalinks.
	 *
	 * @dataProvider data_get_sitemap_url_plain_permalinks
	 */
	public function test_get_sitemap_url_plain_permalinks( $name, $subtype_name, $page, $expected ) {
		$actual = get_sitemap_url( $name, $subtype_name, $page );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test get_sitemap_url() with pretty permalinks.
	 *
	 * @dataProvider data_get_sitemap_url_pretty_permalinks
	 */
	public function test_get_sitemap_url_pretty_permalinks( $name, $subtype_name, $page, $expected ) {
		$this->set_permalink_structure( '/%postname%/' );

		$actual = get_sitemap_url( $name, $subtype_name, $page );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for test_get_sitemap_url_plain_permalinks.
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
	public function data_get_sitemap_url_plain_permalinks() {
		return array(
			array( 'posts', 'post', 1, home_url( '/?sitemap=posts&sitemap-subtype=post&paged=1' ) ),
			array( 'posts', 'post', 0, home_url( '/?sitemap=posts&sitemap-subtype=post&paged=1' ) ),
			array( 'posts', 'page', 1, home_url( '/?sitemap=posts&sitemap-subtype=page&paged=1' ) ),
			array( 'posts', 'page', 5, home_url( '/?sitemap=posts&sitemap-subtype=page&paged=5' ) ),
			// Post type doesn't exist.
			array( 'posts', 'foo', 5, false ),
			array( 'taxonomies', 'category', 1, home_url( '/?sitemap=taxonomies&sitemap-subtype=category&paged=1' ) ),
			array( 'taxonomies', 'post_tag', 1, home_url( '/?sitemap=taxonomies&sitemap-subtype=post_tag&paged=1' ) ),
			// Negative paged, gets converted to its absolute value.
			array( 'taxonomies', 'post_tag', -1, home_url( '/?sitemap=taxonomies&sitemap-subtype=post_tag&paged=1' ) ),
			array( 'users', '', 4, home_url( '/?sitemap=users&paged=4' ) ),
			// Users provider doesn't allow subtypes.
			array( 'users', 'foo', 4, false ),
			// Provider doesn't exist.
			array( 'foo', '', 4, false ),
		);
	}

	/**
	 * Data provider for test_get_sitemap_url_pretty_permalinks.
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
	public function data_get_sitemap_url_pretty_permalinks() {
		return array(
			array( 'posts', 'post', 1, home_url( '/wp-sitemap-posts-post-1.xml' ) ),
			array( 'posts', 'post', 0, home_url( '/wp-sitemap-posts-post-1.xml' ) ),
			array( 'posts', 'page', 1, home_url( '/wp-sitemap-posts-page-1.xml' ) ),
			array( 'posts', 'page', 5, home_url( '/wp-sitemap-posts-page-5.xml' ) ),
			// Post type doesn't exist.
			array( 'posts', 'foo', 5, false ),
			array( 'taxonomies', 'category', 1, home_url( '/wp-sitemap-taxonomies-category-1.xml' ) ),
			array( 'taxonomies', 'post_tag', 1, home_url( '/wp-sitemap-taxonomies-post_tag-1.xml' ) ),
			// Negative paged, gets converted to its absolute value.
			array( 'taxonomies', 'post_tag', -1, home_url( '/wp-sitemap-taxonomies-post_tag-1.xml' ) ),
			array( 'users', '', 4, home_url( '/wp-sitemap-users-4.xml' ) ),
			// Users provider doesn't allow subtypes.
			array( 'users', 'foo', 4, false ),
			// Provider doesn't exist.
			array( 'foo', '', 4, false ),
		);
	}
}
