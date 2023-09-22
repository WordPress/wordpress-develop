<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\DateTime;

use WordPressCS\WordPress\AbstractFunctionParameterSniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Don't use current_time() to get a (timezone corrected) "timestamp".
 *
 * Disallow using the current_time() function to get "timestamps" as it
 * doesn't produce a *real* timestamp, but a "WordPress timestamp", i.e.
 * a Unix timestamp with current timezone offset, not a Unix timestamp ansich.
 *
 * @link https://developer.wordpress.org/reference/functions/current_time/
 * @link https://make.wordpress.org/core/2019/09/23/date-time-improvements-wp-5-3/
 * @link https://core.trac.wordpress.org/ticket/40657
 * @link https://github.com/WordPress/WordPress-Coding-Standards/issues/1791
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   2.2.0
 */
class CurrentTimeTimestampSniff extends AbstractFunctionParameterSniff {

	/**
	 * The group name for this group of functions.
	 *
	 * @since 2.2.0
	 *
	 * @var string
	 */
	protected $group_name = 'current_time';

	/**
	 * List of functions to examine.
	 *
	 * @since 2.2.0
	 *
	 * @var array <string function_name> => <bool always needed ?>
	 */
	protected $target_functions = array(
		'current_time' => true,
	);

	/**
	 * Process the parameters of a matched function.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $stackPtr        The position of the current token in the stack.
	 * @param string $group_name      The name of the group which was matched.
	 * @param string $matched_content The token content (function name) which was matched.
	 * @param array  $parameters      Array with information about the parameters.
	 *
	 * @return void
	 */
	public function process_parameters( $stackPtr, $group_name, $matched_content, $parameters ) {
		/*
		 * We already know there will be valid open & close parentheses as otherwise the parameter
		 * retrieval function call would have returned an empty array, so no additional checks needed.
		 */
		$open_parens  = $this->phpcsFile->findNext( \T_OPEN_PARENTHESIS, $stackPtr );
		$close_parens = $this->tokens[ $open_parens ]['parenthesis_closer'];

		/*
		 * Check whether the first parameter is a timestamp format.
		 */
		for ( $i = $parameters[1]['start']; $i <= $parameters[1]['end']; $i++ ) {
			if ( isset( Tokens::$emptyTokens[ $this->tokens[ $i ]['code'] ] ) ) {
				continue;
			}

			if ( isset( Tokens::$textStringTokens[ $this->tokens[ $i ]['code'] ] ) ) {
				$content_first = trim( $this->strip_quotes( $this->tokens[ $i ]['content'] ) );
				if ( 'U' !== $content_first && 'timestamp' !== $content_first ) {
					// Most likely valid use of current_time().
					return;
				}

				continue;
			}

			if ( isset( Tokens::$heredocTokens[ $this->tokens[ $i ]['code'] ] ) ) {
				continue;
			}

			/*
			 * If we're still here, we've encountered an unexpected token, like a variable or
			 * function call. Bow out as we can't determine the runtime value.
			 */
			return;
		}

		$gmt_true = false;

		/*
		 * Check whether the second parameter, $gmt, is a set to `true` or `1`.
		 */
		if ( isset( $parameters[2] ) ) {
			$content_second = '';
			if ( 'true' === $parameters[2]['raw'] || '1' === $parameters[2]['raw'] ) {
				$content_second = $parameters[2]['raw'];
				$gmt_true       = true;
			} else {
				// Do a more extensive parameter check.
				for ( $i = $parameters[2]['start']; $i <= $parameters[2]['end']; $i++ ) {
					if ( isset( Tokens::$emptyTokens[ $this->tokens[ $i ]['code'] ] ) ) {
						continue;
					}

					$content_second .= $this->tokens[ $i ]['content'];
				}

				if ( 'true' === $content_second || '1' === $content_second ) {
					$gmt_true = true;
				}
			}
		}

		/*
		 * Non-UTC timestamp requested.
		 */
		if ( false === $gmt_true ) {
			$this->phpcsFile->addWarning(
				'Calling current_time() with a $type of "timestamp" or "U" is strongly discouraged as it will not return a Unix (UTC) timestamp. Please consider using a non-timestamp format or otherwise refactoring this code.',
				$stackPtr,
				'Requested'
			);

			return;
		}

		/*
		 * UTC timestamp requested. Should use time() instead.
		 */
		$has_comment = $this->phpcsFile->findNext( Tokens::$commentTokens, ( $stackPtr + 1 ), ( $close_parens + 1 ) );
		$error       = 'Don\'t use current_time() for retrieving a Unix (UTC) timestamp. Use time() instead. Found: %s';
		$error_code  = 'RequestedUTC';

		$code_snippet = "current_time( '" . $content_first . "'";
		if ( isset( $content_second ) ) {
			$code_snippet .= ', ' . $content_second;
		}
		$code_snippet .= ' )';

		if ( false !== $has_comment ) {
			// If there are comments, we don't auto-fix as it would remove those comments.
			$this->phpcsFile->addError( $error, $stackPtr, $error_code, array( $code_snippet ) );

			return;
		}

		$fix = $this->phpcsFile->addFixableError( $error, $stackPtr, $error_code, array( $code_snippet ) );
		if ( true === $fix ) {
			$this->phpcsFile->fixer->beginChangeset();

			for ( $i = ( $stackPtr + 1 ); $i < $close_parens; $i++ ) {
				$this->phpcsFile->fixer->replaceToken( $i, '' );
			}

			$this->phpcsFile->fixer->replaceToken( $stackPtr, 'time(' );
			$this->phpcsFile->fixer->endChangeset();
		}
	}

}
