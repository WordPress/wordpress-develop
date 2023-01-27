<?php

/**
 * @group admin
 * @group user
 */
class Tests_Admin_IncludesUser extends WP_UnitTestCase {

	/**
	 * @ticket       42790
	 * @dataProvider data_is_authorize_application_password_request_valid
	 * @param array  $request    The request data to validate.
	 * @param string $error_code The expected error code, empty if no error.
	 */
	public function test_is_authorize_application_password_request_valid( $request, $error_code ) {
		$error = wp_is_authorize_application_password_request_valid( $request, get_userdata( 1 ) );

		if ( $error_code ) {
			$this->assertWPError( $error );
			$this->assertSame( $error_code, $error->get_error_code() );
		} else {
			$this->assertNotWPError( $error );
		}
	}

	public function data_is_authorize_application_password_request_valid() {
		return array(
			array(
				array(),
				'',
			),
			array(
				array( 'success_url' => 'http://example.org' ),
				'invalid_redirect_scheme',
			),
			array(
				array( 'reject_url' => 'http://example.org' ),
				'invalid_redirect_scheme',
			),
			array(
				array( 'success_url' => 'https://example.org' ),
				'',
			),
			array(
				array( 'reject_url' => 'https://example.org' ),
				'',
			),
			array(
				array( 'success_url' => 'wordpress://example' ),
				'',
			),
			array(
				array( 'reject_url' => 'wordpress://example' ),
				'',
			),
		);
	}

	/**
	 * Tests that wp_is_authorize_application_password_request_valid() accepts
	 * an insecure scheme for a local environment.
	 *
	 * @ticket 52617
	 *
	 * @covers ::wp_is_authorize_application_password_request_valid
	 */
	public function test_should_accept_insecure_scheme_for_local_environment() {
		$request_success = array( 'success_url' => 'http://example.org' );
		$request_reject  = array( 'reject_url' => 'http://example.org' );

		putenv( 'WP_ENVIRONMENT_TYPE=local' );

		$actual_success = wp_is_authorize_application_password_request_valid( $request_success, get_userdata( 1 ) );
		$actual_reject  = wp_is_authorize_application_password_request_valid( $request_reject, get_userdata( 1 ) );

		putenv( 'WP_ENVIRONMENT_TYPE' );

		$this->assertNotWPError( $actual_success, 'A WP_Error object was returned for valid success URL.' );
		$this->assertNotWPError( $actual_reject, 'A WP_Error object was returned for valid rejection URL.' );
	}

	/**
	 * Tests that wp_is_authorize_application_password_request_valid() accepts
	 * an insecure scheme for a local environment.
	 *
	 * @ticket 52617
	 *
	 * @covers ::wp_is_authorize_application_password_request_valid
	 *
	 * @dataProvider data_should_not_accept_insecure_scheme_for_non_local_environment
	 *
	 * @param string $env The environment type.
	 */
	public function test_should_not_accept_insecure_scheme_for_non_local_environment( $env ) {
		$request_success = array( 'success_url' => 'http://example.org' );
		$request_reject  = array( 'reject_url' => 'http://example.org' );

		putenv( "WP_ENVIRONMENT_TYPE=$env" );

		$actual_success = wp_is_authorize_application_password_request_valid( $request_success, get_userdata( 1 ) );
		$actual_reject  = wp_is_authorize_application_password_request_valid( $request_reject, get_userdata( 1 ) );

		putenv( 'WP_ENVIRONMENT_TYPE' );

		$this->assertWPError( $actual_success, 'A WP_Error object was not returned for an invalid success URL.' );
		$this->assertWPError( $actual_reject, 'A WP_Error object was not returned for an invalid rejection URL.' );
	}

	/**
	 * Data provider for test_should_not_accept_insecure_scheme_for_non_local_environment().
	 *
	 * @return array[]
	 */
	public function data_should_not_accept_insecure_scheme_for_non_local_environment() {
		return array(
			'production'  => array( 'production' ),
			'development' => array( 'development' ),
			'staging'     => array( 'staging' ),
		);
	}
}
