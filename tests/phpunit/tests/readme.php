<?php
/**
 * Validate recommended versions for dependencies referenced in `readme.html`,
 * based on external site support pages.
 *
 * @group readme
 */
class Tests_Readme extends WP_UnitTestCase {

	/**
	 * Tests that readme.html contains valid recommended versions for PHP, MySQL, and MariaDB.
	 *
	 * @ticket 63914
	 *
	 * @coversNothing
	 */
	public function test_readme_contains_recommended_versions() {
		$readme = file_get_contents( ABSPATH . 'readme.html' );
		$this->assertIsString( $readme, 'readme.html should exist and be readable.' );

		$this->assertSame( 1, preg_match( '#Recommendations.*PHP</a> version <strong>([0-9.]+)#s', $readme ), 'readme.html should declare a recommended PHP version.' );

		$this->assertSame( 1, preg_match( '#Recommendations.*MySQL</a> version <strong>([0-9.]+)#s', $readme ), 'readme.html should declare a recommended MySQL version.' );

		$this->assertSame( 1, preg_match( '#Recommendations.*MariaDB</a> version <strong>([0-9.]+)#s', $readme ), 'readme.html should declare a recommended MariaDB version.' );
	}

	/**
	 * @coversNothing
	 *
	 * @group external-http
	 */
	public function test_readme_php_version() {
		// This test is designed to only run on trunk.
		$this->skipOnAutomatedBranches();

		$readme = file_get_contents( ABSPATH . 'readme.html' );

		preg_match( '#Recommendations.*PHP</a> version <strong>([0-9.]*)#s', $readme, $matches );

		$response_body = $this->get_response_body( 'https://www.php.net/supported-versions.php' );

		preg_match_all( '#<tr class="(?:stable|security)">\s*<td>\s*<a [^>]*>\s*([0-9.]*)#s', $response_body, $php_matches );

		$this->assertContains( $matches[1], $php_matches[1], "readme.html's Recommended PHP version is too old. Remember to update the WordPress.org Requirements page, too." );
	}

	/**
	 * @coversNothing
	 *
	 * @group external-http
	 */
	public function test_readme_mysql_version() {
		// This test is designed to only run on trunk.
		$this->skipOnAutomatedBranches();

		$readme = file_get_contents( ABSPATH . 'readme.html' );

		preg_match( '#Recommendations.*MySQL</a> version <strong>([0-9.]*)#s', $readme, $matches );

		$response_body = json_decode( $this->get_response_body( 'https://endoflife.date/api/mysql.json' ) );
		$eol_date      = '';

		foreach ( $response_body as $version ) {
			if ( $version->cycle === $matches[1] && false !== $version->eol ) {
				$eol_date = $version->eol;
				break;
			}
		}

		/*
		 * Per https://www.mysql.com/support/, Oracle actively supports MySQL releases for 5 years from GA release.
		 *
		 * The currently recommended MySQL 8.0 branch moved from active support to extended support on 2023-04-19.
		 * As WordPress core may not be fully compatible with MySQL 8.1 at this time, the "supported" period here
		 * is increased to 8 years to include extended support.
		 *
		 * TODO: Reduce this back to 5 years once MySQL 8.1 compatibility is achieved.
		 */
		$mysql_eol    = gmdate( 'Y-m-d', strtotime( $eol_date . ' +8 years' ) );
		$current_date = gmdate( 'Y-m-d' );

		$this->assertLessThan( $mysql_eol, $current_date, "readme.html's Recommended MySQL version is too old. Remember to update the WordPress.org Requirements page, too." );
	}

