<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\DB;

use WordPressCS\WordPress\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Sniff for prepared SQL.
 *
 * Makes sure that variables aren't directly interpolated into SQL statements.
 *
 * @link    https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/#formatting-sql-statements
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.8.0
 * @since   0.13.0 Class name changed: this class is now namespaced.
 * @since   1.0.0  This sniff has been moved from the `WP` category to the `DB` category.
 */
class PreparedSQLSniff extends Sniff {

	/**
	 * The lists of $wpdb methods.
	 *
	 * @since 0.8.0
	 * @since 0.11.0 Changed from static to non-static.
	 *
	 * @var array
	 */
	protected $methods = array(
		'get_var'     => true,
		'get_col'     => true,
		'get_row'     => true,
		'get_results' => true,
		'prepare'     => true,
		'query'       => true,
	);

	/**
	 * Tokens that we don't flag when they are found in a $wpdb method call.
	 *
	 * @since 0.9.0
	 *
	 * @var array
	 */
	protected $ignored_tokens = array(
		\T_OBJECT_OPERATOR          => true,
		\T_OPEN_PARENTHESIS         => true,
		\T_CLOSE_PARENTHESIS        => true,
		\T_STRING_CONCAT            => true,
		\T_CONSTANT_ENCAPSED_STRING => true,
		\T_OPEN_SQUARE_BRACKET      => true,
		\T_CLOSE_SQUARE_BRACKET     => true,
		\T_COMMA                    => true,
		\T_LNUMBER                  => true,
		\T_START_HEREDOC            => true,
		\T_END_HEREDOC              => true,
		\T_START_NOWDOC             => true,
		\T_NOWDOC                   => true,
		\T_END_NOWDOC               => true,
		\T_INT_CAST                 => true,
		\T_DOUBLE_CAST              => true,
		\T_BOOL_CAST                => true,
		\T_NS_SEPARATOR             => true,
	);

	/**
	 * A loop pointer.
	 *
	 * It is a property so that we can access it in all of our methods.
	 *
	 * @since 0.9.0
	 *
	 * @var int
	 */
	protected $i;

	/**
	 * The loop end marker.
	 *
	 * It is a property so that we can access it in all of our methods.
	 *
	 * @since 0.9.0
	 *
	 * @var int
	 */
	protected $end;

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @since 0.8.0
	 *
	 * @return array
	 */
	public function register() {

		$this->ignored_tokens += Tokens::$emptyTokens;

		return array(
			\T_VARIABLE,
			\T_STRING,
		);
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @since 0.8.0
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return int|void Integer stack pointer to skip forward or void to continue
	 *                  normal file processing.
	 */
	public function process_token( $stackPtr ) {

		if ( ! $this->is_wpdb_method_call( $stackPtr, $this->methods ) ) {
			return;
		}

		if ( $this->has_whitelist_comment( 'unprepared SQL', $stackPtr ) ) {
			return;
		}

		for ( $this->i; $this->i < $this->end; $this->i++ ) {

			if ( isset( $this->ignored_tokens[ $this->tokens[ $this->i ]['code'] ] ) ) {
				continue;
			}

			if ( \T_DOUBLE_QUOTED_STRING === $this->tokens[ $this->i ]['code']
				|| \T_HEREDOC === $this->tokens[ $this->i ]['code']
			) {

				$bad_variables = array_filter(
					$this->get_interpolated_variables( $this->tokens[ $this->i ]['content'] ),
					function ( $symbol ) {
						return ( 'wpdb' !== $symbol );
					}
				);

				foreach ( $bad_variables as $bad_variable ) {
					$this->phpcsFile->addError(
						'Use placeholders and $wpdb->prepare(); found interpolated variable $%s at %s',
						$this->i,
						'InterpolatedNotPrepared',
						array(
							$bad_variable,
							$this->tokens[ $this->i ]['content'],
						)
					);
				}
				continue;
			}

			if ( \T_VARIABLE === $this->tokens[ $this->i ]['code'] ) {
				if ( '$wpdb' === $this->tokens[ $this->i ]['content'] ) {
					$this->is_wpdb_method_call( $this->i, $this->methods );
					continue;
				}

				if ( $this->is_safe_casted( $this->i ) ) {
					continue;
				}
			}

			if ( \T_STRING === $this->tokens[ $this->i ]['code'] ) {

				if (
					isset( $this->SQLEscapingFunctions[ $this->tokens[ $this->i ]['content'] ] )
					|| isset( $this->SQLAutoEscapedFunctions[ $this->tokens[ $this->i ]['content'] ] )
				) {

					// Find the opening parenthesis.
					$opening_paren = $this->phpcsFile->findNext( Tokens::$emptyTokens, ( $this->i + 1 ), null, true, null, true );

					if ( false !== $opening_paren
						&& \T_OPEN_PARENTHESIS === $this->tokens[ $opening_paren ]['code']
						&& isset( $this->tokens[ $opening_paren ]['parenthesis_closer'] )
					) {
						// Skip past the end of the function.
						$this->i = $this->tokens[ $opening_paren ]['parenthesis_closer'];
						continue;
					}
				} elseif ( isset( $this->formattingFunctions[ $this->tokens[ $this->i ]['content'] ] ) ) {
					continue;
				}
			}

			$this->phpcsFile->addError(
				'Use placeholders and $wpdb->prepare(); found %s',
				$this->i,
				'NotPrepared',
				array( $this->tokens[ $this->i ]['content'] )
			);
		}

		return $this->end;
	}

}
