<?php

/**
 * Test wp_guess_url().
 *
 * @group functions
 *
 * @covers ::wp_guess_url
 */
class Tests_Functions_wpGuessUrl extends WP_UnitTestCase {

	/**
	 * @ticket 36827
	 *
	 * @dataProvider data_wp_guess_url_should_return_site_url
	 *
	 * @param string $url The URL to navigate to, relative to `site_url()`.
	 */
	public function test_wp_guess_url_should_return_site_url( $url ) {
		$siteurl = site_url();
		$this->go_to( site_url( $url ) );
		$this->assertSame( $siteurl, wp_guess_url() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_guess_url_should_return_site_url() {
		return array(
			'no trailing slash'                            => array( 'url' => 'wp-admin' ),
			'trailing slash'                               => array( 'url' => 'wp-admin/' ),
			'trailing slash, query var'                    => array( 'url' => 'wp-admin/?foo=bar' ),
			'file extension, no trailing slash'            => array( 'url' => 'wp-login.php' ),
			'file extension, query var, no trailing slash' => array( 'url' => 'wp-login.php?foo=bar' ),
		);
	}

	/**
	 * Tests that wp_guess_url() does not make an HTTP request to detect HTTPS support.
	 *
	 * @ticket 52388
	 */
	public function test_wp_guess_url_does_not_make_http_request() {
		$request_made = false;
		$filter       = static function () use ( &$request_made ) {
			$request_made = true;
			return new WP_Error();
		};

		add_filter( 'pre_http_request', $filter );
		wp_guess_url();
		remove_filter( 'pre_http_request', $filter );

		$this->assertFalse( $request_made );
	}
}
