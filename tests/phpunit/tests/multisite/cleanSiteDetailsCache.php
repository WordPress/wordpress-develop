<?php

/**
 * @group ms-required
 * @group ms-site
 * @group multisite
 *
 * @covers ::clean_site_details_cache
 */
class Tests_Multisite_CleanSiteDetailsCache extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	protected static $site_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$site_id = $factory->blog->create(
			array(
				'domain' => WP_TESTS_DOMAIN,
				'path'   => '/clean-site-details-cache/',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		wp_delete_site( self::$site_id );

		wp_update_network_site_counts();
	}

	/**
	 * @ticket 55454
	 */
	public function test_clean_site_details_cache_deletes_domain_path_lookup_cache() {
		$site = get_site( self::$site_id );
		$key  = md5( $site->domain . $site->path );

		// Prime the domain and path lookup cache.
		get_blog_details( self::$site_id );
		$this->assertNotFalse( wp_cache_get( $key, 'blog-lookup' ), 'The domain and path lookup cache should be primed.' );

		clean_site_details_cache( self::$site_id );

		$this->assertFalse( wp_cache_get( $key, 'blog-lookup' ) );
	}

	/**
	 * @ticket 55454
	 */
	public function test_get_blog_details_by_domain_and_path_returns_updated_blogname() {
		$site   = get_site( self::$site_id );
		$fields = array(
			'domain' => $site->domain,
			'path'   => $site->path,
		);

		// Prime the domain and path lookup cache with the current blog name.
		get_blog_details( $fields );

		update_blog_option( self::$site_id, 'blogname', 'Renamed Site' );

		$this->assertSame( 'Renamed Site', get_blog_details( $fields )->blogname );
	}

	/**
	 * @ticket 55454
	 */
	public function test_clean_site_details_cache_with_invalid_site_id_does_not_error() {
		clean_site_details_cache( PHP_INT_MAX );

		$this->assertFalse( wp_cache_get( PHP_INT_MAX, 'site-details' ) );
	}
}
