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
		$this->assertEquals( 1, $filter->get_call_count(), 'The feed should be fetched on the first call.' );

		fetch_feed( 'https://wordpress.org/news/feed/' );
		$this->assertEquals( 1, $filter->get_call_count(), 'The feed should be cached on the second call. For SP 1.8.x upgrades, backport simplepie/simplepie#830 to resolve.' );
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
		$this->assertEquals( 1, $filter->get_call_count(), 'The feed cache should be global.' );
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
}
