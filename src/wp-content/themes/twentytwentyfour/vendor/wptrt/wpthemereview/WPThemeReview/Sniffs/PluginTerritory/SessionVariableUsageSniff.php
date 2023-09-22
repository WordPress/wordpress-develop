<?php
/**
 * WPThemeReview Coding Standard.
 *
 * @package WPTRT\WPThemeReview
 * @link    https://github.com/WPTRT/WPThemeReview
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WPThemeReview\Sniffs\PluginTerritory;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Discourages the use of the session variable.
 * Creating a session writes a file to the server and is unreliable in a multi-server environment.
 *
 * @link  https://make.wordpress.org/themes/handbook/review/...... @todo
 *
 * @since WPCS 0.3.0
 * @since WPCS 0.10.0 The sniff no longer needlessly extends the Generic_Sniffs_PHP_ForbiddenFunctionsSniff
 *                    which it didn't use.
 * @since WPCS 0.12.0 This class now extends WordPress_Sniff.
 * @since WPCS 0.13.0 Class name changed: this class is now namespaced.
 *
 * @since TRTCS 0.1.0 As this sniff will be removed from WPCS in version 2.0, the
 *                    sniff has been cherry-picked into the WPThemeReview standard.
 */
class SessionVariableUsageSniff implements Sniff {

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return [
			\T_VARIABLE,
		];
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where the
	 *                                               token was found.
	 * @param int                         $stackPtr  The position of the current token
	 *                                               in the stack.
	 *
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {

		$tokens = $phpcsFile->getTokens();

		if ( '$_SESSION' === $tokens[ $stackPtr ]['content'] ) {
			$phpcsFile->addError(
				'Usage of $_SESSION variable is prohibited.',
				$stackPtr,
				'SessionVarsProhibited'
			);
		}
	}

}
