<?php

/**
 * Tests for the wp_referer_field() function.
 *
 * @since 6.0.0
 *
 * @group functions.php
 * @covers ::wp_referer_field
 */
class Tests_Functions_wpOriginalRefererField extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		add_filter( 'home_url', array( $this, 'home_url' ) );
	}

	public function home_url() {
		return 'http://example.com/';
	}

	/**
	 * @ticket 55578
	 */
	public function test_wp_referer_field() {

		$_REQUEST['_wp_original_http_referer'] = 'http://example.com/test/';
		wp_original_referer_field();
		$this->expectOutputString( '<input type="hidden" name="_wp_original_http_referer" value="http://example.com/test/" />' );
	}

	/**
	 * @ticket 55578
	 */
	public function test_wp_referer_field_return() {

		$_REQUEST['_wp_original_http_referer'] = 'http://example.com/test/';

		$this->assertSame( '<input type="hidden" name="_wp_original_http_referer" value="http://example.com/test/" />', wp_original_referer_field( false ) );
	}

	/**
	 * @ticket 55578
	 */
	public function test_wp_referer_field_return_previous() {

		$_REQUEST['_wp_original_http_referer'] = 'http://example.com/test/';

		$this->assertSame( '<input type="hidden" name="_wp_original_http_referer" value="http://example.com/test/" />', wp_original_referer_field( false, 'previous' ) );
	}
}
