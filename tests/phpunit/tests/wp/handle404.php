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
		 * WP_UnitTestCase::set_up() replaces the $wp global and resets $wp_sitemaps,
		 * which drops the sitemap query vars registered on 'init'. Priming the sitemaps
		 * server re-registers them, so that sitemap requests are recognized as such.
		 */
		wp_sitemaps_get_server();
	}

	/**
	 * A sitemap request must not 404 just because the main query found no posts.
	 *
	 * Note that no posts are created for these tests: an empty main query is the
	 * condition being tested.
	 *
	 * @ticket 65945
	 *
	 * @dataProvider data_sitemap_requests
	 *
	 * @param non-falsy-string $url Sitemap URL to request.
	 */
	public function test_sitemap_requests_should_not_404_on_a_site_with_no_posts( string $url ) {
		$this->go_to( home_url( $url ) );

		$this->assertTrue( is_sitemap(), 'The request should be recognized as a sitemap request.' );
		$this->assertFalse( is_404(), 'A sitemap request should not be a 404.' );
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
	 * The sitemap stylesheet routes must not 404 either.
	 *
	 * @ticket 65945
	 *
	 * @dataProvider data_sitemap_stylesheet_requests
	 *
	 * @param non-falsy-string $url Stylesheet URL to request.
	 */
	public function test_sitemap_stylesheet_requests_should_not_404_on_a_site_with_no_posts( string $url ) {
		$this->go_to( home_url( $url ) );

		$this->assertFalse( is_404(), 'A sitemap stylesheet request should not be a 404.' );
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
			/*
			 * A paged stylesheet request is not a real route, but it is the only
			 * stylesheet case that is not already covered by the is_home() exception
			 * in WP::handle_404(), so it guards the sitemap-stylesheet check there.
			 */
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
	 * WP_Sitemaps::render_sitemaps() sends that status itself, which is covered by
	 * Tests_Sitemaps_Sitemaps::test_unregistered_provider_should_return_404().
	 *
	 * @ticket 65945
	 */
	public function test_unregistered_sitemap_provider_should_not_404_in_handle_404() {
		$this->go_to( home_url( '/?sitemap=this-provider-does-not-exist' ) );

		$this->assertTrue( is_sitemap(), 'The request should be recognized as a sitemap request.' );
		$this->assertFalse( is_404(), 'WP::handle_404() should not have set a 404.' );
	}
}
