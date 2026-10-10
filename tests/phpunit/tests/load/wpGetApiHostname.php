<?php
/**
 * Unit tests for `wp_get_api_hostname()`.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.8.0
 *
 * @group load
 *
 * @covers ::wp_get_api_hostname
 */
class Test_WP_Get_API_Hostname extends WP_UnitTestCase {

	/**
	 * Tests that `wp_get_api_hostname()` returns the default value.
	 *
	 * @ticket 62132
	 */
	public function test_wp_get_api_hostname_returns_default_value() {
		$this->assertSame( 'https://api.wordpress.org', wp_get_api_hostname() );
		$this->assertSame( 'http://api.wordpress.org', wp_get_api_hostname( true ) );
	}

	/**
	 * Tests that `wp_get_api_hostname()` returns the value of the `WP_API_HOSTNAME` environment variable.
	 *
	 * @ticket 62132
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_get_api_hostname_returns_environment_variable_value() {
		putenv( 'WP_API_HOSTNAME=env.api.org' );
		$this->assertSame( 'env.api.org', wp_get_api_hostname() );
	}

	/**
	 * Tests that `wp_get_api_hostname()` returns the value of the `WP_API_HOSTNAME` constant.
	 *
	 * @ticket 62132
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_get_api_hostname_returns_constant_value() {
		define( 'WP_API_HOSTNAME', 'constant.api.org' );
		$this->assertSame( 'constant.api.org', wp_get_api_hostname() );
	}

	/**
	 * Tests that `wp_get_api_hostname()` returns the value of the `'wp_api_hostname'` filter.
	 *
	 * @ticket 62132
	 */
	public function test_wp_get_api_hostname_returns_filter_value() {
		add_filter(
			'wp_api_hostname',
			static function () {
				return 'filter.api.org';
			}
		);
		$this->assertSame( 'filter.api.org', wp_get_api_hostname() );
	}

	/**
	 * Tests that `wp_get_api_hostname()` prioritizes the constant over the environment variable.
	 *
	 * @ticket 62132
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_get_api_hostname_prioritizes_constant_over_environment_variable() {
		putenv( 'WP_API_HOSTNAME=env.api.org' );
		define( 'WP_API_HOSTNAME', 'constant.api.org' );
		$this->assertSame( 'constant.api.org', wp_get_api_hostname() );
	}

	/**
	 * Tests that `wp_get_api_hostname()` prioritizes the filter over the environment variable.
	 *
	 * @ticket 62132
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_get_api_hostname_prioritizes_filter_over_environment_variable() {
		putenv( 'WP_API_HOSTNAME=env.api.org' );
		add_filter(
			'wp_api_hostname',
			static function () {
				return 'filter.api.org';
			}
		);
		$this->assertSame( 'filter.api.org', wp_get_api_hostname() );
	}

	/**
	 * Tests that `wp_get_api_hostname()` prioritizes the filter over the constant.
	 *
	 * @ticket 62132
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_get_api_hostname_prioritizes_filter_over_constant() {
		define( 'WP_API_HOSTNAME', 'constant.api.org' );
		add_filter(
			'wp_api_hostname',
			static function () {
				return 'filter.api.org';
			}
		);
		$this->assertSame( 'filter.api.org', wp_get_api_hostname() );
	}
}
