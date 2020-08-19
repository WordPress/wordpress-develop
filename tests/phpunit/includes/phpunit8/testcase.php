<?php
/**
 * This file is for PHPUnit 8+, it uses PHP 7.2 syntax for the PHPUnit functions.
 */

require_once dirname( __DIR__ ) . '/phpunit-compat-traits/trait-wp-php72-test-framework.php';

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
	use WP_PHP72_Test_Framework;
	use WP_PHPUnit_Compat;
}
