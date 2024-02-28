<?php

/**
 * Tests for the absint function.
 *
 * @group functions
 *
 * @covers ::absint
 */
class Tests_Functions_absint extends WP_UnitTestCase {

	/**
	 * @ticket 60101
	 *
	 * @dataProvider data_absint
	 */
	public function test_absint( $maybe_int, $expected_value ) {

		$this->assertEquals( $expected_value, absint( $maybe_int ) );
	}

	/**
	 * @ticket 60101
	 *
	 * Returns an array of test data for the `data_absint` method.
	 *
	 * @return array[] An array of test data.
	 */
	public function data_absint() {
		return array(
			'1_int'                 => array(
				'maybe_int'      => 1,
				'expected_value' => 1,
			),
			'9.1_int'               => array(
				'maybe_int'      => 9.1,
				'expected_value' => 9,
			),
			'9.9_int'               => array(
				'maybe_int'      => 9.9,
				'expected_value' => 9,
			),
			'1_string'              => array(
				'maybe_int'      => 1,
				'expected_value' => '1',
			),
			'-1_int'                => array(
				'maybe_int'      => 1,
				'expected_value' => 1,
			),
			'-1_string'             => array(
				'maybe_int'      => 1,
				'expected_value' => 1,
			),
			'string'                => array(
				'maybe_int'      => 'string',
				'expected_value' => 0,
			),
			'999_string'            => array(
				'maybe_int'      => '999_string',
				'expected_value' => 999,
			),
			'string_1'              => array(
				'maybe_int'      => 'string_1',
				'expected_value' => 0,
			),
			'99 string with spaces' => array(
				'maybe_int'      => '99 string with spaces',
				'expected_value' => 99,
			),
			'array(99)'             => array(
				'maybe_int'      => array( 99 ),
				'expected_value' => 1,
			),
			'array("99")'           => array(
				'maybe_int'      => array( '99' ),
				'expected_value' => 1,
			),
		);
	}
}
