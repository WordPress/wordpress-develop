<?php

/**
 * @group sitemaps
 */
class Test_WP_Sitemaps_Renderer extends WP_Test_XML_TestCase {
	public function test_get_sitemap_stylesheet_url() {
		$sitemap_renderer = new WP_Sitemaps_Renderer();
		$stylesheet_url   = $sitemap_renderer->get_sitemap_stylesheet_url();

		$this->assertStringEndsWith( '/?sitemap-stylesheet=sitemap', $stylesheet_url );
	}

	public function test_get_sitemap_stylesheet_url_pretty_permalinks() {
		// Set permalinks for testing.
		$this->set_permalink_structure( '/%year%/%postname%/' );

		$sitemap_renderer = new WP_Sitemaps_Renderer();
		$stylesheet_url   = $sitemap_renderer->get_sitemap_stylesheet_url();

		// Clean up permalinks.
		$this->set_permalink_structure();

		$this->assertStringEndsWith( '/wp-sitemap.xsl', $stylesheet_url );
	}

	public function test_get_sitemap_index_stylesheet_url() {
		$sitemap_renderer = new WP_Sitemaps_Renderer();
		$stylesheet_url   = $sitemap_renderer->get_sitemap_index_stylesheet_url();

		$this->assertStringEndsWith( '/?sitemap-stylesheet=index', $stylesheet_url );
	}

	public function test_get_sitemap_index_stylesheet_url_pretty_permalinks() {
		// Set permalinks for testing.
		$this->set_permalink_structure( '/%year%/%postname%/' );

		$sitemap_renderer = new WP_Sitemaps_Renderer();
		$stylesheet_url   = $sitemap_renderer->get_sitemap_index_stylesheet_url();

		// Clean up permalinks.
		$this->set_permalink_structure();

		$this->assertStringEndsWith( '/wp-sitemap-index.xsl', $stylesheet_url );
	}

	/**
	 * Test XML output for the sitemap index renderer.
	 */
	public function test_get_sitemap_index_xml() {
		$entries = array(
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-post-1.xml',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-page-1.xml',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-taxonomies-category-1.xml',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-taxonomies-post_tag-1.xml',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-users-1.xml',
			),
		);

		$renderer = new WP_Sitemaps_Renderer();

		$actual   = $renderer->get_sitemap_index_xml( $entries );
		$expected = '<?xml version="1.0" encoding="UTF-8"?>' .
					'<?xml-stylesheet type="text/xsl" href="http://' . WP_TESTS_DOMAIN . '/?sitemap-stylesheet=index" ?>' .
					'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' .
					'<sitemap><loc>http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-post-1.xml</loc></sitemap>' .
					'<sitemap><loc>http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-page-1.xml</loc></sitemap>' .
					'<sitemap><loc>http://' . WP_TESTS_DOMAIN . '/wp-sitemap-taxonomies-category-1.xml</loc></sitemap>' .
					'<sitemap><loc>http://' . WP_TESTS_DOMAIN . '/wp-sitemap-taxonomies-post_tag-1.xml</loc></sitemap>' .
					'<sitemap><loc>http://' . WP_TESTS_DOMAIN . '/wp-sitemap-users-1.xml</loc></sitemap>' .
					'</sitemapindex>';

