<?php

/**
 * @group sitemaps
 */
class Tests_Sitemaps_wpSitemapsIndex extends WP_UnitTestCase {

	public function test_get_sitemap_list() {
		$registry = new WP_Sitemaps_Registry();

		/*
		 * The test provider has 3 subtypes.
		 * Each subtype has 4 pages with results.
		 * There are 2 providers registered.
		 * Hence, 3*4*2=24.
		 */
		$registry->add_provider( 'foo', new WP_Sitemaps_Test_Provider( 'foo' ) );
		$registry->add_provider( 'bar', new WP_Sitemaps_Test_Provider( 'bar' ) );

		$sitemap_index = new WP_Sitemaps_Index( $registry );
		$this->assertCount( 24, $sitemap_index->get_sitemap_list() );
	}

	/**
	 * Test that a sitemap index won't contain more than 50000 sitemaps.
	 *
	 * @ticket 50666
	 */
	public function test_get_sitemap_list_limit() {
		$registry = new WP_Sitemaps_Registry();

		// add 3 providers, which combined produce more than the maximum 50000 sitemaps in the index.
		$registry->add_provider( 'provider_1', new WP_Sitemaps_Large_Test_Provider( 25000 ) );
		$registry->add_provider( 'provider_2', new WP_Sitemaps_Large_Test_Provider( 25000 ) );
		$registry->add_provider( 'provider_3', new WP_Sitemaps_Large_Test_Provider( 25000 ) );

		$count = 0;
		foreach ( $registry->get_providers() as $provider ) {
			$count += count( $provider->get_url_list( 1 ) );
		}
		$this->assertGreaterThan( 50000, $count );

		$sitemap_index = new WP_Sitemaps_Index( $registry );
		$this->assertCount( 50000, $sitemap_index->get_sitemap_list() );
	}

	public function test_get_sitemap_list_no_entries() {
		$registry = new WP_Sitemaps_Registry();

		$registry->add_provider( 'foo', new WP_Sitemaps_Empty_Test_Provider( 'foo' ) );

		$sitemap_index = new WP_Sitemaps_Index( $registry );
		$this->assertCount( 0, $sitemap_index->get_sitemap_list() );
	}

	public function test_get_index_url() {
		$sitemap_index = new WP_Sitemaps_Index( new WP_Sitemaps_Registry() );
		$index_url     = $sitemap_index->get_index_url();

		$this->assertStringEndsWith( '/?sitemap=index', $index_url );
	}

	public function test_get_index_url_pretty_permalinks() {
		// Set permalinks for testing.
		$this->set_permalink_structure( '/%year%/%postname%/' );

		$sitemap_index = new WP_Sitemaps_Index( new WP_Sitemaps_Registry() );
		$index_url     = $sitemap_index->get_index_url();

		// Clean up permalinks.
		$this->set_permalink_structure();

		$this->assertStringEndsWith( '/wp-sitemap.xml', $index_url );
	}
}
