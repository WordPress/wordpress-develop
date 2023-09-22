<?php
/**
 * WPThemeReview Coding Standard.
 *
 * @package WPTRT\WPThemeReview
 * @link    https://github.com/WPTRT/WPThemeReview
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WPThemeReview\Sniffs\ThouShallNotUse;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Check for use of <iframe>. Often used for malicious code.
 *
 * @link  https://make.wordpress.org/themes/handbook/review/required/theme-check-plugin/#info
 *
 * @since 0.1.0
 */
class ForbiddenIframeSniff implements Sniff {

	/**
	 * The regex to catch usage of <iframe ...>.
	 *
	 * This regex will prevent matches being made on `<iframe>` without attributes.
	 *
	 * @var string
	 */
	const IFRAME_REGEX = '`(<iframe\s+[^>]+>)`i';

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return Tokens::$textStringTokens;
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

		if ( preg_match( self::IFRAME_REGEX, $tokens[ $stackPtr ]['content'], $matches ) > 0 ) {
			$phpcsFile->addError(
				'Usage of the iframe HTML element is prohibited. Found: %s',
				$stackPtr,
				'Found',
				[ $matches[1] ]
			);
		}
	}

}
