<?php

/**
 * @group wp
 * @group sitemaps
 *
 * @covers WP::handle_404
 */
class Tests_WP_Handle404 extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );

		/*
		 * Priming the server re-registers the sitemap query vars. tear_down()
		 * replaces the $wp global with a fresh WP instance, which carries only
		 * the built-in public query vars, and nulls $GLOBALS['wp_sitemaps'] so
		 * that this call re-runs WP_Sitemaps::init() and adds them back.
		 */
		wp_sitemaps_get_server();
	}

	/**
	 * A sitemap request must not be turned into a 404 by an empty main query.
	 *
	 * Whether the sitemap exists is decided later by WP_Sitemaps::render_sitemaps(),
	 * so some of these URLs still 404 in a full request, just not from here.
	 *
	 * @ticket 65945
	 *
	 * @dataProvider data_sitemap_requests
	 *
	 * @param non-falsy-string $url Sitemap URL to request.
	 */
	public function test_sitemap_requests_should_not_be_404ed_by_an_empty_main_query( string $url ) {
		$this->go_to( home_url( $url ) );

		$this->assertTrue( is_sitemap(), 'The request should be recognized as a sitemap request.' );
		$this->assertFalse( is_404(), 'WP::handle_404() should not have set a 404.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ non-falsy-string }>
	 */
	public function data_sitemap_requests(): array {
		return array(
			'index'                     => array( '/?sitemap=index' ),
			'posts provider'            => array( '/?sitemap=posts&sitemap-subtype=post' ),
			'posts provider, paged'     => array( '/?sitemap=posts&sitemap-subtype=post&paged=2' ),
			'pages provider, paged'     => array( '/?sitemap=posts&sitemap-subtype=page&paged=2' ),
			'taxonomies provider'       => array( '/?sitemap=taxonomies&sitemap-subtype=category' ),
			'taxonomies provider,paged' => array( '/?sitemap=taxonomies&sitemap-subtype=category&paged=3' ),
			'users provider, paged'     => array( '/?sitemap=users&paged=2' ),
		);
	}

	/**
	 * The sitemap stylesheet routes must not be 404ed either.
	 *
	 * Covered separately because is_sitemap() only reflects the `sitemap` query var.
	 *
	 * @ticket 65945
	 *
	 * @dataProvider data_sitemap_stylesheet_requests
	 *
	 * @param non-falsy-string $url Stylesheet URL to request.
	 */
	public function test_sitemap_stylesheet_requests_should_not_be_404ed_by_an_empty_main_query( string $url ) {
		$this->go_to( home_url( $url ) );

		$this->assertFalse( is_404(), 'WP::handle_404() should not have set a 404.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ non-falsy-string }>
	 */
	public function data_sitemap_stylesheet_requests(): array {
		return array(
			'sitemap stylesheet'        => array( '/?sitemap-stylesheet=sitemap' ),
			'index stylesheet'          => array( '/?sitemap-stylesheet=index' ),
			// Not a real route, but the only stylesheet case is_home() doesn't already cover.
			'sitemap stylesheet, paged' => array( '/?sitemap-stylesheet=sitemap&paged=2' ),
		);
	}

	/**
	 * A genuinely unknown URL must still 404.
	 *
	 * @ticket 65945
	 */
	public function test_non_sitemap_request_should_still_404() {
		$this->go_to( home_url( '/this-page-does-not-exist/' ) );

		$this->assertFalse( is_sitemap(), 'The request should not be a sitemap request.' );
		$this->assertTrue( is_404(), 'An unknown URL should still be a 404.' );
	}

	/**
	 * An unregistered sitemap provider must not be turned into a 404 here.
	 *
	 * render_sitemaps() sends that status itself, covered in Tests_Sitemaps_Sitemaps.
	 *
	 * @ticket 65945
	 */
	public function test_unregistered_sitemap_provider_should_not_404_in_handle_404() {
		$this->go_to( home_url( '/?sitemap=this-provider-does-not-exist' ) );

		$this->assertTrue( is_sitemap(), 'The request should be recognized as a sitemap request.' );
		$this->assertFalse( is_404(), 'WP::handle_404() should not have set a 404.' );
	}
}