	/**
	 * @coversNothing
	 *
	 * @group external-http
	 */
	public function test_readme_mariadb_version() {
		// This test is designed to only run on trunk.
		$this->skipOnAutomatedBranches();

		$readme = file_get_contents( ABSPATH . 'readme.html' );

		preg_match( '#Recommendations.*MariaDB</a> version <strong>([0-9.]*)#s', $readme, $matches );

		$response_body = $this->get_response_body( 'https://downloads.mariadb.org/rest-api/mariadb/' );
		$releases      = json_decode( $response_body, true );

		foreach ( $releases['major_releases'] as $release ) {
			if ( isset( $release['release_id'] ) && $release['release_id'] === $matches[1] ) {
				$mariadb_eol = $release['release_eol_date'];
			}
		}

		// If the release ID is not found the version is unsupported.
		if ( ! isset( $mariadb_eol ) ) {
			$this->fail( "{$matches[1]} is not included in MariaDB's list of supported versions. Remember to update the WordPress.org Requirements page, too." );
		}

		$current_date = gmdate( 'Y-m-d' );

		$this->assertLessThan( $mariadb_eol, $current_date, "readme.html's Recommended MariaDB version is too old. Remember to update the WordPress.org Requirements page, too." );
	}

	/**
	 * Helper function to retrieve the response body or skip the test on HTTP timeout or server unavailability.
	 *
	 * @param string $url The URL to retrieve the response from.
	 * @return string The response body.
	 */
	public function get_response_body( $url ) {
		$response = $this->wp_remote_get( $url );
		return $this->parse_response_body( $response, $url );
	}

	/**
	 * Processes the HTTP response, skipping on availability failures or returning the body.
	 *
	 * @param array|WP_Error $response The response from wp_remote_get().
	 * @param string         $url      The URL retrieved.
	 * @return string The response body.
	 */
	public function parse_response_body( $response, $url ) {
		$parsed_url = parse_url( $url );
		$host       = isset( $parsed_url['host'] ) ? $parsed_url['host'] : $url;

		if ( is_wp_error( $response ) ) {
			$error_message           = $response->get_error_message();
			$is_availability_failure = false;

			if ( 'http_request_failed' === $response->get_error_code() ) {
				$availability_patterns = array(
					'Could not resolve host',
					'Failed to connect',
					'Connection refused',
					'unable to connect to',
					'Connection timed out',
					'Network is unreachable',
					'Host is down',
					'getaddrinfo failed',
					'name or service not known',
					'temporary failure in name resolution',
					'Operation timed out',
					'fsocket timed out',
					'Could not connect to server',
					'Connection reset by peer',
				);

				foreach ( $availability_patterns as $pattern ) {
					if ( false !== stripos( $error_message, $pattern ) ) {
						$is_availability_failure = true;
						break;
					}
				}
			}

			if ( $is_availability_failure ) {
				$this->markTestSkipped(
					sprintf(
						'Could not contact %1$s to check versions: %2$s',
						$host,
						$error_message
					)
				);
			}

			$this->assertNotWPError( $response );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_message = sprintf(
				'Could not contact %1$s to check versions. Response code: %2$s. Response body: %3$s',
				$host,
				$response_code,
				$response_body
			);

			if ( in_array( $response_code, array( 429, 500, 502, 503, 504 ), true ) ) {
				$this->markTestSkipped( $error_message );
			}

			$this->fail( $error_message );
		}

		return $response_body;
	}

	/**
	 * Returns the version-appropriate SkippedTestError class name.
	 *
	 * @return string
	 */
	private function get_skipped_test_error_class() {
		return class_exists( 'PHPUnit\Framework\SkippedTestError' )
			? 'PHPUnit\Framework\SkippedTestError'
			: 'PHPUnit_Framework_SkippedTestError';
	}

	/**
	 * @ticket 63914
	 *
	 * @dataProvider data_availability_error_messages
	 *
	 * @param string $message Error message.
	 */
	public function test_get_response_body_skips_on_availability_failures( $message ) {
		$error         = new WP_Error( 'http_request_failed', $message );
		$skipped_class = $this->get_skipped_test_error_class();
		$skipped       = false;

		try {
			$this->parse_response_body( $error, 'https://endoflife.date/api/mysql.json' );
		} catch ( PHPUnit_Framework_AssertionFailedError $e ) {
			if ( is_a( $e, $skipped_class ) ) {
				$skipped = true;
				$this->assertStringContainsString( 'Could not contact endoflife.date to check versions: ' . $message, $e->getMessage() );
			}
		}

		$this->assertTrue( $skipped, 'Expected test to be skipped.' );
	}

