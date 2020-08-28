<?php
/**
 * Trait that contains any new/deprecated/removed functionality in PHPUnit 9
 */

trait WP_PHPUnit9_Compat {

	// New
	function _assertStringContainsString( $needle, $haystack, $message = '' ) {
		// In older versions of PHPUnit, we can just pass through to assertContains.
		$this->assertContains( $needle, $haystack, $message );
	}

	// New
	function _assertStringNotContainsString( $needle, $haystack, $message = '' ) {
		// In older versions of PHPUnit, we can just pass through to assertContains.
		$this->assertNotContains( $needle, $haystack, $message );
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
	 * @ignore
	 *
	 * @throws ExpectationFailedException
	 * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
	 */
	function _assertMatchesRegularExpression( $pattern, $string, $message = '' ) {
		$this->assertRegExp( $pattern, $string, $message );
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
	 * @ignore
	 *
	 * @throws ExpectationFailedException
	 * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
	 */
	function _assertDoesNotMatchRegularExpression( $pattern, $string, $message = '' ) {
		$this->assertNotRegExp( $pattern, $string, $message );
	}

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
	 * @ignore
	 *
	 * @throws ExpectationFailedException
	 * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
	 */
	function _assertFileDoesNotExist( $file, $message = '' ) {
		$this->assertFileNotExists( $file, $message );
	}

	// Removed
	function _assertInternalType( $expected, $actual, $message = '' ) {
		if ( 'integer' === $expected ) {
			$expected = 'int';
		} elseif ( 'boolean' === $expected ) {
			$expected = 'bool';
		}

		$method = "assertIs{$expected}";

		$this->$method( $actual, $message );
	}

	// Removed
	function _assertNotInternalType( $expected, $actual, $message = '' ) {
		if ( 'integer' === $expected ) {
			$expected = 'int';
		} elseif ( 'boolean' === $expected ) {
			$expected = 'bool';
		}

		$method = "assertIsNot{$expected}";

		$this->$method( $actual, $message );
	}

}
