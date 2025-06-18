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

		preg_match( '#Recommendations.*MariaDB</a> version <strong>([0-9.]*)#s', $readme, $matches );
		$maria_db_readme_version = $matches[1];
		list( $major, $minor )   = explode( '.', $maria_db_readme_version );

		$wikidata_id     = 'Q787177'; // Wikidata ID for MariaDB (see https://www.wikidata.org/wiki/Q787177).
		$wiki_data_query = "SELECT ?packageLabel ?version (SUBSTR(STR(?releaseDate), 1, 10) AS ?formattedDate) WHERE {
			wd:{$wikidata_id} p:P348 [
				ps:P348 ?version;
				pq:P548 wd:Q2804309;
				pq:P577 ?releaseDate
			].
			FILTER(REGEX(STR(?version), \"^{$major}\\\\.{$minor}\\\\.\\\\d+$\"))
			BIND(xsd:integer(STRAFTER(?version, \"{$major}.{$minor}.\")) AS ?patch)
			wd:{$wikidata_id} rdfs:label ?packageLabel .
		}
		ORDER BY ASC(?patch)
		LIMIT 1";

		$query_url = add_query_arg(
			array(
				'format' => 'json',
				'query'  => rawurlencode( $wiki_data_query ),
			),
			'https://query.wikidata.org/bigdata/namespace/wdq/sparql'
		);

		$response_body = $this->get_response_body( $query_url );
		$response_body = json_decode( $response_body, true );

		$maria_db_api_version = $response_body['results']['bindings'][0]['version']['value'];
		$release_date         = $response_body['results']['bindings'][0]['formattedDate']['value'];
		$package_label        = $response_body['results']['bindings'][0]['packageLabel']['value'];

		// Per https://mariadb.org/about/#maintenance-policy, MariaDB releases are supported for 5 years.
		$mariadb_eol  = gmdate( 'Y-m-d', strtotime( $release_date . ' +5 years' ) );
		$current_date = gmdate( 'Y-m-d' );

		$this->assertLessThan( $mariadb_eol, $current_date, "readme.html's Recommended MariaDB version is too old. Remember to update the WordPress.org Requirements page, too." );
		$this->assertStringStartsWith( "{$maria_db_readme_version}.", $maria_db_api_version, 'WikiData query did not return the expected MariaDB version.' );
		$this->assertSame( 'MariaDB', $package_label, 'WikiData query did not return the expected package label.' );
	}

	/**
	 * Helper function to retrieve the response body or skip the test on HTTP timeout.
	 *
	 * @param string $url The URL to retrieve the response from.
	 * @return string The response body.
	 */
	public function get_response_body( $url ) {
		$response = $this->wp_remote_get( $url );

		$this->assertNotWPError( $response );

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
