<?php

/**
 * Tests for the wp_is_serving_feed_request() function.
 *
 * @group feed
 *
 * @ticket 65853
 *
 * @covers ::wp_is_serving_feed_request
 */
class Tests_Feed_WpIsServingFeedRequest extends WP_UnitTestCase {

	/**
	 * Backup of the original request URI, restored on tear down.
	 */
	private string $request_uri = '';

	public function set_up() {
		parent::set_up();

		$this->request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

		// Start each test with an unparsed main query, as on a real request during 'init'.
		$GLOBALS['wp_the_query'] = new WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
	}

	public function tear_down() {
		$_SERVER['REQUEST_URI'] = $this->request_uri;
		unset( $_GET['feed'] );

		parent::tear_down();
	}

	/**
	 * Sets a pretty permalink structure, as feed rewrite endpoints require one.
	 */
	private function use_pretty_permalinks(): void {
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );
	}

	/**
	 * Simulates the raw request for a URL: sets the request URI and mirrors the
	 * query string into $_GET, as PHP does on a real request.
	 */
	private function simulate_request( string $request_uri ): void {
		$_SERVER['REQUEST_URI'] = $request_uri;

		$query = wp_parse_url( $request_uri, PHP_URL_QUERY );
		if ( is_string( $query ) && '' !== $query ) {
			parse_str( $query, $_GET );
		}
	}

	/**
	 * Tests that the main query has not been parsed when the tests run their checks.
	 */
	public function test_main_query_is_not_parsed_before_go_to(): void {
		$this->assertFalse( isset( $GLOBALS['wp_query']->query ), 'The main query should not be parsed yet.' );
		$this->assertFalse( is_feed(), 'is_feed() should return false before the main query is parsed.' );
	}

	/**
	 * Tests that an empty feed query var is not treated as a feed request.
	 */
	public function test_should_ignore_empty_feed_query_var(): void {
		$_SERVER['REQUEST_URI'] = '/';
		$_GET['feed']           = '';

		$this->assertFalse( wp_is_serving_feed_request() );
	}

	/**
	 * Tests that feed and non-feed requests are detected with pretty permalinks enabled.
	 *
	 * @dataProvider data_pretty_permalink_request_uris
	 *
	 * @param string $request_uri The raw request URI.
	 * @param bool   $expected    Expected return value.
	 */
	public function test_should_detect_feed_requests_with_pretty_permalinks( string $request_uri, bool $expected ): void {
		$this->use_pretty_permalinks();
		$this->simulate_request( $request_uri );

		$this->assertSame( $expected, wp_is_serving_feed_request() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_pretty_permalink_request_uris(): array {
		return array(
			// Site feeds, all feed types.
			'site default feed'                => array( '/feed/', true ),
			'site feed without trailing slash' => array( '/feed', true ),
			'site rss feed'                    => array( '/feed/rss/', true ),
			'site rss2 feed'                   => array( '/feed/rss2/', true ),
			'site atom feed'                   => array( '/feed/atom/', true ),
			'site rdf feed'                    => array( '/feed/rdf/', true ),
			'root rss2 endpoint'               => array( '/rss2/', true ),
			'root atom endpoint'               => array( '/atom/', true ),

			// Comments feeds.
			'site comments feed'               => array( '/comments/feed/', true ),
			'site comments atom feed'          => array( '/comments/feed/atom/', true ),

			// Archive feeds.
			'category feed'                    => array( '/category/uncategorized/feed/', true ),
			'category rss2 feed'               => array( '/category/uncategorized/feed/rss2/', true ),
			'tag feed'                         => array( '/tag/example/feed/', true ),
			'tag atom feed'                    => array( '/tag/example/feed/atom/', true ),
			'author feed'                      => array( '/author/admin/feed/', true ),
			'year archive feed'                => array( '/2024/feed/', true ),
			'month archive feed'               => array( '/2024/01/feed/', true ),
			'day archive feed'                 => array( '/2024/01/05/feed/', true ),
			'search feed'                      => array( '/search/example/feed/', true ),

			// Single post and page comments feeds.
			'post comments feed'               => array( '/2024/01/hello-world/feed/', true ),
			'post comments atom feed'          => array( '/2024/01/hello-world/feed/atom/', true ),
			'page comments feed'               => array( '/sample-page/feed/', true ),

			// Feed URL variations.
			'paged feed with query string'     => array( '/feed/?paged=2', true ),
			'feed in subdirectory install'     => array( '/blog/feed/', true ),
			'pathinfo permalink feed'          => array( '/index.php/feed/', true ),

			// Non-feed content URLs.
			'home page'                        => array( '/', false ),
			'paged home page'                  => array( '/page/2/', false ),
			'single post'                      => array( '/2024/01/hello-world/', false ),
			'page'                             => array( '/sample-page/', false ),
			'category archive'                 => array( '/category/uncategorized/', false ),
			'paged category archive'           => array( '/category/uncategorized/page/2/', false ),
			'tag archive'                      => array( '/tag/example/', false ),
			'author archive'                   => array( '/author/admin/', false ),
			'year archive'                     => array( '/2024/', false ),
			'month archive'                    => array( '/2024/01/', false ),
			'day archive'                      => array( '/2024/01/05/', false ),
			'search results'                   => array( '/search/example/', false ),
			'post embed'                       => array( '/2024/01/hello-world/embed/', false ),
			'post trackback'                   => array( '/2024/01/hello-world/trackback/', false ),
			'post comment page'                => array( '/2024/01/hello-world/comment-page-2/', false ),
			'plain style post on pretty site'  => array( '/?p=123', false ),

			// Non-feed system URLs.
			'REST API request'                 => array( '/wp-json/wp/v2/posts', false ),
			'sitemap'                          => array( '/wp-sitemap.xml', false ),
			'robots'                           => array( '/robots.txt', false ),
			'favicon'                          => array( '/favicon.ico', false ),
			'login page'                       => array( '/wp-login.php', false ),

			// Near misses that must not be detected as feeds.
			'page slug starting with feed'     => array( '/feedback/', false ),
			'category slug containing feed'    => array( '/category/feed-reviews/', false ),
			'feed base with unknown feed'      => array( '/feed/hello/', false ),
			'feed as non-final path segment'   => array( '/feed/hello-world/comments/', false ),

			/*
			 * Archives whose slug equals a feed type name. Term slugs and author
			 * nicenames are not reserved by wp_unique_post_slug(), so these are real
			 * archive URLs and must not be detected as feeds.
			 */
			'category slug equal to feed type' => array( '/category/atom/', false ),
			'tag slug equal to feed type'      => array( '/tag/rss2/', false ),
			'author slug equal to feed type'   => array( '/author/atom/', false ),
			'nested slug equal to feed type'   => array( '/docs/atom/', false ),

			/*
			 * Working but unadvertised feed URL shorthands are conservative false
			 * negatives: core never generates links to them, and missing them only
			 * means callers keep their pre-query behavior.
			 */
			'search rss2 shorthand'            => array( '/search/example/rss2/', false ),
			'category atom shorthand'          => array( '/category/uncategorized/atom/', false ),

			/*
			 * Known limitation: a term with the literal slug `feed` produces an archive
			 * URL indistinguishable from a default feed URL by path alone. This
			 * documents the accepted false positive.
			 */
			'category slug equal to feed base' => array( '/category/feed/', true ),
		);
	}

	/**
	 * Tests that feed and non-feed requests are detected with plain permalinks.
	 *
	 * With plain permalinks, only the `feed` query string parameter identifies a feed
	 * request. Feed rewrite endpoints do not exist, so a path like `/feed/` would
	 * result in a 404 rather than a feed and must not be matched.
	 *
	 * @dataProvider data_plain_permalink_request_uris
	 *
	 * @param string $request_uri The raw request URI.
	 * @param bool   $expected    Expected return value.
	 */
	public function test_should_detect_feed_requests_with_plain_permalinks( string $request_uri, bool $expected ): void {
		$this->simulate_request( $request_uri );

		$this->assertSame( $expected, wp_is_serving_feed_request() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_plain_permalink_request_uris(): array {
		return array(
			// Site feeds, all feed types.
			'default feed query var'       => array( '/?feed=feed', true ),
			'rss feed query var'           => array( '/?feed=rss', true ),
			'rss2 feed query var'          => array( '/?feed=rss2', true ),
			'atom feed query var'          => array( '/?feed=atom', true ),
			'rdf feed query var'           => array( '/?feed=rdf', true ),
			'feed query var via index.php' => array( '/index.php?feed=rss2', true ),

			// Comments feeds.
			'site comments feed'           => array( '/?feed=comments-rss2', true ),
			'post comments feed'           => array( '/?p=123&feed=rss2', true ),
			'page comments feed'           => array( '/?page_id=2&feed=atom', true ),

			// Archive feeds.
			'category feed'                => array( '/?cat=1&feed=rss2', true ),
			'tag feed'                     => array( '/?tag=example&feed=atom', true ),
			'author feed'                  => array( '/?author=1&feed=rss2', true ),
			'month archive feed'           => array( '/?m=202401&feed=rss2', true ),
			'search feed'                  => array( '/?s=example&feed=rss2', true ),

			// Non-feed content URLs.
			'home page'                    => array( '/', false ),
			'single post'                  => array( '/?p=123', false ),
			'page'                         => array( '/?page_id=2', false ),
			'category archive'             => array( '/?cat=1', false ),
			'tag archive'                  => array( '/?tag=example', false ),
			'author archive'               => array( '/?author=1', false ),
			'month archive'                => array( '/?m=202401', false ),
			'paged home page'              => array( '/?paged=2', false ),
			'post comment page'            => array( '/?p=123&cpage=2', false ),
			'attachment'                   => array( '/?attachment_id=9', false ),
			'search for the word feed'     => array( '/?s=feed', false ),

			// Non-feed system URLs.
			'REST API request'             => array( '/?rest_route=/wp/v2/posts', false ),
			'sitemap'                      => array( '/wp-sitemap.xml', false ),
			'robots'                       => array( '/robots.txt', false ),
			'login page'                   => array( '/wp-login.php', false ),

			// Feed-shaped paths are 404s with plain permalinks, not feeds.
			'feed-shaped path is a 404'    => array( '/feed/', false ),
			'category feed path is a 404'  => array( '/category/uncategorized/feed/', false ),
		);
	}

	/**
	 * Tests that a custom feed registered via add_feed() is detected.
	 */
	public function test_should_detect_custom_feed_registered_via_add_feed(): void {
		global $wp_rewrite;

		$this->use_pretty_permalinks();

		$original_feeds = $wp_rewrite->feeds;

		add_feed( 'json', '__return_empty_string' );

		$this->simulate_request( '/feed/json/' );
		$detected = wp_is_serving_feed_request();

		$wp_rewrite->feeds = $original_feeds;

		$this->assertTrue( $detected );
	}

	/**
	 * Tests that the value does not change once the main query has been parsed.
	 *
	 * Callers consult this function both before the main query is parsed (e.g. during
	 * 'init') and during template rendering, so the answer must be constant for the
	 * duration of a request.
	 *
	 * @dataProvider data_request_lifecycle_urls
	 *
	 * @param string $url      The URL to test, passed to go_to().
	 * @param bool   $expected Expected return value, both before and after parsing.
	 */
	public function test_should_return_same_value_before_and_after_query_is_parsed( string $url, bool $expected ): void {
		$this->use_pretty_permalinks();
		self::factory()->post->create();

		$this->simulate_request( $url );

		$this->assertSame( $expected, wp_is_serving_feed_request(), 'Unexpected value before the main query is parsed.' );

		$this->go_to( $url );

		/*
		 * go_to() clears $_GET for scheme-less URLs rather than populating it from the
		 * query string. On a real request $_GET always mirrors the request URL, so
		 * restore it to model one faithfully.
		 */
		$this->simulate_request( $url );

		$this->assertSame( $expected, is_feed(), 'is_feed() should agree once the main query is parsed.' );
		$this->assertSame( $expected, wp_is_serving_feed_request(), 'The value should not change after the main query is parsed.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_request_lifecycle_urls(): array {
		return array(
			'site feed'      => array( '/feed/', true ),
			'atom feed'      => array( '/feed/atom/', true ),
			'feed query var' => array( '/?feed=rss2', true ),
			'home page'      => array( '/', false ),
		);
	}

	/**
	 * Tests that the result can be overridden with the 'wp_is_serving_feed_request' filter.
	 *
	 * @dataProvider data_filter_overrides
	 *
	 * @param string $request_uri The raw request URI.
	 * @param string $callback    Filter callback to hook.
	 * @param bool   $expected    Expected return value.
	 */
	public function test_should_be_filterable( string $request_uri, string $callback, bool $expected ): void {
		$this->use_pretty_permalinks();
		$this->simulate_request( $request_uri );

		add_filter( 'wp_is_serving_feed_request', $callback );

		$this->assertSame( $expected, wp_is_serving_feed_request() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_filter_overrides(): array {
		return array(
			'forced on for a non-feed URL' => array( '/', '__return_true', true ),
			'forced off for a feed URL'    => array( '/feed/', '__return_false', false ),
		);
	}
}
