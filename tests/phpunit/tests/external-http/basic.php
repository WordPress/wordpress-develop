<?php
/**
 * @group external-http
 */
class Tests_External_HTTP_Basic extends WP_UnitTestCase {

	/**
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_response_code
	 * @covers ::wp_remote_retrieve_body
	 */
	public function test_readme_php_version() {
		// This test is designed to only run on trunk/master.
		$this->skipOnAutomatedBranches();

		$readme = file_get_contents( ABSPATH . 'readme.html' );

		preg_match( '#Recommendations.*PHP</a> version <strong>([0-9.]*)#s', $readme, $matches );

		$response_body = $this->get_response_body( 'https://www.php.net/supported-versions.php' );

		preg_match_all( '#<tr class="stable">\s*<td>\s*<a [^>]*>\s*([0-9.]*)#s', $response_body, $php_matches );

		// TODO: Enable this check once PHP 8.0 compatibility is achieved.
		// $this->assertContains( $matches[1], $php_matches[1], "readme.html's Recommended PHP version is too old. Remember to update the WordPress.org Requirements page, too." );
	}

	/**
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_response_code
	 * @covers ::wp_remote_retrieve_body
	 */
	public function test_readme_mysql_version() {
		// This test is designed to only run on trunk/master.
		$this->skipOnAutomatedBranches();

		$readme = file_get_contents( ABSPATH . 'readme.html' );

		preg_match( '#Recommendations.*MySQL</a> version <strong>([0-9.]*)#s', $readme, $matches );

		$response_body = $this->get_response_body( "https://dev.mysql.com/doc/relnotes/mysql/{$matches[1]}/en/" );

		// Retrieve the date of the first GA release for the recommended branch.
		preg_match( '#.*(\d{4}-\d{2}-\d{2}), General Availability#s', $response_body, $mysql_matches );

		/*
		 * Per https://www.mysql.com/support/, Oracle actively supports MySQL releases for 5 years from GA release.
		 *
		 * The currently recommended MySQL 5.7 branch moved from active support to extended support on 2020-10-21.
		 * As WordPress core is not fully compatible with MySQL 8.0 at this time, the "supported" period here
		 * is increased to 8 years to include extended support.
		 *
		 * TODO: Reduce this back to 5 years once MySQL 8.0 compatibility is achieved.
		 */
		$mysql_eol = strtotime( $mysql_matches[1] . ' +8 years' );

		$this->assertLessThan( $mysql_eol, time(), "readme.html's Recommended MySQL version is too old. Remember to update the WordPress.org Requirements page, too." );
	}

	/**
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_response_code
	 * @covers ::wp_remote_retrieve_body
	 */
	public function test_readme_mariadb_version() {
		// This test is designed to only run on trunk/master.
		$this->skipOnAutomatedBranches();

		$readme = file_get_contents( ABSPATH . 'readme.html' );

		preg_match( '#Recommendations.*MariaDB</a> version <strong>([0-9.]*)#s', $readme, $matches );
		$matches[1] = str_replace( '.', '', $matches[1] );

		$response_body = $this->get_response_body( "https://mariadb.com/kb/en/release-notes-mariadb-{$matches[1]}-series/" );

		// Retrieve the date of the first stable release for the recommended branch.
		preg_match( '#.*Stable.*?(\d{2} [A-Za-z]{3} \d{4})#s', $response_body, $mariadb_matches );

		// Per https://mariadb.org/about/#maintenance-policy, MariaDB releases are supported for 5 years.
		$mariadb_eol = strtotime( $mariadb_matches[1] . ' +5 years' );

		$this->assertLessThan( $mariadb_eol, time(), "readme.html's Recommended MariaDB version is too old. Remember to update the WordPress.org Requirements page, too." );
	}

	/**
	 * Helper function to retrieve the response body or skip the test on HTTP timeout.
	 *
	 * @param string $url The URL to retrieve the response from.
	 * @return string The response body.
	 */
	public function get_response_body( $url ) {
		$response = wp_remote_get( $url );

		$this->skipTestOnTimeout( $response );

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$parsed_url = parse_url( $url );

			$error_message = sprintf(
				'Could not contact %1$s to check versions. Response code: %2$s. Response body: %3$s',
				$parsed_url['host'],
				$response_code,
				$response_body
			);

			if ( 503 === $response_code ) {
				$this->markTestSkipped( $error_message );
			}

			$this->fail( $error_message );
		}

		return $response_body;
	}
}
