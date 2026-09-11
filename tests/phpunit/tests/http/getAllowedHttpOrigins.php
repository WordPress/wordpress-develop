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
	 * Backup of the `home` option.
	 *
	 * @var string
	 */
	private $original_home;

	/**
	 * Backup of the `siteurl` option.
	 *
	 * @var string
	 */
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
	 * A non-default port must be preserved so that an origin served on a custom
	 * port (for example a local or staging install) is matched.
	 *
	 * @ticket 65522
	 */
	public function test_non_default_port_is_preserved() {
		update_option( 'home', 'http://example.com:8080' );
		update_option( 'siteurl', 'http://example.com:8080' );

		$origins = get_allowed_http_origins();

		$this->assertContains( 'http://example.com:8080', $origins );
		$this->assertContains( 'https://example.com:8080', $origins );
		$this->assertNotContains( 'http://example.com', $origins );
	}

	/**
	 * A URL without a port must not gain a port suffix.
	 *
	 * @ticket 65522
	 */
	public function test_url_without_port_has_no_port_suffix() {
		update_option( 'home', 'http://example.com' );
		update_option( 'siteurl', 'http://example.com' );

		$origins = get_allowed_http_origins();

		$this->assertContains( 'http://example.com', $origins );
		$this->assertContains( 'https://example.com', $origins );
		$this->assertNotContains( 'http://example.com:80', $origins );
	}

	/**
	 * The default HTTP and HTTPS ports must be omitted, because browsers leave
	 * them out of the `Origin` request header. An explicit `:80` (or `:443`) in
	 * the site URL must therefore still produce a port-less origin so the check
	 * matches the value the browser actually sends.
	 *
	 * @ticket 65522
	 *
	 * @dataProvider data_default_ports
	 *
	 * @param string $url The site URL with an explicit default port.
	 */
	public function test_default_ports_are_omitted( $url ) {
		update_option( 'home', $url );
		update_option( 'siteurl', $url );

		$origins = get_allowed_http_origins();

		$this->assertContains( 'http://example.com', $origins, 'A port-less HTTP origin should be present.' );
		$this->assertContains( 'https://example.com', $origins, 'A port-less HTTPS origin should be present.' );
		$this->assertNotContains( 'http://example.com:80', $origins, 'The default HTTP port should be omitted.' );
		$this->assertNotContains( 'https://example.com:443', $origins, 'The default HTTPS port should be omitted.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_default_ports() {
		return array(
			'explicit default HTTP port'  => array( 'http://example.com:80' ),
			'explicit default HTTPS port' => array( 'https://example.com:443' ),
		);
	}

	/**
	 * A custom port on the site URL must make a matching origin allowed.
	 *
	 * @ticket 65522
	 *
	 * @covers ::is_allowed_http_origin
	 */
	public function test_is_allowed_http_origin_matches_custom_port() {
		update_option( 'home', 'http://example.com:8080' );
		update_option( 'siteurl', 'http://example.com:8080' );

		$this->assertSame( 'http://example.com:8080', is_allowed_http_origin( 'http://example.com:8080' ) );
		$this->assertSame( '', is_allowed_http_origin( 'http://example.com' ), 'A port-less origin should not match a custom-port site.' );
	}
}
