<?php
/**
 * Tests for the wpGetDirectUpdateHttpsUrl.php function.
 *
 * @group functions
 *
 * @covers ::wp_get_direct_update_https_url
 */#
class Tests_Functions_wpGetDirectUpdateHttpsUrl extends WP_UnitTestCase {

	/**
	 * Original environment variable value.
	 *
	 * @var string|false
	 */
	private $original_direct_update_https_url;

	/**
	 * Sets up an environment without a direct HTTPS update URL.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_direct_update_https_url = getenv( 'WP_DIRECT_UPDATE_HTTPS_URL' );
		putenv( 'WP_DIRECT_UPDATE_HTTPS_URL' );
	}

	/**
	 * Restores the original environment variable value.
	 */
	public function tear_down() {
		if ( false === $this->original_direct_update_https_url ) {
			putenv( 'WP_DIRECT_UPDATE_HTTPS_URL' );
		} else {
			putenv( 'WP_DIRECT_UPDATE_HTTPS_URL=' . $this->original_direct_update_https_url );
		}

		parent::tear_down();
	}

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
