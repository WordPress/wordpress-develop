<?php
/**
 * Tests for the wpGetDirectUpdateHttpsUrl.php function.
 *
 * @group functions.php
 *
 */#
class Tests_Functions_wpGetUpdateHttpsUrl extends WP_UnitTestCase {

	/**
	 * Check that the function returns an empty string if no environment variable is set and no filter is applied.
	 *
	 * @ticket 59623
	 * @covers ::wp_get_update_https_url
	 */
	public function test_wp_get_update_https_url_default() {
		// Test 1: Check that the function returns an empty string if no environment variable is set.
		$result   = wp_get_update_https_url();
		$expected = 'https://wordpress.org/documentation/article/why-should-i-use-https/';
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Check that the function returns environment variable if set.
	 *
	 * @ticket 59637
	 * @covers ::wp_get_update_https_url
	 */
	public function test_wp_get_update_https_url_env() {
		putenv( 'WP_UPDATE_HTTPS_URL=https://example.com' );

		$result   = wp_get_update_https_url();
		$expected = 'https://example.com';
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Check the filter work
	 *
	 * @ticket 59637
	 * @covers ::wp_get_update_https_url
	 */
	public function test_wp_get_update_https_url_filter() {
		// Test 1: Check that the function returns an empty string if no environment variable is set.
		add_filter( 'wp_update_https_url', array( $this, 'wp_get_update_https_url' ) );

		$result   = wp_get_update_https_url();
		$expected = 'https://filtered-example.com';
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Filter for test_wp_get_direct_update_https_url_filter()
	 *
	 * @ticket 59637
	 *
	 * @param $url
	 *
	 * @return string
	 */
	public function wp_get_update_https_url() {

		return 'https://filtered-example.com';
	}

	/**
	 * Filter for wp_get_default_update_https_url()
	 *
	 * @ticket 59637
	 * @covers ::wp_get_default_update_https_url
	 *
	 */
	public function test_wp_get_default_update_https_url() {

		$result   = wp_get_default_update_https_url();
		$expected = 'https://wordpress.org/documentation/article/why-should-i-use-https/';
		$this->assertEquals( $expected, $result );
	}
}
