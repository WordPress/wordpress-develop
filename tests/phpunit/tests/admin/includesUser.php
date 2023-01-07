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
}
