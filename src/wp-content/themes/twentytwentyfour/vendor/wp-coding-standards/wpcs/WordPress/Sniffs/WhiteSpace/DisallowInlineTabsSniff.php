<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\WhiteSpace;

use WordPressCS\WordPress\Sniff;
use WordPressCS\WordPress\PHPCSHelper;

/**
 * Enforces using spaces for mid-line alignment.
 *
 * @link    https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/#indentation
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.12.0
 * @since   0.13.0 Class name changed: this class is now namespaced.
 */
class DisallowInlineTabsSniff extends Sniff {

	/**
	 * The --tab-width CLI value that is being used.
	 *
	 * @var int
	 */
	private $tab_width;

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return array(
			\T_OPEN_TAG,
		);
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return int Integer stack pointer to skip the rest of the file.
	 */
	public function process_token( $stackPtr ) {
		if ( ! isset( $this->tab_width ) ) {
			$this->tab_width = PHPCSHelper::get_tab_width( $this->phpcsFile );
		}

		$check_tokens = array(
			\T_WHITESPACE             => true,
			\T_DOC_COMMENT_WHITESPACE => true,
			\T_DOC_COMMENT_STRING     => true,
		);

		for ( $i = ( $stackPtr + 1 ); $i < $this->phpcsFile->numTokens; $i++ ) {
			// Skip all non-whitespace tokens and skip whitespace at the start of a new line.
			if ( ! isset( $check_tokens[ $this->tokens[ $i ]['code'] ] ) || 1 === $this->tokens[ $i ]['column'] ) {
				continue;
			}

			// If tabs are being converted to spaces by the tokenizer, the
			// original content should be checked instead of the converted content.
			if ( isset( $this->tokens[ $i ]['orig_content'] ) ) {
				$content = $this->tokens[ $i ]['orig_content'];
			} else {
				$content = $this->tokens[ $i ]['content'];
			}

			if ( '' === $content || strpos( $content, "\t" ) === false ) {
				continue;
			}

			$fix = $this->phpcsFile->addFixableError(
				'Spaces must be used for mid-line alignment; tabs are not allowed',
				$i,
				'NonIndentTabsUsed'
			);
			if ( true === $fix ) {
				if ( isset( $this->tokens[ $i ]['orig_content'] ) ) {
					// Use the replacement that PHPCS has already done.
					$this->phpcsFile->fixer->replaceToken( $i, $this->tokens[ $i ]['content'] );
				} else {
					// Replace tabs with spaces, using an indent of $tab_width.
					// Other sniffs can then correct the indent if they need to.
					$spaces     = str_repeat( ' ', $this->tab_width );
					$newContent = str_replace( "\t", $spaces, $this->tokens[ $i ]['content'] );
					$this->phpcsFile->fixer->replaceToken( $i, $newContent );
				}
			}
		}

		// Ignore the rest of the file.
		return ( $this->phpcsFile->numTokens + 1 );
	}

}
