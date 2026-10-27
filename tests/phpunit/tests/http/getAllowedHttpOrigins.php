<?php

/**
 * Tests for get_allowed_http_origins().
 *
 * @group http
 *
 * @covers ::get_allowed_http_origins
 */
class Tests_HTTP_GetAllowedHttpOrigins extends WP_UnitTestCase {

	/**
	 * Forces the values returned by admin_url() and home_url().
	 *
	 * @param string $admin_url Value to return from admin_url().
	 * @param string $home_url  Value to return from home_url().
	 */
	private function force_urls( $admin_url, $home_url ) {
		add_filter(
			'admin_url',
			static function () use ( $admin_url ) {
				return $admin_url;
			}
		);
		add_filter(
			'home_url',
			static function () use ( $home_url ) {
				return $home_url;
			}
		);
	}

	/**
	 * An HTTPS site must not trust the insecure variant of its own origin,
	 * otherwise a network attacker on the plaintext origin can read
	 * credentialed cross-origin responses served over HTTPS.
	 *
	 * @ticket 66391
	 */
	public function test_https_site_excludes_insecure_origin() {
		$this->force_urls( 'https://example.org/wp-admin/', 'https://example.org' );

		$origins = get_allowed_http_origins();

		$this->assertContains( 'https://example.org', $origins );
		$this->assertNotContains( 'http://example.org', $origins );
	}

	/**
	 * A site configured for HTTP keeps the insecure origin, and the secure
	 * variant is always allowed.
	 *
	 * @ticket 66391
	 */
	public function test_http_site_allows_both_schemes() {
		$this->force_urls( 'http://example.org/wp-admin/', 'http://example.org' );

		$origins = get_allowed_http_origins();

		$this->assertContains( 'http://example.org', $origins );
		$this->assertContains( 'https://example.org', $origins );
	}

	/**
	 * The admin and home schemes are evaluated independently, so an HTTPS
	 * admin host does not gain an insecure origin when the home host is HTTP.
	 *
	 * @ticket 66391
	 */
	public function test_admin_and_home_schemes_are_independent() {
		$this->force_urls( 'https://admin.example.org/wp-admin/', 'http://home.example.org' );

		$origins = get_allowed_http_origins();

		$this->assertContains( 'https://admin.example.org', $origins );
		$this->assertNotContains( 'http://admin.example.org', $origins );

		$this->assertContains( 'https://home.example.org', $origins );
		$this->assertContains( 'http://home.example.org', $origins );
	}
}
