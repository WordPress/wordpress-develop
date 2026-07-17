<?php

/**
 * Tests the wp_get_update_php_url() function.
 *
 * @group functions
 *
 * @covers ::wp_get_update_php_url
 */
class Tests_Functions_WpGetUpdatePhpUrl extends WP_UnitTestCase {

	/**
	 * Tests that wp_get_update_php_url() returns the default URL.
	 *
	 * @ticket 65653
	 */
	public function test_wp_get_update_php_url_default() {
		$this->assertSame( 'https://wordpress.org/support/update-php/', wp_get_update_php_url() );
	}

	/**
	 * Tests that wp_get_update_php_url() respects the WP_UPDATE_PHP_URL environment variable.
	 *
	 * @ticket 65653
	 */
	public function test_wp_get_update_php_url_env_var() {
		$custom_url = 'https://example.com/update-php/';
		putenv( 'WP_UPDATE_PHP_URL=' . $custom_url );

		$url = wp_get_update_php_url();

		// Clean up.
		putenv( 'WP_UPDATE_PHP_URL' );

		$this->assertSame( $custom_url, $url );
	}

	/**
	 * Tests that wp_get_update_php_url() respects the wp_update_php_url filter.
	 *
	 * @ticket 65653
	 */
	public function test_wp_get_update_php_url_filter() {
		$custom_url = 'https://example.org/custom-php-update/';
		$callback   = static function () use ( $custom_url ) {
			return $custom_url;
		};

		add_filter( 'wp_update_php_url', $callback );

		$url = wp_get_update_php_url();

		// Clean up the filter to prevent bleeding into other tests.
		remove_filter( 'wp_update_php_url', $callback );

		$this->assertSame( $custom_url, $url );
	}

	/**
	 * Tests that wp_get_update_php_url() falls back to the default URL if an empty string is provided.
	 *
	 * @ticket 65653
	 *
	 * @dataProvider data_wp_get_update_php_url_empty_fallbacks
	 *
	 * @param string $empty_value The empty value to test.
	 */
	public function test_wp_get_update_php_url_empty_fallbacks( $empty_value ) {
		add_filter(
			'wp_update_php_url',
			function () use ( $empty_value ) {
				return $empty_value;
			}
		);

		$this->assertSame( 'https://wordpress.org/support/update-php/', wp_get_update_php_url() );
	}

	/**
	 * Data provider for test_wp_get_update_php_url_empty_fallbacks().
	 *
	 * @return array<string, array{
	 *     empty_value: string,
	 * }>
	 */
	public function data_wp_get_update_php_url_empty_fallbacks(): array {
		return array(
			'empty string' => array( '' ),
			'null'         => array( null ),
			'false'        => array( false ),
		);
	}
}
