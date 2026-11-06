<?php

/**
 * Tests for the wp_get_https_detection_errors() function.
 *
 * @group https-detection
 *
 * @covers ::wp_get_https_detection_errors
 */
class Tests_HTTPS_Detection_WpGetHttpsDetectionErrors extends WP_UnitTestCase {

	/**
	 * Requests made by the function, as the arguments of the `pre_http_request` filter.
	 *
	 * @var array[]
	 */
	private $requests = array();

	public function set_up() {
		parent::set_up();

		$this->requests = array();
	}

	/**
	 * Mocks the responses of the requests, in the order they are made.
	 *
	 * @param array $responses Responses (or errors) to return, one for each request.
	 */
	private function mock_responses( array $responses ) {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$responses ) {
				$this->requests[] = array( $url, $args );

				return array_shift( $responses );
			},
			10,
			3
		);
	}

	/**
	 * Gets a response of a request.
	 *
	 * @param int    $code    Status code.
	 * @param string $message Status message.
	 * @param string $body    Body of the response.
	 * @return array Response.
	 */
	private function get_response( $code, $message, $body ) {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => $message,
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Gets the body of a page of this site.
	 *
	 * @return string HTML that includes the link to the Really Simple Discovery of the site.
	 */
	private function get_local_body() {
		return '<html><head><link rel="EditURI" type="application/rsd+xml" title="RSD" href="' . esc_url( site_url( 'xmlrpc.php?rsd', 'rpc' ) ) . '" /></head></html>';
	}

	/**
	 * Tests that no errors are returned for a valid response.
	 *
	 * @ticket 66506
	 */
	public function test_should_return_no_errors_for_a_valid_response() {
		$this->mock_responses( array( $this->get_response( 200, 'OK', $this->get_local_body() ) ) );

		$this->assertSame( array(), wp_get_https_detection_errors() );
		$this->assertCount( 1, $this->requests, 'One request should be made.' );
	}

	/**
	 * Tests that the request is made to the home page with HTTPS, without using the cache, and verifying the certificate.
	 *
	 * @ticket 66506
	 */
	public function test_should_request_the_home_page_with_https_and_without_cache() {
		$this->mock_responses( array( $this->get_response( 200, 'OK', $this->get_local_body() ) ) );

		wp_get_https_detection_errors();

		$this->assertSame( home_url( '/', 'https' ), $this->requests[0][0], 'The URL should be the home page with HTTPS.' );
		$this->assertSame( 'no-cache', $this->requests[0][1]['headers']['Cache-Control'], 'The cache should not be used.' );
		$this->assertTrue( $this->requests[0][1]['sslverify'], 'The certificate should be verified.' );
	}

	/**
	 * Tests that the errors of the filter are returned without making a request.
	 *
	 * @ticket 66506
	 */
	public function test_should_return_the_errors_of_the_filter_without_a_request() {
		$this->mock_responses( array( $this->get_response( 200, 'OK', $this->get_local_body() ) ) );

		add_filter(
			'pre_wp_get_https_detection_errors',
			static function () {
				return new WP_Error( 'custom_error', 'Custom error.' );
			}
		);

		$this->assertSame( array( 'custom_error' => array( 'Custom error.' ) ), wp_get_https_detection_errors() );
		$this->assertSame( array(), $this->requests, 'No request should be made.' );
	}

	/**
	 * Tests that a filter that does not return an error does not stop the detection.
	 *
	 * @ticket 66506
	 *
	 * @dataProvider data_filter_results_that_are_not_errors
	 *
	 * @param mixed $result Result of the filter.
	 */
	public function test_should_continue_when_the_filter_does_not_return_an_error( $result ) {
		$this->mock_responses( array( $this->get_response( 200, 'OK', $this->get_local_body() ) ) );

		add_filter(
			'pre_wp_get_https_detection_errors',
			static function () use ( $result ) {
				return $result;
			}
		);

		$this->assertSame( array(), wp_get_https_detection_errors() );
		$this->assertCount( 1, $this->requests, 'One request should be made.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_filter_results_that_are_not_errors() {
		return array(
			'null'  => array( null ),
			'false' => array( false ),
			'array' => array( array( 'custom_error' => array( 'Custom error.' ) ) ),
		);
	}

	/**
	 * Tests that a failed verification of the certificate is reported when the request without the verification works.
	 *
	 * @ticket 66506
	 */
	public function test_should_report_a_failed_verification_of_the_certificate() {
		$this->mock_responses(
			array(
				new WP_Error( 'http_request_failed', 'cURL error 60: SSL certificate problem' ),
				$this->get_response( 200, 'OK', $this->get_local_body() ),
			)
		);

		$errors = wp_get_https_detection_errors();

		$this->assertSame( array( 'ssl_verification_failed' ), array_keys( $errors ) );
		$this->assertCount( 2, $this->requests, 'A second request should be made.' );
		$this->assertTrue( $this->requests[0][1]['sslverify'], 'The first request should verify the certificate.' );
		$this->assertFalse( $this->requests[1][1]['sslverify'], 'The second request should not verify the certificate.' );
		$this->assertSame( $this->requests[0][0], $this->requests[1][0], 'The same URL should be requested again.' );
	}

	/**
	 * Tests that a failed request is reported when the request without the verification fails too.
	 *
	 * @ticket 66506
	 */
	public function test_should_report_a_failed_request() {
		$this->mock_responses(
			array(
				new WP_Error( 'http_request_failed', 'First error' ),
				new WP_Error( 'http_request_failed', 'Second error' ),
			)
		);

		$errors = wp_get_https_detection_errors();

		$this->assertSame( array( 'https_request_failed' ), array_keys( $errors ) );
		$this->assertCount( 2, $this->requests, 'Two requests should be made.' );
	}

	/**
	 * Tests that a response other than 200 is reported with its message.
	 *
	 * @ticket 66506
	 */
	public function test_should_report_a_bad_response_code() {
		$this->mock_responses( array( $this->get_response( 404, 'Not Found', $this->get_local_body() ) ) );

		$this->assertSame( array( 'bad_response_code' => array( 'Not Found' ) ), wp_get_https_detection_errors() );
	}

	/**
	 * Tests that a response that is not from this site is reported.
	 *
	 * @ticket 66506
	 */
	public function test_should_report_a_response_that_does_not_come_from_the_site() {
		$this->mock_responses( array( $this->get_response( 200, 'OK', '<html><head></head><body>Another site</body></html>' ) ) );

		$errors = wp_get_https_detection_errors();

		$this->assertSame( array( 'bad_response_source' ), array_keys( $errors ) );
		$this->assertSame( array( 'It looks like the response did not come from this site.' ), $errors['bad_response_source'] );
	}

	/**
	 * Tests that the response is not checked when it is not known whether it comes from the site.
	 *
	 * @ticket 66506
	 */
	public function test_should_not_report_the_source_when_it_cannot_be_determined() {
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );

		$this->mock_responses( array( $this->get_response( 200, 'OK', '<html><head></head><body>Another site</body></html>' ) ) );

		$this->assertSame( array(), wp_get_https_detection_errors() );
	}

	/**
	 * Tests that the errors of the verification are reported together with the ones of the response.
	 *
	 * @ticket 66506
	 */
	public function test_should_report_the_verification_and_the_response_errors_together() {
		$this->mock_responses(
			array(
				new WP_Error( 'http_request_failed', 'cURL error 60: SSL certificate problem' ),
				$this->get_response( 500, 'Internal Server Error', '' ),
			)
		);

		$errors = wp_get_https_detection_errors();

		$this->assertSame( array( 'ssl_verification_failed', 'bad_response_code' ), array_keys( $errors ) );
		$this->assertSame( array( 'Internal Server Error' ), $errors['bad_response_code'] );
	}
}
