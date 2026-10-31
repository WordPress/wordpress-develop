<?php

/**
 * Tests for is_allowed_http_origin().
 *
 * @group http
 *
 * @covers ::is_allowed_http_origin
 */
class Tests_HTTP_IsAllowedHttpOrigin extends WP_UnitTestCase {

	public function tear_down() {
		unset( $_SERVER['HTTP_ORIGIN'] );
		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_origin_when_allowed() {
		$allowed = get_allowed_http_origins();
		$origin  = $allowed[0];

		$this->assertSame( $origin, is_allowed_http_origin( $origin ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_when_not_allowed() {
		$this->assertSame( '', is_allowed_http_origin( 'https://evil.example' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_for_empty_origin() {
		$allowed                = get_allowed_http_origins();
		$_SERVER['HTTP_ORIGIN'] = $allowed[0];

		// Explicit empty string must not fall back to the request Origin header.
		$this->assertSame( '', is_allowed_http_origin( '' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_uses_get_http_origin_when_origin_not_provided() {
		$allowed                = get_allowed_http_origins();
		$_SERVER['HTTP_ORIGIN'] = $allowed[0];

		$this->assertSame( $allowed[0], is_allowed_http_origin() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_allowed_http_origin_filter() {
		add_filter(
			'allowed_http_origin',
			static function () {
				return 'https://filtered.example';
			},
			10,
			2
		);

		$this->assertSame( 'https://filtered.example', is_allowed_http_origin( 'https://evil.example' ) );
	}
}
