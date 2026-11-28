<?php

/**
 * Tests for the wp_original_referer_field() function.
 *
 * @group functions
 *
 * @covers ::wp_original_referer_field
 */
class Tests_Functions_WpOriginalRefererField extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		add_filter( 'home_url', array( $this, 'filter_home_url' ) );
	}

	public function tear_down() {
		unset( $_REQUEST['_wp_original_http_referer'], $_REQUEST['_wp_http_referer'], $_SERVER['HTTP_REFERER'], $_SERVER['REQUEST_URI'] );

		parent::tear_down();
	}

	public function filter_home_url(): string {
		return 'http://example.com';
	}

	/**
	 * Tests that wp_original_referer_field() echoes the hidden input field when display is true.
	 *
	 * @ticket 55729
	 */
	public function test_wp_original_referer_field(): void {
		$_REQUEST['_wp_original_http_referer'] = 'http://example.com/test/';

		$expected = '<input type="hidden" name="_wp_original_http_referer" value="http://example.com/test/" />';
		$this->expectOutputString( $expected );

		wp_original_referer_field();
	}

	/**
	 * Tests that wp_original_referer_field() returns the hidden input field when display is false.
	 *
	 * @ticket 55729
	 */
	public function test_wp_original_referer_field_return(): void {
		$_REQUEST['_wp_original_http_referer'] = 'http://example.com/test/';

		$expected = '<input type="hidden" name="_wp_original_http_referer" value="http://example.com/test/" />';

		$this->assertSame( $expected, wp_original_referer_field( false ) );
	}

	/**
	 * Tests that wp_original_referer_field() falls back to the previous referer when original referer is unset.
	 *
	 * @ticket 55729
	 */
	public function test_wp_original_referer_field_return_previous(): void {
		$_SERVER['HTTP_REFERER'] = 'http://example.com/previous/';
		$_SERVER['REQUEST_URI']  = '/current/';

		$expected = '<input type="hidden" name="_wp_original_http_referer" value="http://example.com/previous/" />';

		$this->assertSame( $expected, wp_original_referer_field( false, 'previous' ) );
	}

	/**
	 * Tests that wp_original_referer_field() falls back to current REQUEST_URI when original referer is unset.
	 *
	 * @ticket 55729
	 */
	public function test_wp_original_referer_field_return_current(): void {
		$_SERVER['REQUEST_URI'] = '/current/';

		$expected = '<input type="hidden" name="_wp_original_http_referer" value="/current/" />';

		$this->assertSame( $expected, wp_original_referer_field( false, 'current' ) );
	}
}
