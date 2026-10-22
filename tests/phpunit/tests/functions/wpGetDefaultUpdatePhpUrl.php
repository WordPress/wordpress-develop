<?php

/**
 * Tests for wp_get_default_update_php_url().
 *
 * @group functions
 *
 * @covers ::wp_get_default_update_php_url
 */
class Tests_Functions_WpGetDefaultUpdatePhpUrl extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_default_php_upgrade_information_url() {
		$this->assertSame(
			_x( 'https://wordpress.org/support/update-php/', 'localized PHP upgrade information page' ),
			wp_get_default_update_php_url()
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_non_empty_https_url() {
		$url = wp_get_default_update_php_url();

		$this->assertNotSame( '', $url );
		$this->assertStringStartsWith( 'https://', $url );
	}
}
