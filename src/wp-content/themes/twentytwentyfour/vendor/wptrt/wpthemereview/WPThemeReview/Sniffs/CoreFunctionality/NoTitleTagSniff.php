<?php
/**
 * WPThemeReview Coding Standard.
 *
 * @package WPTRT\WPThemeReview
 * @link    https://github.com/WPTRT/WPThemeReview
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WPThemeReview\Sniffs\CoreFunctionality;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Restricts the use of the <title> tag, unless it is within a <svg> tag.
 *
 * @link  https://make.wordpress.org/themes/handbook/review/required/
 *
 * @since 0.1.0
 */
class NoTitleTagSniff implements Sniff {

	/**
	 * Property to keep track of whether a <svg> open tag has been encountered.
	 *
	 * @var array
	 */
	private $in_svg;

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

		$tokens   = $phpcsFile->getTokens();
		$content  = $tokens[ $stackPtr ]['content'];
		$filename = $phpcsFile->getFileName();

		// Set to false if it is the first time this sniff is run on a file.
		if ( ! isset( $this->in_svg[ $filename ] ) ) {
			$this->in_svg[ $filename ] = false;
		}

		// No need to check an empty string.
		if ( '' === trim( $content ) ) {
			return;
		}

		// Are we in a <svg> tag ?
		if ( true === $this->in_svg[ $filename ] ) {
			if ( false === strpos( $content, '</svg>' ) ) {
				return;
			} else {
				// Make sure we check any content on this line after the closing svg tag.
				$this->in_svg[ $filename ] = false;
				$content                   = trim( substr( $content, ( strpos( $content, '</svg>' ) + 6 ) ) );
			}
		}

		// We're not in svg, so check if it there's a <svg> open tag on this line.
		if ( false !== strpos( $content, '<svg' ) ) {
			if ( false === strpos( $content, '</svg>' ) ) {
				// Skip the next lines until the closing svg tag, but do check any content
				// on this line before the svg tag.
				$this->in_svg[ $filename ] = true;
				$content                   = trim( substr( $content, 0, ( strpos( $content, '<svg' ) ) ) );
			} else {
				// Ok, we have open and close svg tag on the same line with possibly content before and/or after.
				$before  = trim( substr( $content, 0, ( strpos( $content, '<svg' ) ) ) );
				$after   = trim( substr( $content, ( strpos( $content, '</svg>' ) + 6 ) ) );
				$content = $before . $after;
			}
		}

		// Now let's do the check for the <title> tag.
		if ( false !== strpos( $content, '<title' ) ) {
			$phpcsFile->addError(
				"The title tag must not be used. Use add_theme_support( 'title-tag' ) instead.",
				$stackPtr,
				'TagFound'
			);
		}
	}

}
