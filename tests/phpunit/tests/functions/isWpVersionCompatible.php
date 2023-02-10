<?php

/**
 * Tests the is_wp_version_compatible() function.
 *
 * @group functions.php
 * @covers ::is_wp_version_compatible
 */
class Tests_Functions_IsWpVersionCompatible extends WP_UnitTestCase {
	/**
	 * Tests is_wp_version_compatible().
	 *
	 * @dataProvider data_is_wp_version_compatible
	 *
	 * @ticket 54257
	 *
	 * @param mixed $required The minimum required WordPress version.
	 * @param bool  $expected The expected result.
	 */
	public function test_is_wp_version_compatible( $required, $expected ) {
		$this->assertSame( $expected, is_wp_version_compatible( $required ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_is_wp_version_compatible() {
		global $wp_version;

		$version_parts  = explode( '.', $wp_version );
		$lower_version  = $version_parts;
		$higher_version = $version_parts;

		// Adjust the major version numbers.
		--$lower_version[0];
		++$higher_version[0];

		$lower_version  = implode( '.', $lower_version );
		$higher_version = implode( '.', $higher_version );

		return array(
			// Happy paths.
			'the same version'          => array(
				'required' => $wp_version,
				'expected' => true,
			),
			'a lower required version'  => array(
				'required' => $lower_version,
				'expected' => true,
			),
			'a higher required version' => array(
				'required' => $higher_version,
				'expected' => false,
			),

			// Falsey values.
			'false'                     => array(
				'required' => false,
				'expected' => true,
			),
			'null'                      => array(
				'required' => null,
				'expected' => true,
			),
			'0 int'                     => array(
				'required' => 0,
				'expected' => true,
			),
			'0.0 float'                 => array(
				'required' => 0.0,
				'expected' => true,
			),
			'0 string'                  => array(
				'required' => '0',
				'expected' => true,
			),
			'empty string'              => array(
				'required' => '',
				'expected' => true,
			),
			'empty array'               => array(
				'required' => array(),
				'expected' => true,
			),
		);
	}

	/**
	 * Tests is_wp_version_compatible() with development versions.
	 *
	 * @dataProvider data_is_wp_version_compatible_with_development_versions
	 *
	 * @ticket 54257
	 *
	 * @param string $required  The minimum required WordPress version.
	 * @param string $wp        The value for the $wp_version global variable.
	 * @param bool   $expected  The expected result.
	 */
	public function test_is_wp_version_compatible_with_development_versions( $required, $wp, $expected ) {
		global $wp_version;

		$original_version = $wp_version;
		$wp_version       = $wp;
		$actual           = is_wp_version_compatible( $required );

		// Reset the version before the assertion in case of failure.
		$wp_version = $original_version;

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_is_wp_version_compatible_with_development_versions() {
		global $wp_version;

		// For consistent results, remove possible suffixes.
		list( $version ) = explode( '-', $wp_version );

		$version_parts  = explode( '.', $version );
		$lower_version  = $version_parts;
		$higher_version = $version_parts;

		// Adjust the major version numbers.
		--$lower_version[0];
		++$higher_version[0];

		$lower_version  = implode( '.', $lower_version );
		$higher_version = implode( '.', $higher_version );

		return array(
			'a lower required version and an alpha wordpress version' => array(
				'required' => $lower_version,
				'wp'       => $version . '-alpha-12341-src',
				'expected' => true,
			),
			'a lower required version and a beta wordpress version'   => array(
				'required' => $lower_version,
				'wp'       => $version . '-beta1',
				'expected' => true,
			),
			'a lower required version and a release candidate wordpress version'   => array(
				'required' => $lower_version,
				'wp'       => $version . '-RC1',
				'expected' => true,
			),
			'the same required version and an alpha wordpress version' => array(
				'required' => $version,
				'wp'       => $version . '-alpha-12341-src',
				'expected' => true,
			),
			'the same required version and a beta wordpress version' => array(
				'required' => $version,
				'wp'       => $version . '-beta1',
				'expected' => true,
			),
			'the same required version and a release candidate wordpress version' => array(
				'required' => $version,
				'wp'       => $version . '-RC1',
				'expected' => true,
			),
			'a higher required version and an alpha wordpress version'   => array(
				'required' => $higher_version,
				'wp'       => $version . '-alpha-12341-src',
				'expected' => false,
			),
			'a higher required version and a beta wordpress version'   => array(
				'required' => $higher_version,
				'wp'       => $version . '-beta1',
				'expected' => false,
			),
			'a higher required version and a release candidate wordpress version'   => array(
				'required' => $higher_version,
				'wp'       => $version . '-RC1',
				'expected' => false,
			),
		);
	}
}
