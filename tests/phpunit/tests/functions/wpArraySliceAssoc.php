<?php

/**
 * Tests wp_array_slice_assoc function
 *
 * @since 5.3.0
 *
 * @group functions.php
 * @covers ::wp_array_slice_assoc
 */
class Tests_Functions_wpArraySliceAssoc extends WP_UnitTestCase {

	/**
	 * Tests wp_array_slice_assoc().
	 *
	 * @dataProvider data_wp_array_slice_assoc_arrays
	 *
	 * @ticket 46638
	 *
	 * @param array $target_array The original array.
	 * @param array $keys         The list of keys.
	 * @param array $expected     The expected result.
	 */
	public function test_wp_array_slice_assoc( $target_array, $keys, $expected ) {
		$this->assertSame( $expected, wp_array_slice_assoc( $target_array, $keys ) );
	}

	/**
	 * Data provider for wp_array_slice_assoc().
	 *
	 * @return array
	 */
	public function data_wp_array_slice_assoc_arrays() {
		return array(
			array(
				array( 1 => 1 ),
				array( 1 ),
				array( 1 => 1 ),
			),
			array(
				array( 1 => 1 ),
				array( 0 ),
				array(),
			),
			array(
				array( 1 => array( 1 => 1 ) ),
				array( 1 ),
				array( 1 => array( 1 => 1 ) ),
			),
			array(
				array(
					1 => 1,
					2 => 2,
				),
				array( 1 ),
				array( 1 => 1 ),
			),
			array(
				array(
					1 => 1,
					2 => 2,
				),
				array( 2 ),
				array( 2 => 2 ),
			),
			array(
				array(
					1 => 1,
					2 => 2,
				),
				array( 1, 1 ),
				array( 1 => 1 ),
			),
			array(
				array( 1 => array( 1 => array( 1 => 1 ) ) ),
				array( 1 ),
				array( 1 => array( 1 => array( 1 => 1 ) ) ),
			),
			array(
				array(
					1 => 1,
					2 => 2,
				),
				array( 1, 2 ),
				array(
					1 => 1,
					2 => 2,
				),
			),
			array(
				array(
					'1' => '1',
					'2' => '2',
				),
				array( '1' ),
				array( '1' => '1' ),
			),
			array(
				array(
					'1' => '1',
					'2' => '2',
				),
				array( '2' ),
				array( '2' => '2' ),
			),
			array(
				array(
					'1' => '1',
					'2' => '2',
				),
				array( 1 ),
				array( '1' => '1' ),
			),
			array(
				array(
					'1' => '1',
					'2' => '2',
				),
				array( 1 ),
				array( '1' => '1' ),
			),
			array(
				array( 1 => 1 ),
				array( '1' ),
				array( 1 => 1 ),
			),
		);
	}
}
