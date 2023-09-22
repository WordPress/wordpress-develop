<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\PHP;

use WordPressCS\WordPress\Sniff;

/**
 * Enforces Strict Comparison checks, based upon Squiz code.
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.4.0
 * @since   0.13.0 Class name changed: this class is now namespaced.
 *
 * Last synced with base class ?[unknown date]? at commit ?[unknown commit]?.
 * It is currently unclear whether this sniff is actually based on Squiz code on whether the above
 * reference to it is a copy/paste oversight.
 * @link    Possibly: https://github.com/squizlabs/PHP_CodeSniffer/blob/master/CodeSniffer/Standards/Squiz/Sniffs/Operators/ComparisonOperatorUsageSniff.php
 */
class StrictComparisonsSniff extends Sniff {

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return array(
			\T_IS_EQUAL,
			\T_IS_NOT_EQUAL,
		);
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process_token( $stackPtr ) {

		if ( ! $this->has_whitelist_comment( 'loose comparison', $stackPtr ) ) {
			$error = 'Found: ' . $this->tokens[ $stackPtr ]['content'] . '. Use strict comparisons (=== or !==).';
			$this->phpcsFile->addWarning( $error, $stackPtr, 'LooseComparison' );
		}
	}

}
