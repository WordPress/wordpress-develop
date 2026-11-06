<?php

/**
 * Tests for the wp_is_home_url_using_https() function.
 *
 * @group https-detection
 *
 * @covers ::wp_is_home_url_using_https
 */
class Tests_HTTPS_Detection_WpIsHomeUrlUsingHttps extends WP_UnitTestCase {

	/**
	 * Tests that the result depends on the scheme of the home URL.
	 *
	 * @ticket 66506
	 *
	 * @dataProvider data_home_urls
	 *
	 * @param string $home     Home URL.
	 * @param bool   $expected Whether the home URL uses HTTPS.
	 */
	public function test_should_check_the_scheme_of_the_home_url( $home, $expected ) {
		update_option( 'home', $home );

		$this->assertSame( $expected, wp_is_home_url_using_https() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_home_urls() {
		return array(
			'https'                     => array( 'https://example.com', true ),
			'https with a path'         => array( 'https://example.com/blog', true ),
			'https with a port'         => array( 'https://example.com:8443', true ),
			'http'                      => array( 'http://example.com', false ),
			'http with a path'          => array( 'http://example.com/blog', false ),
			'http with https in a path' => array( 'http://example.com/https://', false ),
		);
	}

	/**
	 * Tests that the URL of the site is not used.
	 *
	 * @ticket 66506
	 */
	public function test_should_not_depend_on_the_site_url() {
		update_option( 'home', 'http://example.com' );
		update_option( 'siteurl', 'https://example.com' );

		$this->assertFalse( wp_is_home_url_using_https() );
	}

	/**
	 * Tests that the filters of the home URL are used.
	 *
	 * @ticket 66506
	 */
	public function test_should_use_the_home_url_filter() {
		update_option( 'home', 'http://example.com' );

		add_filter(
			'home_url',
			static function () {
				return 'https://secure.example.com/';
			}
		);

		$this->assertTrue( wp_is_home_url_using_https() );
	}
}
