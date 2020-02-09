<?php

require_once dirname( __DIR__ ) . '/abstract-testcase.php';

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
	 * Asserts that a condition is not false.
	 *
	 * This method has been backported from a more recent PHPUnit version, as tests running on PHP 5.2 use
	 * PHPUnit 3.6.x.
	 *
	 * @since 4.7.4
	 *
	 * @param bool   $condition Condition to check.
	 * @param string $message   Optional. Message to display when the assertion fails.
	 *
	 * @throws PHPUnit_Framework_AssertionFailedError
	 */
	public static function assertNotFalse( $condition, string $message = '' ): void {
		self::assertThat( $condition, self::logicalNot( self::isFalse() ), $message );
	}
}
