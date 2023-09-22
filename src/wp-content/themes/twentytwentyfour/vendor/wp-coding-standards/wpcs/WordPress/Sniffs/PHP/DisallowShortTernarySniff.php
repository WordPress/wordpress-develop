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
use PHP_CodeSniffer\Util\Tokens;

/**
 * Disallow the use of short ternaries.
 *
 * @link    https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/#ternary-operator
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   2.2.0
 */
class DisallowShortTernarySniff extends Sniff {

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @since 2.2.0
	 *
	 * @return array
	 */
	public function register() {
		return array( \T_INLINE_THEN );
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @since 2.2.0
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process_token( $stackPtr ) {

		$nextNonEmpty = $this->phpcsFile->findNext( Tokens::$emptyTokens, ( $stackPtr + 1 ), null, true );
		if ( false === $nextNonEmpty ) {
			// Live coding or parse error.
			return;
		}

		if ( \T_INLINE_ELSE !== $this->tokens[ $nextNonEmpty ]['code'] ) {
			return;
		}

		$this->phpcsFile->addError(
			'Using short ternaries is not allowed',
			$stackPtr,
			'Found'
		);
	}

}
