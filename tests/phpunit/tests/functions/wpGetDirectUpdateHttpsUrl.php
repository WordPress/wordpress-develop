<?php

/**
 * Tests for the wp_get_direct_update_https_url() function.
 *
 * @group functions.php
 * @covers ::wp_get_direct_update_https_url
 */
class Tests_Functions_wpGetDirectUpdateHttpsUrl extends WP_UnitTestCase {

	/**
	 * Test that wp_get_direct_update_https_url() returns the correct URL when WP_DIRECT_UPDATE_HTTPS_URL is set.
	 */
	public function test_direct_update_url_with_environment_variable() {
		putenv( 'WP_DIRECT_UPDATE_HTTPS_URL=https://example.com/update-https' );
		$this->assertEquals( 'https://example.com/update-https', wp_get_direct_update_https_url() );
		putenv( 'WP_DIRECT_UPDATE_HTTPS_URL=' ); // Reset the environment variable
	}

	/**
	 * Test that wp_get_direct_update_https_url() returns an empty string when WP_DIRECT_UPDATE_HTTPS_URL is not set.
	 */
	public function test_empty_direct_update_url_without_environment_variable() {
		putenv( 'WP_DIRECT_UPDATE_HTTPS_URL=' );
		$this->assertEquals( '', wp_get_direct_update_https_url() );
		putenv( 'WP_DIRECT_UPDATE_HTTPS_URL=' ); // Reset the environment variable
	}

	/**
	 * Test that the wp_direct_update_https_url filter can modify the direct update URL.
	 */
	public function test_direct_update_url_filter() {
		putenv( 'WP_DIRECT_UPDATE_HTTPS_URL=https://example.com/update-https' );

		add_filter( 'wp_direct_update_https_url', function ( $url ) {
			return $url . '/modified';
		} );

		$this->assertEquals( 'https://example.com/update-https/modified', wp_get_direct_update_https_url() );

		putenv( 'WP_DIRECT_UPDATE_HTTPS_URL=' ); // Reset the environment variable
		remove_all_filters( 'wp_direct_update_https_url' );
	}
}
