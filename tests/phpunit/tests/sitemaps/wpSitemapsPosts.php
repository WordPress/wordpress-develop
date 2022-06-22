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

		$this->assertArrayHasKey( 'lastmod', $sitemap_entry );
	}

	/**
	 * Callback for 'wp_sitemaps_posts_show_on_front_entry' filter.
	 */
	public function _show_on_front_entry( $sitemap_entry ) {
		$sitemap_entry['lastmod'] = wp_date( DATE_W3C, time() );

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
			$expected[] = array( 'loc' => home_url( "?p={$post_id}" ) );
		}

		// Check that the URL list is still in the order of the post IDs (i.e., sticky post wasn't moved to the front).
		$this->assertSame( $expected, $url_list, 'The post order did not match.' );
	}
}
