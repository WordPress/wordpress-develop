<?php

/**
 * Tests for the WP_HTTP_Proxy class.
 *
 * @group http
 *
 * @coversDefaultClass WP_HTTP_Proxy
 */
class Tests_HTTP_wpHttpProxy extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		foreach ( array( 'WP_PROXY_HOST', 'WP_PROXY_PORT', 'WP_PROXY_USERNAME', 'WP_PROXY_PASSWORD', 'WP_PROXY_BYPASS_HOSTS' ) as $constant ) {
			if ( defined( $constant ) ) {
				$this->markTestSkipped( "These tests cannot run with the {$constant} constant defined." );
			}
		}

		update_option( 'siteurl', 'http://example.org' );
	}

	/**
	 * Tests the proxy settings when no proxy constants are defined.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_enabled
	 * @covers ::use_authentication
	 * @covers ::host
	 * @covers ::port
	 * @covers ::username
	 * @covers ::password
	 * @covers ::authentication
	 * @covers ::authentication_header
	 */
	public function test_proxy_is_disabled_without_constants() {
		$proxy = new WP_HTTP_Proxy();

		$this->assertFalse( $proxy->is_enabled(), 'The proxy should not be enabled.' );
		$this->assertFalse( $proxy->use_authentication(), 'Authentication should not be used.' );
		$this->assertSame( '', $proxy->host(), 'The host should be empty.' );
		$this->assertSame( '', $proxy->port(), 'The port should be empty.' );
		$this->assertSame( '', $proxy->username(), 'The username should be empty.' );
		$this->assertSame( '', $proxy->password(), 'The password should be empty.' );
		$this->assertSame( ':', $proxy->authentication(), 'The authentication string should only hold the separator.' );
		$this->assertSame( 'Proxy-Authorization: Basic Og==', $proxy->authentication_header(), 'The header should encode the separator only.' );
	}

	/**
	 * Tests the proxy settings read from the proxy constants.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_enabled
	 * @covers ::use_authentication
	 * @covers ::host
	 * @covers ::port
	 * @covers ::username
	 * @covers ::password
	 * @covers ::authentication
	 * @covers ::authentication_header
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_proxy_settings_are_read_from_constants() {
		define( 'WP_PROXY_HOST', 'proxy.example.com' );
		define( 'WP_PROXY_PORT', '8080' );
		define( 'WP_PROXY_USERNAME', 'proxy-user' );
		define( 'WP_PROXY_PASSWORD', 'proxy:pass' );

		$proxy = new WP_HTTP_Proxy();

		$this->assertTrue( $proxy->is_enabled(), 'The proxy should be enabled.' );
		$this->assertTrue( $proxy->use_authentication(), 'Authentication should be used.' );
		$this->assertSame( 'proxy.example.com', $proxy->host() );
		$this->assertSame( '8080', $proxy->port() );
		$this->assertSame( 'proxy-user', $proxy->username() );
		$this->assertSame( 'proxy:pass', $proxy->password() );
		$this->assertSame( 'proxy-user:proxy:pass', $proxy->authentication() );
		$this->assertSame( 'Proxy-Authorization: Basic cHJveHktdXNlcjpwcm94eTpwYXNz', $proxy->authentication_header() );
	}

	/**
	 * Tests that the proxy and its authentication each need both of their constants.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_enabled
	 * @covers ::use_authentication
	 *
	 * @dataProvider data_incomplete_constants
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @param string $constant Name of the only proxy constant that is defined.
	 */
	public function test_proxy_is_disabled_with_incomplete_constants( $constant ) {
		define( $constant, 'value' );

		$proxy = new WP_HTTP_Proxy();

		$this->assertFalse( $proxy->is_enabled(), 'The proxy should not be enabled.' );
		$this->assertFalse( $proxy->use_authentication(), 'Authentication should not be used.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_incomplete_constants() {
		return array(
			'host only'     => array( 'WP_PROXY_HOST' ),
			'port only'     => array( 'WP_PROXY_PORT' ),
			'username only' => array( 'WP_PROXY_USERNAME' ),
			'password only' => array( 'WP_PROXY_PASSWORD' ),
		);
	}

	/**
	 * Tests which requests are sent through the proxy when no hosts are bypassed.
	 *
	 * @ticket 65819
	 *
	 * @covers ::send_through_proxy
	 *
	 * @dataProvider data_send_through_proxy
	 *
	 * @param string $uri      Request URI.
	 * @param bool   $expected Whether the request should be sent through the proxy.
	 */
	public function test_send_through_proxy( $uri, $expected ) {
		$proxy = new WP_HTTP_Proxy();

		$this->assertSame( $expected, $proxy->send_through_proxy( $uri ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_send_through_proxy() {
		return array(
			'external host'               => array( 'https://api.wordpress.org/core/version-check/1.7/', true ),
			'subdomain of the site host'  => array( 'http://sub.example.org/', true ),
			'site host in the path'       => array( 'http://wordpress.org/example.org/', true ),
			'loopback IP address'         => array( 'http://127.0.0.1/', true ),
			'URI that cannot be parsed'   => array( 'http:///example.org', true ),
			'site host'                   => array( 'http://example.org/wp-cron.php', false ),
			'site host with another port' => array( 'https://example.org:8443/', false ),
			'localhost'                   => array( 'http://localhost/wp-cron.php', false ),
			'localhost with a port'       => array( 'http://localhost:8889/', false ),
		);
	}

	/**
	 * Tests that the pre_http_send_through_proxy filter can decide whether the proxy is used.
	 *
	 * @ticket 65819
	 *
	 * @covers ::send_through_proxy
	 *
	 * @dataProvider data_pre_send_through_proxy
	 *
	 * @param string $uri      Request URI.
	 * @param mixed  $filtered Value returned by the filter.
	 * @param mixed  $expected Expected result.
	 */
	public function test_send_through_proxy_can_be_short_circuited( $uri, $filtered, $expected ) {
		add_filter(
			'pre_http_send_through_proxy',
			static function () use ( $filtered ) {
				return $filtered;
			}
		);

		$proxy = new WP_HTTP_Proxy();

		$this->assertSame( $expected, $proxy->send_through_proxy( $uri ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_pre_send_through_proxy() {
		return array(
			'bypass proxy for an external host'           => array( 'https://api.wordpress.org/', false, false ),
			'use proxy for the site host'                 => array( 'http://example.org/', true, true ),
			'use proxy for localhost'                     => array( 'http://localhost/', true, true ),
			'null keeps the default for an external host' => array( 'https://api.wordpress.org/', null, true ),
			'null keeps the default for the site host'    => array( 'http://example.org/', null, false ),
			'non-boolean value is returned as is'         => array( 'https://api.wordpress.org/', 0, 0 ),
		);
	}

	/**
	 * Tests the arguments passed to the pre_http_send_through_proxy filter.
	 *
	 * @ticket 65819
	 *
	 * @covers ::send_through_proxy
	 */
	public function test_send_through_proxy_filter_receives_parsed_urls() {
		$filter = new MockAction();
		add_filter( 'pre_http_send_through_proxy', array( $filter, 'filter' ), 10, 4 );

		$proxy = new WP_HTTP_Proxy();
		$proxy->send_through_proxy( 'https://api.wordpress.org:8443/core/?version=1' );

		$this->assertSame(
			array(
				array(
					null,
					'https://api.wordpress.org:8443/core/?version=1',
					array(
						'scheme' => 'https',
						'host'   => 'api.wordpress.org',
						'port'   => 8443,
						'path'   => '/core/',
						'query'  => 'version=1',
					),
					array(
						'scheme' => 'http',
						'host'   => 'example.org',
					),
				),
			),
			$filter->get_args()
		);
	}

	/**
	 * Tests that the filter does not run for a URI that cannot be parsed.
	 *
	 * @ticket 65819
	 *
	 * @covers ::send_through_proxy
	 */
	public function test_send_through_proxy_does_not_filter_unparsable_uri() {
		$filter = new MockAction();
		add_filter( 'pre_http_send_through_proxy', array( $filter, 'filter' ) );
		add_filter( 'pre_http_send_through_proxy', '__return_false', 20 );

		$proxy = new WP_HTTP_Proxy();

		$this->assertTrue( $proxy->send_through_proxy( 'http:///example.org' ) );
		$this->assertSame( 0, $filter->get_call_count() );
	}

	/**
	 * Tests which hosts bypass the proxy with a list of exact host names.
	 *
	 * @ticket 65819
	 *
	 * @covers ::send_through_proxy
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_send_through_proxy_with_bypass_hosts() {
		define( 'WP_PROXY_BYPASS_HOSTS', 'internal.example.com,  api.example.net,intranet' );

		$proxy = new WP_HTTP_Proxy();

		$this->assertFalse( $proxy->send_through_proxy( 'http://internal.example.com/' ), 'The first bypassed host should not use the proxy.' );
		$this->assertFalse( $proxy->send_through_proxy( 'https://api.example.net/v1/' ), 'A bypassed host after a comma and spaces should not use the proxy.' );
		$this->assertFalse( $proxy->send_through_proxy( 'http://intranet/' ), 'The last bypassed host should not use the proxy.' );
		$this->assertTrue( $proxy->send_through_proxy( 'http://example.com/' ), 'The parent domain of a bypassed host should use the proxy.' );
		$this->assertTrue( $proxy->send_through_proxy( 'http://sub.internal.example.com/' ), 'A subdomain of a bypassed host should use the proxy.' );
		$this->assertTrue( $proxy->send_through_proxy( 'https://api.wordpress.org/' ), 'Another host should use the proxy.' );
		$this->assertFalse( $proxy->send_through_proxy( 'http://example.org/' ), 'The site host should still not use the proxy.' );
		$this->assertFalse( $proxy->send_through_proxy( 'http://localhost/' ), 'Localhost should still not use the proxy.' );
	}

	/**
	 * Tests which hosts bypass the proxy with a list that contains wildcards.
	 *
	 * @ticket 65819
	 *
	 * @covers ::send_through_proxy
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_send_through_proxy_with_wildcard_bypass_hosts() {
		define( 'WP_PROXY_BYPASS_HOSTS', '*.example.com, wordpress.org' );

		$proxy = new WP_HTTP_Proxy();

		$this->assertFalse( $proxy->send_through_proxy( 'http://api.example.com/' ), 'A subdomain matching the wildcard should not use the proxy.' );
		$this->assertFalse( $proxy->send_through_proxy( 'http://deep.api.example.com/' ), 'A nested subdomain matching the wildcard should not use the proxy.' );
		$this->assertFalse( $proxy->send_through_proxy( 'http://API.EXAMPLE.COM/' ), 'Wildcard matching should not be case-sensitive.' );
		$this->assertFalse( $proxy->send_through_proxy( 'https://wordpress.org/' ), 'An exact host in a wildcard list should not use the proxy.' );
		$this->assertTrue( $proxy->send_through_proxy( 'http://example.com/' ), 'The bare domain should use the proxy.' );
		$this->assertTrue( $proxy->send_through_proxy( 'http://api.example.com.evil.test/' ), 'A host that only starts with a bypassed host should use the proxy.' );
		$this->assertTrue( $proxy->send_through_proxy( 'http://notwordpress.org/' ), 'A host that only ends with a bypassed host should use the proxy.' );
		$this->assertTrue( $proxy->send_through_proxy( 'http://apiXexampleXcom/' ), 'Dots in a bypassed host should only match dots.' );
	}
}
