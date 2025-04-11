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
	 * Tests that wp_guess_url() returns an HTTPS URL when the site is already using HTTPS.
	 *
	 * @ticket 52388
	 */
	public function test_wp_guess_url_uses_https_when_already_ssl() {
		$_SERVER['HTTPS']       = 'on';
		$_SERVER['HTTP_HOST']   = 'example.org';
		$_SERVER['REQUEST_URI'] = '/wp-admin/install.php';

		$url = wp_guess_url();

		$this->assertStringStartsWith( 'https://example.org', $url );
		unset( $_SERVER['HTTPS'] );
	}

	/**
	 * Tests that wp_guess_url() returns an HTTPS URL when HTTPS is supported.
	 *
	 * @ticket 52388
	 */
	public function test_wp_guess_url_uses_https_when_supported() {
		unset( $_SERVER['HTTPS'] );
		$_SERVER['HTTP_HOST']   = 'example.org';
		$_SERVER['REQUEST_URI'] = '/wp-admin/install.php';

		if ( ! function_exists( 'wp_is_https_supported' ) ) {
			require_once ABSPATH . WPINC . '/https-detection.php';
		}

		// Mock wp_is_https_supported() to return true.
		add_filter(
			'pre_wp_get_https_detection_errors',
			function () {
				return new WP_Error();
			}
		);

		$url = wp_guess_url();

		$this->assertStringStartsWith( 'https://example.org', $url );
		remove_all_filters( 'pre_wp_get_https_detection_errors' );
	}

	/**
	 * Tests that wp_guess_url() returns an HTTP URL when HTTPS is not supported.
	 *
	 * @ticket 52388
	 */
	public function test_wp_guess_url_uses_http_when_https_not_supported() {
		unset( $_SERVER['HTTPS'] );
		$_SERVER['HTTP_HOST']   = 'example.org';
		$_SERVER['REQUEST_URI'] = '/wp-admin/install.php';

		if ( ! function_exists( 'wp_is_https_supported' ) ) {
			require_once ABSPATH . WPINC . '/https-detection.php';
		}

		// Mock wp_is_https_supported() to return false.
		add_filter(
			'pre_wp_get_https_detection_errors',
			function () {
				$error = new WP_Error();
				$error->add( 'https_not_supported', 'HTTPS not supported' );
				return $error;
			}
		);

		$url = wp_guess_url();

		$this->assertStringStartsWith( 'http://example.org', $url );
		remove_all_filters( 'pre_wp_get_https_detection_errors' );
	}
}
