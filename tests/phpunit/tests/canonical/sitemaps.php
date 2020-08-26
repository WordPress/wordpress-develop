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

	/**
	 * Ensure sitemaps redirects work as expected.
	 *
	 * @dataProvider sitemaps_canonical_redirects_provider
	 * @ticket 50910
	 */
	public function test_sitemaps_canonical_redirects( $test_url, $expected ) {
		$this->assertCanonical( $test_url, $expected, 50910 );
	}

	/**
	 * Data provider for test_sitemaps_canonical_redirects.
	 *
	 * @return array[] {
	 *     Data to test with.
	 *
	 *     @type string $0 The test URL.
	 *     @type string $1 The expected canonical URL.
	 * }
	 */
	public function sitemaps_canonical_redirects_provider() {
		return array(
			// Ugly/incorrect versions redirect correctly.
			array( '/?sitemap=index', '/wp-sitemap.xml' ),
			array( '/wp-sitemap.xml/', '/wp-sitemap.xml' ),
			array( '/?sitemap=posts&sitemap-subtype=post', '/wp-sitemap-posts-post-1.xml' ),
			array( '/?sitemap=posts&sitemap-subtype=post&paged=2', '/wp-sitemap-posts-post-2.xml' ),
			array( '/?sitemap=taxonomies&sitemap-subtype=category', '/wp-sitemap-taxonomies-category-1.xml' ),
			array( '/?sitemap=taxonomies&sitemap-subtype=category&paged=2', '/wp-sitemap-taxonomies-category-2.xml' ),

			// Pretty versions don't redirect incorrectly.
			array( '/wp-sitemap-posts-post-1.xml', '/wp-sitemap-posts-post-1.xml' ),
			array( '/wp-sitemap-posts-post-2.xml', '/wp-sitemap-posts-post-2.xml' ),
			array( '/wp-sitemap-taxonomies-category-1.xml', '/wp-sitemap-taxonomies-category-1.xml' ),
			array( '/wp-sitemap-taxonomies-category-2.xml', '/wp-sitemap-taxonomies-category-2.xml' ),
		);
	}
}
