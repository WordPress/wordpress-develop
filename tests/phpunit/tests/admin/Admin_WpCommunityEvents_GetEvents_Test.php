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
 * @covers WP_Community_Events::get_events
 */
class Admin_WpCommunityEvents_GetEvents_Test extends Admin_WpCommunityEvents_TestCase {
	/**
	 * Test: get_events() should return an instance of WP_Error if the response code is not 200.
	 *
	 * @since 4.8.0
	 */
	public function test_get_events_bad_response_code() {
		add_filter( 'pre_http_request', array( $this, '_http_request_bad_response_code' ) );

		$this->assertWPError( $this->instance->get_events() );

		remove_filter( 'pre_http_request', array( $this, '_http_request_bad_response_code' ) );
	}

	/**
	 * Test: get_events() should return an instance of WP_Error if the response body does not have
	 * the required properties.
	 *
	 * @since 4.8.0
	 */
	public function test_get_events_invalid_response() {
		add_filter( 'pre_http_request', array( $this, '_http_request_invalid_response' ) );

		$this->assertWPError( $this->instance->get_events() );

		remove_filter( 'pre_http_request', array( $this, '_http_request_invalid_response' ) );
	}

	/**
	 * Test: With a valid response, get_events() should return an associative array containing a location array and
	 * an events array with individual events that have Unix start/end timestamps.
	 *
	 * @since 4.8.0
	 */
	public function test_get_events_valid_response() {
		add_filter( 'pre_http_request', array( $this, '_http_request_valid_response' ) );

		$response = $this->instance->get_events();

		$this->assertNotWPError( $response );
		$this->assertSameSetsWithIndex( $this->get_user_location(), $response['location'] );
		$this->assertSame( strtotime( 'next Sunday 1pm' ), $response['events'][0]['start_unix_timestamp'] );
		$this->assertSame( strtotime( 'next Sunday 2pm' ), $response['events'][0]['end_unix_timestamp'] );

		remove_filter( 'pre_http_request', array( $this, '_http_request_valid_response' ) );
	}
}
