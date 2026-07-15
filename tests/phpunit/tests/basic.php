<?php

/**
 * Test the content in some root directory files.
 *
 * @group basic
 */
class Tests_Basic extends WP_UnitTestCase {

	/**
	 * Test copyright year in license.txt.
	 *
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
	 * Test latest stable version is included in SECURITY.md.
	 *
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
	 * Test the version number in package.json is correct.
	 *
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
	 * Test engines.node is included in package.json.
	 *
	 * @depends test_package_json
	 *
	 * @coversNothing
	 */
	public function test_package_json_node_engine( $package_json ) {
		$this->assertArrayHasKey( 'engines', $package_json );
		$this->assertArrayHasKey( 'node', $package_json['engines'] );
	}

	/**
	 * Test the version numbers in package-lock.json are correct.
	 *
	 * In pull requests, the package-lock.json file is updated automatically
	 * to match the version in package.json. This test is intended to ensure
	 * the version numbers are correct in production branches.
	 *
	 * @coversNothing
	 *
	 * @dataProvider data_package_lock_json
	 */
	public function test_package_lock_json( $path ) {
		$package_lock_json = file_get_contents( dirname( ABSPATH ) . '/package-lock.json' );
		$package_lock_json = json_decode( $package_lock_json, true );
		list( $version )   = explode( '-', $GLOBALS['wp_version'] );

		// package-lock.json uses x.y.z, so fill cleaned $wp_version for .0 releases.
		if ( 1 === substr_count( $version, '.' ) ) {
			$version .= '.0';
		}

		$json_paths           = explode( '.', $path );
		$package_lock_version = $package_lock_json;
		foreach ( $json_paths as $json_path ) {
			if ( ! isset( $package_lock_version[ $json_path ] ) ) {
				$this->fail( "package-lock.json does not contain the path '$path'." );
			}
			$package_lock_version = $package_lock_version[ $json_path ];
		}

		$this->assertSame( $version, $package_lock_version, "package-lock.json's $path needs to be updated to $version." );
	}

	/**
	 * Data provider for test_package_lock_json.
	 *
	 * @return array[] Data provider.
	 */
	public function data_package_lock_json() {
		return array(
			'top level' => array( 'version' ),
			'package'   => array( 'packages..version' ),
		);
	}

	/**
	 * Test the version number in composer.json is correct.
	 *
	 * @coversNothing
	 */
	public function test_composer_json() {
		$composer_json   = file_get_contents( dirname( ABSPATH ) . '/composer.json' );
		$composer_json   = json_decode( $composer_json, true );
		list( $version ) = explode( '-', $GLOBALS['wp_version'] );

		// composer.json uses x.y.z, so fill cleaned $wp_version for .0 releases.
		if ( 1 === substr_count( $version, '.' ) ) {
			$version .= '.0';
		}

		$this->assertSame( $version, $composer_json['version'], "composer.json's version needs to be updated to $version." );
	}

	/**
	 * Test the src wp_version always ends with '-src'.
	 *
	 * @coversNothing
	 */
	public function test_src_wp_version_ends_with_src() {
		$version_file = dirname( ABSPATH ) . '/src/wp-includes/version.php';

		$src_wp_version = $this->get_wp_version_from_file( $version_file );

		$this->assertStringEndsWith( '-src', $src_wp_version, 'The src wp_version must end with -src.' );
	}

	/**
	 * Test the build wp_version never ends with '-src'.
	 *
	 * @coversNothing
	 */
	public function test_build_wp_version_does_not_end_with_src() {
		$version_file = dirname( ABSPATH ) . '/build/wp-includes/version.php';

		if ( ! file_exists( $version_file ) ) {
			$this->markTestSkipped( 'The build directory does not exist.' );
		}

		$build_wp_version = $this->get_wp_version_from_file( $version_file );

		$this->assertStringEndsNotWith( '-src', $build_wp_version, 'The build wp_version must not end with -src.' );
	}

	/**
	 * Includes a version.php file in an isolated scope and returns its $wp_version.
	 *
	 * @param string $file Path to a version.php file.
	 * @return string The value of $wp_version defined in the file.
	 */
	private function get_wp_version_from_file( $file ) {
		$wp_version = '';
		include $file;

		return $wp_version;
	}
}
