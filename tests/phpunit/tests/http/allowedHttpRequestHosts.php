<?php

/**
 * Tests for the allowed_http_request_hosts() function.
 *
 * @group http
 *
 * @covers ::allowed_http_request_hosts
 */
class Tests_HTTP_AllowedHttpRequestHosts extends WP_UnitTestCase {

	/**
	 * Tests that a host that is already external stays external, whatever the host is.
	 *
	 * @ticket 66501
	 */
	public function test_should_keep_a_host_that_is_already_external() {
		$this->assertTrue( allowed_http_request_hosts( true, 'unknown.example.com' ) );
		$this->assertTrue( allowed_http_request_hosts( true, WP_TESTS_DOMAIN ) );
	}

	/**
	 * Tests that the host of the site is allowed.
	 *
	 * @ticket 66501
	 */
	public function test_should_allow_the_host_of_the_site() {
		$this->assertTrue( allowed_http_request_hosts( false, WP_TESTS_DOMAIN ) );
	}

	/**
	 * Tests that a host that the site does not allow is not made external.
	 *
	 * @ticket 66501
	 */
	public function test_should_not_allow_other_hosts() {
		$this->assertFalse( allowed_http_request_hosts( false, 'unknown.example.com' ) );
	}

	/**
	 * Tests that the hosts that are added with the `allowed_redirect_hosts` filter are allowed.
	 *
	 * @ticket 66501
	 */
	public function test_should_allow_the_hosts_of_the_allowed_redirect_hosts_filter() {
		add_filter(
			'allowed_redirect_hosts',
			static function ( $hosts ) {
				$hosts[] = 'allowed-by-filter.example.com';

				return $hosts;
			}
		);

		$this->assertTrue( allowed_http_request_hosts( false, 'allowed-by-filter.example.com' ), 'The host of the filter should be allowed.' );
		$this->assertFalse( allowed_http_request_hosts( false, 'not-in-the-filter.example.com' ), 'A host that is not in the filter should not be allowed.' );
	}

	/**
	 * Tests that the function is used by default for the requests of the HTTP API.
	 *
	 * @ticket 66501
	 */
	public function test_should_be_hooked_to_the_filter_of_external_hosts() {
		$this->assertSame( 10, has_filter( 'http_request_host_is_external', 'allowed_http_request_hosts' ) );
	}
}
