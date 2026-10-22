<?php

/**
 * Tests for wp_get_default_update_https_url().
 *
 * @group functions
 *
 * @covers ::wp_get_default_update_https_url
 */
class Tests_Functions_WpGetDefaultUpdateHttpsUrl extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_default_https_documentation_url() {
		$this->assertSame(
			__( 'https://developer.wordpress.org/advanced-administration/security/https/' ),
			wp_get_default_update_https_url()
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_non_empty_https_url() {
		$url = wp_get_default_update_https_url();

		$this->assertNotSame( '', $url );
		$this->assertStringStartsWith( 'https://', $url );
	}
}
