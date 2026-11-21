<?php
/**
 * Unit tests covering WP_Widget_RSS functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-rss.php
 *
 * @group widgets
 */
class Tests_Widgets_wpWidgetRss extends WP_UnitTestCase {

	/**
	 * @ticket 53278
	 * @covers WP_Widget_RSS::widget
	 * @dataProvider data_url_unhappy_path
	 *
	 * @param mixed $url When null, unsets 'url' arg, else, sets to given value.
	 */
	public function test_url_unhappy_path( $url ) {
		$widget   = new WP_Widget_RSS();
		$args     = array(
			'before_title'  => '<h2>',
			'after_title'   => "</h2>\n",
			'before_widget' => '<section id="widget_rss-5" class="widget widget_rss">',
			'after_widget'  => "</section>\n",
		);
		$instance = array(
			'title' => 'Foo',
			'url'   => $url,
		);

		if ( is_null( $url ) ) {
			unset( $instance['url'] );
		}

		$this->expectOutputString( '' );

		$widget->widget( $args, $instance );
	}

	public static function data_url_unhappy_path() {
		return array(
			'when unset'         => array(
				'url' => null,
			),
			'when empty string'  => array(
				'url' => '',
			),
			'when boolean false' => array(
				'url' => false,
			),
		);
	}

	/**
	 * @ticket 53278
	 * @covers WP_Widget_RSS::widget
	 * @dataProvider data_url_happy_path
	 *
	 * @param mixed  $url      URL argument.
	 * @param string $expected Expected output.
	 */
	public function test_url_happy_path( $url, $expected ) {
		add_filter( 'pre_http_request', array( $this, 'mocked_rss_response' ) );

		$widget   = new WP_Widget_RSS();
		$args     = array(
			'before_title'  => '<h2>',
			'after_title'   => "</h2>\n",
			'before_widget' => '<section id="widget_rss-5" class="widget widget_rss">',
			'after_widget'  => "</section>\n",
		);
		$instance = array(
			'title' => 'Foo',
			'url'   => $url,
		);

		ob_start();
		$widget->widget( $args, $instance );
		$actual = ob_get_clean();

		$this->assertStringContainsString( $expected, $actual );
	}

	public static function data_url_happy_path() {
		return array(
			'when url is given' => array(
				'url' => 'https://wordpress.org/news/feed/',
				'<section id="widget_rss-5" class="widget widget_rss"><h2><a class="rsswidget rss-widget-feed" href="https://wordpress.org/news/feed/">',
			),
		);
	}

	/**
	 * Tests that feed items missing a title, link, description or author name
	 * are output without PHP deprecation notices.
	 *
	 * @ticket 61179
	 * @covers ::wp_widget_rss_output
	 */
	public function test_wp_widget_rss_output_should_handle_incomplete_feed_items() {
		add_filter( 'pre_http_request', array( $this, 'mocked_incomplete_items_rss_response' ) );

		ob_start();
		wp_widget_rss_output(
			'https://example.org/feed/',
			array(
				'show_author'  => 1,
				'show_date'    => 1,
				'show_summary' => 1,
			)
		);
		$actual = ob_get_clean();

		$expected = '<ul>' .
			'<li>Title only<div class="rssSummary"></div></li>' .
			"<li><a class='rsswidget' href='https://example.org/link-only/'>Untitled</a><div class=\"rssSummary\"></div></li>" .
			'<li>Untitled<div class="rssSummary">Description only.</div></li>' .
			'<li>Author without a name<div class="rssSummary"></div> <cite></cite></li>' .
			'<li>Untitled <span class="rss-date">December 16, 2020</span><div class="rssSummary"></div></li>' .
			"<li><a class='rsswidget' href='https://example.org/complete-item/'>Complete item</a> <span class=\"rss-date\">December 15, 2020</span><div class=\"rssSummary\">Complete item description.</div> <cite>Complete Author</cite></li>" .
			'</ul>';

		$this->assertSame( $expected, $actual );
	}

	public function mocked_incomplete_items_rss_response() {
		$response         = $this->mocked_rss_response();
		$response['body'] = file_get_contents( DIR_TESTDATA . '/feed/rss-incomplete-items.xml' );

		return $response;
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
