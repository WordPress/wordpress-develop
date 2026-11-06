<?php

/**
 * Tests for the wp_remote_retrieve_header() function.
 *
 * @group http
 *
 * @covers ::wp_remote_retrieve_header
 */
class Tests_HTTP_WpRemoteRetrieveHeader extends WP_UnitTestCase {

	/**
	 * Tests that the value of a header is returned.
	 *
	 * @ticket 66501
	 */
	public function test_should_return_the_value_of_the_header() {
		$response = array(
			'headers' => array(
				'content-type' => 'text/html',
				'x-custom'     => 'custom value',
			),
		);

		$this->assertSame( 'text/html', wp_remote_retrieve_header( $response, 'content-type' ) );
		$this->assertSame( 'custom value', wp_remote_retrieve_header( $response, 'x-custom' ) );
	}

	/**
	 * Tests that the values of the headers that are repeated are returned as they are.
	 *
	 * @ticket 66501
	 */
	public function test_should_return_the_value_of_a_header_that_is_repeated() {
		$response = array(
			'headers' => array(
				'set-cookie' => array( 'a=1', 'b=2' ),
			),
		);

		$this->assertSame( array( 'a=1', 'b=2' ), wp_remote_retrieve_header( $response, 'set-cookie' ) );
	}

	/**
	 * Tests that the headers of the HTTP API are looked up without taking the case into account.
	 *
	 * @ticket 66501
	 */
	public function test_should_find_the_header_of_a_case_insensitive_dictionary_in_any_case() {
		$response = array(
			'headers' => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( array( 'Content-Type' => 'application/json' ) ),
		);

		$this->assertSame( 'application/json', wp_remote_retrieve_header( $response, 'content-type' ), 'The lowercase name should be found.' );
		$this->assertSame( 'application/json', wp_remote_retrieve_header( $response, 'Content-Type' ), 'The original name should be found.' );
		$this->assertSame( 'application/json', wp_remote_retrieve_header( $response, 'CONTENT-TYPE' ), 'The uppercase name should be found.' );
	}

	/**
	 * Tests that the name of a header of an array is case sensitive.
	 *
	 * @ticket 66501
	 */
	public function test_should_not_ignore_the_case_of_the_header_of_an_array() {
		$response = array(
			'headers' => array( 'content-type' => 'text/html' ),
		);

		$this->assertSame( '', wp_remote_retrieve_header( $response, 'Content-Type' ) );
	}

	/**
	 * Tests that an empty string is returned when the header is not set.
	 *
	 * @ticket 66501
	 */
	public function test_should_return_an_empty_string_when_the_header_is_not_set() {
		$response = array(
			'headers' => array( 'content-type' => 'text/html' ),
		);

		$this->assertSame( '', wp_remote_retrieve_header( $response, 'x-missing' ) );
	}

	/**
	 * Tests that the value of a header is returned even when it is empty or falsy.
	 *
	 * @ticket 66501
	 *
	 * @dataProvider data_falsy_values
	 *
	 * @param mixed $value Value of the header.
	 */
	public function test_should_return_the_value_of_a_header_that_is_falsy( $value ) {
		$response = array(
			'headers' => array( 'x-falsy' => $value ),
		);

		$this->assertSame( $value, wp_remote_retrieve_header( $response, 'x-falsy' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_falsy_values() {
		return array(
			'zero as a string' => array( '0' ),
			'empty string'     => array( '' ),
		);
	}

	/**
	 * Tests that an empty string is returned when the response is an error.
	 *
	 * @ticket 66501
	 */
	public function test_should_return_an_empty_string_for_an_error() {
		$this->assertSame( '', wp_remote_retrieve_header( new WP_Error( 'http_request_failed', 'Failed' ), 'content-type' ) );
	}

	/**
	 * Tests that an empty string is returned when the response has no headers.
	 *
	 * @ticket 66501
	 */
	public function test_should_return_an_empty_string_when_the_response_has_no_headers() {
		$this->assertSame( '', wp_remote_retrieve_header( array( 'body' => 'text' ), 'content-type' ) );
	}
}
