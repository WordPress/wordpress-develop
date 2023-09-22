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
 * Enforces WordPress array spacing format.
 *
 * - Check for no space between array keyword and array opener.
 * - Check for no space between the parentheses of an empty array.
 * - Checks for one space after the array opener / before the array closer in single-line arrays.
 * - Checks that associative arrays are multi-line.
 * - Checks that each array item in a multi-line array starts on a new line.
 * - Checks that the array closer in a multi-line array is on a new line.
 *
 * @link    https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/#indentation
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.11.0 - The WordPress specific additional checks have now been split off
 *                   from the `WordPress.Arrays.ArrayDeclaration` sniff into this sniff.
 *                 - Added sniffing & fixing for associative arrays.
 * @since   0.12.0 Decoupled this sniff from the upstream sniff completely.
 *                 This sniff now extends the WordPressCS native `Sniff` class instead.
 * @since   0.13.0 Added the last remaining checks from the `WordPress.Arrays.ArrayDeclaration`
 *                 sniff which were not covered elsewhere.
 *                 The `WordPress.Arrays.ArrayDeclaration` sniff has now been deprecated.
 * @since   0.13.0 Class name changed: this class is now namespaced.
 * @since   0.14.0 Single item associative arrays are now by default exempt from the
 *                 "must be multi-line" rule. This behaviour can be changed using the
 *                 `allow_single_item_single_line_associative_arrays` property.
 */
class ArrayDeclarationSpacingSniff extends Sniff {

	/**
	 * Whether or not to allow single item associative arrays to be single line.
	 *
	 * @since 0.14.0
	 *
	 * @var bool Defaults to true.
	 */
	public $allow_single_item_single_line_associative_arrays = true;

