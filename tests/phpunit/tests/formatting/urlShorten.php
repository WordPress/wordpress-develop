<?php

/**
 * @group formatting
 *
 * @covers ::url_shorten
 */
class Tests_Formatting_UrlShorten extends WP_UnitTestCase {

	/**
	 * @dataProvider data_url_shorten
	 *
	 * @param string $expected Expected shortened URL.
	 * @param string $url      URL to shorten.
	 */
	public function test_url_shorten( $expected, $url ) {
		$this->assertSame( $expected, url_shorten( $url ) );
	}

	public function data_url_shorten() {
		// When shortened, the URL is cut to ( $length - 3 ) characters before '&hellip;' is appended.
		return array(
			'escaped slashes are not stripped' => array(
				'wordpress\.org/about/philosophy',
				'wordpress\.org/about/philosophy',
			),
			'no change needed'                 => array(
				'wordpress.org/about/philosophy',
				'wordpress.org/about/philosophy',
			),
			'http and trailing slash removed'  => array(
				'wordpress.org/about/philosophy',
				'http://wordpress.org/about/philosophy/',
			),
			'http and www removed'             => array(
				'wordpress.org/about/philosophy',
				'http://www.wordpress.org/about/philosophy/',
			),
			'exactly 35 characters kept'       => array(
				'wordpress.org/about/philosophy/#box',
				'http://wordpress.org/about/philosophy/#box',
			),
			'shortened to 32 when over 35'     => array(
				'wordpress.org/about/philosophy/#&hellip;',
				'http://wordpress.org/about/philosophy/#decisions',
			),
		);
	}

	/**
	 * Ensures the optional $length parameter overrides the default of 35.
	 *
	 * @dataProvider data_url_shorten_custom_length
	 *
	 * @param string $expected Expected shortened URL.
	 * @param string $url      URL to shorten.
	 * @param int    $length   Maximum length of the shortened URL.
	 */
	public function test_url_shorten_respects_custom_length( $expected, $url, $length ) {
		$this->assertSame( $expected, url_shorten( $url, $length ) );
	}

	public function data_url_shorten_custom_length() {
		// The URL below is 41 characters long after cleaning.
		$url = 'http://wordpress.org/about/philosophy/#decisions';

		return array(
			'kept when length exceeds the URL' => array(
				'wordpress.org/about/philosophy/#decisions',
				$url,
				100,
			),
			'kept when length equals the URL'  => array(
				'wordpress.org/about/philosophy/#decisions',
				$url,
				41,
			),
			'shortened when length is smaller' => array(
				'wordpress.org/about/philosophy/#decis&hellip;',
				$url,
				40,
			),
		);
	}
}
