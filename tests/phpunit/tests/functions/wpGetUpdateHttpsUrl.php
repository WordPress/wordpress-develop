<?php
/**
 * Tests for the wp_get_update_https_url() function.
 *
 * @group functions
 *
 * @covers ::wp_get_update_https_url
 */
class Tests_Functions_WpGetUpdateHttpsUrl extends WP_UnitTestCase {

	/**
	 * Original environment variable value.
	 *
	 * @var string|false
	 */
	private $original_update_https_url;

	/**
	 * Sets up an environment without an HTTPS update URL.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_update_https_url = getenv( 'WP_UPDATE_HTTPS_URL' );
		putenv( 'WP_UPDATE_HTTPS_URL' );
	}

	/**
	 * Restores the original environment variable value.
	 */
	public function tear_down() {
		if ( false === $this->original_update_https_url ) {
			putenv( 'WP_UPDATE_HTTPS_URL' );
		} else {
			putenv( 'WP_UPDATE_HTTPS_URL=' . $this->original_update_https_url );
		}

		parent::tear_down();
	}

	/**
	 * Tests that the default HTTPS update documentation URL is returned when no override is set.
	 *
	 * @ticket 59637
	 */
	public function test_wp_get_update_https_url_default() {
		$this->assertSame( 'https://developer.wordpress.org/advanced-administration/security/https/', wp_get_update_https_url() );
	}

	/**
	 * Tests that the environment variable override is returned when set.
	 *
	 * @ticket 59637
	 */
	public function test_wp_get_update_https_url_env() {
		putenv( 'WP_UPDATE_HTTPS_URL=https://example.com' );

		$this->assertSame( 'https://example.com', wp_get_update_https_url() );

		putenv( 'WP_UPDATE_HTTPS_URL=' );

		$this->assertSame( wp_get_default_update_https_url(), wp_get_update_https_url() );
	}

	/**
	 * Tests that the URL can be modified via the 'wp_update_https_url' filter.
	 *
	 * @ticket 59637
	 */
	public function test_wp_get_update_https_url_filter() {
		add_filter( 'wp_update_https_url', array( $this, 'filter_wp_update_https_url' ) );

		$this->assertSame( 'https://filtered-example.com', wp_get_update_https_url() );
	}

	/**
	 * Filter callback for the 'wp_update_https_url' hook.
	 *
	 * @return string
	 */
	public function filter_wp_update_https_url() {
		return 'https://filtered-example.com';
	}
}
