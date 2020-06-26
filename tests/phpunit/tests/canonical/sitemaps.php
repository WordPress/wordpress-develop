<?php

/**
 * @group canonical
 * @group rewrite
 * @group query
 * @group sitemaps
 */
class Tests_Canonical_Sitemaps extends WP_Canonical_UnitTestCase {

	public function setUp() {
		parent::setUp();
		$wp_sitemaps = new WP_Sitemaps();
		$wp_sitemaps->init();
	}

	public function test_remove_trailing_slashes_for_sitemap_index_requests() {
		$this->set_permalink_structure( '/%postname%/' );
		$this->assertCanonical( '/wp-sitemap.xml', '/wp-sitemap.xml' );
		$this->assertCanonical( '/wp-sitemap.xml/', '/wp-sitemap.xml' );
	}

	public function test_remove_trailing_slashes_for_sitemap_index_stylesheet_requests() {
		$this->set_permalink_structure( '/%postname%/' );
		$this->assertCanonical( '/wp-sitemap-index.xsl', '/wp-sitemap-index.xsl' );
		$this->assertCanonical( '/wp-sitemap-index.xsl/', '/wp-sitemap-index.xsl' );
	}

	public function test_remove_trailing_slashes_for_sitemap_requests() {
		$this->set_permalink_structure( '/%postname%/' );
		$this->assertCanonical( '/wp-sitemap-posts-post-1.xml', '/wp-sitemap-posts-post-1.xml' );
		$this->assertCanonical( '/wp-sitemap-posts-post-1.xml/', '/wp-sitemap-posts-post-1.xml' );
		$this->assertCanonical( '/wp-sitemap-users-1.xml', '/wp-sitemap-users-1.xml' );
		$this->assertCanonical( '/wp-sitemap-users-1.xml/', '/wp-sitemap-users-1.xml' );
	}

	public function test_remove_trailing_slashes_for_sitemap_stylesheet_requests() {
		$this->set_permalink_structure( '/%postname%/' );
		$this->assertCanonical( '/wp-sitemap.xsl', '/wp-sitemap.xsl' );
		$this->assertCanonical( '/wp-sitemap.xsl/', '/wp-sitemap.xsl' );
	}

}
