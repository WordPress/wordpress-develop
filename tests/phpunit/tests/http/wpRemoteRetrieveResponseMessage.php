<?php

/**
 * Tests for the wp_remote_retrieve_response_message() function.
 *
 * @group http
 *
 * @covers ::wp_remote_retrieve_response_message
 */
class Tests_HTTP_WpRemoteRetrieveResponseMessage extends WP_UnitTestCase {

	/**
	 * Tests that the message of the response is returned.
	 *
	 * @ticket 66501
	 *
	 * @dataProvider data_messages
	 *
	 * @param string $message Message of the response.
	 */
	public function test_should_return_the_message_of_the_response( $message ) {
		$response = array(
			'response' => array(
				'code'    => 200,
				'message' => $message,
			),
		);

		$this->assertSame( $message, wp_remote_retrieve_response_message( $response ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_messages() {
		return array(
			'OK'                    => array( 'OK' ),
			'Not Found'             => array( 'Not Found' ),
			'Internal Server Error' => array( 'Internal Server Error' ),
			'empty message'         => array( '' ),
		);
	}

	/**
	 * Tests that only the message is returned, and not the code.
	 *
	 * @ticket 66501
	 */
	public function test_should_not_return_the_code() {
		$response = array(
			'response' => array(
				'code'    => 404,
				'message' => 'Not Found',
			),
		);

		$this->assertNotSame( 404, wp_remote_retrieve_response_message( $response ) );
		$this->assertSame( 'Not Found', wp_remote_retrieve_response_message( $response ) );
	}

	/**
	 * Tests that an empty string is returned when the response is an error.
	 *
	 * @ticket 66501
	 */
	public function test_should_return_an_empty_string_for_an_error() {
		$this->assertSame( '', wp_remote_retrieve_response_message( new WP_Error( 'http_request_failed', 'Failed' ) ) );
	}

	/**
	 * Tests that an empty string is returned when the response does not have the response data.
	 *
	 * @ticket 66501
	 */
	public function test_should_return_an_empty_string_when_the_response_has_no_response_data() {
		$this->assertSame( '', wp_remote_retrieve_response_message( array( 'body' => 'text' ) ) );
	}

	/**
	 * Tests that an empty string is returned when the response data is not an array.
	 *
	 * @ticket 66501
	 *
	 * @dataProvider data_response_data_that_is_not_an_array
	 *
	 * @param mixed $response_data Value of the response data.
	 */
	public function test_should_return_an_empty_string_when_the_response_data_is_not_an_array( $response_data ) {
		$this->assertSame( '', wp_remote_retrieve_response_message( array( 'response' => $response_data ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_response_data_that_is_not_an_array() {
		return array(
			'string' => array( 'OK' ),
			'null'   => array( null ),
			'int'    => array( 200 ),
		);
	}
}
