<?php

abstract class WP_Test_REST_TestCase extends WP_UnitTestCase {
	/**
	 * Asserts that the REST API response has specified error.
	 *
	 * @since 6.6.0 Added the `$message` parameter.
	 *
	 * @param string|int                        $code       Expected error code.
	 * @param true|WP_Error|WP_REST_Response    $response   REST API response.
	 * @param int                               $status     Optional. status code.
	 * @param string                            $message    Optional. Message to display when the assertion fails.
	 *
	 */
	protected function assertErrorResponse( $code, $response, $status = null, $message = '' ) {

		if ( $response instanceof WP_REST_Response ) {
			$response = $response->as_error();
		}

		$this->assertWPError( $response, $message );
		$this->assertSame( $code, $response->get_error_code(), $message );

		if ( null !== $status ) {
			$data = $response->get_error_data();
			$this->assertArrayHasKey( 'status', $data, $message );
			$this->assertSame( $status, $data['status'], $message );
		}
	}
}
