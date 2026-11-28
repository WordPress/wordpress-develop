<?php

/**
 * Tests for the apache_mod_loaded function.
 *
 * @group Functions.php
 *
 * @covers ::apache_mod_loaded
 */
class Tests_Functions_apacheModLoaded extends WP_UnitTestCase {

	/**
	 * @ticket 60054
	 *
	 * @dataProvider data_apache_mod_loaded
	 */
	public function test_apache_mod_loaded( $mod, $default_value, $expected ) {

		$this->assertSame( $expected, apache_mod_loaded( $mod, $default_value ) );
	}

	/**
	 * Returns the data provider array for the test function test_apache_mod_loaded.
	 *
	 * @return array The data provider array.
	 */
	public function data_apache_mod_loaded() {
		global $is_apache;

		return array(
			'default'       => array(
				'mod'      => 'mod_rewrite',
				'default'  => null,
				'expected' => $is_apache,
			),
			'missing_mod'   => array(
				'mod'      => 'missing_mod',
				'default'  => null,
				'expected' => false,
			),
			'default_value' => array(
				'mod'      => 'missing_mod',
				'default'  => 'default_value',
				'expected' => ( ! $is_apache ) ? false : 'default_value',
			),
		);
	}
}
