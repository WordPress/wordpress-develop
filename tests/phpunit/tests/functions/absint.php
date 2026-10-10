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
	 *
	 * @param mixed            $test_value
	 * @param non-negative-int $expected_value
	 */
	public function test_absint( $test_value, int $expected_value ) {
		$this->assertSame( $expected_value, absint( $test_value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ test_value: mixed, expected_value: non-negative-int }>
	 */
	public function data_absint(): array {
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

	/**
	 * Tests the remaining scalar types, null, and the empty array, along with
	 * large floats that are still within the integer range.
	 *
	 * @ticket 65826
	 *
	 * @dataProvider data_absint_other_types
	 *
	 * @param mixed            $test_value     Test value.
	 * @param non-negative-int $expected_value Expected return value.
	 */
	public function test_absint_other_types( $test_value, int $expected_value ): void {
		$this->assertSame( $expected_value, absint( $test_value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ test_value: mixed, expected_value: non-negative-int }>
	 */
	public function data_absint_other_types(): array {
		/*
		 * The largest power of two which fits in an integer, and which is therefore
		 * also exactly representable as a float: 2^62 on 64-bit builds, 2^30 on
		 * 32-bit builds. A literal would be parsed as a float on 32-bit builds.
		 */
		$large_in_range_value = ( PHP_INT_MAX >> 1 ) + 1;

		return array(
			'null'                          => array(
				'test_value'     => null,
				'expected_value' => 0,
			),
			'true'                          => array(
				'test_value'     => true,
				'expected_value' => 1,
			),
			'false'                         => array(
				'test_value'     => false,
				'expected_value' => 0,
			),
			'empty array'                   => array(
				'test_value'     => array(),
				'expected_value' => 0,
			),
			'large in-range float'          => array(
				'test_value'     => (float) $large_in_range_value,
				'expected_value' => $large_in_range_value,
			),
			'large in-range negative float' => array(
				'test_value'     => (float) -$large_in_range_value,
				'expected_value' => $large_in_range_value,
			),
		);
	}

	/**
	 * Tests that an object is converted to `1`, with a notice (PHP 7) or
	 * warning (PHP 8) about the object to int conversion.
	 *
	 * @ticket 65826
	 */
	public function test_absint_object(): void {
		$error = null;

		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$error ): bool {
				$error = $errstr;
				return true;
			}
		);
		$actual = absint( new stdClass() );
		restore_error_handler();

		$this->assertSame( 1, $actual );
		$this->assertSame( 'Object of class stdClass could not be converted to int', $error );
	}

	/**
	 * Tests that values which used to make `abs()` overflow to a float
	 * return the closest possible integer instead of causing a fatal error.
	 *
	 * `(float) PHP_INT_MAX` is equal to the float previously returned for
	 * these values, so this is the most backward compatible integer result.
	 *
	 * Numeric strings which exceed the integer range are saturated to
	 * `PHP_INT_MIN` / `PHP_INT_MAX` by the string to int conversion, and strings
	 * which exceed the float range become `INF`, which converts to `0`.
	 *
	 * @ticket 65826
	 *
	 * @dataProvider data_absint_extreme_values
	 *
	 * @param mixed            $test_value     Test value.
	 * @param non-negative-int $expected_value Expected return value.
	 */
	public function test_absint_extreme_values( $test_value, int $expected_value ): void {
		$this->assertSame( $expected_value, absint( $test_value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ test_value: mixed, expected_value: non-negative-int }>
	 */
	public function data_absint_extreme_values(): array {
		return array(
			'PHP_INT_MAX'                             => array(
				'test_value'     => PHP_INT_MAX,
				'expected_value' => PHP_INT_MAX,
			),
			'PHP_INT_MIN'                             => array(
				'test_value'     => PHP_INT_MIN,
				'expected_value' => PHP_INT_MAX,
			),
			'string below the integer range'          => array(
				'test_value'     => '-99999999999999999999',
				'expected_value' => PHP_INT_MAX,
			),
			'string above the integer range'          => array(
				'test_value'     => '99999999999999999999',
				'expected_value' => PHP_INT_MAX,
			),
			'float string below the integer range'    => array(
				'test_value'     => '-99999999999999999999.9',
				'expected_value' => PHP_INT_MAX,
			),
			'float string above the integer range'    => array(
				'test_value'     => '99999999999999999999.9',
				'expected_value' => PHP_INT_MAX,
			),
			'exponent string above the integer range' => array(
				'test_value'     => '1e20',
				'expected_value' => PHP_INT_MAX,
			),
			'exponent string above the float range'   => array(
				'test_value'     => '1e309',
				'expected_value' => 0,
			),
		);
	}

	/**
	 * Tests that floats which cannot be represented as an integer are capped
	 * at `PHP_INT_MAX`, and that non-finite floats return `0`, without an
	 * out of range float to int cast, which warns as of PHP 8.5.
	 *
	 * @ticket 65826
	 *
	 * @dataProvider data_absint_unrepresentable_floats
	 *
	 * @param float            $test_value     Test value.
	 * @param non-negative-int $expected_value Expected return value.
	 */
	public function test_absint_unrepresentable_floats( float $test_value, int $expected_value ): void {
		$this->assertSame( $expected_value, absint( $test_value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ test_value: float, expected_value: non-negative-int }>
	 */
	public function data_absint_unrepresentable_floats(): array {
		return array(
			'(float) PHP_INT_MAX (2^63)'  => array(
				'test_value'     => (float) PHP_INT_MAX,
				'expected_value' => PHP_INT_MAX,
			),
			'(float) PHP_INT_MIN (-2^63)' => array(
				'test_value'     => (float) PHP_INT_MIN,
				'expected_value' => PHP_INT_MAX,
			),
			'1.0e20'                      => array(
				'test_value'     => 1.0e20,
				'expected_value' => PHP_INT_MAX,
			),
			'-1.0e20'                     => array(
				'test_value'     => -1.0e20,
				'expected_value' => PHP_INT_MAX,
			),
			'INF'                         => array(
				'test_value'     => INF,
				'expected_value' => 0,
			),
			'NAN'                         => array(
				'test_value'     => NAN,
				'expected_value' => 0,
			),
		);
	}
}
