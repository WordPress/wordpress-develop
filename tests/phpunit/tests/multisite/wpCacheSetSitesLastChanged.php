<?php

/**
 * Tests for the wp_cache_set_sites_last_changed() function.
 *
 * @group ms-required
 * @group multisite
 * @group cache
 *
 * @covers ::wp_cache_set_sites_last_changed
 */
class Tests_Multisite_WpCacheSetSitesLastChanged extends WP_UnitTestCase {

	/**
	 * A site used across the tests.
	 *
	 * @var int
	 */
	protected static $site_id;

	/**
	 * Creates a shared site before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$site_id = $factory->blog->create();
	}

	/**
	 * Adding site meta should update the 'sites' and 'blog-meta'
	 * last changed values but leave 'site-queries' untouched.
	 *
	 * @ticket 65487
	 */
	public function test_site_meta_action_updates_sites_and_blog_meta() {
		$sites_before        = wp_cache_get_last_changed( 'sites' );
		$blog_meta_before    = wp_cache_get_last_changed( 'blog-meta' );
		$site_queries_before = wp_cache_get_last_changed( 'site-queries' );

		add_site_meta( self::$site_id, 'test_key', 'test_value' );

		$this->assertNotSame(
			$sites_before,
			wp_cache_get_last_changed( 'sites' ),
			'The sites last changed value should be updated.'
		);
		$this->assertNotSame(
			$blog_meta_before,
			wp_cache_get_last_changed( 'blog-meta' ),
			'The blog-meta last changed value should be updated.'
		);
		$this->assertSame(
			$site_queries_before,
			wp_cache_get_last_changed( 'site-queries' ),
			'The site-queries last changed value should not be updated.'
		);
	}

	/**
	 * Cleaning the blog cache should update the 'sites' and 'site-queries'
	 * last changed values but leave 'blog-meta' untouched.
	 *
	 * @ticket 65487
	 */
	public function test_site_query_action_updates_sites_and_site_queries() {
		$sites_before        = wp_cache_get_last_changed( 'sites' );
		$blog_meta_before    = wp_cache_get_last_changed( 'blog-meta' );
		$site_queries_before = wp_cache_get_last_changed( 'site-queries' );

		clean_blog_cache( self::$site_id );

		$this->assertNotSame(
			$sites_before,
			wp_cache_get_last_changed( 'sites' ),
			'The sites last changed value should be updated.'
		);
		$this->assertNotSame(
			$site_queries_before,
			wp_cache_get_last_changed( 'site-queries' ),
			'The site-queries last changed value should be updated.'
		);
		$this->assertSame(
			$blog_meta_before,
			wp_cache_get_last_changed( 'blog-meta' ),
			'The blog-meta last changed value should not be updated.'
		);
	}
}
