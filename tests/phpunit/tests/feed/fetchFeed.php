<?php
/**
 * Tests for the fetch_feed() function.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.7.0
 *
 * @group feed
 *
 * @covers ::fetch_feed
 */
class Tests_Feed_FetchFeed extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		add_filter( 'pre_http_request', array( $this, 'mocked_rss_response' ) );
	}

	/**
	 * @ticket 62354
	 */
	public function test_empty_charset_does_not_trigger_fatal_error() {
		add_filter( 'pre_option_blog_charset', '__return_empty_string', 20 );

		$feed = fetch_feed( 'https://wordpress.org/news/feed/' );

		foreach ( $feed->get_items( 0, 1 ) as $item ) {
			$content = $item->get_content();
		}

		$this->assertStringContainsString( '<a href="https://learn.wordpress.org/">Learn WordPress</a> is a learning resource providing workshops, quizzes, courses, lesson plans, and discussion groups so that anyone, from beginners to advanced users, can learn to do more with WordPress.', $content );
	}

	/**
	 * Ensure WP_Error object returned for 404 response.
	 *
	 * @ticket 64136
	 */
	public function test_fetch_feed_returns_error_for_404_response() {
		// Priority 15 to ensure this runs after the mocked_rss_response filter.
		add_filter( 'pre_http_request', array( $this, 'mocked_rss_404_error_response' ), 15 );

		$feed = fetch_feed( 'https://example.org/news/feed/' );

		$this->assertWPError( $feed, 'A WP_Error object is expected for failing requests.' );
		$this->assertSame( 'simplepie-error', $feed->get_error_code() );
	}

	/**
	 * Ensure fetch_feed() returns WP_Error if any feed errors.
	 *
	 * @ticket 64136
	 */
	public function test_fetch_feed_multiple_returns_error_if_any_feed_errors() {
		// Priority 15 to ensure this runs after the mocked_rss_response filter.
		add_filter( 'pre_http_request', array( $this, 'mocked_rss_404_error_response' ), 15 );
		add_filter(
			'pre_http_request',
			/**
			 * Remove the 404 error response after the first call.
			 */
			function ( $response ) {
				remove_filter( 'pre_http_request', array( $this, 'mocked_rss_404_error_response' ), 15 );

				return $response;
			},
			20 // Priority 20 to ensure it runs after the 404 error response.
		);

		$feed = fetch_feed( array( 'https://example.org/news/feed/', 'https://wordpress.org/news/feed/' ) );

		$this->assertWPError( $feed, 'A WP_Error object is expected for any failing requests.' );
		$this->assertSame( 'simplepie-error', $feed->get_error_code() );
		$this->assertCount( 1, $feed->get_error_messages()[0], 'There should be one error message for the failed feed.' );
	}

	/**
	 * Ensure fetch_feed() includes messages for all feeds that error.
	 *
	 * @ticket 64136
	 */
	public function test_fetch_feed_multiple_returns_error_if_all_feeds_error() {
		// Priority 15 to ensure this runs after the mocked_rss_response filter.
		add_filter( 'pre_http_request', array( $this, 'mocked_rss_404_error_response' ), 15 );
		$feed = fetch_feed( array( 'https://example.org/news/feed/', 'https://example.com/news/feed/' ) );

		$this->assertWPError( $feed, 'A WP_Error object is expected for failing requests.' );
		$this->assertSame( 'simplepie-error', $feed->get_error_code() );
		$this->assertCount( 2, $feed->get_error_messages()[0], 'There should be two error messages, one for each failed feed.' );
	}

	/**
	 * Ensure fetch_feed() returns a SimplePie object for an empty URL (string).
	 *
	 * @ticket 64136
	 */
	public function test_fetch_feed_returns_a_simplepie_object_for_unspecified_url_string() {
		$feed = fetch_feed( '' );

		$this->assertInstanceOf( 'SimplePie\\SimplePie', $feed );
	}

	/**
	 * Ensure fetch_feed() returns a SimplePie object for an empty URL (array).
	 *
	 * @ticket 64136
	 */
	public function test_fetch_feed_returns_a_simplepie_object_for_unspecified_url_array() {
		$feed = fetch_feed( array() );

		$this->assertInstanceOf( 'SimplePie\\SimplePie', $feed );
	}

	/**
	 * Ensure fetch_feed() accepts multiple feeds.
	 *
	 * The main purpose of this test is to ensure that the SimplePie deprecation warning
	 * is not thrown when requesting multiple feeds.
	 *
	 * Secondly it confirms that the markup of the first two items match as they will
	 * both be from the same feed URL as the array contains the WordPress News feed twice.
	 *
	 * @ticket 64136
	 */
	public function test_fetch_feed_supports_multiple_feeds() {
		$feed    = fetch_feed( array( 'https://wordpress.org/news/feed/', 'https://wordpress.org/news/feed/atom/' ) );
		$content = array();

		foreach ( $feed->get_items( 0, 2 ) as $item ) {
			$content[] = $item->get_content();
		}

		$this->assertEqualHTML( $content[0], $content[1], null, 'The contents of the first two items should be identical.' );
		$this->assertCount( 20, $feed->get_items(), 'The feed should contain 20 items.' );
	}

	/**
	 * Ensure that fetch_feed() is cached on second and subsequent calls.
	 *
	 * Note: The HTTP request is mocked on the `pre_http_request` filter so
	 * this test doesn't make any HTTP requests so it doesn't need to be
	 * placed in the external-http group.
	 *
	 * @ticket 63717
	 *
	 * @group feed
	 *
	 * @covers ::fetch_feed
	 */
	public function test_fetch_feed_cached() {
		$filter = new MockAction();
		add_filter( 'pre_http_request', array( $filter, 'filter' ) );

		fetch_feed( 'https://wordpress.org/news/feed/' );
		$this->assertSame( 1, $filter->get_call_count(), 'The feed should be fetched on the first call.' );

		fetch_feed( 'https://wordpress.org/news/feed/' );
		$this->assertSame( 1, $filter->get_call_count(), 'The feed should be cached on the second call. For SP 1.8.x upgrades, backport simplepie/simplepie#830 to resolve.' );
	}

	/**
	 * Ensure that fetch_feed uses the global cache on Multisite.
	 *
	 * @ticket 63719
	 *
	 * @group feed
	 * @group ms-required
	 *
	 * @covers ::fetch_feed
	 * @covers WP_Feed_Cache_Transient
	 */
	public function test_fetch_feed_uses_global_cache() {
		$second_blog_id = self::factory()->blog->create();

		$filter = new MockAction();
		add_filter( 'pre_http_request', array( $filter, 'filter' ) );

		fetch_feed( 'https://wordpress.org/news/feed/' );

		switch_to_blog( $second_blog_id );

		fetch_feed( 'https://wordpress.org/news/feed/' );
		$this->assertSame( 1, $filter->get_call_count(), 'The feed cache should be global.' );
	}

	/**
	 * Mock response for `fetch_feed()`.
	 *
	 * This simulates a response from WordPress.org's server for the news feed
	 * to avoid making actual HTTP requests during the tests.
	 *
	 * The method runs on the `pre_http_request` filter, a low level filter
	 * to allow developers to determine whether a request would have been made
	 * under normal circumstances.
	 *
	 * @return array Mocked response data.
	 */
	public function mocked_rss_response() {
		$single_value_headers = array(
			'Content-Type' => 'application/rss+xml; charset=UTF-8',
			'link'         => '<https://wordpress.org/news/wp-json/>; rel="https://api.w.org/"',
		);

		return array(
			'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( $single_value_headers ),
			'body'     => file_get_contents( DIR_TESTDATA . '/feed/wordpress-org-news.xml' ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Mock 404 error response for `fetch_feed()`.
	 *
	 * This simulates a 404 response to test error handling in `fetch_feed()`.
	 *
	 * @return array Mocked 404 error response data.
	 */
	public function mocked_rss_404_error_response() {
		return array(
			'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary(),
			'body'     => '',
			'response' => array(
				'code'    => 404,
				'message' => 'Not Found',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
