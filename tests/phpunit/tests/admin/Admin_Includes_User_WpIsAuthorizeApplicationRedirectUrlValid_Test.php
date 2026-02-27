<?php

/**
 * @group admin
 * @group user
 *
 * @covers ::wp_is_authorize_application_redirect_url_valid
 */
class Admin_Includes_User_WpIsAuthorizeApplicationRedirectUrlValid_Test extends WP_UnitTestCase {

	/**
	 * Test redirect URL validation for application password authorization.
	 *
	 * @ticket nnnnn
	 *
	 * @dataProvider data_is_authorize_application_redirect_url_valid
	 *
	 * @param string $url                 The URL to validate.
	 * @param string $expected_error_code The expected error code, empty if no error is expected.
	 * @param string $expected_message    The expected error message, empty if no error is expected.
	 * @param string $env                 The environment type. Defaults to 'production'.
	 */
	public function test_is_authorize_application_redirect_url_valid( $url, $expected_error_code, $expected_message = '', $env = 'production' ) {
		putenv( "WP_ENVIRONMENT_TYPE=$env" );

		$actual = wp_is_authorize_application_redirect_url_valid( $url );

		putenv( 'WP_ENVIRONMENT_TYPE' );

		if ( $expected_error_code ) {
			$this->assertWPError( $actual, 'A WP_Error object is expected.' );
			$this->assertSame( $expected_error_code, $actual->get_error_code(), 'Unexpected error code.' );
			if ( $expected_message ) {
				$this->assertSame( $expected_message, $actual->get_error_message(), 'Unexpected error message.' );
			}
		} else {
			$this->assertNotWPError( $actual, 'A WP_Error object is not expected.' );
		}
	}

	public function data_is_authorize_application_redirect_url_valid() {
		return array(
			// Empty URL is valid (no redirect).
			'empty URL'                              => array(
				'url'                 => '',
				'expected_error_code' => '',
			),

			// Valid HTTPS URLs.
			'https URL'                              => array(
				'url'                 => 'https://example.org',
				'expected_error_code' => '',
			),
			'https URL with path'                    => array(
				'url'                 => 'https://example.org/callback',
				'expected_error_code' => '',
			),
			'https URL with port'                    => array(
				'url'                 => 'https://example.org:8443/callback',
				'expected_error_code' => '',
			),
			'https URL with query params'            => array(
				'url'                 => 'https://example.org/callback?existing=param',
				'expected_error_code' => '',
			),

			// Valid app scheme URLs.
			'app scheme URL'                         => array(
				'url'                 => 'wordpress://example',
				'expected_error_code' => '',
			),
			'custom app scheme URL'                  => array(
				'url'                 => 'myapp://callback',
				'expected_error_code' => '',
			),

			// Userinfo in URL (authority confusion attack).
			'username and password in URL'           => array(
				'url'                 => 'https://user:pass@evil.com/capture',
				'expected_error_code' => 'invalid_redirect_url_format',
				'expected_message'    => 'Credentials are not allowed in the URL.',
			),
			'username only in URL'                   => array(
				'url'                 => 'https://google.com@evil.com/capture',
				'expected_error_code' => 'invalid_redirect_url_format',
				'expected_message'    => 'Credentials are not allowed in the URL.',
			),
			'username with empty password in URL'    => array(
				'url'                 => 'https://user:@evil.com/capture',
				'expected_error_code' => 'invalid_redirect_url_format',
				'expected_message'    => 'Credentials are not allowed in the URL.',
			),

			// Invalid protocols.
			'javascript protocol'                    => array(
				'url'                 => 'javascript:alert(1)',
				'expected_error_code' => 'invalid_redirect_url_format',
			),
			'data protocol'                          => array(
				'url'                 => 'data:text/html,<script>alert(1)</script>',
				'expected_error_code' => 'invalid_redirect_url_format',
			),

			// Invalid format.
			'no scheme'                              => array(
				'url'                 => 'example.org/callback',
				'expected_error_code' => 'invalid_redirect_url_format',
			),

			// HTTP scheme depends on environment.
			'http URL on production'                 => array(
				'url'                 => 'http://example.org',
				'expected_error_code' => 'invalid_redirect_scheme',
				'expected_message'    => '',
				'env'                 => 'production',
			),
			'http URL on local'                      => array(
				'url'                 => 'http://example.org',
				'expected_error_code' => '',
				'expected_message'    => '',
				'env'                 => 'local',
			),
		);
	}
}
