<?php

/**
 * Tests for the `wp_is_rest_endpoint()` function.
 *
 * @group rest-api
 * @covers ::wp_is_rest_endpoint
 */
class Tests_Media_Wp_Is_Rest_Endpoint extends WP_UnitTestCase {

	/**
	 * Tests that `wp_is_rest_endpoint()` returns false by default.
	 *
	 * @ticket 42061
	 */
	public function test_wp_is_rest_endpoint_default() {
		$this->assertFalse( wp_is_rest_endpoint() );
	}

	/**
	 * Tests that `wp_is_rest_endpoint()` relies on whether the global REST server is dispatching.
	 *
	 * @ticket 42061
	 */
	public function test_wp_is_rest_endpoint_via_global() {
		global $wp_rest_server;

		$wp_rest_server = new Spy_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		// The presence of a REST server itself won't set this to true.
		$this->assertFalse( wp_is_rest_endpoint() );

		// Set up filter to record value during dispatching.
		$result_within_request = null;
		add_filter(
			'rest_pre_dispatch',
			function ( $result ) use ( &$result_within_request ) {
				$result_within_request = wp_is_rest_endpoint();
				return $result;
			}
		);

		/*
		 * Dispatch a request (doesn't matter that it's invalid).
		 * This already is completed after this method call.
		 */
		$wp_rest_server->dispatch( new WP_REST_Request() );

		// Within that request, the function should have returned true.
		$this->assertTrue( $result_within_request );

		// After the dispatching, the function should return false again.
		$this->assertFalse( wp_is_rest_endpoint() );
	}

	/**
	 * Tests that `wp_is_rest_endpoint()` returns a result enforced via filter.
	 *
	 * @ticket 42061
	 */
	public function test_wp_is_rest_endpoint_via_filter() {
		add_filter( 'wp_is_rest_endpoint', '__return_true' );
		$this->assertTrue( wp_is_rest_endpoint() );
	}
}
