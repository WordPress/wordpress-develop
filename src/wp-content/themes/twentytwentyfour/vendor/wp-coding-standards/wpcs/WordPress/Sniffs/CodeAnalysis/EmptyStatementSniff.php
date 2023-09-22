<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\CodeAnalysis;

use WordPressCS\WordPress\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Checks against empty statements.
 *
 * - Check against two semi-colons with no executable code in between.
 * - Check against an empty PHP open - close tag combination.
 *
 * {@internal This check should at some point in the future be pulled upstream and probably
 *            merged into the upstream `Generic.CodeAnalysis.EmptyStatement` sniff.
 *            This will need to wait until the WPCS minimum requirements have gone up
 *            beyond PHPCS 3.x though as it is not likely that new features will be accepted
 *            still for the PHPCS 2.x branch.}}
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.12.0
 * @since   0.13.0 Class name changed: this class is now namespaced.
 */
class EmptyStatementSniff extends Sniff {

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return array(
			\T_SEMICOLON,
			\T_CLOSE_TAG,
		);
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return int|void Integer stack pointer to skip forward or void to continue
	 *                  normal file processing.
	 */
	public function process_token( $stackPtr ) {

		switch ( $this->tokens[ $stackPtr ]['type'] ) {
			/*
			 * Detect `something();;`.
			 */
			case 'T_SEMICOLON':
				$prevNonEmpty = $this->phpcsFile->findPrevious(
					Tokens::$emptyTokens,
					( $stackPtr - 1 ),
					null,
					true
				);

				if ( false === $prevNonEmpty
					|| ( \T_SEMICOLON !== $this->tokens[ $prevNonEmpty ]['code']
						&& \T_OPEN_TAG !== $this->tokens[ $prevNonEmpty ]['code']
						&& \T_OPEN_TAG_WITH_ECHO !== $this->tokens[ $prevNonEmpty ]['code'] )
				) {
					return;
				}

				if ( isset( $this->tokens[ $stackPtr ]['nested_parenthesis'] ) ) {
					$nested      = $this->tokens[ $stackPtr ]['nested_parenthesis'];
					$last_closer = array_pop( $nested );
					if ( isset( $this->tokens[ $last_closer ]['parenthesis_owner'] )
						&& \T_FOR === $this->tokens[ $this->tokens[ $last_closer ]['parenthesis_owner'] ]['code']
					) {
						// Empty for() condition.
						return;
					}
				}

				$fix = $this->phpcsFile->addFixableWarning(
					'Empty PHP statement detected: superfluous semi-colon.',
					$stackPtr,
					'SemicolonWithoutCodeDetected'
				);
				if ( true === $fix ) {
					$this->phpcsFile->fixer->beginChangeset();

					if ( \T_OPEN_TAG === $this->tokens[ $prevNonEmpty ]['code']
						|| \T_OPEN_TAG_WITH_ECHO === $this->tokens[ $prevNonEmpty ]['code']
					) {
						/*
						 * Check for superfluous whitespace after the semi-colon which will be
						 * removed as the `<?php ` open tag token already contains whitespace,
						 * either a space or a new line and in case of a new line, the indentation
						 * should be done via tabs, so spaces can be safely removed.
						 */
						if ( \T_WHITESPACE === $this->tokens[ ( $stackPtr + 1 ) ]['code'] ) {
							$replacement = str_replace( ' ', '', $this->tokens[ ( $stackPtr + 1 ) ]['content'] );
							$this->phpcsFile->fixer->replaceToken( ( $stackPtr + 1 ), $replacement );
						}
					}

					for ( $i = $stackPtr; $i > $prevNonEmpty; $i-- ) {
						if ( \T_SEMICOLON !== $this->tokens[ $i ]['code']
							&& \T_WHITESPACE !== $this->tokens[ $i ]['code']
						) {
							break;
						}
						$this->phpcsFile->fixer->replaceToken( $i, '' );
					}

					$this->phpcsFile->fixer->endChangeset();
				}
				break;

			/*
			 * Detect `<?php ?>`.
			 */
			case 'T_CLOSE_TAG':
				$prevNonEmpty = $this->phpcsFile->findPrevious(
					\T_WHITESPACE,
					( $stackPtr - 1 ),
					null,
					true
				);

				if ( false === $prevNonEmpty
					|| ( \T_OPEN_TAG !== $this->tokens[ $prevNonEmpty ]['code']
						&& \T_OPEN_TAG_WITH_ECHO !== $this->tokens[ $prevNonEmpty ]['code'] )
				) {
					return;
				}

				$fix = $this->phpcsFile->addFixableWarning(
					'Empty PHP open/close tag combination detected.',
					$prevNonEmpty,
					'EmptyPHPOpenCloseTagsDetected'
				);
				if ( true === $fix ) {
					$this->phpcsFile->fixer->beginChangeset();
					for ( $i = $prevNonEmpty; $i <= $stackPtr; $i++ ) {
						$this->phpcsFile->fixer->replaceToken( $i, '' );
					}
					$this->phpcsFile->fixer->endChangeset();
				}
				break;

			default:
				/* Deliberately left empty. */
				break;
		}
	}

}
