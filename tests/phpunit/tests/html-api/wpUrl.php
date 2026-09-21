<?php
/**
 * Unit tests covering WP_URL functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.6.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_URL
 */
class Tests_HtmlApi_WpUrl extends WP_UnitTestCase {
	/**
	 * Ensures that invalid URLs are invalidated.
	 *
	 * @ticket {TICKET_NUMBER}
	 *
	 * @dataProvider data_invalid_urls
	 *
	 * @param string $raw_url Contains something that isn't a valid URL.
	 */
	public function test_invalidates_non_urls( $raw_url ) {
		$this->assertNull(
			WP_URL::parse( $raw_url ),
			"Should have rejected invalid URL: {$raw_url}"
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_invalid_urls() {
		return array(
			'Invalid scheme'        => array( 'sip://123.456.789.0' ),
			'Missing scheme-suffix' => array( 'http:path' ),
			'Broken scheme-suffix'  => array( 'http:/path' ),
			'Non-ASCII hostname'    => array( 'https://going-to-🌕.com' ),
			'Missing port number'   => array( 'http://domain:' ),
			'Too-high port number'  => array( 'http://domain:135481' ),
		);
	}
}
