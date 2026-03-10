<?php

/**
 * Tests for wp_get_branch_version() and wp_get_wp_version() $part parameter.
 *
 * @group functions
 *
 * @covers ::wp_get_branch_version
 * @covers ::wp_get_wp_version
 */
class Tests_Functions_WpGetBranchVersion extends WP_UnitTestCase {

	/**
	 * Tests extracting the major (x.y) version from a WordPress version string.
	 *
	 * @dataProvider data_major_version
	 *
	 * @ticket 64830
	 *
	 * @param string $version  The input version string.
	 * @param string $expected The expected major version string.
	 */
	public function test_major_version( $version, $expected ) {
		$this->assertSame( $expected, wp_get_branch_version( $version ) );
	}

	/**
	 * Data provider for major version extraction.
	 *
	 * @return array[]
	 */
	public function data_major_version() {
		return array(
			'major ending with 0 and no minor'             => array( '7.0', '7.0' ),
			'minor number zero'                            => array( '7.0.0', '7.0' ),
			'minor with a major that ends in zero'         => array( '7.0.1', '7.0' ),
			'double digit minor with trailing zero'        => array( '7.0.10', '7.0' ),
			'double digit first part of major having zero' => array( '10.0.0', '10.0' ),
			'triple digit major'                           => array( '100.1.0', '100.1' ),
			'typical release'                              => array( '6.9', '6.9' ),
			'typical minor release'                        => array( '6.9.1', '6.9' ),
			'alpha suffix'                                 => array( '7.0-alpha-61215', '7.0' ),
			'beta suffix'                                  => array( '7.0-beta3-61849', '7.0' ),
			'RC suffix'                                    => array( '7.0-RC1', '7.0' ),
			'src suffix'                                   => array( '7.0-alpha-61215-src', '7.0' ),
			'single component'                             => array( '7', '7.0' ),
		);
	}

	/**
	 * Tests that wp_get_wp_version( 'major' ) returns the expected major version.
	 *
	 * @ticket 64830
	 */
	public function test_wp_get_wp_version_major() {
		$expected = wp_get_branch_version( wp_get_wp_version() );
		$this->assertSame( $expected, wp_get_wp_version( 'major' ) );
	}

	/**
	 * Tests that wp_get_wp_version( 'minor' ) returns the expected minor version.
	 *
	 * @ticket 64830
	 */
	public function test_wp_get_wp_version_minor() {
		$full    = wp_get_wp_version();
		$parts   = preg_split( '/[.-]/', $full, 4 );
		$expected = $parts[0] . '.' . ( $parts[1] ?? '0' ) . '.' . ( $parts[2] ?? '0' );
		$this->assertSame( $expected, wp_get_wp_version( 'minor' ) );
	}

	/**
	 * Tests that wp_get_wp_version() with no argument still returns the full version.
	 *
	 * @ticket 64830
	 */
	public function test_wp_get_wp_version_full_default() {
		$this->assertSame( $GLOBALS['wp_version'], wp_get_wp_version() );
	}

	/**
	 * Tests that wp_get_wp_version( 'full' ) returns the full version.
	 *
	 * @ticket 64830
	 */
	public function test_wp_get_wp_version_full_explicit() {
		$this->assertSame( $GLOBALS['wp_version'], wp_get_wp_version( 'full' ) );
	}

	/**
	 * Tests that wp_get_branch_version() with no argument returns the current major version.
	 *
	 * @ticket 64830
	 */
	public function test_wp_get_branch_version_defaults_to_current() {
		$this->assertSame( wp_get_wp_version( 'major' ), wp_get_branch_version() );
	}
}
