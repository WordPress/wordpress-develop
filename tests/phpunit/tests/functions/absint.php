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
	 * @ticket 65826
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
			'zero'                         => array(
				'test_value'     => 0,
				'expected_value' => 0,
			),
			'1 int'                        => array(
				'test_value'     => 1,
				'expected_value' => 1,
			),
			'1 string'                     => array(
				'test_value'     => '1',
				'expected_value' => 1,
			),
			'-1 int'                       => array(
				'test_value'     => -1,
				'expected_value' => 1,
			),
			'-1 string'                    => array(
				'test_value'     => '-1',
				'expected_value' => 1,
			),
			'9.1 float'                    => array(
				'test_value'     => 9.1,
				'expected_value' => 9,
			),
			'9.9 float'                    => array(
				'test_value'     => 9.9,
				'expected_value' => 9,
			),
			'string'                       => array(
				'test_value'     => 'string',
				'expected_value' => 0,
			),
			'string_1'                     => array(
				'test_value'     => 'string_1',
				'expected_value' => 0,
			),
			'999_string'                   => array(
				'test_value'     => '999_string',
				'expected_value' => 999,
			),
			'99 string with spaces'        => array(
				'test_value'     => '99 string with spaces',
				'expected_value' => 99,
			),
			'99 array'                     => array(
				'test_value'     => array( 99 ),
				'expected_value' => 1,
			),
			'99 string array'              => array(
				'test_value'     => array( '99' ),
				'expected_value' => 1,
			),
			'PHP_INT_MAX int'              => array(
				'test_value'     => PHP_INT_MAX,
				'expected_value' => PHP_INT_MAX,
			),
			'PHP_INT_MIN int'              => array(
				'test_value'     => PHP_INT_MIN,
				'expected_value' => PHP_INT_MAX,
			),
			// The adjacent value is representable, so it does not need clamping.
			'PHP_INT_MIN + 1 int'          => array(
				'test_value'     => PHP_INT_MIN + 1,
				'expected_value' => PHP_INT_MAX,
			),
			'PHP_INT_MAX string'           => array(
				'test_value'     => '9223372036854775807',
				'expected_value' => PHP_INT_MAX,
			),
			'PHP_INT_MIN string'           => array(
				'test_value'     => '-9223372036854775808',
				'expected_value' => PHP_INT_MAX,
			),
			/*
			 * Casting a numeric string beyond the integer range clamps without
			 * overflowing, so this needs no special handling. PHP 8.5.0 and 8.5.1
			 * warned here, which was reverted in PHP 8.5.2.
			 */
			'PHP_INT_MAX * 1000 string'    => array(
				'test_value'     => '9223372036854775807000',
				'expected_value' => PHP_INT_MAX,
			),
			'out of range negative string' => array(
				'test_value'     => '-99999999999999999999',
				'expected_value' => PHP_INT_MAX,
			),
			'out of range positive string' => array(
				'test_value'     => '99999999999999999999',
				'expected_value' => PHP_INT_MAX,
			),
			'out of range float string'    => array(
				'test_value'     => '1.0e30',
				'expected_value' => PHP_INT_MAX,
			),
			'out of range negative float'  => array(
				'test_value'     => -1.0e30,
				'expected_value' => PHP_INT_MAX,
			),
			'out of range positive float'  => array(
				'test_value'     => 1.0e30,
				'expected_value' => PHP_INT_MAX,
			),
			'PHP_INT_MAX as a float'       => array(
				'test_value'     => (float) PHP_INT_MAX,
				'expected_value' => PHP_INT_MAX,
			),
			'PHP_INT_MIN as a float'       => array(
				'test_value'     => (float) PHP_INT_MIN,
				'expected_value' => PHP_INT_MAX,
			),
			/*
			 * Integer arithmetic silently overflows to a float, which is the most
			 * likely way an out of range value reaches this function.
			 */
			'PHP_INT_MAX + 1'              => array(
				'test_value'     => PHP_INT_MAX + 1,
				'expected_value' => PHP_INT_MAX,
			),
			'PHP_INT_MIN - 1'              => array(
				'test_value'     => PHP_INT_MIN - 1,
				'expected_value' => PHP_INT_MAX,
			),
			/*
			 * Overflowing to exactly 2**64 wraps to 0 when cast, rather than to an
			 * arbitrary value, so it is easily mistaken for a legitimate result.
			 */
			'PHP_INT_MAX * 2'              => array(
				'test_value'     => PHP_INT_MAX * 2,
				'expected_value' => PHP_INT_MAX,
			),
			'PHP_INT_MIN * 2'              => array(
				'test_value'     => PHP_INT_MIN * 2,
				'expected_value' => PHP_INT_MAX,
			),
			'in range float'               => array(
				'test_value'     => 2.0 ** 63 - 2048.0,
				'expected_value' => 9223372036854773760,
			),
			'INF'                          => array(
				'test_value'     => INF,
				'expected_value' => PHP_INT_MAX,
			),
			'-INF'                         => array(
				'test_value'     => -INF,
				'expected_value' => PHP_INT_MAX,
			),
			'NAN'                          => array(
				'test_value'     => NAN,
				'expected_value' => 0,
			),
		);
	}
}
