<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\WP;

use WordPressCS\WordPress\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Capital P Dangit!
 *
 * Verify the correct spelling of `WordPress` in text strings, comments and class names.
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.12.0
 * @since   0.13.0 Class name changed: this class is now namespaced.
 */
class CapitalPDangitSniff extends Sniff {

	/**
	 * Regex to match a large number or spelling variations of WordPress in text strings.
	 *
	 * Prevents matches on:
	 * - URLs for wordpress.org/com/net/test/tv.
	 * - `@...` usernames starting with `wordpress`
	 * - email addresses with a domain starting with `wordpress`
	 * - email addresses with a user name ending with `wordpress`
	 * - (most) variable names.
	 * - directory paths containing a folder starting or ending with `wordpress`.
	 * - file names containing `wordpress` for a limited set of extensions.
	 * - `wordpress` prefixed or suffixed with dashes as those are indicators that the
	 *   term is probably used as part of a CSS class, such as `fa-wordpress`
	 *   or filename/path like `class-wordpress-importer.php`.
	 * - back-tick quoted `wordpress`.
	 *
	 * @var string
	 */
	const WP_REGEX = '#(?<![\\\\/\$@`-])\b(Word[ _-]*Pres+)\b(?![@/`-]|\.(?:org|com|net|test|tv)|[^\s<>\'"()]*?\.(?:php|js|css|png|j[e]?pg|gif|pot))#i';

	/**
	 * Regex to match a large number or spelling variations of WordPress in class names.
	 *
	 * @var string
	 */
	const WP_CLASSNAME_REGEX = '`(?:^|_)(Word[_]*Pres+)(?:_|$)`i';

	/**
	 * String tokens we want to listen for.
	 *
	 * @var array
	 */
	private $text_string_tokens = array(
		\T_CONSTANT_ENCAPSED_STRING => \T_CONSTANT_ENCAPSED_STRING,
		\T_DOUBLE_QUOTED_STRING     => \T_DOUBLE_QUOTED_STRING,
		\T_HEREDOC                  => \T_HEREDOC,
		\T_NOWDOC                   => \T_NOWDOC,
		\T_INLINE_HTML              => \T_INLINE_HTML,
	);

	/**
	 * Comment tokens we want to listen for as they contain text strings.
	 *
	 * @var array
	 */
	private $comment_text_tokens = array(
		\T_DOC_COMMENT        => \T_DOC_COMMENT,
		\T_DOC_COMMENT_STRING => \T_DOC_COMMENT_STRING,
		\T_COMMENT            => \T_COMMENT,
	);

