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
	 * @ticket 57809
	 *
	 * @dataProvider data_wp_is_authorize_application_redirect_url_valid
	 *
	 * @param string $url                 The redirect URL to validate.
	 * @param string $expected_error_code The expected error code, empty if no error is expected.
	 * @param string $env                 The environment type. Defaults to 'production'.
	 */
	public function test_wp_is_authorize_application_redirect_url_valid( $url, $expected_error_code, $env = 'production' ) {
		putenv( "WP_ENVIRONMENT_TYPE=$env" );

		$actual = wp_is_authorize_application_redirect_url_valid( $url );

		putenv( 'WP_ENVIRONMENT_TYPE' );

		if ( $expected_error_code ) {
			$this->assertWPError( $actual, 'A WP_Error object is expected.' );
			$this->assertSame( $expected_error_code, $actual->get_error_code(), 'Unexpected error code.' );
		} else {
			$this->assertTrue( $actual, 'The URL should be considered valid.' );
		}
	}

	/**
	 * Data provider for test_wp_is_authorize_application_redirect_url_valid.
	 *
	 * @return array[]
	 */
	public function data_wp_is_authorize_application_redirect_url_valid() {
		$environment_types = array( 'local', 'development', 'staging', 'production' );

		$datasets = array();
		foreach ( $environment_types as $environment_type ) {
			// Empty URL should always be valid.
			$datasets[ $environment_type . ' and an empty URL' ] = array(
				'url'                 => '',
				'expected_error_code' => '',
				'env'                 => $environment_type,
			);

			// HTTPS URLs should always be valid.
			$datasets[ $environment_type . ' and a "https" scheme URL' ] = array(
				'url'                 => 'https://example.org',
				'expected_error_code' => '',
				'env'                 => $environment_type,
			);

			$datasets[ $environment_type . ' and a "https" scheme URL with path' ] = array(
				'url'                 => 'https://example.org/callback',
				'expected_error_code' => '',
				'env'                 => $environment_type,
			);

			// Custom app schemes should always be valid.
			$datasets[ $environment_type . ' and a custom app scheme URL' ] = array(
				'url'                 => 'wordpress://callback',
				'expected_error_code' => '',
				'env'                 => $environment_type,
			);

			$datasets[ $environment_type . ' and another custom app scheme URL' ] = array(
				'url'                 => 'myapp://auth/callback',
				'expected_error_code' => '',
				'env'                 => $environment_type,
			);

			// Invalid protocols should always be rejected.
			$datasets[ $environment_type . ' and a "javascript" scheme URL' ] = array(
				'url'                 => 'javascript://example.org/%0Aalert(1)',
				'expected_error_code' => 'invalid_redirect_url_format',
				'env'                 => $environment_type,
			);

			$datasets[ $environment_type . ' and a "data" scheme URL' ] = array(
				'url'                 => 'data://text/html,test',
				'expected_error_code' => 'invalid_redirect_url_format',
				'env'                 => $environment_type,
			);

			// Invalid URL formats should always be rejected.
			$datasets[ $environment_type . ' and a URL without a valid scheme' ] = array(
				'url'                 => 'not-a-url',
				'expected_error_code' => 'invalid_redirect_url_format',
				'env'                 => $environment_type,
			);

			$datasets[ $environment_type . ' and a URL with an empty host' ] = array(
				'url'                 => 'http://',
				'expected_error_code' => 'invalid_redirect_url_format',
				'env'                 => $environment_type,
			);

			// HTTP + loopback IP addresses should be valid in all environments.
			$datasets[ $environment_type . ' and a "http" scheme URL with 127.0.0.1' ] = array(
				'url'                 => 'http://127.0.0.1/callback',
				'expected_error_code' => '',
				'env'                 => $environment_type,
			);

			$datasets[ $environment_type . ' and a "http" scheme URL with IPv6 loopback' ] = array(
				'url'                 => 'http://[::1]/callback',
				'expected_error_code' => '',
				'env'                 => $environment_type,
			);

			// HTTP + loopback IP addresses with ports should be valid in all environments.
			$datasets[ $environment_type . ' and a "http" scheme URL with 127.0.0.1 and port' ] = array(
				'url'                 => 'http://127.0.0.1:3000/callback',
				'expected_error_code' => '',
				'env'                 => $environment_type,
			);

			$datasets[ $environment_type . ' and a "http" scheme URL with IPv6 loopback and port' ] = array(
				'url'                 => 'http://[::1]:8080/callback',
				'expected_error_code' => '',
				'env'                 => $environment_type,
			);

			// HTTP + non-loopback host should only be valid in local environments.
			$datasets[ $environment_type . ' and a "http" scheme URL with a non-loopback host' ] = array(
				'url'                 => 'http://example.org',
				'expected_error_code' => 'local' === $environment_type ? '' : 'invalid_redirect_scheme',
				'env'                 => $environment_type,
			);

			$datasets[ $environment_type . ' and a "http" scheme URL with a non-loopback host and path' ] = array(
				'url'                 => 'http://example.org/callback',
				'expected_error_code' => 'local' === $environment_type ? '' : 'invalid_redirect_scheme',
				'env'                 => $environment_type,
			);

			// Boundary cases: hostnames and addresses NOT treated as loopback.
			$datasets[ $environment_type . ' and a "http" scheme URL with localhost' ] = array(
				'url'                 => 'http://localhost/callback',
				'expected_error_code' => 'local' === $environment_type ? '' : 'invalid_redirect_scheme',
				'env'                 => $environment_type,
			);

			$datasets[ $environment_type . ' and a "http" scheme URL with 127.0.0.2' ] = array(
				'url'                 => 'http://127.0.0.2/callback',
				'expected_error_code' => 'local' === $environment_type ? '' : 'invalid_redirect_scheme',
				'env'                 => $environment_type,
			);

			$datasets[ $environment_type . ' and a "http" scheme URL with expanded IPv6 loopback' ] = array(
				'url'                 => 'http://[0:0:0:0:0:0:0:1]/callback',
				'expected_error_code' => 'local' === $environment_type ? '' : 'invalid_redirect_scheme',
				'env'                 => $environment_type,
			);

			$datasets[ $environment_type . ' and a "http" scheme URL with localhost.localdomain' ] = array(
				'url'                 => 'http://localhost.localdomain/callback',
				'expected_error_code' => 'local' === $environment_type ? '' : 'invalid_redirect_scheme',
				'env'                 => $environment_type,
			);

			$datasets[ $environment_type . ' and a "http" scheme URL with localhost as subdomain' ] = array(
				'url'                 => 'http://localhost.example.org/callback',
				'expected_error_code' => 'local' === $environment_type ? '' : 'invalid_redirect_scheme',
				'env'                 => $environment_type,
			);

			$datasets[ $environment_type . ' and a "http" scheme URL with localhost as suffix' ] = array(
				'url'                 => 'http://examplelocalhost.org/callback',
				'expected_error_code' => 'local' === $environment_type ? '' : 'invalid_redirect_scheme',
				'env'                 => $environment_type,
			);
		}

		return $datasets;
	}
}
