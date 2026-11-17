<?php

/**
 * Tests for wp_internal_hosts() and wp_is_internal_link().
 *
 * @group link
 *
 * @covers ::wp_internal_hosts
 * @covers ::wp_is_internal_link
 */
class Tests_Link_WpIsInternalLink extends WP_UnitTestCase {

	/**
	 * Tests that the home URL host is treated as internal.
	 *
	 * @ticket 65819
	 */
	public function test_wp_internal_hosts_includes_home_url_host() {
		$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		$this->assertContains( $home_host, wp_internal_hosts() );
	}

	/**
	 * Tests that hosts from the wp_internal_hosts filter are included and lowercased.
	 *
	 * Runs in a separate process so the static cache inside wp_internal_hosts()
	 * is empty when the filter runs.
	 *
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_internal_hosts_filter_adds_hosts() {
		add_filter(
			'wp_internal_hosts',
			static function ( $hosts ) {
				$hosts[] = 'Example.COM';
				$hosts[] = 'cdn.example.com';
				$hosts[] = 'cdn.example.com';
				return $hosts;
			}
		);

		$hosts = wp_internal_hosts();

		$this->assertContains( 'example.com', $hosts );
		$this->assertContains( 'cdn.example.com', $hosts );
		$this->assertSame( 1, count( array_keys( $hosts, 'cdn.example.com', true ) ) );
	}

	/**
	 * Tests that same-site http and https URLs are internal.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_internal_links
	 *
	 * @param string $scheme_and_path URL format with a `%s` placeholder for the home host.
	 */
	public function test_wp_is_internal_link_returns_true_for_internal_urls( $scheme_and_path ) {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$link = sprintf( $scheme_and_path, $host );

		$this->assertTrue( wp_is_internal_link( $link ) );
	}

	/**
	 * Data provider for internal link URLs.
	 *
	 * @return array[]
	 */
	public static function data_internal_links() {
		return array(
			'http'             => array( 'http://%s/sample-page/' ),
			'https'            => array( 'https://%s/sample-page/' ),
			'uppercase scheme' => array( 'HTTPS://%s/' ),
		);
	}

	/**
	 * Tests that host matching is case-insensitive.
	 *
	 * @ticket 65819
	 */
	public function test_wp_is_internal_link_is_case_insensitive_for_host() {
		$host = strtoupper( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		$this->assertTrue( wp_is_internal_link( 'https://' . $host . '/page/' ) );
	}

	/**
	 * Tests that external and non-site URLs are not internal.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_external_links
	 *
	 * @param string $link URL to test.
	 */
	public function test_wp_is_internal_link_returns_false_for_external_urls( $link ) {
		$this->assertFalse( wp_is_internal_link( $link ) );
	}

	/**
	 * Data provider for non-internal URLs.
	 *
	 * @return array[]
	 */
	public static function data_external_links() {
		return array(
			'external https' => array( 'https://example.net/page/' ),
			'external http'  => array( 'http://wordpress.org/' ),
			'mailto'         => array( 'mailto:hello@example.org' ),
			'relative path'  => array( '/relative-path/' ),
			'empty string'   => array( '' ),
		);
	}
}
