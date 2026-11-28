<?php
/**
 * Tests for WpdbQueryDynamicMethodReturnTypeExtension.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests;

use PHPStan\Testing\TypeInferenceTestCase;

/**
 * Checks the types PHPStan infers for the results of wpdb queries against the `assertType()`
 * calls in the fixtures.
 */
final class WpdbQueryDynamicMethodReturnTypeExtensionTest extends TypeInferenceTestCase {

	/**
	 * Gathers the assertions from the fixtures.
	 *
	 * @return iterable<string, mixed[]>
	 */
	public function data_file_asserts(): iterable {
		yield from self::gatherAssertTypes( __DIR__ . '/data/wpdb.php' );
	}

	/**
	 * @dataProvider data_file_asserts
	 *
	 * @param string $assert_type Kind of assertion.
	 * @param string $file        Fixture file.
	 * @param mixed  ...$args     Assertion arguments.
	 */
	public function test_file_asserts( string $assert_type, string $file, ...$args ): void {
		$this->assertFileAsserts( $assert_type, $file, ...$args );
	}

	/**
	 * Returns the configuration registering the extension and the visitor it relies on.
	 *
	 * @return list<string>
	 */
	public static function getAdditionalConfigFiles(): array {
		return array( __DIR__ . '/tests.neon' );
	}
}
