<?php

require_once __DIR__ . '/abstract-testcase.php';

/**
 * Defines a basic fixture to run multiple tests.
 *
 * Resets the state of the WordPress installation before and after every test.
 *
 * Includes utility functions and assertions useful for testing WordPress.
 *
 * All WordPress unit tests should inherit from this class.
 */
class WP_UnitTestCase extends WP_UnitTestCase_Base {

	/**
	 * Asserts that two variables are equal (with delta).
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.6.0
	 *
	 * @param mixed  $expected First value to compare.
	 * @param mixed  $actual   Second value to compare.
	 * @param float  $delta    Allowed numerical distance between two values to consider them equal.
	 * @param string $message  Optional. Message to display when the assertion fails.
	 *
	 * @throws ExpectationFailedException
	 * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
	 */
	public static function assertEqualsWithDelta( $expected, $actual, $delta, $message = '' ) {
		$constraint = new PHPUnit_Framework_Constraint_IsEqual(
			$expected,
			$delta
		);

		static::assertThat( $actual, $constraint, $message );
	}
}
