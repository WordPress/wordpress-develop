<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `wp_get_raw_referer()` function.
 *
 * @group functions
 *
 * @covers ::wp_get_raw_referer
 */
class WpGetRawRefererTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		$_SERVER['HTTP_REFERER']      = '';
		$_SERVER['REQUEST_URI']       = '';
		$_REQUEST['_wp_http_referer'] = '';
	}

	public function tear_down() {
		$_SERVER['HTTP_REFERER']      = '';
		$_SERVER['REQUEST_URI']       = '';
		$_REQUEST['_wp_http_referer'] = '';

		parent::tear_down();
	}

	/**
	 * @ticket 27152
	 */
	public function test_raw_referer_empty() {
		$this->assertFalse( wp_get_raw_referer() );
	}

	/**
	 * @ticket 27152
	 */
	public function test_raw_referer() {
		$_SERVER['HTTP_REFERER'] = addslashes( 'http://example.com/foo?bar' );
		$this->assertSame( 'http://example.com/foo?bar', wp_get_raw_referer() );
	}

	/**
	 * @ticket 27152
	 */
	public function test_raw_referer_from_request() {
		$_REQUEST['_wp_http_referer'] = addslashes( 'http://foo.bar/baz' );
		$this->assertSame( 'http://foo.bar/baz', wp_get_raw_referer() );
	}

	/**
	 * @ticket 27152
	 */
	public function test_raw_referer_both() {
		$_SERVER['HTTP_REFERER']      = addslashes( 'http://example.com/foo?bar' );
		$_REQUEST['_wp_http_referer'] = addslashes( 'http://foo.bar/baz' );
		$this->assertSame( 'http://foo.bar/baz', wp_get_raw_referer() );
	}

	/**
	 * @ticket 57670
	 */
	public function test_raw_referer_is_false_on_invalid_request_parameter() {
		$_REQUEST['_wp_http_referer'] = array( 'demo' );
		$this->assertFalse( wp_get_raw_referer() );
	}
}