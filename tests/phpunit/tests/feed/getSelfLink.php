<?php

/**
 * Tests for the get_self_link() function.
 *
 * @group feed
 *
 * @covers ::get_self_link
 */
class Tests_Feed_GetSelfLink extends WP_UnitTestCase {

	/**
	 * The original value of `$_SERVER['REQUEST_URI']`.
	 *
	 * @var string
	 */
	private $original_request_uri;

	public function set_up() {
		parent::set_up();

		$this->original_request_uri = $_SERVER['REQUEST_URI'];
	}

	public function tear_down() {
		$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		unset( $_SERVER['HTTPS'] );

		parent::tear_down();
	}

	/**
	 * Tests that get_self_link() returns a full URL using the home host.
	 *
	 * @ticket 53998
	 */
	public function test_returns_full_url() {
		$_SERVER['REQUEST_URI'] = '/feed/';

		$url = get_self_link();

		$this->assertStringStartsWith( 'http', $url );
		$this->assertStringContainsString( '/feed/', $url );
	}

	/**
	 * Tests that get_self_link() uses the host from home_url().
	 *
	 * @ticket 53998
	 */
	public function test_host_from_home_url() {
		$original_home = get_option( 'home' );
		update_option( 'home', 'http://feeds.example.com' );

		$_SERVER['REQUEST_URI'] = '/feed/';

		$url = get_self_link();

		update_option( 'home', $original_home );

		$this->assertStringContainsString( 'feeds.example.com', $url );
	}

	/**
	 * Tests that get_self_link() reflects the current request scheme.
	 *
	 * @ticket 53998
	 */
	public function test_scheme_follows_is_ssl() {
		$_SERVER['REQUEST_URI'] = '/feed/';
		$_SERVER['HTTPS']       = 'on';

		$url = get_self_link();

		$this->assertStringStartsWith( 'https://', $url );
	}

	/**
	 * Tests that get_self_link() preserves query strings.
	 *
	 * @ticket 53998
	 */
	public function test_preserves_query_string() {
		$_SERVER['REQUEST_URI'] = '/feed/?cat=1&paged=2';

		$url = get_self_link();

		$this->assertStringContainsString( 'cat=1', $url );
		$this->assertStringContainsString( 'paged=2', $url );
	}

	/**
	 * Tests that get_self_link() includes the subdirectory prefix.
	 *
	 * @ticket 53998
	 */
	public function test_includes_subdirectory_prefix() {
		$original_home = get_option( 'home' );
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN . '/blog' );

		$_SERVER['REQUEST_URI'] = '/blog/feed/';

		$url = get_self_link();

		update_option( 'home', $original_home );

		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/blog/feed/', $url );
	}

	/**
	 * Tests the reverse-proxy case where the path prefix is stripped.
	 *
	 * @ticket 53998
	 */
	public function test_reverse_proxy_prepends_home_path() {
		$original_home = get_option( 'home' );
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN . '/subsite' );

		$_SERVER['REQUEST_URI'] = '/feed/';

		$url = get_self_link();

		update_option( 'home', $original_home );

		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/subsite/feed/', $url );
	}
}
