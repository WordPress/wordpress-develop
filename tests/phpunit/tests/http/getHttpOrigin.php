<?php

/**
 * Tests for get_http_origin().
 *
 * @group http
 *
 * @covers ::get_http_origin
 */
class Tests_HTTP_GetHttpOrigin extends WP_UnitTestCase {

	public function tear_down() {
		unset( $_SERVER['HTTP_ORIGIN'] );
		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_when_origin_header_missing() {
		unset( $_SERVER['HTTP_ORIGIN'] );

		$this->assertSame( '', get_http_origin() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_http_origin_header() {
		$_SERVER['HTTP_ORIGIN'] = 'https://example.com';

		$this->assertSame( 'https://example.com', get_http_origin() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_http_origin_filter() {
		$_SERVER['HTTP_ORIGIN'] = 'https://example.com';

		add_filter(
			'http_origin',
			static function () {
				return 'https://filtered.example';
			}
		);

		$this->assertSame( 'https://filtered.example', get_http_origin() );
	}
}
