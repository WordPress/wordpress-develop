<?php
/**
 * Validate recommended versions for dependencies referenced in `readme.html`,
 * based on external site support pages.
 *
 * @group external-http
 */
class Tests_Readme extends WP_UnitTestCase {

	/**
	 * @coversNothing
	 */
	public function test_readme_php_version() {
		$this->markTestSkipped(
			'Temporarily disabled. Test should be re-enabled once WordPress is fully compatible with PHP 8.0+.'
		);

		// This test is designed to only run on trunk.
		$this->skipOnAutomatedBranches();

		$readme = file_get_contents( ABSPATH . 'readme.html' );

		preg_match( '#Recommendations.*PHP</a> version <strong>([0-9.]*)#s', $readme, $matches );

		$response_body = $this->get_response_body( 'https://www.php.net/supported-versions.php' );

		preg_match_all( '#<tr class="stable">\s*<td>\s*<a [^>]*>\s*([0-9.]*)#s', $response_body, $php_matches );

		$this->assertContains( $matches[1], $php_matches[1], "readme.html's Recommended PHP version is too old. Remember to update the WordPress.org Requirements page, too." );
	}

	/**
	 * @coversNothing
	 */
	public function test_readme_mysql_version() {
		// This test is designed to only run on trunk.
		$this->skipOnAutomatedBranches();

		$readme = file_get_contents( ABSPATH . 'readme.html' );

		preg_match( '#Recommendations.*MySQL</a> version <strong>([0-9.]*)#s', $readme, $matches );

		$response_body = $this->get_response_body( "https://dev.mysql.com/doc/relnotes/mysql/{$matches[1]}/en/" );

		// Retrieve the date of the first GA release for the recommended branch.
		preg_match( '#.*(\d{4}-\d{2}-\d{2}), General Availability#s', $response_body, $mysql_matches );

		/*
		 * Per https://www.mysql.com/support/, Oracle actively supports MySQL releases for 5 years from GA release.
		 *
		 * The currently recommended MySQL 8.0 branch moved from active support to extended support on 2023-04-19.
		 * As WordPress core may not be fully compatible with MySQL 8.1 at this time, the "supported" period here
		 * is increased to 8 years to include extended support.
		 *
		 * TODO: Reduce this back to 5 years once MySQL 8.1 compatibility is achieved.
		 */
		$mysql_eol    = gmdate( 'Y-m-d', strtotime( $mysql_matches[1] . ' +8 years' ) );
		$current_date = gmdate( 'Y-m-d' );

		$this->assertLessThan( $mysql_eol, $current_date, "readme.html's Recommended MySQL version is too old. Remember to update the WordPress.org Requirements page, too." );
	}

	/**
	 * @coversNothing
	 */
	public function test_readme_mariadb_version() {
		// This test is designed to only run on trunk.
		$this->skipOnAutomatedBranches();

		$readme = file_get_contents( ABSPATH . 'readme.html' );

		preg_match( '#Recommendations.*MariaDB</a> version <strong>([0-9.]*)#s', $readme, $recommended );
		$this->assertCount( 2, $recommended, 'The recommended version was not matched.' );

		$all_releases       = $this->get_response_body( 'https://mariadb.org/mariadb/all-releases/' );
		$recommended_regex  = str_replace( '.', '\.', $recommended[1] ) . '\.\d{1,2}'; // Examples: 10.4.1 or 10.4.22
		$release_date_regex = '(\d{4}-\d{2}-\d{2})'; // Example: 2024-01-01

		// Retrieve the date of the first stable release for the recommended branch.
		preg_match( "#{$recommended_regex}.*?{$release_date_regex}#", $all_releases, $release_date );
		$this->assertCount( 2, $release_date, 'The release date was not matched.' );

		// Per https://mariadb.org/about/#maintenance-policy, MariaDB releases are supported for 5 years.
		$mariadb_eol  = gmdate( 'Y-m-d', strtotime( $release_date[1] . ' +5 years' ) );
		$current_date = gmdate( 'Y-m-d' );

		$this->assertLessThan( $mariadb_eol, $current_date, "readme.html's Recommended MariaDB version is too old. Remember to update the WordPress.org Requirements page, too." );
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
