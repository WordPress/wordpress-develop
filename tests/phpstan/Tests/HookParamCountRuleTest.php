<?php
/**
 * Tests for HookParamCountRule.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use WordPress\PHPStan\HookDocBlock;
use WordPress\PHPStan\HookParamCountRule;

/**
 * @extends RuleTestCase<HookParamCountRule>
 */
final class HookParamCountRuleTest extends RuleTestCase {

	/**
	 * Returns the rule under test, with its documentation resolved against the
	 * fixture WordPress root.
	 *
	 * @return HookParamCountRule
	 */
	protected function getRule(): Rule {
		return new HookParamCountRule( self::getContainer()->getByType( HookDocBlock::class ) );
	}

	/**
	 * Returns the configuration registering the visitors and services the rule relies on.
	 *
	 * @return list<string>
	 */
	public static function getAdditionalConfigFiles(): array {
		return array( __DIR__ . '/tests.neon' );
	}

	/**
	 * Tests the mismatches reported, and the calls left alone.
	 */
	public function test_rule(): void {
		$this->analyse(
			array( __DIR__ . '/data/hook-param-count.php' ),
			array(
				array( 'apply_filters() for hook "two_params_short" provides 1 argument, but the hook is documented with 2 parameters.', 22 ),
				array( 'apply_filters() for hook "one_param_long" provides 2 arguments, but the hook is documented with 1 parameter.', 29 ),
				array( 'do_action() for hook "no_params_extra" provides 1 argument, but its docblock documents no parameters.', 39 ),
				array( 'do_action() for hook "no_params_extra_two" provides 2 arguments, but its docblock documents no parameters.', 44 ),
				array( 'do_action_ref_array() for hook "ref_array_long" provides 2 arguments, but the hook is documented with 1 parameter.', 51 ),
				array( 'apply_filters_deprecated() for hook "deprecated_short" provides 1 argument, but the hook is documented with 2 parameters.', 59 ),
				array( 'apply_filters() for hook "spread_known_long" provides 3 arguments, but the hook is documented with 1 parameter.', 90 ),
				array( 'apply_filters() for hook "documented_filter" provides 1 argument, but the hook is documented with 2 parameters.', 113 ),
				array( 'do_action() for hook "documented_action" provides 2 arguments, but the hook is documented with 1 parameter.', 116 ),
				array( 'apply_filters() for hook "index_template_hierarchy" provides 2 arguments, but the hook is documented with 1 parameter.', 119 ),
				array( 'apply_filters() for hook "malformed_tag" provides 2 arguments, but the hook is documented with 1 parameter.', 135 ),
				array( 'do_action() for hook "wp_tests_{$type}_fired" provides 2 arguments, but the hook is documented with 1 parameter.', 144 ),
			)
		);
	}

	/**
	 * Tests that every mismatch is reported under the same identifier.
	 */
	public function test_error_identifier(): void {
		$errors = $this->gatherAnalyserErrors( array( __DIR__ . '/data/hook-param-count.php' ) );

		$this->assertNotEmpty( $errors );

		foreach ( $errors as $error ) {
			$this->assertSame( 'wordpress.hookParamCountMismatch', $error->getIdentifier(), sprintf( 'Line %d', $error->getLine() ) );
		}
	}
}