	/**
	 * Combined text string and comment tokens array.
	 *
	 * This property is set in the register() method and used for lookups.
	 *
	 * @var array
	 */
	private $text_and_comment_tokens = array();

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @since 0.12.0
	 *
	 * @return array
	 */
	public function register() {
		// Union the arrays - keeps the array keys.
		$this->text_and_comment_tokens = ( $this->text_string_tokens + $this->comment_text_tokens );

		$targets = ( $this->text_and_comment_tokens + Tokens::$ooScopeTokens );

		// Also sniff for array tokens to make skipping anything within those more efficient.
		$targets[ \T_ARRAY ]            = \T_ARRAY;
		$targets[ \T_OPEN_SHORT_ARRAY ] = \T_OPEN_SHORT_ARRAY;

		return $targets;
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @since 0.12.0
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return int|void Integer stack pointer to skip forward or void to continue
	 *                  normal file processing.
	 */
	public function process_token( $stackPtr ) {

		if ( $this->has_whitelist_comment( 'spelling', $stackPtr ) ) {
			return;
		}

		/*
		 * Ignore tokens within an array definition as this is a false positive in 80% of all cases.
		 *
		 * The return values skip to the end of the array.
		 * This prevents the sniff "hanging" on very long configuration arrays.
		 */
		if ( \T_OPEN_SHORT_ARRAY === $this->tokens[ $stackPtr ]['code'] && isset( $this->tokens[ $stackPtr ]['bracket_closer'] ) ) {
			return $this->tokens[ $stackPtr ]['bracket_closer'];
		} elseif ( \T_ARRAY === $this->tokens[ $stackPtr ]['code'] && isset( $this->tokens[ $stackPtr ]['parenthesis_closer'] ) ) {
			return $this->tokens[ $stackPtr ]['parenthesis_closer'];
		}

		/*
		 * Deal with misspellings in class/interface/trait names.
		 * These are not auto-fixable, but need the attention of a developer.
		 */
		if ( isset( Tokens::$ooScopeTokens[ $this->tokens[ $stackPtr ]['code'] ] ) ) {
			$classname = $this->phpcsFile->getDeclarationName( $stackPtr );
			if ( empty( $classname ) ) {
				return;
			}

			if ( preg_match_all( self::WP_CLASSNAME_REGEX, $classname, $matches, \PREG_PATTERN_ORDER ) > 0 ) {
				$mispelled = $this->retrieve_misspellings( $matches[1] );

				if ( ! empty( $mispelled ) ) {
					$this->phpcsFile->addWarning(
						'Please spell "WordPress" correctly. Found: "%s" as part of the class/interface/trait name.',
						$stackPtr,
						'MisspelledClassName',
						array( implode( ', ', $mispelled ) )
					);
				}
			}

			return;
		}

		/*
		 * Deal with misspellings in text strings and documentation.
		 */

		// Ignore content of docblock @link tags.
		if ( \T_DOC_COMMENT_STRING === $this->tokens[ $stackPtr ]['code']
			|| \T_DOC_COMMENT === $this->tokens[ $stackPtr ]['code']
		) {

			$comment_start = $this->phpcsFile->findPrevious( \T_DOC_COMMENT_OPEN_TAG, ( $stackPtr - 1 ) );
			if ( false !== $comment_start ) {
				$comment_tag = $this->phpcsFile->findPrevious( \T_DOC_COMMENT_TAG, ( $stackPtr - 1 ), $comment_start );
				if ( false !== $comment_tag && '@link' === $this->tokens[ $comment_tag ]['content'] ) {
					// @link tag, so ignore.
					return;
				}
			}
		}

		// Ignore any text strings which are array keys `$var['key']` as this is a false positive in 80% of all cases.
		if ( \T_CONSTANT_ENCAPSED_STRING === $this->tokens[ $stackPtr ]['code'] ) {
			$prevToken = $this->phpcsFile->findPrevious( Tokens::$emptyTokens, ( $stackPtr - 1 ), null, true, null, true );
			if ( false !== $prevToken && \T_OPEN_SQUARE_BRACKET === $this->tokens[ $prevToken ]['code'] ) {
				$nextToken = $this->phpcsFile->findNext( Tokens::$emptyTokens, ( $stackPtr + 1 ), null, true, null, true );
				if ( false !== $nextToken && \T_CLOSE_SQUARE_BRACKET === $this->tokens[ $nextToken ]['code'] ) {
					return;
				}
			}
		}

		// Ignore constant declarations via define().
		if ( $this->is_in_function_call( $stackPtr, array( 'define' => true ), true, true ) ) {
			return;
		}

		// Ignore constant declarations using the const keyword.
		$stop_points = array(
			\T_CONST,
			\T_SEMICOLON,
			\T_OPEN_TAG,
			\T_CLOSE_TAG,
			\T_OPEN_CURLY_BRACKET,
		);
		$maybe_const = $this->phpcsFile->findPrevious( $stop_points, ( $stackPtr - 1 ) );
		if ( false !== $maybe_const && \T_CONST === $this->tokens[ $maybe_const ]['code'] ) {
			return;
		}

		$content = $this->tokens[ $stackPtr ]['content'];

		if ( preg_match_all( self::WP_REGEX, $content, $matches, ( \PREG_PATTERN_ORDER | \PREG_OFFSET_CAPTURE ) ) > 0 ) {
			/*
			 * Prevent some typical false positives.
			 */
			if ( isset( $this->text_and_comment_tokens[ $this->tokens[ $stackPtr ]['code'] ] ) ) {
				$offset = 0;
				foreach ( $matches[1] as $key => $match_data ) {
					$next_offset = ( $match_data[1] + \strlen( $match_data[0] ) );

					// Prevent matches on part of a URL.
					if ( preg_match( '`http[s]?://[^\s<>\'"()]*' . preg_quote( $match_data[0], '`' ) . '`', $content, $discard, 0, $offset ) === 1 ) {
						unset( $matches[1][ $key ] );
					} elseif ( preg_match( '`[a-z]+=(["\'])' . preg_quote( $match_data[0], '`' ) . '\1`', $content, $discard, 0, $offset ) === 1 ) {
						// Prevent matches on html attributes like: `value="wordpress"`.
						unset( $matches[1][ $key ] );
					} elseif ( preg_match( '`\\\\\'' . preg_quote( $match_data[0], '`' ) . '\\\\\'`', $content, $discard, 0, $offset ) === 1 ) {
						// Prevent matches on xpath queries and such: `\'wordpress\'`.
						unset( $matches[1][ $key ] );
					} elseif ( preg_match( '`(?:\?|&amp;|&)[a-z0-9_]+=' . preg_quote( $match_data[0], '`' ) . '(?:&|$)`', $content, $discard, 0, $offset ) === 1 ) {
						// Prevent matches on url query strings: `?something=wordpress`.
						unset( $matches[1][ $key ] );
					}

					$offset = $next_offset;
				}

				if ( empty( $matches[1] ) ) {
					return;
				}
			}

			$mispelled = $this->retrieve_misspellings( $matches[1] );

			if ( empty( $mispelled ) ) {
				return;
			}

			$fix = $this->phpcsFile->addFixableWarning(
				'Please spell "WordPress" correctly. Found %s misspelling(s): %s',
				$stackPtr,
				'Misspelled',
				array(
					\count( $mispelled ),
					implode( ', ', $mispelled ),
				)
			);

			if ( true === $fix ) {
				// Apply fixes based on offset to ensure we don't replace false positives.
				$replacement = $content;
				foreach ( $matches[1] as $match ) {
					$replacement = substr_replace( $replacement, 'WordPress', $match[1], \strlen( $match[0] ) );
				}

				$this->phpcsFile->fixer->replaceToken( $stackPtr, $replacement );
			}
		}
	}

	/**
	 * Retrieve a list of misspellings based on an array of matched variations on the target word.
	 *
	 * @param array $match_stack Array of matched variations of the target word.
	 * @return array Array containing only the misspelled variants.
	 */
	protected function retrieve_misspellings( $match_stack ) {
		$mispelled = array();
		foreach ( $match_stack as $match ) {
			// Deal with multi-dimensional arrays when capturing offset.
			if ( \is_array( $match ) ) {
				$match = $match[0];
			}

			if ( 'WordPress' !== $match ) {
				$mispelled[] = $match;
			}
		}

		return $mispelled;
	}

}
