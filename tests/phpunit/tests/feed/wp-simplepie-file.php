<?php
/**
 * Unit tests for methods in `WP_SimplePie_File`.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.6.1
 */

/**
 * Tests the `WP_SimplePie_File` class.
 *
 * @group feed
 * @group wp-simplepie-file
 *
 * @since 5.6.1
 */
class Tests_WP_SimplePie_File extends WP_UnitTestCase {
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . '/wp-includes/class-simplepie.php';
		require_once ABSPATH . '/wp-includes/class-wp-simplepie-file.php';
	}

	/**
	 * Test that single- and multiple-value headers are parsed in the way that SimplePie expects.
	 *
	 * @dataProvider data_header_parsing
	 *
	 * @covers WP_SimplePie_File::__construct
	 *
	 * @since 5.6.1
	 *
	 * @ticket 51056
	 */
	public function test_header_parsing( $callback, $header_field, $expected ) {
		add_filter( 'pre_http_request', array( $this, $callback ) );

		$file = new WP_SimplePie_File( 'https://wordpress.org/news/feed/' );

		$this->assertSame( $expected, $file->headers[ $header_field ] );
	}

	/**
	 * Provide test cases for `test_header_parsing()`.
	 *
	 * @return array
	 */
	public function data_header_parsing() {
		return array(
			'single content type header works' => array(
				'mocked_response_single_header_values',
				'content-type',
				'application/rss+xml; charset=UTF-8',
			),

			'single generic header works'      => array(
				'mocked_response_single_header_values',
				'link',
				'<https://wordpress.org/news/wp-json/>; rel="https://api.w.org/"',
			),

			'only the final content-type header should be used' => array(
				'mocked_response_multiple_header_values',
				'content-type',
				'application/rss+xml; charset=UTF-8',
			),

			'multiple generic header values should be merged into a comma separated string' => array(
				'mocked_response_multiple_header_values',
				'link',
				'<https://wordpress.org/news/wp-json/>; rel="https://api.w.org/", <https://wordpress.org/news/wp/v2/categories/3>; rel="alternate"; type="application/json"',
			),
		);
	}

	/**
	 * Mock a feed HTTP response where headers only have one value.
	 */
	public function mocked_response_single_header_values() {
		$single_value_headers = array(
			'content-type' => 'application/rss+xml; charset=UTF-8',
			'link'         => '<https://wordpress.org/news/wp-json/>; rel="https://api.w.org/"',
		);

		return array(
			'headers'  => new Requests_Utility_CaseInsensitiveDictionary( $single_value_headers ),
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
	 * Mock a feed HTTP response where headers have multiple values.
	 */
	public function mocked_response_multiple_header_values() {
		$response = $this->mocked_response_single_header_values();

		$multiple_value_headers = array(
			'content-type' => array(
				'application/rss+xml; charset=ISO-8859-2',
				'application/rss+xml; charset=UTF-8',
			),

			'link'         => array(
				'<https://wordpress.org/news/wp-json/>; rel="https://api.w.org/"',
				'<https://wordpress.org/news/wp/v2/categories/3>; rel="alternate"; type="application/json"',
			),
		);

		$response['headers'] = new Requests_Utility_CaseInsensitiveDictionary( $multiple_value_headers );

		return $response;
	}
}
