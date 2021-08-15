<?php

require_once dirname( __FILE__ ) . '/abstract-testcase.php';

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
	public static function assertNotFalse( $condition, $message = '' ) {
		self::assertThat( $condition, self::logicalNot( self::isFalse() ), $message );
	}

	/**
	 * Wrapper method for the `setUpBeforeClass()` method for forward-compatibility with WP 5.9.
	 */
	public static function set_up_before_class() {
		static::setUpBeforeClass();
	}

	/**
	 * Wrapper method for the `tearDownAfterClass()` method for forward-compatibility with WP 5.9.
	 */
	public static function tear_down_after_class() {
		static::tearDownAfterClass();
	}

	/**
	 * Wrapper method for the `setUp()` method for forward-compatibility with WP 5.9.
	 */
	public function set_up() {
		static::setUp();
	}

	/**
	 * Wrapper method for the `tearDown()` method for forward-compatibility with WP 5.9.
	 */
	public function tear_down() {
		static::tearDown();
	}
}
