<?php

/**
 * Test the content in some root directory files.
 *
 * @group basic
 */
class Tests_Basic extends WP_UnitTestCase {

	/**
	 * @coversNothing
	 */
	public function test_license() {
		// This test is designed to only run on trunk.
		$this->skipOnAutomatedBranches();

		$license = file_get_contents( ABSPATH . 'license.txt' );
		preg_match( '#Copyright 2011-(\d+) by the contributors#', $license, $matches );
		$license_year = trim( $matches[1] );
		$this_year    = gmdate( 'Y' );

		$this->assertSame( $this_year, $license_year, "license.txt's year needs to be updated to $this_year." );
	}

	/**
	 * @coversNothing
	 */
	public function test_security_md() {
		// This test is designed to only run on trunk.
		$this->skipOnAutomatedBranches();

		$security = file_get_contents( dirname( ABSPATH ) . '/SECURITY.md' );
		preg_match_all( '#\d.\d.x#', $security, $matches );
		$supported_versions = $matches[0];
		$current_version    = substr( $GLOBALS['wp_version'], 0, 3 );
		$latest_stable      = number_format( (float) $current_version - 0.1, 1 ) . '.x';

		$this->assertContains( $latest_stable, $supported_versions, "SECURITY.md's version needs to be updated to $latest_stable." );
	}

	/**
	 * @coversNothing
	 */
	public function test_package_json() {
		$package_json    = file_get_contents( dirname( ABSPATH ) . '/package.json' );
		$package_json    = json_decode( $package_json, true );
		list( $version ) = explode( '-', $GLOBALS['wp_version'] );

		// package.json uses x.y.z, so fill cleaned $wp_version for .0 releases.
		if ( 1 === substr_count( $version, '.' ) ) {
			$version .= '.0';
		}

		$this->assertSame( $version, $package_json['version'], "package.json's version needs to be updated to $version." );

		return $package_json;
	}

	/**
	 * @depends test_package_json
	 *
	 * @coversNothing
	 */
	public function test_package_json_node_engine( $package_json ) {
		$this->assertArrayHasKey( 'engines', $package_json );
		$this->assertArrayHasKey( 'node', $package_json['engines'] );
	}
}
