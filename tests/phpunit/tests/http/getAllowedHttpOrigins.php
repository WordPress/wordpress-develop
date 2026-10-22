<?php

/**
 * Tests for get_allowed_http_origins() and is_allowed_http_origin().
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group http
 *
 * @covers ::get_allowed_http_origins
 */
class Tests_HTTP_GetAllowedHttpOrigins extends WP_UnitTestCase {

	private $original_home;
	private $original_siteurl;

	public function set_up() {
		parent::set_up();
		$this->original_home    = get_option( 'home' );
		$this->original_siteurl = get_option( 'siteurl' );
	}

	public function tear_down() {
		update_option( 'home', $this->original_home );
		update_option( 'siteurl', $this->original_siteurl );
		parent::tear_down();
	}

	/**
	 * Non-standard ports must appear in the allowed origins list so that
	 * CORS requests from the same host on that port are not incorrectly
	 * rejected.
	 *
	 * @ticket 65522
	 */
	public function test_non_standard_port_is_preserved() {
		update_option( 'home', 'http://example.com:8080' );
		update_option( 'siteurl', 'http://example.com:8080' );

		$origins = get_allowed_http_origins();

		$this->assertContains( 'http://example.com:8080', $origins );
		$this->assertContains( 'https://example.com:8080', $origins );
	}

	/**
	 * @ticket 65522
	 */
	public function test_standard_port_url_produces_no_port_suffix() {
		update_option( 'home', 'http://example.com' );
		update_option( 'siteurl', 'http://example.com' );

		$origins = get_allowed_http_origins();

		$this->assertContains( 'http://example.com', $origins );
		$this->assertNotContains( 'http://example.com:80', $origins );
	}

	/**
	 * When home and siteurl differ (e.g. separate admin domain), ports on
	 * each are preserved independently.
	 *
	 * @ticket 65522
	 */
	public function test_different_home_and_admin_ports_are_both_preserved() {
		update_option( 'home', 'http://example.com:8080' );
		update_option( 'siteurl', 'http://admin.example.com:9090' );

		$origins = get_allowed_http_origins();

		$this->assertContains( 'http://example.com:8080', $origins );
		$this->assertContains( 'http://admin.example.com:9090', $origins );
	}

	/**
	 * @ticket 65522
	 * @covers ::is_allowed_http_origin
	 */
	public function test_is_allowed_http_origin_accepts_origin_with_port() {
		update_option( 'home', 'http://example.com:8080' );
		update_option( 'siteurl', 'http://example.com:8080' );

		$this->assertSame( 'http://example.com:8080', is_allowed_http_origin( 'http://example.com:8080' ) );
	}

	/**
	 * @ticket 65522
	 * @covers ::is_allowed_http_origin
	 */
	public function test_is_allowed_http_origin_rejects_origin_without_port_when_site_uses_port() {
		update_option( 'home', 'http://example.com:8080' );
		update_option( 'siteurl', 'http://example.com:8080' );

		$this->assertSame( '', is_allowed_http_origin( 'http://example.com' ) );
	}
}
