<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\Arrays;

use WordPressCS\WordPress\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Check for proper spacing in array key references.
 *
 * @link    https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/#space-usage
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.3.0
 * @since   0.7.0  This sniff now has the ability to fix a number of the issues it flags.
 * @since   0.12.0 This class now extends the WordPressCS native `Sniff` class.
 * @since   0.13.0 Class name changed: this class is now namespaced.
 * @since   2.2.0  The sniff now also checks the size of the spacing, if applicable.
 */
class ArrayKeySpacingRestrictionsSniff extends Sniff {

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return array(
			\T_OPEN_SQUARE_BRACKET,
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

		$token = $this->tokens[ $stackPtr ];
		if ( ! isset( $token['bracket_closer'] ) ) {
			$this->phpcsFile->addWarning( 'Missing bracket closer.', $stackPtr, 'MissingBracketCloser' );
			return;
		}

		$need_spaces = $this->phpcsFile->findNext(
			array( \T_CONSTANT_ENCAPSED_STRING, \T_LNUMBER, \T_WHITESPACE, \T_MINUS ),
			( $stackPtr + 1 ),
			$token['bracket_closer'],
			true
		);

		$spaced1 = ( \T_WHITESPACE === $this->tokens[ ( $stackPtr + 1 ) ]['code'] );
		$spaced2 = ( \T_WHITESPACE === $this->tokens[ ( $token['bracket_closer'] - 1 ) ]['code'] );

		// It should have spaces unless if it only has strings or numbers as the key.
		if ( false !== $need_spaces
			&& ( false === $spaced1 || false === $spaced2 )
		) {
			$error = 'Array keys must be surrounded by spaces unless they contain a string or an integer.';
			$fix   = $this->phpcsFile->addFixableError( $error, $stackPtr, 'NoSpacesAroundArrayKeys' );
			if ( true === $fix ) {
				if ( ! $spaced1 ) {
					$this->phpcsFile->fixer->addContentBefore( ( $stackPtr + 1 ), ' ' );
				}
				if ( ! $spaced2 ) {
					$this->phpcsFile->fixer->addContentBefore( $token['bracket_closer'], ' ' );
				}
			}
		} elseif ( false === $need_spaces && ( $spaced1 || $spaced2 ) ) {
			$error = 'Array keys must NOT be surrounded by spaces if they only contain a string or an integer.';
			$fix   = $this->phpcsFile->addFixableError( $error, $stackPtr, 'SpacesAroundArrayKeys' );
			if ( true === $fix ) {
				if ( $spaced1 ) {
					$this->phpcsFile->fixer->beginChangeset();
					$this->phpcsFile->fixer->replaceToken( ( $stackPtr + 1 ), '' );

					for ( $i = ( $stackPtr + 2 ); $i < $token['bracket_closer']; $i++ ) {
						if ( \T_WHITESPACE !== $this->tokens[ $i ]['code'] ) {
							break;
						}

						$this->phpcsFile->fixer->replaceToken( $i, '' );
					}

					$this->phpcsFile->fixer->endChangeset();
				}
				if ( $spaced2 ) {
					$this->phpcsFile->fixer->beginChangeset();
					$this->phpcsFile->fixer->replaceToken( ( $token['bracket_closer'] - 1 ), '' );

					for ( $i = ( $token['bracket_closer'] - 2 ); $i > $stackPtr; $i-- ) {
						if ( \T_WHITESPACE !== $this->tokens[ $i ]['code'] ) {
							break;
						}

						$this->phpcsFile->fixer->replaceToken( $i, '' );
					}

					$this->phpcsFile->fixer->endChangeset();
				}
			}
		}

		// If spaces are needed, check that there is only one space.
		if ( false !== $need_spaces && ( $spaced1 || $spaced2 ) ) {
			if ( $spaced1 ) {
				$ptr    = ( $stackPtr + 1 );
				$length = 0;
				if ( $this->tokens[ $ptr ]['line'] !== $this->tokens[ ( $ptr + 1 ) ]['line'] ) {
					$length = 'newline';
				} else {
					$length = $this->tokens[ $ptr ]['length'];
				}

				if ( 1 !== $length ) {
					$error = 'There should be exactly one space before the array key. Found: %s';
					$data  = array( $length );
					$fix   = $this->phpcsFile->addFixableError(
						$error,
						$ptr,
						'TooMuchSpaceBeforeKey',
						$data
					);

					if ( true === $fix ) {
						$this->phpcsFile->fixer->beginChangeset();
						$this->phpcsFile->fixer->replaceToken( $ptr, ' ' );

						for ( $i = ( $ptr + 1 ); $i < $token['bracket_closer']; $i++ ) {
							if ( \T_WHITESPACE !== $this->tokens[ $i ]['code'] ) {
								break;
							}

							$this->phpcsFile->fixer->replaceToken( $i, '' );
						}

						$this->phpcsFile->fixer->endChangeset();
					}
				}
			}

			if ( $spaced2 ) {
				$prev_non_empty = $this->phpcsFile->findPrevious( Tokens::$emptyTokens, ( $token['bracket_closer'] - 1 ), null, true );
				$ptr            = ( $prev_non_empty + 1 );
				$length         = 0;
				if ( $this->tokens[ $ptr ]['line'] !== $this->tokens[ $token['bracket_closer'] ]['line'] ) {
					$length = 'newline';
				} else {
					$length = $this->tokens[ $ptr ]['length'];
				}

				if ( 1 !== $length ) {
					$error = 'There should be exactly one space after the array key. Found: %s';
					$data  = array( $length );
					$fix   = $this->phpcsFile->addFixableError(
						$error,
						$ptr,
						'TooMuchSpaceAfterKey',
						$data
					);

					if ( true === $fix ) {
						$this->phpcsFile->fixer->beginChangeset();
						$this->phpcsFile->fixer->replaceToken( $ptr, ' ' );

						for ( $i = ( $ptr + 1 ); $i < $token['bracket_closer']; $i++ ) {
							if ( \T_WHITESPACE !== $this->tokens[ $i ]['code'] ) {
								break;
							}

							$this->phpcsFile->fixer->replaceToken( $i, '' );
						}

						$this->phpcsFile->fixer->endChangeset();
					}
				}
			}
		}
	}

}
