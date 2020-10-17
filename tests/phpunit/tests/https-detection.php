<?php

/**
 * @group https-detection
 */
class Tests_HTTPS_Detection extends WP_UnitTestCase {

	private $last_request_url;

	public function setUp() {
		parent::setUp();

		remove_all_filters( 'option_home' );
		remove_all_filters( 'option_siteurl' );
		remove_all_filters( 'home_url' );
		remove_all_filters( 'site_url' );
	}

	public function test_wp_is_using_https() {
		update_option( 'home', 'http://example.com/' );
		update_option( 'siteurl', 'http://example.com/' );
		$this->assertFalse( wp_is_using_https() );

		// Expect false if only one of the two relevant URLs is HTTPS.
		update_option( 'siteurl', 'https://example.com/' );
		$this->assertFalse( wp_is_using_https() );

		update_option( 'home', 'https://example.com/' );
		$this->assertTrue( wp_is_using_https() );
	}

	public function test_wp_is_https_supported() {
		// The function works with cached errors, so only test that here.
		$wp_error = new WP_Error();

		// No errors, so HTTPS is supported.
		update_option( 'https_detection_errors', $wp_error->errors );
		$this->assertTrue( wp_is_https_supported() );

		// Errors, so HTTPS is not supported.
		$wp_error->add( 'ssl_verification_failed', 'SSL verification failed.' );
		update_option( 'https_detection_errors', $wp_error->errors );
		$this->assertFalse( wp_is_https_supported() );
	}

	public function test_wp_update_https_detection_errors() {
		// Set HTTP URL, the request below should use its HTTPS version.
		update_option( 'home', 'http://example.com/' );
		add_filter( 'pre_http_request', array( $this, 'record_request_url' ), 10, 3 );

		// If initial request succeeds, all good.
		add_filter( 'pre_http_request', array( $this, 'mock_success_with_sslverify' ), 10, 2 );
		wp_update_https_detection_errors();
		$this->assertEquals( array(), get_option( 'https_detection_errors' ) );

		// If initial request fails and request without SSL verification succeeds,
		// return error with 'ssl_verification_failed' error code.
		add_filter( 'pre_http_request', array( $this, 'mock_error_with_sslverify' ), 10, 2 );
		add_filter( 'pre_http_request', array( $this, 'mock_success_without_sslverify' ), 10, 2 );
		wp_update_https_detection_errors();
		$this->assertEquals(
			array( 'ssl_verification_failed' => array( 'Bad SSL certificate.' ) ),
			get_option( 'https_detection_errors' )
		);

		// If both initial request and request without SSL verification fail,
		// return actual error from request.
		add_filter( 'pre_http_request', array( $this, 'mock_error_with_sslverify' ), 10, 2 );
		add_filter( 'pre_http_request', array( $this, 'mock_error_without_sslverify' ), 10, 2 );
		wp_update_https_detection_errors();
		$this->assertEquals(
			array( 'bad_ssl_certificate' => array( 'Bad SSL certificate.' ) ),
			get_option( 'https_detection_errors' )
		);

		// If request succeeds, but response is not 200, return error with
		// 'response_error' error code.
		add_filter( 'pre_http_request', array( $this, 'mock_not_found' ), 10, 2 );
		wp_update_https_detection_errors();
		$this->assertEquals(
			array( 'response_error' => array( 'Not Found' ) ),
			get_option( 'https_detection_errors' )
		);

		// Check that the requests are made to the correct URL.
		$this->assertEquals( 'https://example.com/', $this->last_request_url );
	}

	public function test_wp_cron_schedule_https_detection() {
		wp_cron_schedule_https_detection();
		$this->assertEquals( 'twicedaily', wp_get_schedule( 'wp_https_detection' ) );
	}

	public function test_wp_cron_conditionally_prevent_sslverify() {
		// If URL is not using HTTPS, don't set 'sslverify' to false.
		$request = array(
			'url'  => 'http://example.com/',
			'args' => array( 'sslverify' => true ),
		);
		$this->assertEquals( $request, wp_cron_conditionally_prevent_sslverify( $request ) );

		// If URL is using HTTPS, set 'sslverify' to false.
		$request                       = array(
			'url'  => 'https://example.com/',
			'args' => array( 'sslverify' => true ),
		);
		$expected                      = $request;
		$expected['args']['sslverify'] = false;
		$this->assertEquals( $expected, wp_cron_conditionally_prevent_sslverify( $request ) );
	}

	public function record_request_url( $preempt, $parsed_args, $url ) {
		$this->last_request_url = $url;
		return $preempt;
	}

	public function mock_success_with_sslverify( $preempt, $parsed_args ) {
		if ( ! empty( $parsed_args['sslverify'] ) ) {
			return $this->mock_success();
		}
		return $preempt;
	}

	public function mock_error_with_sslverify( $preempt, $parsed_args ) {
		if ( ! empty( $parsed_args['sslverify'] ) ) {
			return $this->mock_error();
		}
		return $preempt;
	}

	public function mock_success_without_sslverify( $preempt, $parsed_args ) {
		if ( empty( $parsed_args['sslverify'] ) ) {
			return $this->mock_success();
		}
		return $preempt;
	}

	public function mock_error_without_sslverify( $preempt, $parsed_args ) {
		if ( empty( $parsed_args['sslverify'] ) ) {
			return $this->mock_error();
		}
		return $preempt;
	}

	public function mock_not_found() {
		return array(
			'body'     => '<!DOCTYPE html><html><head><title>404</title></head><body>Not Found</body></html>',
			'response' => array(
				'code'    => 404,
				'message' => 'Not Found',
			),
		);
	}

	private function mock_success() {
		return array(
			'body'     => '<!DOCTYPE html><html><head><title>Page Title</title></head><body>Page Content.</body></html>',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
	}

	private function mock_error() {
		return new WP_Error( 'bad_ssl_certificate', 'Bad SSL certificate.' );
	}
}
