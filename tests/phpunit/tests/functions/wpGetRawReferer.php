<?php

/**
 * Tests for the wp_get_raw_referer() function.
 *
 * @group functions
 *
 * @covers ::wp_get_raw_referer
 */
class Tests_Functions_WpGetRawReferer extends WP_UnitTestCase {

	public function tear_down() {
		unset( $_REQUEST['_wp_http_referer'], $_SERVER['HTTP_REFERER'] );

		parent::tear_down();
	}

	/**
	 * Tests that wp_get_raw_referer() returns the value of $_REQUEST['_wp_http_referer'].
	 *
	 * @ticket 55729
	 */
	public function test_wp_get_raw_referer_from_request(): void {
		$_REQUEST['_wp_http_referer'] = 'http://example.com/request';

		$this->assertSame( 'http://example.com/request', wp_get_raw_referer() );
	}

	/**
	 * Tests that wp_get_raw_referer() returns the value of $_SERVER['HTTP_REFERER'].
	 *
	 * @ticket 55729
	 */
	public function test_wp_get_raw_referer_from_server(): void {
		$_SERVER['HTTP_REFERER'] = 'http://example.com/server';

		$this->assertSame( 'http://example.com/server', wp_get_raw_referer() );
	}

	/**
	 * Tests that wp_get_raw_referer() prioritizes $_REQUEST['_wp_http_referer'] over $_SERVER['HTTP_REFERER'].
	 *
	 * @ticket 55729
	 */
	public function test_wp_get_raw_referer_prefers_request_over_server(): void {
		$_REQUEST['_wp_http_referer'] = 'http://example.com/request';
		$_SERVER['HTTP_REFERER']      = 'http://example.com/server';

		$this->assertSame( 'http://example.com/request', wp_get_raw_referer() );
	}

	/**
	 * Tests that wp_get_raw_referer() returns false when no referer is set.
	 *
	 * @ticket 55729
	 */
	public function test_wp_get_raw_referer_not_set(): void {
		$this->assertFalse( wp_get_raw_referer() );
	}

	/**
	 * Tests that wp_get_raw_referer() returns false when $_REQUEST['_wp_http_referer'] is not a string.
	 *
	 * @ticket 55729
	 */
	public function test_wp_get_raw_referer_invalid_request_parameter(): void {
		$_REQUEST['_wp_http_referer'] = array( 'invalid' );

		$this->assertFalse( wp_get_raw_referer() );
	}
}
