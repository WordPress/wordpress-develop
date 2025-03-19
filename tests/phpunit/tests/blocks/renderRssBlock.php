<?php
/**
 * Tests for core/rss Gutenberg block.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.8.0
 *
 * @group blocks
 */

/**
 * Class for testing the core/rss Gutenberg block.
 *
 * @since 6.8.0
 */
class Tests_Blocks_RssBlock extends WP_UnitTestCase {

	/**
	 * Set up before each test.
	 *
	 * @ticket 62400
	 */
	public function set_up() {
		parent::set_up();

		add_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'return_zero_feed_cache' ) );
		add_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10, 3 );
	}

	/**
	 * Clean up after each test.
	 *
	 * @ticket 62400
	 */
	public function tear_down() {
		remove_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'return_zero_feed_cache' ) );
		remove_filter( 'pre_http_request', array( $this, 'mock_http_request' ) );
		parent::tear_down();
	}

	/**
	 * Set feed cache to zero to prevent caching interfering with tests.
	 *
	 * @ticket 62400
	 *
	 * @return int Zero value.
	 */
	public function return_zero_feed_cache() {
		return 0;
	}

	/**
	 * Mock HTTP request to return test feed data.
	 *
	 * @ticket 62400
	 *
	 * @param bool|array $response The existing response or false.
	 * @param array      $args     The request arguments.
	 * @param string     $url      The request URL.
	 * @return array The mocked response.
	 */
	public function mock_http_request( $response, $args, $url ) {
		if ( 'https://example.com/testrss.xml' !== $url ) {
			return $response;
		}

		$mock_rss = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:media="http://search.yahoo.com/mrss/" xmlns:fn="https://www.publishwithfoundation.com/rss/2.0" xmlns:slash="http://purl.org/rss/1.0/modules/slash/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" version="2.0">
<channel>
<title>Test RSS Feed</title>
<link>https://www.example.com</link>
<atom:link href="https://www.example.com/rss.xml" rel="self" type="application/rss+xml"/>
<description>This is a test RSS feed for unit testing.</description>
<language>en-us</language>
<pubDate>Wed, 19 Mar 2025 00:00:01 -0700</pubDate>
<lastBuildDate>Wed, 19 Mar 2025 03:58:04 -0700</lastBuildDate>
<generator>Test</generator>
<item>
<title>Test Article 1</title>
<link>https://www.example.com/article1</link>
<guid isPermaLink="true">https://www.example.com/article1</guid>
<dc:creator>Test Author</dc:creator>
<description>This is a test article description.</description>
<pubDate>Thu, 10 Mar 2025 04:00:00 -0700</pubDate>
<source url="https://www.example.com">Test Source</source>
</item>
<item>
<title>Test Article 2</title>
<link>https://www.example.com/article2</link>
<guid isPermaLink="true">https://www.example.com/article2</guid>
<dc:creator>Test Author 2</dc:creator>
<description>This is another test article description.</description>
<pubDate>Wed, 18 Mar 2025 10:30:00 -0700</pubDate>
<source url="https://www.example.com">Test Source</source>
</item>
</channel>
</rss>
XML;

		return array(
			'headers'  => array(
				'content-type' => 'application/rss+xml; charset=UTF-8',
			),
			'body'     => $mock_rss,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Sets up the "core/rss" block context for testing.
	 * This is needed to avoid null access in WP_Block_Supports::apply_block_supports().
	 *
	 * @ticket 62400
	 */
	private function setup_block_context() {
		$block = array(
			'blockName' => 'core/rss',
			'attrs'     => array(),
		);

		$wp_block_supports = WP_Block_Supports::get_instance();
		$reflection        = new ReflectionClass( $wp_block_supports );
		$property          = $reflection->getProperty( 'block_to_render' );
		$property->setAccessible( true );
		$property->setValue( $wp_block_supports, $block );
	}

	/**
	 * Test that the date in the RSS feed is correctly rendered in the HTML.
	 *
	 * @ticket 62400
	 *
	 * @covers ::render_block_core_rss
	 */
	public function test_rss_date_rendering() {

		$original_date_format = get_option( 'date_format' );
		$original_gmt_offset  = get_option( 'gmt_offset' );

		update_option( 'date_format', 'F j, Y' );
		// We set to UTC+9 to test timezone conversion.
		update_option( 'gmt_offset', 9 );

		$this->setup_block_context();

		// Mock RSS Attributes.
		$attributes = array(
			'feedURL'        => 'https://example.com/testrss.xml',
			'itemsToShow'    => 2,
			'displayExcerpt' => false,
			'displayAuthor'  => false,
			'displayDate'    => true,
			'blockLayout'    => 'list',
		);

		$rendered_html = render_block_core_rss( $attributes );

		$this->assertStringContainsString( '<time datetime=', $rendered_html, 'No time element found in rendered HTML' );

		$this->assertStringContainsString( 'March 19, 2025', $rendered_html, 'Formatted date not found in rendered HTML' );

		if ( preg_match( '/<time datetime="([^"]*)"/', $rendered_html, $matches ) ) {
			$datetime_attr = $matches[1];
			$this->assertStringContainsString( '2025-03-19', $datetime_attr, 'ISO datetime format missing expected date' );
		} else {
			$this->fail( 'Could not find datetime attribute in time element' );
		}

		update_option( 'date_format', $original_date_format );
		update_option( 'gmt_offset', $original_gmt_offset );
	}
}
