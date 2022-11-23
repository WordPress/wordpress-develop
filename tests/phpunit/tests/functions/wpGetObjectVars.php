<?php

/**
 * Tests for wp_get_object_vars()
 *
 * @ticket 52738
 *
 * @group functions.php
 * @covers ::wp_get_object_vars
 */
class Tests_Functions_WpGetObjectVars extends WP_UnitTestCase {

	public function test_wp_get_object_vars() {
		$object   = (object) array( 'k' => 'v' );
		$expected = array( 'k' => 'v' );
		$this->assertSame( $expected, wp_get_object_vars( $object ) );
	}

	/**
	 * @dataProvider data_wp_get_object_vars_php_7_or_greater
	 */
	public function test_wp_get_object_vars_php_7_or_greater( $object, $expected ) {
		if ( version_compare( PHP_VERSION, '7.0.0', '<' ) ) {
			$this->markTestSkipped( 'This test can only run on PHP 7.0 or greater due to illegal member variable name.' );
		}

		$this->assertSame( $expected, wp_get_object_vars( $object ) );
	}

	public function data_wp_get_object_vars_php_7_or_greater() {
		return array(
			array(
				(object) array(
					'nb-val' => chr( 0 ),
					'k'      => 'v',
				),
				array(
					'nb-val' => chr( 0 ),
					'k'      => 'v',
				),
			),
			array(
				(object) array(
					chr( 0 ) => 'Null-byte',
					'k'      => 'v',
				),
				array( 'k' => 'v' ),
			),
			array(
				(object) array(
					chr( 0 ) . 'n' => 'Starts with null-byte',
					'k'            => 'v',
				),
				array( 'k' => 'v' ),
			),
			array(
				(object) array(
					'n' . chr( 0 ) => 'Ends with null-byte',
					'k'            => 'v',
				),
				array(
					'n' . chr( 0 ) => 'Ends with null-byte',
					'k'     => 'v',
				),
			),
		);
	}
}
