<?php

/**
 * @group compat
 *
 * @covers ::clamp
 */
class Tests_Compat_clamp extends WP_UnitTestCase {

	/**
	 * Tests that clamp() is always available (either from PHP or WP).
	 *
	 * @ticket 65143
	 */
	public function test_clamp_availability(): void {
		$this->assertTrue( function_exists( 'clamp' ) );
	}

	/**
	 * Tests clamp().
	 *
	 * @ticket 65143
	 *
	 * @dataProvider data_clamp
	 *
	 * @param mixed $expected The expected clamped value.
	 * @param mixed $value    The value to clamp.
	 * @param mixed $min      The minimum bound.
	 * @param mixed $max      The maximum bound.
	 */
	public function test_clamp( $expected, $value, $min, $max ): void {
		$this->assertSame( $expected, clamp( $value, $min, $max ) );
	}

	/**
	 * Provides data for {@see self::test_clamp()}.
	 *
	 * @return array<string, array{ expected: mixed, value: mixed, min: mixed, max: mixed }>
	 */
	public function data_clamp(): array {
		return array(
			'integer within range'           => array(
				'expected' => 5,
				'value'    => 5,
				'min'      => 1,
				'max'      => 10,
			),
			'integer below min'              => array(
				'expected' => 1,
				'value'    => -5,
				'min'      => 1,
				'max'      => 10,
			),
			'integer above max'              => array(
				'expected' => 10,
				'value'    => 99,
				'min'      => 1,
				'max'      => 10,
			),
			'integer equals min'             => array(
				'expected' => 1,
				'value'    => 1,
				'min'      => 1,
				'max'      => 10,
			),
			'integer equals max'             => array(
				'expected' => 10,
				'value'    => 10,
				'min'      => 1,
				'max'      => 10,
			),
			'min equals max, value matches'  => array(
				'expected' => 5,
				'value'    => 5,
				'min'      => 5,
				'max'      => 5,
			),
			'min equals max, value below'    => array(
				'expected' => 5,
				'value'    => 3,
				'min'      => 5,
				'max'      => 5,
			),
			'min equals max, value above'    => array(
				'expected' => 5,
				'value'    => 7,
				'min'      => 5,
				'max'      => 5,
			),
			'float within range'             => array(
				'expected' => 0.5,
				'value'    => 0.5,
				'min'      => 0.0,
				'max'      => 1.0,
			),
			'float below min'                => array(
				'expected' => 0.0,
				'value'    => -0.5,
				'min'      => 0.0,
				'max'      => 1.0,
			),
			'float above max'                => array(
				'expected' => 1.0,
				'value'    => 1.5,
				'min'      => 0.0,
				'max'      => 1.0,
			),
			'negative range, within'         => array(
				'expected' => -5,
				'value'    => -5,
				'min'      => -10,
				'max'      => -1,
			),
			'negative range, below min'      => array(
				'expected' => -10,
				'value'    => -99,
				'min'      => -10,
				'max'      => -1,
			),
			'negative range, above max'      => array(
				'expected' => -1,
				'value'    => 0,
				'min'      => -10,
				'max'      => -1,
			),
			'zero within range'              => array(
				'expected' => 0,
				'value'    => 0,
				'min'      => -1,
				'max'      => 1,
			),
			'mixed int/float, within range'  => array(
				'expected' => 5,
				'value'    => 5,
				'min'      => 0.0,
				'max'      => 10.0,
			),
			'INF as value'                   => array(
				'expected' => 100,
				'value'    => INF,
				'min'      => 0,
				'max'      => 100,
			),
			'-INF as value'                  => array(
				'expected' => 0,
				'value'    => -INF,
				'min'      => 0,
				'max'      => 100,
			),
			'INF as max, value within range' => array(
				'expected' => 50,
				'value'    => 50,
				'min'      => 0,
				'max'      => INF,
			),
			'INF as max, value equals INF'   => array(
				'expected' => INF,
				'value'    => INF,
				'min'      => 0,
				'max'      => INF,
			),
			'string within range'            => array(
				'expected' => 'l',
				'value'    => 'l',
				'min'      => 'a',
				'max'      => 'z',
			),
			'string below min'               => array(
				'expected' => 'e',
				'value'    => 'a',
				'min'      => 'e',
				'max'      => 'z',
			),
			'string above max'               => array(
				'expected' => 'p',
				'value'    => 'z',
				'min'      => 'a',
				'max'      => 'p',
			),
		);
	}

