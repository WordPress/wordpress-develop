<?php

/**
 * @group admin
 * @group user
 */
class Tests_Admin_IncludesUser extends WP_UnitTestCase {

	/**
	 * Test redirect URLs for application password authorization requests.
	 *
	 * @ticket 42790
	 * @ticket 52617
	 *
	 * @covers ::wp_is_authorize_application_password_request_valid
	 *
	 * @dataProvider data_is_authorize_application_password_request_valid
	 *
	 * @param array  $request             The request data to validate.
	 * @param string $expected_error_code The expected error code, empty if no error is expected.
	 * @param string $env                 The environment type. Defaults to 'production'.
	 */
	public function test_is_authorize_application_password_request_valid( $request, $expected_error_code, $env = 'production' ) {
		putenv( "WP_ENVIRONMENT_TYPE=$env" );

		$actual = wp_is_authorize_application_password_request_valid( $request, get_userdata( 1 ) );

		putenv( 'WP_ENVIRONMENT_TYPE' );

		if ( $expected_error_code ) {
			$this->assertWPError( $actual, 'A WP_Error object is expected.' );
			$this->assertSame( $expected_error_code, $actual->get_error_code(), 'Unexpected error code.' );
		} else {
			$this->assertNotWPError( $actual, 'A WP_Error object is not expected.' );
		}
	}

	public function data_is_authorize_application_password_request_valid() {
		return array(
			'prod, empty request args'      => array(
				array(),
				'',
			),
			'prod, http success URL'        => array(
				array( 'success_url' => 'http://example.org' ),
				'invalid_redirect_scheme',
			),
			'prod, http reject URL'         => array(
				array( 'reject_url' => 'http://example.org' ),
				'invalid_redirect_scheme',
			),
			'prod, https success URL'       => array(
				array( 'success_url' => 'https://example.org' ),
				'',
			),
			'prod, https reject URL'        => array(
				array( 'reject_url' => 'https://example.org' ),
				'',
			),
			'prod, app scheme success URL'  => array(
				array( 'success_url' => 'wordpress://example' ),
				'',
			),
			'prod, app scheme reject URL'   => array(
				array( 'reject_url' => 'wordpress://example' ),
				'',
			),
			'local, empty request args'     => array(
				array(),
				'',
				'local',
			),
			'local, http success URL'       => array(
				array( 'success_url' => 'http://example.org' ),
				'',
				'local',
			),
			'local, http reject URL'        => array(
				array( 'reject_url' => 'http://example.org' ),
				'',
				'local',
			),
			'local, https success URL'      => array(
				array( 'success_url' => 'https://example.org' ),
				'',
				'local',
			),
			'local, https reject URL'       => array(
				array( 'reject_url' => 'https://example.org' ),
				'',
				'local',
			),
			'local, app scheme success URL' => array(
				array( 'success_url' => 'wordpress://example' ),
				'',
				'local',
			),
			'local, app scheme reject URL'  => array(
				array( 'reject_url' => 'wordpress://example' ),
				'',
				'local',
			),

		);
	}
}
