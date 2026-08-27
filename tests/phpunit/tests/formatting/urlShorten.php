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
	 * @param string $url      URL to shorten.
	 * @param string $expected Expected shortened URL.
	 */
	public function test_url_shorten( $url, $expected ) {
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
				'http://wordpress.org/about/philosophy/',
				'wordpress.org/about/philosophy',
			),
			'http and www removed'             => array(
				'http://www.wordpress.org/about/philosophy/',
				'wordpress.org/about/philosophy',
			),
			'exactly 35 characters kept'       => array(
				'http://wordpress.org/about/philosophy/#box',
				'wordpress.org/about/philosophy/#box',
			),
			'shortened to 32 when over 35'     => array(
				'http://wordpress.org/about/philosophy/#decisions',
				'wordpress.org/about/philosophy/#&hellip;',
			),
		);
	}

	/**
	 * Ensures the optional $length parameter overrides the default of 35.
	 *
	 * @dataProvider data_url_shorten_custom_length
	 *
	 * @param string $url      URL to shorten.
	 * @param int    $length   Maximum length of the shortened URL.
	 * @param string $expected Expected shortened URL.
	 */
	public function test_url_shorten_respects_custom_length( $url, $length, $expected ) {
		$this->assertSame( $expected, url_shorten( $url, $length ) );
	}

	public function data_url_shorten_custom_length() {
		// The URL below is 41 characters long after cleaning.
		$url = 'http://wordpress.org/about/philosophy/#decisions';

		return array(
			'kept when length exceeds the URL' => array(
				$url,
				100,
				'wordpress.org/about/philosophy/#decisions',
			),
			'kept when length equals the URL'  => array(
				$url,
				41,
				'wordpress.org/about/philosophy/#decisions',
			),
			'shortened when length is smaller' => array(
				$url,
				40,
				'wordpress.org/about/philosophy/#decis&hellip;',
			),
		);
	}
}
