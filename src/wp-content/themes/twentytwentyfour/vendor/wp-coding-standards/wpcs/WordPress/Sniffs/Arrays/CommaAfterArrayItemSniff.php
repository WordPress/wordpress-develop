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
 * Enforces a comma after each array item and the spacing around it.
 *
 * Rules:
 * - For multi-line arrays, a comma is needed after each array item.
 * - Same for single-line arrays, but no comma is allowed after the last array item.
 * - There should be no space between the comma and the end of the array item.
 * - There should be exactly one space between the comma and the start of the
 *   next array item for single-line items.
 *
 * @link    https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/#indentation
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.12.0
 * @since   0.13.0 Class name changed: this class is now namespaced.
 */
class CommaAfterArrayItemSniff extends Sniff {

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return array(
			\T_ARRAY,
			\T_OPEN_SHORT_ARRAY,
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

		// This array is empty, so the below checks aren't necessary.
		if ( ( $opener + 1 ) === $closer ) {
			return;
		}

		$single_line = true;
		if ( $this->tokens[ $opener ]['line'] !== $this->tokens[ $closer ]['line'] ) {
			$single_line = false;
		}

		$array_items = $this->get_function_call_parameters( $stackPtr );
		if ( empty( $array_items ) ) {
			// Strange, no array items found.
			return;
		}

		$array_item_count = \count( $array_items );

		// Note: $item_index is 1-based and the array items are split on the commas!
		foreach ( $array_items as $item_index => $item ) {
			$maybe_comma = ( $item['end'] + 1 );
			$is_comma    = false;
			if ( isset( $this->tokens[ $maybe_comma ] ) && \T_COMMA === $this->tokens[ $maybe_comma ]['code'] ) {
				$is_comma = true;
			}

			/*
			 * Check if this is a comma at the end of the last item in a single line array.
			 */
			if ( true === $single_line && $item_index === $array_item_count ) {

				$this->phpcsFile->recordMetric(
					$stackPtr,
					'Single line array - comma after last item',
					( true === $is_comma ? 'yes' : 'no' )
				);

				if ( true === $is_comma ) {
					$fix = $this->phpcsFile->addFixableError(
						'Comma not allowed after last value in single-line array declaration',
						$maybe_comma,
						'CommaAfterLast'
					);

					if ( true === $fix ) {
						$this->phpcsFile->fixer->replaceToken( $maybe_comma, '' );
					}
				}

				/*
				 * No need to do the spacing checks for the last item in a single line array.
				 * This is handled by another sniff checking the spacing before the array closer.
				 */
				continue;
			}

			$last_content = $this->phpcsFile->findPrevious(
				Tokens::$emptyTokens,
				$item['end'],
				$item['start'],
				true
			);

			if ( false === $last_content ) {
				// Shouldn't be able to happen, but just in case, ignore this array item.
				continue;
			}

			/**
			 * Make sure every item in a multi-line array has a comma at the end.
			 *
			 * Should in reality only be triggered by the last item in a multi-line array
			 * as otherwise we'd have a parse error already.
			 */
			if ( false === $is_comma && false === $single_line ) {

				$fix = $this->phpcsFile->addFixableError(
					'Each array item in a multi-line array declaration must end in a comma',
					$last_content,
					'NoComma'
				);

				if ( true === $fix ) {
					$this->phpcsFile->fixer->addContent( $last_content, ',' );
				}
			}

			if ( false === $single_line && $item_index === $array_item_count ) {
				$this->phpcsFile->recordMetric(
					$stackPtr,
					'Multi-line array - comma after last item',
					( true === $is_comma ? 'yes' : 'no' )
				);
			}

			if ( false === $is_comma ) {
				// Can't check spacing around the comma if there is no comma.
				continue;
			}

			/*
			 * Check for whitespace at the end of the array item.
			 */
			if ( $last_content !== $item['end']
				// Ignore whitespace at the end of a multi-line item if it is the end of a heredoc/nowdoc.
				&& ( true === $single_line
					|| ! isset( Tokens::$heredocTokens[ $this->tokens[ $last_content ]['code'] ] ) )
			) {
				$newlines = 0;
				$spaces   = 0;
				for ( $i = $item['end']; $i > $last_content; $i-- ) {

					if ( \T_WHITESPACE === $this->tokens[ $i ]['code'] ) {
						if ( $this->tokens[ $i ]['content'] === $this->phpcsFile->eolChar ) {
							$newlines++;
						} else {
							$spaces += $this->tokens[ $i ]['length'];
						}
					} elseif ( \T_COMMENT === $this->tokens[ $i ]['code']
						|| isset( Tokens::$phpcsCommentTokens[ $this->tokens[ $i ]['code'] ] )
					) {
						break;
					}
				}

				$space_phrases = array();
				if ( $spaces > 0 ) {
					$space_phrases[] = $spaces . ' spaces';
				}
				if ( $newlines > 0 ) {
					$space_phrases[] = $newlines . ' newlines';
				}
				unset( $newlines, $spaces );

				$fix = $this->phpcsFile->addFixableError(
					'Expected 0 spaces between "%s" and comma; %s found',
					$maybe_comma,
					'SpaceBeforeComma',
					array(
						$this->tokens[ $last_content ]['content'],
						implode( ' and ', $space_phrases ),
					)
				);

				if ( true === $fix ) {
					$this->phpcsFile->fixer->beginChangeset();
					for ( $i = $item['end']; $i > $last_content; $i-- ) {

						if ( \T_WHITESPACE === $this->tokens[ $i ]['code'] ) {
							$this->phpcsFile->fixer->replaceToken( $i, '' );

						} elseif ( \T_COMMENT === $this->tokens[ $i ]['code']
							|| isset( Tokens::$phpcsCommentTokens[ $this->tokens[ $i ]['code'] ] )
						) {
							// We need to move the comma to before the comment.
							$this->phpcsFile->fixer->addContent( $last_content, ',' );
							$this->phpcsFile->fixer->replaceToken( $maybe_comma, '' );

							/*
							 * No need to worry about removing too much whitespace in
							 * combination with a `//` comment as in that case, the newline
							 * is part of the comment, so we're good.
							 */

							break;
						}
					}
					$this->phpcsFile->fixer->endChangeset();
				}
			}

			if ( ! isset( $this->tokens[ ( $maybe_comma + 1 ) ] ) ) {
				// Shouldn't be able to happen, but just in case.
				continue;
			}

			/*
			 * Check whitespace after the comma.
			 */
			$next_token = $this->tokens[ ( $maybe_comma + 1 ) ];

			if ( \T_WHITESPACE === $next_token['code'] ) {

				if ( false === $single_line && $this->phpcsFile->eolChar === $next_token['content'] ) {
					continue;
				}

				$next_non_whitespace = $this->phpcsFile->findNext(
					\T_WHITESPACE,
					( $maybe_comma + 1 ),
					$closer,
					true
				);

				if ( false === $next_non_whitespace
					|| ( false === $single_line
						&& $this->tokens[ $next_non_whitespace ]['line'] === $this->tokens[ $maybe_comma ]['line']
						&& ( \T_COMMENT === $this->tokens[ $next_non_whitespace ]['code']
							|| isset( Tokens::$phpcsCommentTokens[ $this->tokens[ $next_non_whitespace ]['code'] ] ) ) )
				) {
					continue;
				}

				$space_length = $next_token['length'];
				if ( 1 === $space_length ) {
					continue;
				}

				$fix = $this->phpcsFile->addFixableError(
					'Expected 1 space between comma and "%s"; %s found',
					$maybe_comma,
					'SpaceAfterComma',
					array(
						$this->tokens[ $next_non_whitespace ]['content'],
						$space_length,
					)
				);

				if ( true === $fix ) {
					$this->phpcsFile->fixer->replaceToken( ( $maybe_comma + 1 ), ' ' );
				}
			} else {
				// This is either a comment or a mixed single/multi-line array.
				// Just add a space and let other sniffs sort out the array layout.
				$fix = $this->phpcsFile->addFixableError(
					'Expected 1 space between comma and "%s"; 0 found',
					$maybe_comma,
					'NoSpaceAfterComma',
					array( $next_token['content'] )
				);

				if ( true === $fix ) {
					$this->phpcsFile->fixer->addContent( $maybe_comma, ' ' );
				}
			}
		}
	}

}
