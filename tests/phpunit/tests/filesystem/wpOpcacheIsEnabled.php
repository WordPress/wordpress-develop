<?php

/**
 * Tests wp_opcache_is_enabled().
 *
 * @group file
 * @group filesystem
 *
 * @covers ::wp_opcache_is_enabled
 */
class Tests_Filesystem_WpOpcacheIsEnabled extends WP_UnitTestCase {

	/**
	 * Ensures the helper is available and returns a boolean.
	 *
	 * @ticket 65395
	 */
	public function test_wp_opcache_is_enabled_returns_bool() {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$this->assertIsBool( wp_opcache_is_enabled() );
	}

	/**
	 * Tests that the helper matches expected detection for the current environment.
	 *
	 * @ticket 65395
	 */
	public function test_wp_opcache_is_enabled_matches_environment() {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$expected = false;

		if ( function_exists( 'opcache_get_status' ) ) {
			$status = @opcache_get_status( false ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Warning emitted when the API is restricted.

			if ( is_array( $status ) && array_key_exists( 'opcache_enabled', $status ) ) {
				$expected = (bool) $status['opcache_enabled'];
			} else {
				$expected = wp_validate_boolean( ini_get( 'opcache.enable' ) );
			}
		}

		$this->assertSame( $expected, wp_opcache_is_enabled() );
	}
}
