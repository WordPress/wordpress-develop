<?php
/**
 * Tests for PreparedSQLPlaceholdersRule.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use WordPress\PHPStan\PreparedSQLPlaceholdersRule;

/**
 * @extends RuleTestCase<PreparedSQLPlaceholdersRule>
 */
final class PreparedSQLPlaceholdersRuleTest extends RuleTestCase {

	/**
	 * Returns the rule under test.
	 *
	 * @return PreparedSQLPlaceholdersRule
	 */
	protected function getRule(): Rule {
		return new PreparedSQLPlaceholdersRule();
	}

	/**
	 * Returns the configuration the fixtures are analyzed under.
	 *
	 * @return list<string>
	 */
	public static function getAdditionalConfigFiles(): array {
		return array( __DIR__ . '/tests.neon' );
	}

	/**
	 * Tests the fixture, whose first part is the sniff's own test file. The lines
	 * reported there are the ones PHP_CodeSniffer reports for the
	 * `UnfinishedPrepare` code.
	 */
	public function test_rule(): void {
		$this->analyse(
			array( __DIR__ . '/data/prepared-sql-placeholders.php' ),
			self::expected(
				array(
					23  => '$wpdb->prepare()',
					33  => '$wpdb->prepare()',
					34  => '$wpdb->prepare()',
					66  => '$wpdb->prepare()',
					68  => '$wpdb->prepare()',
					234 => '$wpdb->prepare()',
					246 => '$wpdb->prepare()',
					354 => '$wpdb->prepare()',
					// The sniff's test file ends above; the rest extends it.
					542 => '$wpdb->prepare()',
					543 => '$wpdb->prepare()',
					544 => '$wpdb->prepare()',
					545 => '$wpdb->prepare()',
					546 => '$wpdb?->prepare()',
					547 => 'wpdb::prepare()',
					552 => '$wpdb->prepare()',
					559 => '$wpdb->prepare()',
					564 => '$wpdb->prepare()',
					572 => '$wpdb->prepare()',
					574 => '$wpdb->prepare()',
					580 => '$wpdb->prepare()',
					581 => '$wpdb->prepare()',
					582 => '$wpdb->prepare()',
					583 => '$wpdb->prepare()',
					594 => '$wpdb->prepare()',
					595 => '$wpdb->prepare()',
					610 => '$db->prepare()',
				)
			)
		);
	}

	/**
	 * Tests that every error carries the sniff code as its identifier.
	 */
	public function test_error_identifier(): void {
		$errors = $this->gatherAnalyserErrors( array( __DIR__ . '/data/prepared-sql-placeholders.php' ) );

		$this->assertNotEmpty( $errors );

		foreach ( $errors as $error ) {
			$this->assertSame( PreparedSQLPlaceholdersRule::IDENTIFIER, $error->getIdentifier(), sprintf( 'Line %d', $error->getLine() ) );
		}
	}

	/**
	 * Builds the expected errors from the call reported on each line.
	 *
	 * @param array<int, string> $lines Call per line.
	 * @return list<array{string, int}>
	 */
	private static function expected( array $lines ): array {
		$expected = array();

		foreach ( $lines as $line => $call ) {
			$expected[] = array(
				sprintf( 'Replacement variables found, but no valid placeholders found in the query passed to %s.', $call ),
				$line,
			);
		}

		return $expected;
	}
}
