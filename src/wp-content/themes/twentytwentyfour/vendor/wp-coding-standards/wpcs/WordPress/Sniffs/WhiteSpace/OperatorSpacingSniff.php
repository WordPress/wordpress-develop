<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\OperatorSpacingSniff as PHPCS_Squiz_OperatorSpacingSniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Verify operator spacing, uses the Squiz sniff, but additionally also sniffs for the `!` (boolean not) operator.
 *
 * "Always put spaces after commas, and on both sides of logical, comparison, string and assignment operators."
 *
 * @link    https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/#space-usage
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.1.0
 * @since   0.3.0  This sniff now has the ability to fix the issues it flags.
 * @since   0.12.0 This sniff used to be a copy of a very old and outdated version of the
 *                 upstream sniff.
 *                 Now, the sniff defers completely to the upstream sniff, adding just the
 *                 T_BOOLEAN_NOT and the logical operators (`&&` and the like) - via the
 *                 registration method and changing the value of the customizable
 *                 $ignoreNewlines property.
 * @since   0.13.0 Class name changed: this class is now namespaced.
 *
 * Last synced with base class June 2017 at commit 41127aa4764536f38f504fb3f7b8831f05919c89.
 * @link    https://github.com/squizlabs/PHP_CodeSniffer/blob/master/CodeSniffer/Standards/Squiz/Sniffs/WhiteSpace/OperatorSpacingSniff.php
 */
class OperatorSpacingSniff extends PHPCS_Squiz_OperatorSpacingSniff {

	/**
	 * Allow newlines instead of spaces.
	 *
	 * N.B.: The upstream sniff defaults to `false`.
	 *
	 * @var boolean
	 */
	public $ignoreNewlines = true;


	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		$tokens                   = parent::register();
		$tokens[ \T_BOOLEAN_NOT ] = \T_BOOLEAN_NOT;
		$tokens[ \T_INSTANCEOF ]  = \T_INSTANCEOF;
		$logical_operators        = Tokens::$booleanOperators;

		// Using array union to auto-dedup.
		return $tokens + $logical_operators;
	}

}
