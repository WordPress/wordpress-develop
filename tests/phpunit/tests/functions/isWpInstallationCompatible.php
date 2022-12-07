<?php

/**
 * Tests the is_wp_installation_compatible() function.
 *
 * @group functions.php
 * @covers ::is_wp_installation_compatible
 */
class Tests_Functions_IsWpInstallationCompatible extends WP_UnitTestCase {
	/**
	 * Tests is_wp_installation_compatible().
	 *
	 * @dataProvider data_is_wp_installation_compatible
	 *
	 * @param mixed $required The required WordPress installation, true for multisite false otherwise.
	 * @param bool  $expected The expected result.
	 */
	public function test_is_wp_installation_compatible( $required, $expected ) {
		$this->assertSame( $expected, is_wp_installation_compatible( $required ) );
	}

	/**
	 * Data provider for test_is_wp_installation_compatible()
	 *
	 * @return array
	 */
	public function data_is_wp_installation_compatible() {

		return array(
			'true'         => array(
				'required' => true,
				'expected' => is_multisite(),
			),
			'false'        => array(
				'required' => false,
				'expected' => true,
			),
			'1 int'        => array(
				'required' => 1,
				'expected' => is_multisite(),
			),
			'0 int'        => array(
				'required' => 0,
				'expected' => true,
			),
			'empty string' => array(
				'required' => '',
				'expected' => true,
			),
			'null'         => array(
				'required' => null,
				'expected' => true,
			),
			'true string'  => array(
				'required' => 'true',
				'expected' => is_multisite(),
			),
			'false string' => array(
				'required' => 'false',
				'expected' => true,
			),
			'1 string'     => array(
				'required' => '1',
				'expected' => is_multisite(),
			),
			'0 string'     => array(
				'required' => '0',
				'expected' => true,
			),
		);
	}
}
