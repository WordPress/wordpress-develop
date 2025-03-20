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
