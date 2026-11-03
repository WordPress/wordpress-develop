<?php

/**
 * Tests for the wp_get_referer() function.
 *
 * @group functions
 *
 * @covers ::wp_get_referer
 */
class Tests_Functions_WpGetReferer extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		add_filter( 'home_url', array( $this, 'filter_home_url' ) );
	}

	public function tear_down() {
		unset( $_REQUEST['_wp_http_referer'], $_SERVER['HTTP_REFERER'], $_SERVER['REQUEST_URI'] );

		parent::tear_down();
	}

	public function filter_home_url(): string {
		return 'http://example.com';
	}

	/**
	 * Tests that wp_get_referer() returns the referer from $_REQUEST['_wp_http_referer'].
	 *
	 * @ticket 55729
	 */
	public function test_wp_get_referer_from_request(): void {
		$_REQUEST['_wp_http_referer'] = 'http://example.com/other';
		$_SERVER['REQUEST_URI']       = '/current';

		$this->assertSame( 'http://example.com/other', wp_get_referer() );
	}

	/**
	 * Tests that wp_get_referer() returns the referer from $_SERVER['HTTP_REFERER'].
	 *
	 * @ticket 55729
	 */
	public function test_wp_get_referer_from_server(): void {
		$_SERVER['HTTP_REFERER'] = 'http://example.com/other';
		$_SERVER['REQUEST_URI']  = '/current';

		$this->assertSame( 'http://example.com/other', wp_get_referer() );
	}

	/**
	 * Tests that wp_get_referer() prioritizes $_REQUEST['_wp_http_referer'] over $_SERVER['HTTP_REFERER'].
	 *
	 * @ticket 55729
	 */
	public function test_wp_get_referer_prefers_request_over_server(): void {
		$_REQUEST['_wp_http_referer'] = 'http://example.com/from-request';
		$_SERVER['HTTP_REFERER']      = 'http://example.com/from-server';
		$_SERVER['REQUEST_URI']       = '/current';

		$this->assertSame( 'http://example.com/from-request', wp_get_referer() );
	}

	/**
	 * Tests that wp_get_referer() rejects an external host.
	 *
	 * @ticket 55729
	 */
	public function test_wp_get_referer_rejects_external_host(): void {
		$_SERVER['HTTP_REFERER'] = 'http://external.com/other';
		$_SERVER['REQUEST_URI']  = '/current';

		$this->assertFalse( wp_get_referer() );
	}

	/**
	 * Tests that wp_get_referer() rejects a referer matching the current request URI.
	 *
	 * @ticket 55729
	 */
	public function test_wp_get_referer_rejects_same_request_uri(): void {
		$_SERVER['HTTP_REFERER'] = 'http://example.com/current';
		$_SERVER['REQUEST_URI']  = '/current';

		$this->assertFalse( wp_get_referer() );
	}

	/**
	 * Tests that wp_get_referer() returns false when no referer is set.
	 *
	 * @ticket 55729
	 */
	public function test_wp_get_referer_not_set(): void {
		$this->assertFalse( wp_get_referer() );
	}
}
