<?php

/**
 * Tests for the WP_Option_Autoload class.
 *
 * @group option
 *
 * @covers WP_Option_Autoload
 */
class Tests_Option_wpOptionAutoload extends WP_UnitTestCase {

	/**
	 * Tests that the constants hold the values stored in the database.
	 *
	 * These strings are stored in the `autoload` column of the options table,
	 * so they must never change.
	 *
	 * @ticket 61045
	 */
	public function test_constants_match_stored_values() {
		$this->assertSame(
			array(
				'ON'         => 'on',
				'OFF'        => 'off',
				'AUTO'       => 'auto',
				'AUTO_ON'    => 'auto-on',
				'AUTO_OFF'   => 'auto-off',
				'LEGACY_YES' => 'yes',
				'LEGACY_NO'  => 'no',
			),
			( new ReflectionClass( 'WP_Option_Autoload' ) )->getConstants()
		);
	}
}
