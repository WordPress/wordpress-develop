<?php

/**
 * @group sitemaps
 *
 * @covers WP_Sitemaps_Stylesheet
 */
class Tests_Sitemaps_wpSitemapsStylesheet extends WP_UnitTestCase {

	/**
	 * Tests that the stylesheet methods are deprecated and return an empty string.
	 *
	 * @ticket 65593
	 *
	 * @dataProvider data_deprecated_methods
	 *
	 * @param string $method Deprecated method name.
	 */
	public function test_deprecated_methods( string $method ): void {
		$this->setExpectedDeprecated( "WP_Sitemaps_Stylesheet::$method" );

		$stylesheet = new WP_Sitemaps_Stylesheet();

		$this->assertSame( '', $stylesheet->$method() );
	}

	/**
	 * Data provider for {@see self::test_deprecated_methods()}.
	 *
	 * @return array<non-falsy-string, array{ method: 'get_sitemap_stylesheet'|'get_sitemap_index_stylesheet'|'get_stylesheet_css' }>
	 */
	public function data_deprecated_methods(): array {
		return array(
			'sitemap stylesheet' => array( 'method' => 'get_sitemap_stylesheet' ),
			'index stylesheet'   => array( 'method' => 'get_sitemap_index_stylesheet' ),
			'stylesheet CSS'     => array( 'method' => 'get_stylesheet_css' ),
		);
	}
}
