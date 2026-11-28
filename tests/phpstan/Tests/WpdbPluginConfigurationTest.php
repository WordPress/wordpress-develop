<?php
/**
 * Tests for WpdbQueryDynamicMethodReturnTypeExtension as configured for a plugin or theme.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests;

use PHPStan\Testing\TypeInferenceTestCase;

/**
 * Checks the types PHPStan infers for the results of wpdb queries with `wpdbTablesMatchSchema` false and tables
 * declared in `wpdbTables`, against the `assertType()` calls in the fixture.
 */
final class WpdbPluginConfigurationTest extends TypeInferenceTestCase {

	/**
	 * Gathers the assertions from the fixture.
	 *
	 * @return iterable<string, mixed[]>
	 */
	public function data_file_asserts(): iterable {
		yield from self::gatherAssertTypes( __DIR__ . '/data/wpdb-plugin.php' );
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
	 * Returns the configuration registering the extension, as configured for a plugin or theme.
	 *
	 * @return list<string>
	 */
	public static function getAdditionalConfigFiles(): array {
		return array( __DIR__ . '/tests.neon', __DIR__ . '/tests-plugin.neon' );
	}
}
