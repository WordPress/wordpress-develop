<?php

/**
 * Tests the is_php_version_compatible() function.
 *
 * @group functions.php
 * @covers ::is_php_version_compatible
 */
class Tests_Functions_IsPhpVersionCompatible extends WP_UnitTestCase {
	/**
	 * Tests is_php_version_compatible().
	 *
	 * @dataProvider data_is_php_version_compatible
	 *
	 * @ticket 54257
	 *
	 * @param mixed $required The minimum required PHP version.
	 * @param bool  $expected The expected result.
	 */
	public function test_is_php_version_compatible( $required, $expected ) {
		$this->assertSame( $expected, is_php_version_compatible( $required ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_is_php_version_compatible() {
		$php_version = PHP_VERSION;

		$version_parts  = explode( '.', $php_version );
		$lower_version  = $version_parts;
		$higher_version = $version_parts;

		// Adjust the major version numbers.
		--$lower_version[0];
		++$higher_version[0];

		$lower_version  = implode( '.', $lower_version );
		$higher_version = implode( '.', $higher_version );

		return array(
			// Happy paths.
			'a lower required version'  => array(
				'required' => $lower_version,
				'expected' => true,
			),
			'the same version'          => array(
				'required' => $php_version,
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
}
