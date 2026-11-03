<?php

/**
 * Tests for the wp_get_original_referer() function.
 *
 * @group functions
 *
 * @covers ::wp_get_original_referer
 */
class Tests_Functions_WpOriginalReferer extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		add_filter( 'home_url', array( $this, 'filter_home_url' ) );
	}

	public function tear_down() {
		unset( $_REQUEST['_wp_original_http_referer'] );

		parent::tear_down();
	}

	public function filter_home_url(): string {
		return 'http://example.com';
	}

	/**
	 * Tests that wp_get_original_referer() returns false when $_REQUEST['_wp_original_http_referer'] is not set.
	 *
	 * @ticket 55729
	 */
	public function test_wp_get_original_referer_not_set(): void {
		$this->assertFalse( wp_get_original_referer() );
	}

	/**
	 * Tests that wp_get_original_referer() returns the original referer when set.
	 *
	 * @ticket 55729
	 */
	public function test_wp_get_original_referer(): void {
		$_REQUEST['_wp_original_http_referer'] = 'http://example.com/original';

		$this->assertSame( 'http://example.com/original', wp_get_original_referer() );
	}

	/**
	 * Tests that wp_get_original_referer() rejects an external host.
	 *
	 * @ticket 55729
	 */
	public function test_wp_get_original_referer_rejects_external_host(): void {
		$_REQUEST['_wp_original_http_referer'] = 'http://external.com/original';

		$this->assertFalse( wp_get_original_referer() );
	}
}
