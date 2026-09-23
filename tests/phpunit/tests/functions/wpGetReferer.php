<?php

/**
 * Tests for the wp_referer_field() function.
 *
 * @since 6.0.0
 *
 * @group functions.php
 * @covers ::wp_get_referer
 */
class Tests_Functions_wpGetReferer extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		add_filter( 'home_url', array( $this, 'home_url' ) );
	}

	public function home_url() {

		return 'http://example.com/';
	}

	/**
	 * @ticket 55729
	 */
	public function test_wp_get_referer__wp_http_referer_referer() {

		$_REQUEST['_wp_http_referer'] = 'http://example.com';
		$this->assertSame( 'http://example.com', wp_get_referer(), '$_REQUEST["_wp_http_referer"] set but no $_REQUEST["REQUEST_URI"] set' );
	}

	/**
	 * @ticket 55729
	 */
	public function test_wp_get_referer_HTTP_REFERER_referer() {

		$_SERVER['HTTP_REFERER'] = 'http://example.com';
		$this->assertSame( 'http://example.com', wp_get_referer(), '$_REQUEST["HTTP_REFERER"] set but no $_REQUEST["REQUEST_URI"] set' );
	}

	/**
	 * @ticket 55729
	 */
	public function test_wp_get_referer_HTTP_REFERER_referer_set_to_XX() {

		$_SERVER['HTTP_REFERER'] = 'http://xxxx.com';
		$this->assertFalse( wp_get_referer(), '$_REQUEST["HTTP_REFERER"] set but not $_REQUEST["HTTP_REFERER"] set to the current home_url' );
	}
	/**
	 * @ticket 55729
	 */
	public function test_wp_get_referer_both_set_referer() {

		$_REQUEST['_wp_http_referer'] = 'http://example.com';
		$_SERVER['HTTP_REFERER']      = 'http://NotUSED.com';
		$this->assertSame( 'http://example.com', wp_get_referer(), 'Both set should use _wp_http_referer but no $_REQUEST["REQUEST_URI"] set' );
	}
	/**
	 * @ticket 55729
	 */
	public function test_wp_get_referer_no_referer() {

		$this->assertFalse( wp_get_referer(), 'no referer set' );
	}
}
