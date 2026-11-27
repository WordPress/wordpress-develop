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
	public static function data_sitemap_requests(): array {
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
	 * A legacy sitemap stylesheet query var must not bypass 404 handling.
	 *
	 * @ticket 65945
	 */
	public function test_legacy_sitemap_stylesheet_query_var_does_not_bypass_404() {
		global $wp;

		$public_query_vars = $wp->public_query_vars;
		$wp->add_query_var( 'sitemap-stylesheet' );

		$this->go_to( home_url( '/this-page-does-not-exist/?sitemap-stylesheet=sitemap' ) );
		$wp->public_query_vars = $public_query_vars;

		$this->assertTrue( is_404(), 'A legacy sitemap stylesheet query var should not bypass 404 handling.' );
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

	/**
	 * A feed for an object that does not exist should 404.
	 *
	 * @ticket 56883
	 *
	 * @group feed
	 *
	 * @dataProvider data_feeds_for_missing_objects
	 *
	 * @param non-falsy-string $url       Feed URL to request.
	 * @param non-falsy-string $query_var Query var the URL should set.
	 */
	public function test_feed_for_missing_object_should_404( string $url, string $query_var ) {
		global $wp;

		create_initial_taxonomies();
		flush_rewrite_rules();

		$this->go_to( home_url( $url ) );

		$this->assertArrayHasKey( $query_var, $wp->query_vars, "The request should set the '{$query_var}' query var." );
		$this->assertTrue( is_feed(), 'The request should still be recognized as a feed.' );
		$this->assertTrue( is_404(), 'A feed for an object that does not exist should be a 404.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ non-falsy-string, non-falsy-string }>
	 */
	public static function data_feeds_for_missing_objects(): array {
		return array(
			'tag'           => array( '/tag/no-such-tag/feed/', 'tag' ),
			'category'      => array( '/category/no-such-category/feed/', 'category_name' ),
			'author'        => array( '/author/no-such-user/feed/', 'author_name' ),
			'post comments' => array( '/no-such-post/feed/', 'name' ),
		);
	}

	/**
	 * An empty feed for an existing object, or for a query without an object, should not 404.
	 *
	 * @ticket 56883
	 *
	 * @group feed
	 *
	 * @dataProvider data_empty_feeds
	 *
	 * @param non-falsy-string $url       Feed URL to request.
	 * @param non-falsy-string $query_var Query var the URL should set.
	 */
	public function test_empty_feed_should_not_404( string $url, string $query_var ) {
		global $wp;

		create_initial_taxonomies();
		flush_rewrite_rules();

		self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => 'empty-tag',
			)
		);
		self::factory()->post->create( array( 'post_name' => 'post-without-comments' ) );
		self::factory()->user->create( array( 'user_nicename' => 'author-without-posts' ) );

		$this->go_to( home_url( $url ) );

		$this->assertTrue( is_feed(), 'The request should be recognized as a feed.' );
		$this->assertArrayHasKey( $query_var, $wp->query_vars, "The request should set the '{$query_var}' query var." );
		$this->assertFalse( is_404(), 'An empty feed should not be a 404.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ non-falsy-string, non-falsy-string }>
	 */
	public static function data_empty_feeds(): array {
		return array(
			'tag without posts'     => array( '/tag/empty-tag/feed/', 'tag' ),
			'author without posts'  => array( '/author/author-without-posts/feed/', 'author_name' ),
			'post without comments' => array( '/post-without-comments/feed/', 'name' ),
			'date archive'          => array( '/2001/01/feed/', 'monthnum' ),
			'search'                => array( '/?s=no-matching-posts&feed=rss2', 's' ),
			'comments'              => array( '/comments/feed/', 'withcomments' ),
		);
	}
}
