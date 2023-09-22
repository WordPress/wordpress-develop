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
use PHP_CodeSniffer\Util\Tokens;

/**
 * Warn on line indentation ending with spaces for precision alignment.
 *
 * WP demands tabs for indentation. In rare cases, spaces for precision alignment can be
 * intentional and acceptable, but more often than not, this is a typo.
 *
 * The `Generic.WhiteSpace.DisallowSpaceIndent` sniff already checks for space indentation
 * and auto-fixes to tabs.
 *
 * This sniff only checks for precision alignments which can not be corrected by the
 * `Generic.WhiteSpace.DisallowSpaceIndent` sniff.
 *
 * As this may be intentional, this sniff explicitly does *NOT* contain a fixer.
 *
 * @link    https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/#indentation
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.14.0
 */
class PrecisionAlignmentSniff extends Sniff {

	/**
	 * A list of tokenizers this sniff supports.
	 *
	 * @var array
	 */
	public $supportedTokenizers = array(
		'PHP',
		'JS',
		'CSS',
	);

	/**
	 * Allow for providing a list of tokens for which (preceding) precision alignment should be ignored.
	 *
	 * <rule ref="WordPress.WhiteSpace.PrecisionAlignment">
	 *    <properties>
	 *        <property name="ignoreAlignmentTokens" type="array">
	 *            <element value="T_COMMENT"/>
	 *            <element value="T_INLINE_HTML"/>
	 *        </property>
	 *    </properties>
	 * </rule>
	 *
	 * @var array
	 */
	public $ignoreAlignmentTokens = array();

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
			\T_OPEN_TAG_WITH_ECHO,
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

		// Handle any custom ignore tokens received from a ruleset.
		$ignoreAlignmentTokens = $this->merge_custom_array( $this->ignoreAlignmentTokens );

		$check_tokens  = array(
			\T_WHITESPACE             => true,
			\T_INLINE_HTML            => true,
			\T_DOC_COMMENT_WHITESPACE => true,
			\T_COMMENT                => true,
		);
		$check_tokens += Tokens::$phpcsCommentTokens;

		for ( $i = 0; $i < $this->phpcsFile->numTokens; $i++ ) {

			if ( 1 !== $this->tokens[ $i ]['column'] ) {
				continue;
			} elseif ( isset( $check_tokens[ $this->tokens[ $i ]['code'] ] ) === false
				|| ( isset( $this->tokens[ ( $i + 1 ) ] )
					&& \T_WHITESPACE === $this->tokens[ ( $i + 1 ) ]['code'] )
				|| $this->tokens[ $i ]['content'] === $this->phpcsFile->eolChar
				|| isset( $ignoreAlignmentTokens[ $this->tokens[ $i ]['type'] ] )
				|| ( isset( $this->tokens[ ( $i + 1 ) ] )
					&& isset( $ignoreAlignmentTokens[ $this->tokens[ ( $i + 1 ) ]['type'] ] ) )
			) {
				continue;
			}

			$spaces = 0;
			switch ( $this->tokens[ $i ]['type'] ) {
				case 'T_WHITESPACE':
					$spaces = ( $this->tokens[ $i ]['length'] % $this->tab_width );
					break;

				case 'T_DOC_COMMENT_WHITESPACE':
					$length = $this->tokens[ $i ]['length'];
					$spaces = ( $length % $this->tab_width );

					if ( isset( $this->tokens[ ( $i + 1 ) ] )
						&& ( \T_DOC_COMMENT_STAR === $this->tokens[ ( $i + 1 ) ]['code']
							|| \T_DOC_COMMENT_CLOSE_TAG === $this->tokens[ ( $i + 1 ) ]['code'] )
						&& 0 !== $spaces
					) {
						// One alignment space expected before the *.
						--$spaces;
					}
					break;

				case 'T_COMMENT':
				case 'T_PHPCS_ENABLE':
				case 'T_PHPCS_DISABLE':
				case 'T_PHPCS_SET':
				case 'T_PHPCS_IGNORE':
				case 'T_PHPCS_IGNORE_FILE':
					/*
					 * Indentation whitespace for subsequent lines of multi-line comments
					 * are tokenized as part of the comment.
					 */
					$comment    = ltrim( $this->tokens[ $i ]['content'] );
					$whitespace = str_replace( $comment, '', $this->tokens[ $i ]['content'] );
					$length     = \strlen( $whitespace );
					$spaces     = ( $length % $this->tab_width );

					if ( isset( $comment[0] ) && '*' === $comment[0] && 0 !== $spaces ) {
						--$spaces;
					}
					break;

				case 'T_INLINE_HTML':
					if ( $this->tokens[ $i ]['content'] === $this->phpcsFile->eolChar ) {
						$spaces = 0;
					} else {
						/*
						 * Indentation whitespace for inline HTML is part of the T_INLINE_HTML token.
						 */
						$content    = ltrim( $this->tokens[ $i ]['content'] );
						$whitespace = str_replace( $content, '', $this->tokens[ $i ]['content'] );
						$spaces     = ( \strlen( $whitespace ) % $this->tab_width );
					}

					/*
					 * Prevent triggering on multi-line /*-style inline javascript comments.
					 * This may cause false negatives as there is no check for being in a
					 * <script> tag, but that will be rare.
					 */
					if ( isset( $content[0] ) && '*' === $content[0] && 0 !== $spaces ) {
						--$spaces;
					}
					break;
			}

			if ( $spaces > 0 && ! $this->has_whitelist_comment( 'precision alignment', $i ) ) {
				$this->phpcsFile->addWarning(
					'Found precision alignment of %s spaces.',
					$i,
					'Found',
					array( $spaces )
				);
			}
		}

		// Ignore the rest of the file.
		return ( $this->phpcsFile->numTokens + 1 );
	}

}
