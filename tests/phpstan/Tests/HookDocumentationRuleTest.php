<?php
/**
 * Tests for HookDocumentationRule.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests;

use PHPStan\Analyser\Error;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use WordPress\PHPStan\HookDocBlock;
use WordPress\PHPStan\HookDocumentationRule;

/**
 * @extends RuleTestCase<HookDocumentationRule>
 */
final class HookDocumentationRuleTest extends RuleTestCase {

	/**
	 * Returns the rule under test, with its documentation resolved against the
	 * fixture WordPress root.
	 *
	 * @return HookDocumentationRule
	 */
	protected function getRule(): Rule {
		return new HookDocumentationRule( self::getContainer()->getByType( HookDocBlock::class ) );
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
	 * Tests the errors reported for a file outside the WordPress root, whose
	 * reference comments resolve against the root directly.
	 */
	public function test_rule(): void {
		$missing = 'is not preceded by a docblock documenting the hook, nor by a "This filter/action is documented in <file>" reference comment.';
		$no_params = 'is preceded by a docblock that documents no parameters. A filter is documented with a `@param` tag for the value being filtered, plus one for each further argument passed.';

		$this->analyse(
			array( __DIR__ . '/data/hook-documentation.php' ),
			array(
				array( 'apply_filters() call for hook "undocumented_filter" ' . $missing, 15 ),
				array( 'do_action() call for hook "undocumented_action" ' . $missing, 17 ),
				array( 'apply_filters() call for hook "paramless_filter" ' . $no_params, 34 ),
				array( 'apply_filters() call for hook "var_annotated_filter" ' . $no_params, 37 ),
				array( 'apply_filters() call for hook "documented_filter" references documentation in "wp-includes/missing.php", but no such file exists in the tree being analyzed.', 46 ),
				array( 'apply_filters() call for hook "documented_filter" references documentation in "wp-includes/other.php", but no documented "documented_filter" hook is found there.', 49 ),
				array( 'apply_filters() call for hook "only_referenced_here" references documentation in "wp-includes/canonical.php", but no documented "only_referenced_here" hook is found there.', 52 ),
				array( 'apply_filters() call for hook "foo_bar" references documentation in "wp-includes/canonical.php", but no documented "foo_bar" hook is found there.', 61 ),
				array( 'apply_filters() call for hook "{$prefix}_bar" references documentation in "wp-includes/canonical.php", but no documented "{$prefix}_bar" hook is found there.', 64 ),
				array( 'apply_filters() call for hook "pre_option_{$option}" ' . $missing, 73 ),
				array( 'apply_filters() call for hook "prefix_$context" ' . $missing, 75 ),
				array( 'apply_filters() call for hook "second_item" ' . $missing, 93 ),
				array( 'apply_filters_deprecated() call for hook "old_filter" ' . $missing, 103 ),
				array( 'do_action_ref_array() call for hook "ref_action" ' . $missing, 105 ),
				array( 'apply_filters() call for hook "inside_function" ' . $missing, 120 ),
			)
		);
	}

	/**
	 * Tests that a reference is resolved by walking up from the file holding it,
	 * so a theme template can reference a sibling as well as a core file.
	 */
	public function test_references_resolve_relative_to_the_referencing_file(): void {
		$this->analyse(
			array( __DIR__ . '/data/wordpress/wp-content/themes/wptests/index.php' ),
			array(
				array( 'do_action() call for hook "wptests_missing_theme_hook" references documentation in "author.php", but no documented "wptests_missing_theme_hook" hook is found there.', 15 ),
				array( 'apply_filters() call for hook "documented_filter" references documentation in "single.php", but no such file exists in the tree being analyzed.', 18 ),
			)
		);
	}

	/**
	 * Tests that a reference written with a dot segment resolves inside the tree,
	 * and that one leading outside the tree is reported as missing.
	 */
	public function test_references_never_resolve_outside_the_root(): void {
		$this->analyse(
			array( __DIR__ . '/data/wordpress/wp-admin/admin.php' ),
			array(
				array( 'apply_filters() call for hook "outside_filter" references documentation in "../outside.php", but no such file exists in the tree being analyzed.', 19 ),
			)
		);
	}

	/**
	 * Tests that each kind of problem is reported under its own identifier.
	 */
	public function test_error_identifiers(): void {
		$errors = $this->gatherAnalyserErrors( array( __DIR__ . '/data/hook-documentation.php' ) );

		$identifiers = array();
		foreach ( $errors as $error ) {
			$identifiers[ (int) $error->getLine() ] = $error->getIdentifier();
		}

		$this->assertSame(
			array(
				15  => 'wordpress.hookDocMissing',
				17  => 'wordpress.hookDocMissing',
				34  => 'wordpress.hookDocNoParams',
				37  => 'wordpress.hookDocNoParams',
				46  => 'wordpress.hookDocReferenceFileMissing',
				49  => 'wordpress.hookDocReferenceHookMissing',
				52  => 'wordpress.hookDocReferenceHookMissing',
				61  => 'wordpress.hookDocReferenceHookMissing',
				64  => 'wordpress.hookDocReferenceHookMissing',
				73  => 'wordpress.hookDocMissing',
				75  => 'wordpress.hookDocMissing',
				93  => 'wordpress.hookDocMissing',
				103 => 'wordpress.hookDocMissing',
				105 => 'wordpress.hookDocMissing',
				120 => 'wordpress.hookDocMissing',
			),
			$identifiers
		);
	}
}
