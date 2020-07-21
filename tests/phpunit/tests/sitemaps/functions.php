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

		$this->assertEquals( $expected_posts, 300, 'Can not confirm max URL number for posts.' );
		$this->assertEquals( $expected_taxonomies, 50, 'Can not confirm max URL number for taxonomies.' );
		$this->assertEquals( $expected_users, 1, 'Can not confirm max URL number for users.' );
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

		$this->assertEquals( array_keys( $expected ), array_keys( $sitemaps ), 'Unable to confirm default sitemap types are registered.' );

		foreach ( $expected as $name => $provider ) {
			$this->assertTrue( is_a( $sitemaps[ $name ], $provider ), "Default $name sitemap is not a $provider object." );
		}
	}
}
