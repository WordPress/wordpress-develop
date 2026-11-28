<?php
/**
 * Tests for WpdbQueryDynamicMethodReturnTypeExtension where the core tables may have more columns than the core schema.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests;

use PHPStan\Testing\TypeInferenceTestCase;

/**
 * Checks the types PHPStan infers for the results of wpdb queries with `wpdbTablesMatchSchema` false, against the
 * `assertType()` calls in the fixture.
 */
final class WpdbTablesNotMatchingSchemaTest extends TypeInferenceTestCase {

	/**
	 * Gathers the assertions from the fixture.
	 *
	 * @return iterable<string, mixed[]>
	 */
	public function data_file_asserts(): iterable {
		yield from self::gatherAssertTypes( __DIR__ . '/data/wpdb-tables-not-matching-schema.php' );
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
	 * Returns the configuration registering the extension, with `wpdbTablesMatchSchema` false.
	 *
	 * @return list<string>
	 */
	public static function getAdditionalConfigFiles(): array {
		return array( __DIR__ . '/tests.neon', __DIR__ . '/tests-tables-not-matching-schema.neon' );
	}
}