	/**
	 * Tests clamp() with DateTimeImmutable.
	 *
	 * @ticket 65143
	 *
	 * @dataProvider data_clamp_datetime
	 *
	 * @param DateTimeImmutable $expected The expected clamped value.
	 * @param DateTimeImmutable $value    The value to clamp.
	 * @param DateTimeImmutable $min      The minimum bound.
	 * @param DateTimeImmutable $max      The maximum bound.
	 */
	public function test_clamp_with_datetime( DateTimeImmutable $expected, DateTimeImmutable $value, DateTimeImmutable $min, DateTimeImmutable $max ): void {
		$this->assertEquals( $expected, clamp( $value, $min, $max ) );
	}

	/**
	 * Provides data for {@see self::test_clamp_with_datetime()}.
	 *
	 * @return array<string, array{ expected: DateTimeImmutable, value: DateTimeImmutable, min: DateTimeImmutable, max: DateTimeImmutable }>
	 */
	public function data_clamp_datetime(): array {
		return array(
			'within range' => array(
				'expected' => new DateTimeImmutable( '2025-01-15' ),
				'value'    => new DateTimeImmutable( '2025-01-15' ),
				'min'      => new DateTimeImmutable( '2025-01-01' ),
				'max'      => new DateTimeImmutable( '2025-01-31' ),
			),
			'below min'    => array(
				'expected' => new DateTimeImmutable( '2025-01-01' ),
				'value'    => new DateTimeImmutable( '2024-12-01' ),
				'min'      => new DateTimeImmutable( '2025-01-01' ),
				'max'      => new DateTimeImmutable( '2025-01-31' ),
			),
			'above max'    => array(
				'expected' => new DateTimeImmutable( '2025-01-31' ),
				'value'    => new DateTimeImmutable( '2025-03-01' ),
				'min'      => new DateTimeImmutable( '2025-01-01' ),
				'max'      => new DateTimeImmutable( '2025-01-31' ),
			),
		);
	}

	/**
	 * Tests clamp() when a bound is not comparable by the usual ordering rules.
	 *
	 * Comparison in PHP is not transitive when operands of different types are mixed. For
	 * example, comparing an int against null coerces both operands to bool, so `-1 < null`
	 * is false while `null < 0` is also false. Both bounds can therefore compare as exceeded
	 * at the same time, in which case PHP returns `$max`, since it checks the upper bound
	 * first. These expectations are taken from PHP's own clamp() tests.
	 *
	 * @ticket 65143
	 *
	 * @dataProvider data_clamp_with_null_bound
	 *
	 * @param mixed $expected The expected clamped value.
	 * @param mixed $value    The value to clamp.
	 * @param mixed $min      The minimum bound.
	 * @param mixed $max      The maximum bound.
	 */
	public function test_clamp_with_null_bound( $expected, $value, $min, $max ): void {
		$this->assertSame( $expected, clamp( $value, $min, $max ) );
	}

	/**
	 * Provides data for {@see self::test_clamp_with_null_bound()}.
	 *
	 * @return array<string, array{ expected: mixed, value: mixed, min: mixed, max: mixed }>
	 */
	public function data_clamp_with_null_bound(): array {
		return array(
			// The upper bound is checked before the lower bound, so $max wins when both compare as exceeded.
			'null max, both bounds exceeded'  => array(
				'expected' => null,
				'value'    => -1,
				'min'      => 0,
				'max'      => null,
			),
			'null value, negative min'        => array(
				'expected' => -1,
				'value'    => null,
				'min'      => -1,
				'max'      => 1,
			),
			'null value, positive min'        => array(
				'expected' => 1,
				'value'    => null,
				'min'      => 1,
				'max'      => 3,
			),
			'null value, above negative max'  => array(
				'expected' => -3,
				'value'    => null,
				'min'      => -3,
				'max'      => -1,
			),
			'null min, value within range'    => array(
				'expected' => -9999,
				'value'    => -9999,
				'min'      => null,
				'max'      => 10,
			),
			'null min, value above max'       => array(
				'expected' => 10,
				'value'    => 12,
				'min'      => null,
				'max'      => 10,
			),
			'false max, both bounds exceeded' => array(
				'expected' => false,
				'value'    => -1,
				'min'      => 0,
				'max'      => false,
			),
		);
	}

