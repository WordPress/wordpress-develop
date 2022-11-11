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

	/**
	 * @dataProvider data_wp_get_object_vars
	 *
	 * @param $object
	 * @param $expected
	 */
	public function test_wp_get_object_vars( $object, $expected ) {
		$this->assertSame( $expected, wp_get_object_vars( $object ) );
	}

	public function data_wp_get_object_vars() {
		return array(
			array(
				(object) array(
					'nb-val'   => chr( 0 ),
					'property' => 'value',
				),
				array(
					'nb-val'   => chr( 0 ),
					'property' => 'value',
				),
			),
			array(
				(object) array(
					'property' => 'value',
				),
				array( 'property' => 'value' ),
			),
			array(
				(object) array(
					chr( 0 )   => 'Null-byte',
					'property' => 'value',
				),
				array( 'property' => 'value' ),
			),
			array(
				(object) array(
					chr( 0 ) . 'name' => 'Starts with null-byte',
					'property'        => 'value',
				),
				array( 'property' => 'value' ),
			),
			array(
				(object) array(
					'name' . chr( 0 ) => 'Ends with null-byte',
					'property'        => 'value',
				),
				array(
					'name' . chr( 0 ) => 'Ends with null-byte',
					'property'        => 'value',
				),
			),
		);
	}
}
