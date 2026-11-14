<?php

/**
 * Tests for the ms_allowed_http_request_hosts() function.
 *
 * @group http
 * @group ms-required
 *
 * @covers ::ms_allowed_http_request_hosts
 */
class Tests_HTTP_MsAllowedHttpRequestHosts extends WP_UnitTestCase {

	/**
	 * Tests that a host that is already external stays external, without a query.
	 *
	 * @ticket 66501
	 */
	public function test_should_keep_a_host_that_is_already_external_without_a_query() {
		global $wpdb;

		$queries = $wpdb->num_queries;

		$this->assertTrue( ms_allowed_http_request_hosts( true, 'ms-external.example.com' ) );
		$this->assertSame( $queries, $wpdb->num_queries, 'The database should not be queried.' );
	}

	/**
	 * Tests that the domain of the network is allowed, without a query.
	 *
	 * @ticket 66501
	 */
	public function test_should_allow_the_domain_of_the_network_without_a_query() {
		global $wpdb;

		$queries = $wpdb->num_queries;

		$this->assertTrue( ms_allowed_http_request_hosts( false, get_network()->domain ) );
		$this->assertSame( $queries, $wpdb->num_queries, 'The database should not be queried.' );
	}

	/**
	 * Tests that the domain of a site of the network is allowed.
	 *
	 * @ticket 66501
	 */
	public function test_should_allow_the_domain_of_a_site_of_the_network() {
		self::factory()->blog->create(
			array(
				'domain' => 'ms-allowed-site.example.net',
				'path'   => '/',
			)
		);

		$this->assertTrue( ms_allowed_http_request_hosts( false, 'ms-allowed-site.example.net' ) );
	}

	/**
	 * Tests that a domain that is not one of the network or of its sites is not allowed.
	 *
	 * @ticket 66501
	 */
	public function test_should_not_allow_other_hosts() {
		$this->assertFalse( ms_allowed_http_request_hosts( false, 'ms-unknown-host.example.net' ) );
	}

	/**
	 * Tests that the database is queried only once for the same host.
	 *
	 * @ticket 66501
	 */
	public function test_should_query_the_database_once_for_the_same_host() {
		$queries = 0;
		add_filter(
			'query',
			static function ( $query ) use ( &$queries ) {
				if ( str_contains( $query, 'ms-cached-host.example.net' ) ) {
					++$queries;
				}

				return $query;
			}
		);

		$this->assertFalse( ms_allowed_http_request_hosts( false, 'ms-cached-host.example.net' ), 'The first call should not allow the host.' );
		$this->assertFalse( ms_allowed_http_request_hosts( false, 'ms-cached-host.example.net' ), 'The second call should not allow the host.' );

		$this->assertSame( 1, $queries, 'The host should be queried once.' );
	}

	/**
	 * Tests that the function is used by default for the requests of the HTTP API in a network.
	 *
	 * @ticket 66501
	 */
	public function test_should_be_hooked_to_the_filter_of_external_hosts() {
		$this->assertSame( 20, has_filter( 'http_request_host_is_external', 'ms_allowed_http_request_hosts' ) );
	}
}
