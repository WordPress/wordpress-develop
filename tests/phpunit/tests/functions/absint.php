<?php

/**
 * Tests for the absint() function.
 *
 * @group functions
 *
 * @covers ::absint
 */
class Tests_Functions_Absint extends WP_UnitTestCase {

	/**
	 * @ticket 60101
	 *
	 * @dataProvider data_absint
	 */
	public function test_absint( $test_value, $expected_value ) {
		$this->assertSame( $expected_value, absint( $test_value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Test parameters {
	 *     @type string $test_value Test value.
	 *     @type string $expected   Expected return value.
	 * }
	 */
	public function data_absint() {
		return array(
			'1 int'                 => array(
				'test_value'     => 1,
				'expected_value' => 1,
			),
			'1 string'              => array(
				'test_value'     => '1',
				'expected_value' => 1,
			),
			'-1 int'                => array(
				'test_value'     => -1,
				'expected_value' => 1,
			),
			'-1 string'             => array(
				'test_value'     => '-1',
				'expected_value' => 1,
			),
			'9.1 float'             => array(
				'test_value'     => 9.1,
				'expected_value' => 9,
			),
			'9.9 float'             => array(
				'test_value'     => 9.9,
				'expected_value' => 9,
			),
			'string'                => array(
				'test_value'     => 'string',
				'expected_value' => 0,
			),
			'string_1'              => array(
				'test_value'     => 'string_1',
				'expected_value' => 0,
			),
			'999_string'            => array(
				'test_value'     => '999_string',
				'expected_value' => 999,
			),
			'99 string with spaces' => array(
				'test_value'     => '99 string with spaces',
				'expected_value' => 99,
			),
			'99 array'              => array(
				'test_value'     => array( 99 ),
				'expected_value' => 1,
			),
			'99 string array'       => array(
				'test_value'     => array( '99' ),
				'expected_value' => 1,
			),
		);
	}
}