	/**
	 * Data provider for availability failure messages.
	 *
	 * @return array[]
	 */
	public static function data_availability_error_messages() {
		return array(
			'host resolution'             => array( 'cURL error 6: Could not resolve host: endoflife.date' ),
			'fsocket timed out'           => array( 'fsocket timed out' ),
			'could not connect to server' => array( 'cURL error 7: Could not connect to server' ),
			'connection reset by peer'    => array( 'cURL error 35: Connection reset by peer' ),
		);
	}

	/**
	 * @ticket 63914
	 */
	public function test_get_response_body_fails_on_invalid_url() {
		$error         = new WP_Error( 'http_request_failed', 'A valid URL was not provided.' );
		$skipped_class = $this->get_skipped_test_error_class();
		$failed        = false;

		try {
			$this->parse_response_body( $error, 'https://endoflife.date/api/mysql.json' );
		} catch ( PHPUnit_Framework_AssertionFailedError $e ) {
			$failed = true;
			$this->assertNotInstanceOf( $skipped_class, $e, 'The request should fail rather than skip.' );
		}

		$this->assertTrue( $failed, 'Expected an assertion failure.' );
	}

	/**
	 * @ticket 63914
	 */
	public function test_get_response_body_fails_on_request_not_executed() {
		$error         = new WP_Error( 'http_request_not_executed', 'User has blocked requests through HTTP.' );
		$skipped_class = $this->get_skipped_test_error_class();
		$failed        = false;

		try {
			$this->parse_response_body( $error, 'https://endoflife.date/api/mysql.json' );
		} catch ( PHPUnit_Framework_AssertionFailedError $e ) {
			$failed = true;
			$this->assertNotInstanceOf( $skipped_class, $e, 'The request should fail rather than skip.' );
		}

		$this->assertTrue( $failed, 'Expected an assertion failure.' );
	}

	/**
	 * @ticket 63914
	 *
	 * @dataProvider data_availability_status_codes
	 *
	 * @param int $status_code The HTTP status code.
	 */
	public function test_get_response_body_skips_on_server_availability_status_codes( $status_code ) {
		$response = array(
			'response' => array( 'code' => $status_code ),
			'body'     => 'Service Unavailable',
		);

		$skipped_class = $this->get_skipped_test_error_class();
		$skipped       = false;

		try {
			$this->parse_response_body( $response, 'https://endoflife.date/api/mysql.json' );
		} catch ( PHPUnit_Framework_AssertionFailedError $e ) {
			if ( is_a( $e, $skipped_class ) ) {
				$skipped = true;
				$this->assertStringContainsString( "Response code: $status_code", $e->getMessage() );
			}
		}

		$this->assertTrue( $skipped, 'Expected test to be skipped.' );
	}

	/**
	 * Data provider for server availability status codes.
	 *
	 * @return array[]
	 */
	public static function data_availability_status_codes() {
		return array(
			'429 Too Many Requests' => array( 429 ),
			'500 Server Error'      => array( 500 ),
			'502 Bad Gateway'       => array( 502 ),
			'503 Unavailable'       => array( 503 ),
			'504 Gateway Timeout'   => array( 504 ),
		);
	}

	/**
	 * @ticket 63914
	 */
	public function test_get_response_body_fails_on_404_status_code() {
		$response = array(
			'response' => array( 'code' => 404 ),
			'body'     => 'Not Found',
		);

		$skipped_class = $this->get_skipped_test_error_class();
		$failed        = false;

		try {
			$this->parse_response_body( $response, 'https://endoflife.date/api/mysql.json' );
		} catch ( PHPUnit_Framework_AssertionFailedError $e ) {
			$failed = true;
			$this->assertNotInstanceOf( $skipped_class, $e, 'The request should fail rather than skip.' );
		}

		$this->assertTrue( $failed, 'Expected an assertion failure.' );
	}
}