		$this->assertXMLEquals( $expected, $actual, 'Sitemap index markup incorrect.' );
	}

	/**
	 * Test XML output for the sitemap index renderer with lastmod attributes.
	 */
	public function test_get_sitemap_index_xml_with_lastmod() {
		$entries = array(
			array(
				'loc'     => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-post-1.xml',
				'lastmod' => '2005-01-01',
			),
			array(
				'loc'     => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-page-1.xml',
				'lastmod' => '2005-01-01',
			),
			array(
				'loc'     => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-taxonomies-category-1.xml',
				'lastmod' => '2005-01-01',
			),
			array(
				'loc'     => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-taxonomies-post_tag-1.xml',
				'lastmod' => '2005-01-01',
			),
			array(
				'loc'     => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-users-1.xml',
				'lastmod' => '2005-01-01',
			),
		);

		$renderer = new WP_Sitemaps_Renderer();

		$actual   = $renderer->get_sitemap_index_xml( $entries );
		$expected = '<?xml version="1.0" encoding="UTF-8"?>' .
			'<?xml-stylesheet type="text/xsl" href="http://' . WP_TESTS_DOMAIN . '/?sitemap-stylesheet=index" ?>' .
			'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' .
			'<sitemap><loc>http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-post-1.xml</loc><lastmod>2005-01-01</lastmod></sitemap>' .
			'<sitemap><loc>http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-page-1.xml</loc><lastmod>2005-01-01</lastmod></sitemap>' .
			'<sitemap><loc>http://' . WP_TESTS_DOMAIN . '/wp-sitemap-taxonomies-category-1.xml</loc><lastmod>2005-01-01</lastmod></sitemap>' .
			'<sitemap><loc>http://' . WP_TESTS_DOMAIN . '/wp-sitemap-taxonomies-post_tag-1.xml</loc><lastmod>2005-01-01</lastmod></sitemap>' .
			'<sitemap><loc>http://' . WP_TESTS_DOMAIN . '/wp-sitemap-users-1.xml</loc><lastmod>2005-01-01</lastmod></sitemap>' .
			'</sitemapindex>';

		$this->assertXMLEquals( $expected, $actual, 'Sitemap index markup incorrect.' );
	}

	/**
	 * Test that all children of Q{http://www.sitemaps.org/schemas/sitemap/0.9}sitemap in the
	 * rendered index XML are defined in the Sitemaps spec (i.e., loc, lastmod).
	 *
	 * Note that when a means of adding elements in extension namespaces is settled on,
	 * this test will need to be updated accordingly.
	 *
	 * @expectedIncorrectUsage WP_Sitemaps_Renderer::get_sitemap_index_xml
	 */
	public function test_get_sitemap_index_xml_extra_elements() {
		$url_list = array(
			array(
				'loc'     => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-post-1.xml',
				'unknown' => 'this is a test',
			),
			array(
				'loc'     => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-page-1.xml',
				'unknown' => 'that was a test',
			),
		);

		$renderer = new WP_Sitemaps_Renderer();

		$xml_dom = $this->loadXML( $renderer->get_sitemap_index_xml( $url_list ) );
		$xpath   = new DOMXPath( $xml_dom );
		$xpath->registerNamespace( 'sitemap', 'http://www.sitemaps.org/schemas/sitemap/0.9' );

		$this->assertEquals(
			0,
			$xpath->evaluate( "count( /sitemap:sitemapindex/sitemap:sitemap/*[  namespace-uri() != 'http://www.sitemaps.org/schemas/sitemap/0.9' or not( local-name() = 'loc' or local-name() = 'lastmod' ) ] )" ),
			'Invalid child of "sitemap:sitemap" in rendered index XML.'
		);
	}

	/**
	 * Test XML output for the sitemap index renderer when stylesheet is disabled.
	 */
	public function test_get_sitemap_index_xml_without_stylesheet() {
		$entries = array(
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-post-1.xml',
			),
		);

		add_filter( 'wp_sitemaps_stylesheet_index_url', '__return_false' );

		$renderer = new WP_Sitemaps_Renderer();

		$xml_dom = $this->loadXML( $renderer->get_sitemap_index_xml( $entries ) );
		$xpath   = new DOMXPath( $xml_dom );

		$this->assertSame(
			0,
			$xpath->query( '//processing-instruction( "xml-stylesheet" )' )->length,
			'Sitemap index incorrectly contains the xml-stylesheet processing instruction.'
		);
	}

	/**
	 * Test XML output for the sitemap page renderer.
	 */
	public function test_get_sitemap_xml() {
		$url_list = array(
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/2019/10/post-1',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/2019/10/post-2',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/2019/10/post-3',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/2019/10/post-4',
			),
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/2019/10/post-5',
			),
		);

		$renderer = new WP_Sitemaps_Renderer();

		$actual   = $renderer->get_sitemap_xml( $url_list );
		$expected = '<?xml version="1.0" encoding="UTF-8"?>' .
					'<?xml-stylesheet type="text/xsl" href="http://' . WP_TESTS_DOMAIN . '/?sitemap-stylesheet=sitemap" ?>' .
					'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' .
					'<url><loc>http://' . WP_TESTS_DOMAIN . '/2019/10/post-1</loc></url>' .
					'<url><loc>http://' . WP_TESTS_DOMAIN . '/2019/10/post-2</loc></url>' .
					'<url><loc>http://' . WP_TESTS_DOMAIN . '/2019/10/post-3</loc></url>' .
					'<url><loc>http://' . WP_TESTS_DOMAIN . '/2019/10/post-4</loc></url>' .
					'<url><loc>http://' . WP_TESTS_DOMAIN . '/2019/10/post-5</loc></url>' .
					'</urlset>';

		$this->assertXMLEquals( $expected, $actual, 'Sitemap page markup incorrect.' );
	}

	/**
	 * Test XML output for the sitemap page renderer when stylesheet is disabled.
	 */
	public function test_get_sitemap_xml_without_stylesheet() {
		$url_list = array(
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/2019/10/post-1',
			),
		);

		add_filter( 'wp_sitemaps_stylesheet_url', '__return_false' );

		$renderer = new WP_Sitemaps_Renderer();

		$xml_dom = $this->loadXML( $renderer->get_sitemap_xml( $url_list ) );
		$xpath   = new DOMXPath( $xml_dom );

		$this->assertSame(
			0,
			$xpath->query( '//processing-instruction( "xml-stylesheet" )' )->length,
			'Sitemap incorrectly contains the xml-stylesheet processing instruction.'
		);
	}

	/**
	 * Test that all children of Q{http://www.sitemaps.org/schemas/sitemap/0.9}url in the
	 * rendered sitemap XML are defined in the Sitemaps spec (i.e., loc, lastmod, changefreq, priority).
	 *
	 * Note that when a means of adding elements in extension namespaces is settled on,
	 * this test will need to be updated accordingly.
	 *
	 * @expectedIncorrectUsage WP_Sitemaps_Renderer::get_sitemap_xml
	 */
	public function test_get_sitemap_xml_extra_elements() {
		$url_list = array(
			array(
				'loc'    => 'http://' . WP_TESTS_DOMAIN . '/2019/10/post-1',
				'string' => 'value',
				'number' => 200,
			),
			array(
				'loc'    => 'http://' . WP_TESTS_DOMAIN . '/2019/10/post-2',
				'string' => 'another value',
				'number' => 300,
			),
		);

		$renderer = new WP_Sitemaps_Renderer();

		$xml_dom = $this->loadXML( $renderer->get_sitemap_xml( $url_list ) );
		$xpath   = new DOMXPath( $xml_dom );
		$xpath->registerNamespace( 'sitemap', 'http://www.sitemaps.org/schemas/sitemap/0.9' );

		$this->assertEquals(
			0,
			$xpath->evaluate( "count( /sitemap:urlset/sitemap:url/*[  namespace-uri() != 'http://www.sitemaps.org/schemas/sitemap/0.9' or not( local-name() = 'loc' or local-name() = 'lastmod' or local-name() = 'changefreq' or local-name() = 'priority' ) ] )" ),
			'Invalid child of "sitemap:url" in rendered XML.'
		);
	}
}
