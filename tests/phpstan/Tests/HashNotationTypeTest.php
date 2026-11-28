<?php
/**
 * Tests the types PHPStan infers once HashNotationVisitor has translated a hash.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests;

use PHPStan\Testing\TypeInferenceTestCase;

/**
 * Checks the `assertType()` calls in the hash notation fixture.
 */
final class HashNotationTypeTest extends TypeInferenceTestCase {

	/**
	 * Gathers the assertions from the fixture.
	 *
	 * @return iterable<string, mixed[]>
	 */
	public function data_file_asserts(): iterable {
		yield from self::gatherAssertTypes( __DIR__ . '/data/hash-notation.php' );
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
	 * Returns the configuration registering the visitor.
	 *
	 * @return list<string>
	 */
	public static function getAdditionalConfigFiles(): array {
		return array( __DIR__ . '/tests.neon' );
	}
}
