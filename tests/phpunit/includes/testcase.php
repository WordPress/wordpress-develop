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
		static::assertEquals( $expected, $actual, $message, $delta );
	}

	/**
	 * Asserts that a variable is of type array.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsArray( $actual, $message = '' ) {
		static::assertInternalType( 'array', $actual, $message );
	}

	/**
	 * Asserts that a variable is of type bool.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsBool( $actual, $message = '' ) {
		static::assertInternalType( 'bool', $actual, $message );
	}

	/**
	 * Asserts that a variable is of type float.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsFloat( $actual, $message = '' ) {
		static::assertInternalType( 'float', $actual, $message );
	}

	/**
	 * Asserts that a variable is of type int.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsInt( $actual, $message = '' ) {
		static::assertInternalType( 'int', $actual, $message );
	}

	/**
	 * Asserts that a variable is of type numeric.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsNumeric( $actual, $message = '' ) {
		static::assertInternalType( 'numeric', $actual, $message );
	}

	/**
	 * Asserts that a variable is of type object.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsObject( $actual, $message = '' ) {
		static::assertInternalType( 'object', $actual, $message );
	}

	/**
	 * Asserts that a variable is of type resource.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsResource( $actual, $message = '' ) {
		static::assertInternalType( 'resource', $actual, $message );
	}

	/**
	 * Asserts that a variable is of type string.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsString( $actual, $message = '' ) {
		static::assertInternalType( 'string', $actual, $message );
	}

	/**
	 * Asserts that a variable is of type scalar.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsScalar( $actual, $message = '' ) {
		static::assertInternalType( 'scalar', $actual, $message );
	}

	/**
	 * Asserts that a variable is of type callable.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsCallable( $actual, $message = '' ) {
		static::assertInternalType( 'callable', $actual, $message );
	}

	/**
	 * Asserts that a variable is of type iterable.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * Support for `iterable` was only added to `Assert::assertNotInternalType()`
	 * in PHPUnit 7.1.0, so this polyfill cannot use a direct fall-through
	 * to that functionality until WordPress test suite requires PHPUnit 7.1.0
	 * as the minimum version.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsIterable( $actual, $message = '' ) {
		static::assertTrue( is_iterable( $actual ), $message );
	}

	/**
	 * Asserts that a variable is not of type array.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsNotArray( $actual, $message = '' ) {
		static::assertNotInternalType( 'array', $actual, $message );
	}

	/**
	 * Asserts that a variable is not of type bool.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsNotBool( $actual, $message = '' ) {
		static::assertNotInternalType( 'bool', $actual, $message );
	}

	/**
	 * Asserts that a variable is not of type float.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsNotFloat( $actual, $message = '' ) {
		static::assertNotInternalType( 'float', $actual, $message );
	}

	/**
	 * Asserts that a variable is not of type int.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsNotInt( $actual, $message = '' ) {
		static::assertNotInternalType( 'int', $actual, $message );
	}

	/**
	 * Asserts that a variable is not of type numeric.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsNotNumeric( $actual, $message = '' ) {
		static::assertNotInternalType( 'numeric', $actual, $message );
	}

	/**
	 * Asserts that a variable is not of type object.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsNotObject( $actual, $message = '' ) {
		static::assertNotInternalType( 'object', $actual, $message );
	}

	/**
	 * Asserts that a variable is not of type resource.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsNotResource( $actual, $message = '' ) {
		static::assertNotInternalType( 'resource', $actual, $message );
	}

	/**
	 * Asserts that a variable is not of type string.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsNotString( $actual, $message = '' ) {
		static::assertNotInternalType( 'string', $actual, $message );
	}

	/**
	 * Asserts that a variable is not of type scalar.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsNotScalar( $actual, $message = '' ) {
		static::assertNotInternalType( 'scalar', $actual, $message );
	}

	/**
	 * Asserts that a variable is not of type callable.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsNotCallable( $actual, $message = '' ) {
		static::assertNotInternalType( 'callable', $actual, $message );
	}

	/**
	 * Asserts that a variable is not of type iterable.
	 *
	 * This method has been backported from a more recent PHPUnit version,
	 * as tests running on PHP 5.6 use PHPUnit 5.7.x.
	 *
	 * Support for `iterable` was only added to `Assert::assertNotInternalType()`
	 * in PHPUnit 7.1.0, so this polyfill cannot use a direct fall-through
	 * to that functionality until WordPress test suite requires PHPUnit 7.1.0
	 * as the minimum version.
	 *
	 * @since 5.9.0
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public static function assertIsNotIterable( $actual, $message = '' ) {
		static::assertFalse( is_iterable( $actual ), $message );
	}
}
