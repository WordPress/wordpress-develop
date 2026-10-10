<?php

/**
 * Tests for the wp_referer_field() function.
 *
 * @since 6.0.0
 *
 * @group functions.php
 * @covers ::wp_get_raw_referer
 */
class Tests_Functions_wpGetRawReferer extends WP_UnitTestCase {

	/**
	 * @ticket 55729
	 */
	public function test_wp_get_original__wp_http_referer_referer() {
		$_REQUEST['_wp_http_referer'] = 'http://_wp_http_referer.com';
		$this->assertSame( 'http://_wp_http_referer.com', wp_get_raw_referer(), '$_REQUEST["_wp_http_referer"] set' );
	}

	/**
	 * @ticket 55729
	 */
	public function test_wp_get_original_HTTP_REFERER_referer() {

		$_SERVER['HTTP_REFERER'] = 'http://referer.com';
		$this->assertSame( 'http://referer.com', wp_get_raw_referer(), '$_REQUEST["HTTP_REFERER"] set' );
	}

	/**
	 * @ticket 55729
	 */
	public function test_wp_get_original_both_set_referer() {

		$_REQUEST['_wp_http_referer'] = 'http://_wp_http_referer.com';
		$_SERVER['HTTP_REFERER']      = 'http://NotUSED.com';
		$this->assertSame( 'http://_wp_http_referer.com', wp_get_raw_referer(), 'Both set should use _wp_http_referer' );
	}

	/**
	 * @ticket 55729
	 */
	public function test_wp_get_original_no_referer() {

		$this->assertFalse( wp_get_raw_referer(), 'no referer set' );
	}
}
