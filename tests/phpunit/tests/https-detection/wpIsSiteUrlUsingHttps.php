<?php

/**
 * Tests for the wp_is_site_url_using_https() function.
 *
 * @group https-detection
 *
 * @covers ::wp_is_site_url_using_https
 */
class Tests_HTTPS_Detection_WpIsSiteUrlUsingHttps extends WP_UnitTestCase {

	/**
	 * Tests that the result depends on the scheme of the site URL.
	 *
	 * @ticket 66506
	 *
	 * @dataProvider data_site_urls
	 *
	 * @param string $site_url Site URL.
	 * @param bool   $expected Whether the site URL uses HTTPS.
	 */
	public function test_should_check_the_scheme_of_the_site_url( $site_url, $expected ) {
		update_option( 'siteurl', $site_url );

		$this->assertSame( $expected, wp_is_site_url_using_https() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_site_urls() {
		return array(
			'https'                     => array( 'https://example.com', true ),
			'https with a path'         => array( 'https://example.com/wp', true ),
			'https with a port'         => array( 'https://example.com:8443', true ),
			'http'                      => array( 'http://example.com', false ),
			'http with a path'          => array( 'http://example.com/wp', false ),
			'http with https in a path' => array( 'http://example.com/https://', false ),
		);
	}

	/**
	 * Tests that the URL of the home page is not used.
	 *
	 * @ticket 66506
	 */
	public function test_should_not_depend_on_the_home_url() {
		update_option( 'home', 'https://example.com' );
		update_option( 'siteurl', 'http://example.com' );

		$this->assertFalse( wp_is_site_url_using_https() );
	}

	/**
	 * Tests that the `site_url` filter is used.
	 *
	 * @ticket 66506
	 */
	public function test_should_use_the_site_url_filter() {
		update_option( 'siteurl', 'http://example.com' );

		add_filter(
			'site_url',
			static function () {
				return 'https://secure.example.com';
			}
		);

		$this->assertTrue( wp_is_site_url_using_https(), 'The filter should make the URL use HTTPS.' );

		remove_all_filters( 'site_url' );
		add_filter(
			'site_url',
			static function () {
				return 'http://insecure.example.com';
			}
		);
		update_option( 'siteurl', 'https://example.com' );

		$this->assertFalse( wp_is_site_url_using_https(), 'The filter should make the URL use HTTP.' );
	}

	/**
	 * Tests that the `site_url` filter receives the option of the site URL and no path.
	 *
	 * @ticket 66506
	 */
	public function test_should_pass_the_site_url_option_to_the_filter() {
		update_option( 'siteurl', 'https://example.com/wp' );

		$received = null;
		add_filter(
			'site_url',
			static function ( $url, $path, $scheme, $blog_id ) use ( &$received ) {
				$received = array( $url, $path, $scheme, $blog_id );

				return $url;
			},
			10,
			4
		);

		wp_is_site_url_using_https();

		$this->assertSame( array( 'https://example.com/wp', '', null, null ), $received );
	}
}
