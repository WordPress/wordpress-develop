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
	public function test_readme() {
		// This test is designed to only run on trunk/master.
		$this->skipOnAutomatedBranches();

		$readme = file_get_contents( ABSPATH . 'readme.html' );

		/* Check PHP version */
		preg_match( '#Recommendations.*PHP</a> version <strong>([0-9.]*)#s', $readme, $matches );

		$response = wp_remote_get( 'https://www.php.net/supported-versions.php' );

		$this->skipTestOnTimeout( $response );

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_message = sprintf(
				'Could not contact PHP.net to check versions. Response code: %s. Response body: %s',
				$response_code,
				$response_body
			);

			if ( 503 === $response_code ) {
				$this->markTestSkipped( $error_message );
			}

			$this->fail( $error_message );
		}

		preg_match_all( '#<tr class="stable">\s*<td>\s*<a [^>]*>\s*([0-9.]*)#s', $response_body, $phpmatches );

		$this->assertContains( $matches[1], $phpmatches[1], "readme.html's Recommended PHP version is too old. Remember to update the WordPress.org Requirements page, too." );

		/* Check MySQL version */
		preg_match( '#Recommendations.*MySQL</a> version <strong>([0-9.]*)#s', $readme, $matches );

		$response = wp_remote_get( "https://dev.mysql.com/doc/relnotes/mysql/{$matches[1]}/en/" );

		$this->skipTestOnTimeout( $response );

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_message = sprintf(
				'Could not contact dev.MySQL.com to check versions. Response code: %s. Response body: %s',
				$response_code,
				$response_body
			);

			if ( 503 === $response_code ) {
				$this->markTestSkipped( $error_message );
			}

			$this->fail( $error_message );
		}

		preg_match( '#(\d{4}-\d{2}-\d{2}), General Availability#', $response_body, $mysqlmatches );

		// Per https://www.mysql.com/support/, Oracle actively supports MySQL releases for 5 years from GA release.
		$mysql_eol = strtotime( $mysqlmatches[1] . ' +5 years' );

		$this->assertLessThan( $mysql_eol, time(), "readme.html's Recommended MySQL version is too old. Remember to update the WordPress.org Requirements page, too." );

		/* Check MariaDB version */
		preg_match( '#Recommendations.*MariaDB</a> version <strong>([0-9.]*)#s', $readme, $matches );
		$mariadb_version = str_replace( '.', '', $matches[1] );

		$response = wp_remote_get( "https://mariadb.com/kb/en/changes-improvements-in-mariadb-{$mariadb_version}/" );

		$this->skipTestOnTimeout( $response );

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_message = sprintf(
				'Could not contact MariaDB.com to check versions. Response code: %s. Response body: %s',
				$response_code,
				$response_body
			);

			if ( 503 === $response_code ) {
				$this->markTestSkipped( $error_message );
			}

			$this->fail( $error_message );
		}

		preg_match( '#is no longer supported. Please use a#s', $response_body, $mariadb_matches );

		$this->assertEquals( 'is no longer supported. Please use a', $mariadb_matches[0][0], "readme.html's Recommended MariaDB version is too old. Remember to update the WordPress.org Requirements page, too." );
	}
}
