<?php
/**
 * Tests for the wpGetDirectUpdateHttpsUrl.php function.
 *
 * @group functions.php
 *
 * @covers ::wp_get_direct_update_https_url
 */#
class Tests_Functions_wpGetDirectUpdateHttpsUrl extends WP_UnitTestCase {

	/**
	 * Check that the function returns an empty string if no environment variable is set and no filter is applied.
	 *
	 * @ticket 59623
	 */
	public function test_wp_get_direct_update_https_url_default() {
		// Test 1: Check that the function returns an empty string if no environment variable is set.
		$result   = wp_get_direct_update_https_url();
		$expected = '';
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Check that the function returns environment variable if set.
	 *
	 * @ticket 59623
	 */
	public function test_wp_get_direct_update_https_url_env() {
		putenv( 'WP_DIRECT_UPDATE_HTTPS_URL=https://example.com' );

		$result   = wp_get_direct_update_https_url();
		$expected = 'https://example.com';
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Check the filter work
	 *
	 * @ticket 59623
	 */
	public function test_wp_get_direct_update_https_url_filter() {
		// Test 1: Check that the function returns an empty string if no environment variable is set.
		add_filter( 'wp_direct_update_https_url', array( $this, 'wp_get_direct_update_https_url_filter' ) );

		$result   = wp_get_direct_update_https_url();
		$expected = 'https://filtered-example.com';
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Filter for test_wp_get_direct_update_https_url_filter()
	 *
	 * @ticket 59623
	 *
	 * @param $url
	 *
	 * @return string
	 */
	public function wp_get_direct_update_https_url_filter() {

		return 'https://filtered-example.com';
	}
}
