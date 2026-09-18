<?php

/**
 * Tests for wp_should_load_separate_core_block_assets() and
 * wp_should_load_block_assets_on_demand().
 *
 * Both functions must bail for feed requests — including before the main query is
 * parsed, e.g. during 'init', which is when block styles are registered — while
 * behaving normally for all other front end requests. See wp_is_serving_feed_request().
 *
 * @group dependencies
 * @group scripts
 * @group feed
 *
 * @ticket 65853
 *
 * @covers ::wp_should_load_separate_core_block_assets
 * @covers ::wp_should_load_block_assets_on_demand
 */
class Tests_Dependencies_WpShouldLoadBlockAssets extends WP_UnitTestCase {

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
	 * Simulates the raw request for a URL under the given permalink mode: sets the
	 * permalink structure and request URI, and mirrors the query string into $_GET,
	 * as PHP does on a real request.
	 */
	private function simulate_request( string $permalink_mode, string $request_uri ): void {
		if ( 'pretty' === $permalink_mode ) {
			$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );
		}

		$_SERVER['REQUEST_URI'] = $request_uri;

		$query = wp_parse_url( $request_uri, PHP_URL_QUERY );
		if ( is_string( $query ) && '' !== $query ) {
			parse_str( $query, $_GET );
		}
	}

	/**
	 * Data provider shared by both functions: feed and non-feed URLs in both
	 * permalink modes, with whether the URL is a feed request.
	 *
	 * @return array[]
	 */
	public function data_urls_for_both_permalink_modes(): array {
		return array(
			// Pretty permalinks, feed URLs.
			'pretty: site feed'              => array( 'pretty', '/feed/', true ),
			'pretty: site atom feed'         => array( 'pretty', '/feed/atom/', true ),
			'pretty: category feed'          => array( 'pretty', '/category/uncategorized/feed/', true ),
			'pretty: comments feed'          => array( 'pretty', '/comments/feed/', true ),
			'pretty: post comments feed'     => array( 'pretty', '/2024/01/hello-world/feed/', true ),

			// Pretty permalinks, non-feed URLs.
			'pretty: home page'              => array( 'pretty', '/', false ),
			'pretty: single post'            => array( 'pretty', '/2024/01/hello-world/', false ),
			'pretty: page'                   => array( 'pretty', '/sample-page/', false ),
			'pretty: category archive'       => array( 'pretty', '/category/uncategorized/', false ),
			'pretty: search results'         => array( 'pretty', '/search/example/', false ),
			'pretty: feed-like page slug'    => array( 'pretty', '/feedback/', false ),

			// Plain permalinks, feed URLs.
			'plain: rss2 feed'               => array( 'plain', '/?feed=rss2', true ),
			'plain: atom feed'               => array( 'plain', '/?feed=atom', true ),
			'plain: category feed'           => array( 'plain', '/?cat=1&feed=rss2', true ),
			'plain: post comments feed'      => array( 'plain', '/?p=123&feed=rss2', true ),

			// Plain permalinks, non-feed URLs.
			'plain: home page'               => array( 'plain', '/', false ),
			'plain: single post'             => array( 'plain', '/?p=123', false ),
			'plain: page'                    => array( 'plain', '/?page_id=2', false ),
			'plain: search for word feed'    => array( 'plain', '/?s=feed', false ),
			'plain: feed-shaped path is 404' => array( 'plain', '/feed/', false ),
		);
	}

	/**
	 * Tests that wp_should_load_separate_core_block_assets() bails for feed requests
	 * and honors its filter for all other requests, before the main query is parsed.
	 *
	 * @dataProvider data_urls_for_both_permalink_modes
	 *
	 * @param string $permalink_mode Either 'pretty' or 'plain'.
	 * @param string $request_uri    The raw request URI.
	 * @param bool   $is_feed_url    Whether the URL is a feed request.
	 */
	public function test_wp_should_load_separate_core_block_assets( string $permalink_mode, string $request_uri, bool $is_feed_url ): void {
		$this->simulate_request( $permalink_mode, $request_uri );

		add_filter( 'should_load_separate_core_block_assets', '__return_true' );

		$this->assertSame(
			! $is_feed_url,
			wp_should_load_separate_core_block_assets(),
			$is_feed_url
				? 'Should return false for a feed request even when enabled via the filter.'
				: 'Should return true for a non-feed request when enabled via the filter.'
		);

		remove_filter( 'should_load_separate_core_block_assets', '__return_true' );
		add_filter( 'should_load_separate_core_block_assets', '__return_false' );

		$this->assertFalse(
			wp_should_load_separate_core_block_assets(),
			'Should return false when disabled via the filter, regardless of the request.'
		);
	}

	/**
	 * Tests that wp_should_load_block_assets_on_demand() bails for feed requests
	 * and honors its filter for all other requests, before the main query is parsed.
	 *
	 * @dataProvider data_urls_for_both_permalink_modes
	 *
	 * @param string $permalink_mode Either 'pretty' or 'plain'.
	 * @param string $request_uri    The raw request URI.
	 * @param bool   $is_feed_url    Whether the URL is a feed request.
	 */
	public function test_wp_should_load_block_assets_on_demand( string $permalink_mode, string $request_uri, bool $is_feed_url ): void {
		$this->simulate_request( $permalink_mode, $request_uri );

		add_filter( 'should_load_block_assets_on_demand', '__return_true' );

		$this->assertSame(
			! $is_feed_url,
			wp_should_load_block_assets_on_demand(),
			$is_feed_url
				? 'Should return false for a feed request even when enabled via the filter.'
				: 'Should return true for a non-feed request when enabled via the filter.'
		);

		remove_filter( 'should_load_block_assets_on_demand', '__return_true' );
		add_filter( 'should_load_block_assets_on_demand', '__return_false' );

		$this->assertFalse(
			wp_should_load_block_assets_on_demand(),
			'Should return false when disabled via the filter, regardless of the request.'
		);
	}

	/**
	 * Tests that wp_should_load_block_assets_on_demand() still defaults to the value of
	 * wp_should_load_separate_core_block_assets() for non-feed requests.
	 *
	 * @dataProvider data_permalink_modes
	 *
	 * @param string $permalink_mode Either 'pretty' or 'plain'.
	 */
	public function test_on_demand_default_follows_separate_assets( string $permalink_mode ): void {
		$this->simulate_request( $permalink_mode, '/' );

		add_filter( 'should_load_separate_core_block_assets', '__return_true' );

		$this->assertTrue(
			wp_should_load_block_assets_on_demand(),
			'Enabling separate core block assets should enable on-demand loading by default.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_permalink_modes(): array {
		return array(
			'pretty permalinks' => array( 'pretty' ),
			'plain permalinks'  => array( 'plain' ),
		);
	}
}
