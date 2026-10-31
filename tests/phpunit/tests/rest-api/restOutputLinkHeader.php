<?php

/**
 * Tests for rest_output_link_header().
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @group restapi
 * @group xdebug
 *
 * @covers ::rest_output_link_header
 */
class Tests_REST_API_RestOutputLinkHeader extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_outputs_nothing_to_body() {
		ob_start();
		rest_output_link_header();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * @ticket 65819
	 *
	 * @requires function xdebug_get_headers
	 */
	public function test_returns_early_when_rest_url_is_empty() {
		add_filter( 'rest_url', '__return_empty_string' );

		$headers_before = xdebug_get_headers();

		rest_output_link_header();

		$this->assertSame( $headers_before, xdebug_get_headers() );
	}

	/**
	 * @ticket 65819
	 *
	 * @requires function xdebug_get_headers
	 */
	public function test_sends_rest_api_link_header() {
		$api_root = sanitize_url( get_rest_url() );

		rest_output_link_header();

		$headers  = xdebug_get_headers();
		$expected = sprintf( 'Link: <%s>; rel="https://api.w.org/"', $api_root );

		$this->assertContains( $expected, $headers );
	}
}
