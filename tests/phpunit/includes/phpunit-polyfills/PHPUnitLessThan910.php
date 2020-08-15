<?php

use PHPUnit\Framework\TestCase;

/**
 * Polyfills select PHPUnit functionality introduced in PHPUnit 9.1.0 to older PHPUnit versions.
 *
 * When the minimum supported PHPUnit version of the WP testsuite goes
 * beyond PHPUnit 9.1.0, this polyfill trait can be removed.
 *
 * @since 5.6.0
 */
trait PHPUnitLessThan910 {

	/**
	 * Asserts that a file does not exist.
	 *
	 * The `assertFileDoesNotExist()` method was introduced in PHPUnit 9.1.0 to
	 * replace the `assertFileNotExists()` method, which was deprecated in the
	 * same version.
	 *
	 * This polyfills the _new_ method to older PHPUnit versions.
	 *
	 * @link https://phpunit.readthedocs.io/en/7.5/assertions.html#assertfileexists Old method.
	 * @link https://phpunit.readthedocs.io/en/9.3/assertions.html#assertfileexists New method.
	 *
	 * @since 5.6.0
	 *
	 * @throws ExpectationFailedException
	 * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
	 */
	public static function assertFileDoesNotExist( string $filename, string $message = '' ): void {
		// PHPUnit >= 9.1.0: Use the PHPUnit native method under the new name.
		if ( is_callable( array( TestCase::class, 'assertFileDoesNotExist' ) ) ) {
			TestCase::assertFileDoesNotExist( $filename, $message );
			return;
		}

		// PHPUnit < 9.1.0: Use the PHPUnit native method using the old name.
		TestCase::assertFileNotExists( $filename, $message );
	}

	/**
	 * Asserts that a string matches a given regular expression.
	 *
	 * The `assertMatchesRegularExpression()` method was introduced in PHPUnit 9.1.0 to
	 * replace the `assertRegExp()` method, which was deprecated in the same version.
	 *
	 * This polyfills the _new_ method to older PHPUnit versions.
	 *
	 * @link https://phpunit.readthedocs.io/en/7.5/assertions.html#assertregexp                   Old method.
	 * @link https://phpunit.readthedocs.io/en/9.3/assertions.html#assertmatchesregularexpression New method.
	 *
	 * @since 5.6.0
	 *
	 * @throws ExpectationFailedException
	 * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
	 */
	public static function assertMatchesRegularExpression(
		string $pattern,
		string $string,
		string $message = ''
	): void {
		// PHPUnit >= 9.1.0: Use the PHPUnit native method under the new name.
		if ( is_callable( array( TestCase::class, 'assertMatchesRegularExpression' ) ) ) {
			TestCase::assertMatchesRegularExpression( $pattern, $string, $message );
			return;
		}

		// PHPUnit < 9.1.0: Use the PHPUnit native method using the old name.
		TestCase::assertRegExp( $pattern, $string, $message );
	}

	/**
	 * Asserts that a string does not match a given regular expression.
	 *
	 * The `assertDoesNotMatchRegularExpression()` method was introduced in PHPUnit 9.1.0 to
	 * replace the `assertRegExp()` method, which was deprecated in the same version.
	 *
	 * This polyfills the _new_ method to older PHPUnit versions.
	 *
	 * @link https://phpunit.readthedocs.io/en/7.5/assertions.html#assertregexp                   Old method.
	 * @link https://phpunit.readthedocs.io/en/9.3/assertions.html#assertmatchesregularexpression New method.
	 *
	 * @since 5.6.0
	 *
	 * @throws ExpectationFailedException
	 * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
	 */
	public static function assertDoesNotMatchRegularExpression(
		string $pattern,
		string $string,
		string $message = ''
	): void {
		// PHPUnit >= 9.1.0: Use the PHPUnit native method under the new name.
		if ( is_callable( array( TestCase::class, 'assertDoesNotMatchRegularExpression' ) ) ) {
			TestCase::assertDoesNotMatchRegularExpression( $pattern, $string, $message );
			return;
		}

		// PHPUnit < 9.1.0: Use the PHPUnit native method using the old name.
		TestCase::assertNotRegExp( $pattern, $string, $message );
	}

}
