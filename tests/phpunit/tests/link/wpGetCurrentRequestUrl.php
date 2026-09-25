<?php

/**
 * Tests for the wp_get_current_request_url() function.
 *
 * @group link
 * @group url
 *
 * @covers ::wp_get_current_request_url
 */
class Tests_Link_WpGetCurrentRequestUrl extends WP_UnitTestCase {

	/**
	 * The original value of `$_SERVER['REQUEST_URI']`.
	 *
	 * @var string
	 */
	private $original_request_uri;

	/**
	 * The original value of `$_SERVER['HTTP_HOST']`.
	 *
	 * @var string
	 */
	private $original_http_host;

	/**
	 * The original 'home' option value.
	 *
	 * @var string
	 */
	private $original_home;

	public function set_up() {
		parent::set_up();

		$this->original_request_uri = $_SERVER['REQUEST_URI'];
		$this->original_http_host   = $_SERVER['HTTP_HOST'];
		$this->original_home        = get_option( 'home' );
	}

	public function tear_down() {
		$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		$_SERVER['HTTP_HOST']   = $this->original_http_host;
		update_option( 'home', $this->original_home );
		unset( $_SERVER['HTTPS'] );

		parent::tear_down();
	}

	/**
	 * Tests that the function returns a full URL for a root install.
	 *
	 * @ticket 53998
	 */
	public function test_returns_full_url_for_root_install() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN );
		$_SERVER['REQUEST_URI'] = '/sample-page/?foo=bar';

		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/sample-page/?foo=bar',
			wp_get_current_request_url()
		);
	}

	/**
	 * Tests that the function includes the home path for a subdirectory install
	 * when the REQUEST_URI already contains the subdirectory.
	 *
	 * @ticket 53998
	 */
	public function test_standard_subdirectory_install() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN . '/subdir' );
		$_SERVER['REQUEST_URI'] = '/subdir/sample-page/?foo=bar';

		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/subdir/sample-page/?foo=bar',
			wp_get_current_request_url()
		);
	}

	/**
	 * Tests the reverse-proxy case where the proxy strips the path prefix
	 * from REQUEST_URI before forwarding to WordPress.
	 *
	 * @ticket 53998
	 */
	public function test_reverse_proxy_stripped_prefix() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN . '/branch-slug' );
		$_SERVER['REQUEST_URI'] = '/sample-page/?foo=bar';

		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/branch-slug/sample-page/?foo=bar',
			wp_get_current_request_url()
		);
	}

	/**
	 * Tests the reverse-proxy case with a root-level request.
	 *
	 * @ticket 53998
	 */
	public function test_reverse_proxy_root_request() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN . '/branch-slug' );
		$_SERVER['REQUEST_URI'] = '/';

		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/branch-slug/',
			wp_get_current_request_url()
		);
	}

	/**
	 * Tests that an explicit $request_uri parameter overrides $_SERVER.
	 *
	 * @ticket 53998
	 */
	public function test_explicit_request_uri_parameter() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN );
		$_SERVER['REQUEST_URI'] = '/should-not-be-used/';

		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/explicit-path/',
			wp_get_current_request_url( '/explicit-path/' )
		);
	}

	/**
	 * Tests that the scheme follows is_ssl() for a standard install.
	 *
	 * @ticket 53998
	 */
	public function test_scheme_follows_is_ssl_https() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN );
		$_SERVER['REQUEST_URI'] = '/test/';
		$_SERVER['HTTPS']       = 'on';

		$this->assertStringStartsWith( 'https://', wp_get_current_request_url() );
	}

	/**
	 * Tests that the scheme is HTTP when HTTPS is off.
	 *
	 * @ticket 53998
	 */
	public function test_scheme_follows_is_ssl_http() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN );
		$_SERVER['REQUEST_URI'] = '/test/';
		$_SERVER['HTTPS']       = 'off';

		$this->assertStringStartsWith( 'http://', wp_get_current_request_url() );
	}

	/**
	 * Tests that the host comes from home_url(), not from HTTP_HOST.
	 *
	 * @ticket 53998
	 */
	public function test_host_from_home_url_not_http_host() {
		update_option( 'home', 'http://configured-host.example.com' );
		$_SERVER['HTTP_HOST']   = 'container-host.internal';
		$_SERVER['REQUEST_URI'] = '/page/';

		$url = wp_get_current_request_url();

		$this->assertStringContainsString( 'configured-host.example.com', $url );
		$this->assertStringNotContainsString( 'container-host.internal', $url );
	}

	/**
	 * Tests that a port in the home URL is preserved.
	 *
	 * @ticket 53998
	 */
	public function test_port_in_home_url_is_preserved() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN . ':8080' );
		$_SERVER['REQUEST_URI'] = '/page/';

		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . ':8080/page/',
			wp_get_current_request_url()
		);
	}

	/**
	 * Tests that query strings are preserved in the returned URL.
	 *
	 * @ticket 53998
	 */
	public function test_query_string_preserved() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN );
		$_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?post_type=page&orderby=date';

		$url = wp_get_current_request_url();

		$this->assertStringContainsString( 'post_type=page', $url );
		$this->assertStringContainsString( 'orderby=date', $url );
	}

	/**
	 * Tests the reverse-proxy case with query strings.
	 *
	 * @ticket 53998
	 */
	public function test_reverse_proxy_with_query_string() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN . '/branch' );
		$_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?post_type=page';

		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/branch/wp-admin/edit.php?post_type=page',
			wp_get_current_request_url()
		);
	}

	/**
	 * Tests with an empty REQUEST_URI.
	 *
	 * @ticket 53998
	 */
	public function test_empty_request_uri() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN );
		$_SERVER['REQUEST_URI'] = '';

		$url = wp_get_current_request_url();

		$this->assertSame( 'http://' . WP_TESTS_DOMAIN, $url );
	}

	/**
	 * Tests that a trailing-slash home path is handled correctly.
	 *
	 * The home_url('/') always produces a trailing slash. When REQUEST_URI
	 * starts with it (which is always the case for '/'), the standard path
	 * should be taken.
	 *
	 * @ticket 53998
	 */
	public function test_root_home_with_trailing_slash() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN );
		$_SERVER['REQUEST_URI'] = '/wp-login.php';

		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/wp-login.php',
			wp_get_current_request_url()
		);
	}

	/**
	 * Tests the reverse-proxy case with the explicit $request_uri parameter.
	 *
	 * @ticket 53998
	 */
	public function test_reverse_proxy_with_explicit_request_uri() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN . '/prefix' );

		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/prefix/my-account/',
			wp_get_current_request_url( '/my-account/' )
		);
	}

	/**
	 * Tests data provider scenarios for standard and reverse-proxy installs.
	 *
	 * @ticket 53998
	 *
	 * @dataProvider data_request_url_scenarios
	 *
	 * @param string $home        The home option value.
	 * @param string $request_uri The value for `$_SERVER['REQUEST_URI']`.
	 * @param string $expected    The expected URL.
	 */
	public function test_request_url_scenarios( $home, $request_uri, $expected ) {
		update_option( 'home', $home );
		$_SERVER['REQUEST_URI'] = $request_uri;

		$this->assertSame( $expected, wp_get_current_request_url() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_request_url_scenarios() {
		$domain = WP_TESTS_DOMAIN;

		return array(
			'root install, simple path'                  => array(
				'home'        => "http://{$domain}",
				'request_uri' => '/hello-world/',
				'expected'    => "http://{$domain}/hello-world/",
			),
			'root install, wp-admin path with query'     => array(
				'home'        => "http://{$domain}",
				'request_uri' => '/wp-admin/options.php?updated=1',
				'expected'    => "http://{$domain}/wp-admin/options.php?updated=1",
			),
			'subdirectory install, matching REQUEST_URI' => array(
				'home'        => "http://{$domain}/wp",
				'request_uri' => '/wp/hello-world/',
				'expected'    => "http://{$domain}/wp/hello-world/",
			),
			'reverse proxy, stripped /wp prefix'         => array(
				'home'        => "http://{$domain}/wp",
				'request_uri' => '/hello-world/',
				'expected'    => "http://{$domain}/wp/hello-world/",
			),
			'reverse proxy, stripped /branch prefix'     => array(
				'home'        => "http://{$domain}/deploy-branch",
				'request_uri' => '/my-account/',
				'expected'    => "http://{$domain}/deploy-branch/my-account/",
			),
			'reverse proxy, root request'                => array(
				'home'        => "http://{$domain}/deploy-branch",
				'request_uri' => '/',
				'expected'    => "http://{$domain}/deploy-branch/",
			),
		);
	}
}
