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
 * Check if a theme uses include(_once) or require(_once) when get_template_part() should be used.
 *
 * @link  https://make.wordpress.org/themes/handbook/review/required/#core-functionality-and-features
 *
 * @since 0.1.0
 */
class FileIncludeSniff implements Sniff {

	/**
	 * A list of files to skip.
	 *
	 * @var array
	 */
	protected $file_whitelist = [
		'functions.php' => true,
	];

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return Tokens::$includeTokens;
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

		$tokens    = $phpcsFile->getTokens();
		$token     = $tokens[ $stackPtr ];
		$file_name = basename( $phpcsFile->getFileName() );

		if ( ! isset( $this->file_whitelist[ $file_name ] ) ) {
			$phpcsFile->addWarning(
				'Check that %s is not being used to load template files. "get_template_part()" should be used to load template files.',
				$stackPtr,
				'FileIncludeFound',
				[ $token['content'] ]
			);
		}
	}

}
