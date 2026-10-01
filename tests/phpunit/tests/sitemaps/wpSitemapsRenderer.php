<?php

/**
 * @group sitemaps
 */
class Tests_Sitemaps_wpSitemapsRenderer extends WP_Test_XML_TestCase {

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

		$this->assertSame(
			0.0,
			$xpath->evaluate( "count( /sitemap:sitemapindex/sitemap:sitemap/*[  namespace-uri() != 'http://www.sitemaps.org/schemas/sitemap/0.9' or not( local-name() = 'loc' or local-name() = 'lastmod' ) ] )" ),
			'Invalid child of "sitemap:sitemap" in rendered index XML.'
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

		$this->assertSame(
			0.0,
			$xpath->evaluate( "count( /sitemap:urlset/sitemap:url/*[  namespace-uri() != 'http://www.sitemaps.org/schemas/sitemap/0.9' or not( local-name() = 'loc' or local-name() = 'lastmod' or local-name() = 'changefreq' or local-name() = 'priority' ) ] )" ),
			'Invalid child of "sitemap:url" in rendered XML.'
		);
	}

	/**
	 * Test that the sitemap stylesheet URL can be filtered.
	 *
	 * @covers WP_Sitemaps_Renderer::get_sitemap_stylesheet_url
	 */
	public function test_get_sitemap_stylesheet_url_filter() {
		$custom_url = 'https://example.com/custom-sitemap.xsl';

		add_filter(
			'wp_sitemaps_stylesheet_url',
			static function () use ( $custom_url ) {
				return $custom_url;
			}
		);

		$sitemap_renderer = new WP_Sitemaps_Renderer();
		$entries          = array(
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/2019/10/post-1',
			),
		);

