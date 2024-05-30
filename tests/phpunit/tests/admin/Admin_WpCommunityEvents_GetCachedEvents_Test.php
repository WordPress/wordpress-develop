<?php

require_once __DIR__ . '/Admin_WpCommunityEvents_TestCase.php';

/**
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.8.0
 *
 * @group admin
 * @group community-events
 *
 * @covers WP_Community_Events::get_cached_events
 */
class Admin_WpCommunityEvents_GetCachedEvents_Test extends Admin_WpCommunityEvents_TestCase {
	/**
	 * Test: The response body should not be cached if the response code is not 200.
	 *
	 * @since 4.8.0
	 */
	public function test_get_cached_events_bad_response_code() {
		add_filter( 'pre_http_request', array( $this, '_http_request_bad_response_code' ) );

		$this->instance->get_events();

		$this->assertFalse( $this->instance->get_cached_events() );

		remove_filter( 'pre_http_request', array( $this, '_http_request_bad_response_code' ) );
	}

	/**
	 * Test: The response body should not be cached if it does not have the required properties.
	 *
	 * @since 4.8.0
	 */
	public function test_get_cached_events_invalid_response() {
		add_filter( 'pre_http_request', array( $this, '_http_request_invalid_response' ) );

		$this->instance->get_events();

		$this->assertFalse( $this->instance->get_cached_events() );

		remove_filter( 'pre_http_request', array( $this, '_http_request_invalid_response' ) );
	}

	/**
	 * Test: `get_cached_events()` should return the same data as get_events(), including Unix start/end
	 * timestamps for each event.
	 *
	 * @since 4.8.0
	 */
	public function test_get_cached_events_valid_response() {
		add_filter( 'pre_http_request', array( $this, '_http_request_valid_response' ) );

		$this->instance->get_events();

		$cached_events = $this->instance->get_cached_events();

		$this->assertNotWPError( $cached_events );
		$this->assertSameSetsWithIndex( $this->get_user_location(), $cached_events['location'] );
		$this->assertSame( strtotime( 'next Sunday 1pm' ), $cached_events['events'][0]['start_unix_timestamp'] );
		$this->assertSame( strtotime( 'next Sunday 2pm' ), $cached_events['events'][0]['end_unix_timestamp'] );

		remove_filter( 'pre_http_request', array( $this, '_http_request_valid_response' ) );
	}
}
