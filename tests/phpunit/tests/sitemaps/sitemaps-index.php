<?php

/**
 * @group sitemaps
 */
class Test_WP_Sitemaps_Index extends WP_UnitTestCase {
	public function test_get_sitemap_list() {
		$registry = new WP_Sitemaps_Registry();

		/*
		 * The test provider has 3 subtypes.
		 * Each subtype has 4 pages with results.
		 * There are 2 providers registered.
		 * Hence, 3*4*2=24.
		 */
		$registry->add_sitemap( 'foo', new WP_Sitemaps_Test_Provider( 'foo' ) );
		$registry->add_sitemap( 'bar', new WP_Sitemaps_Test_Provider( 'bar' ) );

		$sitemap_index = new WP_Sitemaps_Index( $registry );
		$this->assertCount( 24, $sitemap_index->get_sitemap_list() );
	}

	public function test_get_sitemap_list_no_entries() {
		$registry = new WP_Sitemaps_Registry();

		$registry->add_sitemap( 'foo', new Core_WP_Empty_Test_Provider( 'foo' ) );

		$sitemap_index = new WP_Sitemaps_Index( $registry );
		$this->assertCount( 0, $sitemap_index->get_sitemap_list() );
	}

	public function test_get_index_url() {
		$sitemap_index = new WP_Sitemaps_Index( new WP_Sitemaps_Registry() );
		$index_url = $sitemap_index->get_index_url();

		$this->assertStringEndsWith( '/?sitemap=index', $index_url );
	}

	public function test_get_index_url_pretty_permalinks() {
		// Set permalinks for testing.
		$this->set_permalink_structure( '/%year%/%postname%/' );

		$sitemap_index = new WP_Sitemaps_Index( new WP_Sitemaps_Registry() );
		$index_url = $sitemap_index->get_index_url();

		// Clean up permalinks.
		$this->set_permalink_structure();

		$this->assertStringEndsWith( '/wp-sitemap.xml', $index_url );
	}
}