		$actual = $sitemap_renderer->get_sitemap_xml( $entries );
		$this->assertStringContainsString( '<?xml-stylesheet type="text/xsl" href="' . $custom_url . '" ?>', $actual );
	}

	/**
	 * Test that the sitemap index stylesheet URL can be filtered.
	 *
	 * @covers WP_Sitemaps_Renderer::get_sitemap_index_stylesheet_url
	 */
	public function test_get_sitemap_index_stylesheet_url_filter() {
		$custom_url = 'https://example.com/custom-sitemap-index.xsl';

		add_filter(
			'wp_sitemaps_stylesheet_index_url',
			static function () use ( $custom_url ) {
				return $custom_url;
			}
		);

		$sitemap_renderer = new WP_Sitemaps_Renderer();
		$entries          = array(
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-post-1.xml',
			),
		);

		$actual = $sitemap_renderer->get_sitemap_index_xml( $entries );
		$this->assertStringContainsString( '<?xml-stylesheet type="text/xsl" href="' . $custom_url . '" ?>', $actual );
	}

	/**
	 * Test XML output for empty sitemap page renderer.
	 *
	 * @covers WP_Sitemaps_Renderer::get_sitemap_xml
	 */
	public function test_get_sitemap_xml_empty() {
		$renderer = new WP_Sitemaps_Renderer();
		$actual   = $renderer->get_sitemap_xml( array() );
		$expected = '<?xml version="1.0" encoding="UTF-8"?>' .
					'<?xml-stylesheet type="text/xsl" href="http://' . WP_TESTS_DOMAIN . '/?sitemap-stylesheet=sitemap" ?>' .
					'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>';

		$this->assertXMLEquals( $expected, $actual, 'Empty sitemap markup incorrect.' );
	}

	/**
	 * Test XML output for empty sitemap index renderer.
	 *
	 * @covers WP_Sitemaps_Renderer::get_sitemap_index_xml
	 */
	public function test_get_sitemap_index_xml_empty() {
		$renderer = new WP_Sitemaps_Renderer();
		$actual   = $renderer->get_sitemap_index_xml( array() );
		$expected = '<?xml version="1.0" encoding="UTF-8"?>' .
					'<?xml-stylesheet type="text/xsl" href="http://' . WP_TESTS_DOMAIN . '/?sitemap-stylesheet=index" ?>' .
					'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>';

		$this->assertXMLEquals( $expected, $actual, 'Empty sitemap index markup incorrect.' );
	}

	/**
	 * Test XML output for the sitemap page renderer with all supported fields.
	 *
	 * @covers WP_Sitemaps_Renderer::get_sitemap_xml
	 */
	public function test_get_sitemap_xml_with_all_supported_fields() {
		$url_list = array(
			array(
				'loc'        => 'http://' . WP_TESTS_DOMAIN . '/2019/10/post-1',
				'lastmod'    => '2020-01-01',
				'changefreq' => 'monthly',
				'priority'   => '0.8',
			),
			array(
				'loc'        => 'http://' . WP_TESTS_DOMAIN . '/2019/10/post-2',
				'lastmod'    => '2020-02-02',
				'changefreq' => 'daily',
				'priority'   => '1.0',
			),
		);

		$renderer = new WP_Sitemaps_Renderer();

		$actual   = $renderer->get_sitemap_xml( $url_list );
		$expected = '<?xml version="1.0" encoding="UTF-8"?>' .
					'<?xml-stylesheet type="text/xsl" href="http://' . WP_TESTS_DOMAIN . '/?sitemap-stylesheet=sitemap" ?>' .
					'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' .
					'<url>' .
					'<loc>http://' . WP_TESTS_DOMAIN . '/2019/10/post-1</loc>' .
					'<lastmod>2020-01-01</lastmod>' .
					'<changefreq>monthly</changefreq>' .
					'<priority>0.8</priority>' .
					'</url>' .
					'<url>' .
					'<loc>http://' . WP_TESTS_DOMAIN . '/2019/10/post-2</loc>' .
					'<lastmod>2020-02-02</lastmod>' .
					'<changefreq>daily</changefreq>' .
					'<priority>1.0</priority>' .
					'</url>' .
					'</urlset>';

		$this->assertXMLEquals( $expected, $actual, 'Sitemap page markup with all supported fields incorrect.' );
	}

	/**
	 * Test XML escaping for sitemap entries.
	 *
	 * @covers WP_Sitemaps_Renderer::get_sitemap_xml
	 */
	public function test_get_sitemap_xml_escaping() {
		$url_list = array(
			array(
				'loc'        => 'http://' . WP_TESTS_DOMAIN . '/?foo=1&bar=2',
				'lastmod'    => '2020-01-01 & "quotes"',
				'changefreq' => 'weekly < monthly',
				'priority'   => '0.5 > 0.3',
			),
		);

		$renderer = new WP_Sitemaps_Renderer();
		$xml_dom  = $this->loadXML( $renderer->get_sitemap_xml( $url_list ) );
		$xpath    = new DOMXPath( $xml_dom );
		$xpath->registerNamespace( 'sitemap', 'http://www.sitemaps.org/schemas/sitemap/0.9' );

		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/?foo=1&bar=2', $xpath->evaluate( 'string(/sitemap:urlset/sitemap:url/sitemap:loc)' ) );
		$this->assertSame( '2020-01-01 & "quotes"', $xpath->evaluate( 'string(/sitemap:urlset/sitemap:url/sitemap:lastmod)' ) );
		$this->assertSame( 'weekly < monthly', $xpath->evaluate( 'string(/sitemap:urlset/sitemap:url/sitemap:changefreq)' ) );
		$this->assertSame( '0.5 > 0.3', $xpath->evaluate( 'string(/sitemap:urlset/sitemap:url/sitemap:priority)' ) );
	}

	/**
	 * Test XML escaping for sitemap index entries.
	 *
	 * @covers WP_Sitemaps_Renderer::get_sitemap_index_xml
	 */
	public function test_get_sitemap_index_xml_escaping() {
		$entries = array(
			array(
				'loc'     => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap.php?foo=1&bar=2',
				'lastmod' => '2020-01-01 & "special"',
			),
		);

		$renderer = new WP_Sitemaps_Renderer();
		$xml_dom  = $this->loadXML( $renderer->get_sitemap_index_xml( $entries ) );
		$xpath    = new DOMXPath( $xml_dom );
		$xpath->registerNamespace( 'sitemap', 'http://www.sitemaps.org/schemas/sitemap/0.9' );

		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap.php?foo=1&bar=2', $xpath->evaluate( 'string(/sitemap:sitemapindex/sitemap:sitemap/sitemap:loc)' ) );
		$this->assertSame( '2020-01-01 & "special"', $xpath->evaluate( 'string(/sitemap:sitemapindex/sitemap:sitemap/sitemap:lastmod)' ) );
	}

	/**
	 * Test render_index() outputs index XML.
	 *
	 * @covers WP_Sitemaps_Renderer::render_index
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_render_index() {
		$entries = array(
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/wp-sitemap-posts-post-1.xml',
			),
		);

		$renderer     = new WP_Sitemaps_Renderer();
		$expected_xml = $renderer->get_sitemap_index_xml( $entries );

		$this->expectOutputString( $expected_xml );
		$renderer->render_index( $entries );
	}

	/**
	 * Test render_sitemap() outputs sitemap XML.
	 *
	 * @covers WP_Sitemaps_Renderer::render_sitemap
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_render_sitemap() {
		$url_list = array(
			array(
				'loc' => 'http://' . WP_TESTS_DOMAIN . '/2019/10/post-1',
			),
		);

		$renderer     = new WP_Sitemaps_Renderer();
		$expected_xml = $renderer->get_sitemap_xml( $url_list );

		$this->expectOutputString( $expected_xml );
		$renderer->render_sitemap( $url_list );
	}
}
