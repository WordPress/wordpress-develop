<?php

/**
 * Tests for the wp_referer_field() function.
 *
 * @since 6.0.0
 *
 * @group functions.php
 * @covers ::wp_get_original_referer
 */
class Tests_Functions_wpOriginalReferer extends WP_UnitTestCase {
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
	public function test_wp_get_original_referer_not_set() {

		$this->assertSame( false, wp_get_original_referer(), '_wp_original_http_referer not set' );
	}

	/**
	 * @ticket 55729
	 */
	public function test_wp_get_original_referer() {

		$_REQUEST['_wp_original_http_referer'] = 'http://example.com';
		$this->assertSame( 'http://example.com', wp_get_original_referer(), '_wp_original_http_referer set' );
	}
}
