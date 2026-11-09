<?php
/**
 * Tests for PreparedSQLRule.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use WordPress\PHPStan\PreparedSQLRule;

/**
 * @extends RuleTestCase<PreparedSQLRule>
 */
final class PreparedSQLRuleTest extends RuleTestCase {

	/**
	 * Returns the rule under test.
	 *
	 * @return PreparedSQLRule
	 */
	protected function getRule(): Rule {
		return new PreparedSQLRule();
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
	 * `InterpolatedNotPrepared` code.
	 */
	public function test_rule(): void {
		$this->analyse(
			array(
				__DIR__ . '/data/prepared-sql.php',
				__DIR__ . '/data/PreparedSQLTestClass.php',
			),
			self::expected(
				array(
					4   => array( '$_GET[\'title\']', '$wpdb->query()' ),
					5   => array( '$var', '$wpdb->query()' ),
					7   => array( '$_GET[\'title\']', '$wpdb->prepare()' ),
					8   => array( '$var', '$wpdb->prepare()' ),
					12  => array( '$escaped_var', '$wpdb->query()' ),
					16  => array( '$_GET[\'foo\']', '$wpdb->prepare()' ),
					17  => array( '$_GET[\'foo\']', '$wpdb->prepare()' ),
					18  => array( '$_GET[\'foo\']', '$wpdb->prepare()' ),
					54  => array( '$_GET[\'foo\']', '$wpdb->prepare()' ),
					64  => array( '$foo', '$wpdb->query()' ),
					71  => array( '$var', '$wpdb->query()' ),
					106 => array( '$title->sub()', '$wpdb->query()' ),
					107 => array( '${\\title->sub}', '$wpdb->query()' ),
					108 => array( '${\\title->{$sub}}', '$wpdb->query()' ),
					109 => array( '${\\title->{${\'sub\'}}}', '$wpdb->query()' ),
					112 => array( '$wpdb', '$wpdb->query()' ),
					// The sniff's test file ends above; the rest extends it.
					// Line 39 is in PreparedSQLTestClass.php.
					39  => array( '$foo', '$this->db->query()' ),
					170 => array( '$foo', '$wpdb->query()' ),
					178 => array( '$foo', '$wpdb->query()' ),
					184 => array( '$author', '$wpdb->query()' ),
					185 => array( '$status', '$wpdb->query()' ),
					190 => array( '$foo', '$wpdb->query()' ),
					191 => array( '$foo', '$wpdb->prepare()' ),
					203 => array( '$foo', '$wpdb->query()' ),
					204 => array( '$foo', '$wpdb->query()' ),
					205 => array( array( '$foo', '$wpdb->query()' ), array( '$bar', '$wpdb->query()' ) ),
					213 => array( '$foo', '$wpdb->prepare()' ),
					214 => array( array( '$foo', '$wpdb->prepare()' ), array( '$baz', '$wpdb->get_var()' ) ),
					218 => array( '$wpdb[\'posts\']', '$wpdb->query()' ),
					219 => array( '$db->posts', '$wpdb->query()' ),
					226 => array( '$foo', '$db->query()' ),
					228 => array( '$foo', '$db?->get_row()' ),
					238 => array( '$part', '$wpdb->query()' ),
				)
			)
		);
	}

	/**
	 * Tests that every error carries the sniff code as its identifier.
	 */
	public function test_error_identifier(): void {
		$errors = $this->gatherAnalyserErrors( array( __DIR__ . '/data/prepared-sql.php' ) );

		$this->assertNotEmpty( $errors );

		foreach ( $errors as $error ) {
			$this->assertSame( PreparedSQLRule::IDENTIFIER, $error->getIdentifier(), sprintf( 'Line %d', $error->getLine() ) );
		}
	}

	/**
	 * Builds the expected errors from the variable and call reported on each line.
	 *
	 * @param array<int, array{string, string}|list<array{string, string}>> $lines Variable and call per line, or several.
	 * @return list<array{string, int}>
	 */
	private static function expected( array $lines ): array {
		$expected = array();

		foreach ( $lines as $line => $reports ) {
			if ( is_string( $reports[0] ) ) {
				$reports = array( $reports );
			}

			foreach ( $reports as $report ) {
				$expected[] = array(
					sprintf(
						'Use placeholders and $wpdb->prepare(); found interpolated variable %s in the query passed to %s.',
						$report[0],
						$report[1]
					),
					$line,
				);
			}
		}

		return $expected;
	}
}