	/**
	 * Tests that clamp() throws when a null $max compares as smaller than $min.
	 *
	 * A null $max is coerced to bool for the comparison, so it is smaller than any truthy
	 * $min. These expectations are taken from PHP's own clamp() tests.
	 *
	 * @ticket 65143
	 *
	 * @dataProvider data_clamp_throws_for_null_max
	 *
	 * @param mixed $value The value to clamp.
	 * @param mixed $min   The minimum bound.
	 */
	public function test_clamp_throws_for_null_max( $value, $min ): void {
		$this->expectException( $this->value_error_class() );
		$this->expectExceptionMessage( 'clamp(): Argument #2 ($min) must be smaller than or equal to argument #3 ($max)' );

		clamp( $value, $min, null );
	}

	/**
	 * Provides data for {@see self::test_clamp_throws_for_null_max()}.
	 *
	 * @return array<string, array{ value: mixed, min: mixed }>
	 */
	public function data_clamp_throws_for_null_max(): array {
		return array(
			'value below min' => array(
				'value' => -9999,
				'min'   => 5,
			),
			'value above min' => array(
				'value' => 12,
				'min'   => -5,
			),
		);
	}

	/**
	 * Tests that clamp() throws when $min is NAN.
	 *
	 * @ticket 65143
	 */
	public function test_clamp_throws_for_nan_min(): void {
		$this->expectException( $this->value_error_class() );
		$this->expectExceptionMessage( 'clamp(): Argument #2 ($min) must not be NAN' );

		clamp( 5, NAN, 10 );
	}

	/**
	 * Tests that clamp() throws when $max is NAN.
	 *
	 * @ticket 65143
	 */
	public function test_clamp_throws_for_nan_max(): void {
		$this->expectException( $this->value_error_class() );
		$this->expectExceptionMessage( 'clamp(): Argument #3 ($max) must not be NAN' );

		clamp( 5, 0, NAN );
	}

	/**
	 * Tests that clamp() throws when $min is greater than $max.
	 *
	 * @ticket 65143
	 */
	public function test_clamp_throws_when_min_greater_than_max(): void {
		$this->expectException( $this->value_error_class() );
		$this->expectExceptionMessage( 'clamp(): Argument #2 ($min) must be smaller than or equal to argument #3 ($max)' );

		clamp( 5, 10, 1 );
	}

	/**
	 * Tests that clamp() throws when $min is INF and $max is finite.
	 *
	 * @ticket 65143
	 */
	public function test_clamp_throws_when_inf_min_greater_than_max(): void {
		$this->expectException( $this->value_error_class() );
		$this->expectExceptionMessage( 'clamp(): Argument #2 ($min) must be smaller than or equal to argument #3 ($max)' );

		clamp( 5, INF, 10 );
	}

	/**
	 * Tests that clamp() with a NAN value returns NAN (no exception).
	 *
	 * @ticket 65143
	 */
	public function test_clamp_with_nan_value_returns_nan(): void {
		$result = clamp( NAN, 0, 10 );
		$this->assertNan( $result );
	}

	/**
	 * Returns the expected exception class for ValueError-equivalent errors.
	 *
	 * ValueError is thrown whenever the class exists (PHP 8.0+ or provided by a polyfill), with
	 * InvalidArgumentException as the fallback on PHP 7.x.
	 *
	 * @return class-string<Throwable> The fully qualified exception class name.
	 */
	private function value_error_class(): string {
		return class_exists( 'ValueError', false ) ? ValueError::class : InvalidArgumentException::class;
	}
}
