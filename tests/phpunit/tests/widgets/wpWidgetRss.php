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
	 * Ensures that processing a feed returns when its channel link is not an HTTP URL.
	 *
	 * @ticket 62341
	 * @covers ::wp_widget_rss_process
	 * @dataProvider data_wp_widget_rss_process_channel_link
	 *
	 * @param string $channel_link Markup of the channel link in the feed.
	 * @param string $expected     Expected link.
	 */
	public function test_wp_widget_rss_process_channel_link( $channel_link, $expected ) {
		add_filter(
			'pre_http_request',
			static function () use ( $channel_link ) {
				return array(
					'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary(
						array( 'Content-Type' => 'application/rss+xml; charset=UTF-8' )
					),
					'body'     => '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Feed</title>' . $channel_link . '</channel></rss>',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			}
		);

		$result = wp_widget_rss_process(
			array(
				'url'   => 'https://example.org/feed/',
				'items' => 5,
			)
		);

		$this->assertSame( $expected, $result['link'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_wp_widget_rss_process_channel_link() {
		return array(
			'HTTPS URL'       => array(
				'channel_link' => '<link>https://example.com/</link>',
				'expected'     => 'https://example.com/',
			),
			'FTP URL'         => array(
				'channel_link' => '<link>ftp://example.com/</link>',
				'expected'     => '',
			),
			'no link element' => array(
				'channel_link' => '',
				'expected'     => '',
			),
		);
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