	/**
	 * Token this sniff targets.
	 *
	 * Also used for distinguishing between the array and an array value
	 * which is also an array.
	 *
	 * @since 0.12.0
	 *
	 * @var array
	 */
	private $targets = array(
		\T_ARRAY            => \T_ARRAY,
		\T_OPEN_SHORT_ARRAY => \T_OPEN_SHORT_ARRAY,
	);

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @since 0.12.0
	 *
	 * @return array
	 */
	public function register() {
		return $this->targets;
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @since 0.12.0 The actual checks contained in this method used to
	 *               be in the `processSingleLineArray()` method.
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process_token( $stackPtr ) {

		if ( \T_OPEN_SHORT_ARRAY === $this->tokens[ $stackPtr ]['code']
			&& $this->is_short_list( $stackPtr )
		) {
			// Short list, not short array.
			return;
		}

		/*
		 * Determine the array opener & closer.
		 */
		$array_open_close = $this->find_array_open_close( $stackPtr );
		if ( false === $array_open_close ) {
			// Array open/close could not be determined.
			return;
		}

		$opener = $array_open_close['opener'];
		$closer = $array_open_close['closer'];
		unset( $array_open_close );

		/*
		 * Long arrays only: Check for space between the array keyword and the open parenthesis.
		 */
		if ( \T_ARRAY === $this->tokens[ $stackPtr ]['code'] ) {

			if ( ( $stackPtr + 1 ) !== $opener ) {
				$error      = 'There must be no space between the "array" keyword and the opening parenthesis';
				$error_code = 'SpaceAfterKeyword';

				$nextNonWhitespace = $this->phpcsFile->findNext( \T_WHITESPACE, ( $stackPtr + 1 ), ( $opener + 1 ), true );
				if ( $nextNonWhitespace !== $opener ) {
					// Don't auto-fix: Something other than whitespace found between keyword and open parenthesis.
					$this->phpcsFile->addError( $error, $stackPtr, $error_code );
				} else {

					$fix = $this->phpcsFile->addFixableError( $error, $stackPtr, $error_code );

					if ( true === $fix ) {
						$this->phpcsFile->fixer->beginChangeset();
						for ( $i = ( $stackPtr + 1 ); $i < $opener; $i++ ) {
							$this->phpcsFile->fixer->replaceToken( $i, '' );
						}
						$this->phpcsFile->fixer->endChangeset();
						unset( $i );
					}
				}
				unset( $error, $error_code, $nextNonWhitespace, $fix );
			}
		}

		/*
		 * Check for empty arrays.
		 */
		$nextNonWhitespace = $this->phpcsFile->findNext( \T_WHITESPACE, ( $opener + 1 ), ( $closer + 1 ), true );
		if ( $nextNonWhitespace === $closer ) {

			if ( ( $opener + 1 ) !== $closer ) {
				$fix = $this->phpcsFile->addFixableError(
					'Empty array declaration must have no space between the parentheses',
					$stackPtr,
					'SpaceInEmptyArray'
				);

				if ( true === $fix ) {
					$this->phpcsFile->fixer->beginChangeset();
					for ( $i = ( $opener + 1 ); $i < $closer; $i++ ) {
						$this->phpcsFile->fixer->replaceToken( $i, '' );
					}
					$this->phpcsFile->fixer->endChangeset();
					unset( $i );
				}
			}

			// This array is empty, so the below checks aren't necessary.
			return;
		}
		unset( $nextNonWhitespace );

		// Pass off to either the single line or multi-line array analysis.
		if ( $this->tokens[ $opener ]['line'] === $this->tokens[ $closer ]['line'] ) {
			$this->process_single_line_array( $stackPtr, $opener, $closer );
		} else {
			$this->process_multi_line_array( $stackPtr, $opener, $closer );
		}
	}

	/**
	 * Process a single-line array.
	 *
	 * @since 0.13.0 The actual checks contained in this method used to
	 *               be in the `process()` method.
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 * @param int $opener   The position of the array opener.
	 * @param int $closer   The position of the array closer.
	 *
	 * @return void
	 */
	protected function process_single_line_array( $stackPtr, $opener, $closer ) {
		/*
		 * Check that associative arrays are always multi-line.
		 */
		$array_has_keys = $this->phpcsFile->findNext( \T_DOUBLE_ARROW, $opener, $closer );
		if ( false !== $array_has_keys ) {

			$array_items = $this->get_function_call_parameters( $stackPtr );

			if ( ( false === $this->allow_single_item_single_line_associative_arrays
					&& ! empty( $array_items ) )
				|| ( true === $this->allow_single_item_single_line_associative_arrays
					&& \count( $array_items ) > 1 )
			) {
				/*
				 * Make sure the double arrow is for *this* array, not for a nested one.
				 */
				$array_has_keys = false; // Reset before doing more detailed check.
				foreach ( $array_items as $item ) {
					for ( $ptr = $item['start']; $ptr <= $item['end']; $ptr++ ) {
						if ( \T_DOUBLE_ARROW === $this->tokens[ $ptr ]['code'] ) {
							$array_has_keys = true;
							break 2;
						}

						// Skip passed any nested arrays.
						if ( isset( $this->targets[ $this->tokens[ $ptr ]['code'] ] ) ) {
							$nested_array_open_close = $this->find_array_open_close( $ptr );
							if ( false === $nested_array_open_close ) {
								// Nested array open/close could not be determined.
								continue;
							}

							$ptr = $nested_array_open_close['closer'];
						}
					}
				}

				if ( true === $array_has_keys ) {

					$phrase = 'an';
					if ( true === $this->allow_single_item_single_line_associative_arrays ) {
						$phrase = 'a multi-item';
					}
					$fix = $this->phpcsFile->addFixableError(
						'When %s array uses associative keys, each value should start on a new line.',
						$closer,
						'AssociativeArrayFound',
						array( $phrase )
					);

					if ( true === $fix ) {

						$this->phpcsFile->fixer->beginChangeset();

						foreach ( $array_items as $item ) {
							/*
							 * Add a line break before the first non-empty token in the array item.
							 * Prevents extraneous whitespace at the start of the line which could be
							 * interpreted as alignment whitespace.
							 */
							$first_non_empty = $this->phpcsFile->findNext(
								Tokens::$emptyTokens,
								$item['start'],
								( $item['end'] + 1 ),
								true
							);
							if ( false === $first_non_empty ) {
								continue;
							}

							if ( $item['start'] <= ( $first_non_empty - 1 )
								&& \T_WHITESPACE === $this->tokens[ ( $first_non_empty - 1 ) ]['code']
							) {
								// Remove whitespace which would otherwise becoming trailing
								// (as it gives problems with the fixed file).
								$this->phpcsFile->fixer->replaceToken( ( $first_non_empty - 1 ), '' );
							}

							$this->phpcsFile->fixer->addNewlineBefore( $first_non_empty );
						}

						$this->phpcsFile->fixer->endChangeset();
					}

					// No need to check for spacing around opener/closer as this array should be multi-line.
					return;
				}
			}
		}

		/*
		 * Check that there is a single space after the array opener and before the array closer.
		 */
		if ( \T_WHITESPACE !== $this->tokens[ ( $opener + 1 ) ]['code'] ) {

			$fix = $this->phpcsFile->addFixableError(
				'Missing space after array opener.',
				$opener,
				'NoSpaceAfterArrayOpener'
			);

			if ( true === $fix ) {
				$this->phpcsFile->fixer->addContent( $opener, ' ' );
			}
		} elseif ( ' ' !== $this->tokens[ ( $opener + 1 ) ]['content'] ) {

			$fix = $this->phpcsFile->addFixableError(
				'Expected 1 space after array opener, found %s.',
				$opener,
				'SpaceAfterArrayOpener',
				array( \strlen( $this->tokens[ ( $opener + 1 ) ]['content'] ) )
			);

			if ( true === $fix ) {
				$this->phpcsFile->fixer->replaceToken( ( $opener + 1 ), ' ' );
			}
		}

		if ( \T_WHITESPACE !== $this->tokens[ ( $closer - 1 ) ]['code'] ) {

			$fix = $this->phpcsFile->addFixableError(
				'Missing space before array closer.',
				$closer,
				'NoSpaceBeforeArrayCloser'
			);

			if ( true === $fix ) {
				$this->phpcsFile->fixer->addContentBefore( $closer, ' ' );
			}
		} elseif ( ' ' !== $this->tokens[ ( $closer - 1 ) ]['content'] ) {

			$fix = $this->phpcsFile->addFixableError(
				'Expected 1 space before array closer, found %s.',
				$closer,
				'SpaceBeforeArrayCloser',
				array( \strlen( $this->tokens[ ( $closer - 1 ) ]['content'] ) )
			);

			if ( true === $fix ) {
				$this->phpcsFile->fixer->replaceToken( ( $closer - 1 ), ' ' );
			}
		}
	}

	/**
	 * Process a multi-line array.
	 *
	 * @since 0.13.0 The actual checks contained in this method used to
	 *               be in the `ArrayDeclaration` sniff.
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 * @param int $opener   The position of the array opener.
	 * @param int $closer   The position of the array closer.
	 *
	 * @return void
	 */
	protected function process_multi_line_array( $stackPtr, $opener, $closer ) {
		/*
		 * Check that the closing bracket is on a new line.
		 */
		$last_content = $this->phpcsFile->findPrevious( \T_WHITESPACE, ( $closer - 1 ), $opener, true );
		if ( false !== $last_content
			&& $this->tokens[ $last_content ]['line'] === $this->tokens[ $closer ]['line']
		) {
			$fix = $this->phpcsFile->addFixableError(
				'Closing parenthesis of array declaration must be on a new line',
				$closer,
				'CloseBraceNewLine'
			);
			if ( true === $fix ) {
				$this->phpcsFile->fixer->beginChangeset();

				if ( $last_content < ( $closer - 1 )
					&& \T_WHITESPACE === $this->tokens[ ( $closer - 1 ) ]['code']
				) {
					// Remove whitespace which would otherwise becoming trailing
					// (as it gives problems with the fixed file).
					$this->phpcsFile->fixer->replaceToken( ( $closer - 1 ), '' );
				}

				$this->phpcsFile->fixer->addNewlineBefore( $closer );
				$this->phpcsFile->fixer->endChangeset();
			}
		}

		/*
		 * Check that each array item starts on a new line.
		 */
		$array_items      = $this->get_function_call_parameters( $stackPtr );
		$end_of_last_item = $opener;

		foreach ( $array_items as $item ) {
			$end_of_this_item = ( $item['end'] + 1 );

			// Find the line on which the item starts.
			$first_content = $this->phpcsFile->findNext(
				array( \T_WHITESPACE, \T_DOC_COMMENT_WHITESPACE ),
				$item['start'],
				$end_of_this_item,
				true
			);

			// Ignore comments after array items if the next real content starts on a new line.
			if ( $this->tokens[ $first_content ]['line'] === $this->tokens[ $end_of_last_item ]['line']
				&& ( \T_COMMENT === $this->tokens[ $first_content ]['code']
				|| isset( Tokens::$phpcsCommentTokens[ $this->tokens[ $first_content ]['code'] ] ) )
			) {
				$end_of_comment = $first_content;

				// Find the end of (multi-line) /* */- style trailing comments.
				if ( substr( ltrim( $this->tokens[ $end_of_comment ]['content'] ), 0, 2 ) === '/*' ) {
					while ( ( \T_COMMENT === $this->tokens[ $end_of_comment ]['code']
						|| isset( Tokens::$phpcsCommentTokens[ $this->tokens[ $end_of_comment ]['code'] ] ) )
						&& substr( rtrim( $this->tokens[ $end_of_comment ]['content'] ), -2 ) !== '*/'
						&& ( $end_of_comment + 1 ) < $end_of_this_item
					) {
						$end_of_comment++;
					}

					if ( $this->tokens[ $end_of_comment ]['line'] !== $this->tokens[ $end_of_last_item ]['line'] ) {
						// Multi-line trailing comment.
						$end_of_last_item = $end_of_comment;
					}
				}

				$next = $this->phpcsFile->findNext(
					array( \T_WHITESPACE, \T_DOC_COMMENT_WHITESPACE ),
					( $end_of_comment + 1 ),
					$end_of_this_item,
					true
				);

				if ( false === $next ) {
					// Shouldn't happen, but just in case.
					$end_of_last_item = $end_of_this_item;
					continue;
				}

				if ( $this->tokens[ $next ]['line'] !== $this->tokens[ $first_content ]['line'] ) {
					$first_content = $next;
				}
			}

			if ( false === $first_content ) {
				// Shouldn't happen, but just in case.
				$end_of_last_item = $end_of_this_item;
				continue;
			}

			if ( $this->tokens[ $end_of_last_item ]['line'] === $this->tokens[ $first_content ]['line'] ) {

				$fix = $this->phpcsFile->addFixableError(
					'Each item in a multi-line array must be on a new line',
					$first_content,
					'ArrayItemNoNewLine'
				);

				if ( true === $fix ) {

					$this->phpcsFile->fixer->beginChangeset();

					if ( ( $end_of_last_item + 1 ) <= ( $first_content - 1 )
						&& \T_WHITESPACE === $this->tokens[ ( $first_content - 1 ) ]['code']
					) {
						// Remove whitespace which would otherwise becoming trailing
						// (as it gives problems with the fixed file).
						$this->phpcsFile->fixer->replaceToken( ( $first_content - 1 ), '' );
					}

					$this->phpcsFile->fixer->addNewlineBefore( $first_content );
					$this->phpcsFile->fixer->endChangeset();
				}
			}

			$end_of_last_item = $end_of_this_item;
		}
	}

}
