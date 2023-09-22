<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\PHP;

use WordPressCS\WordPress\Sniff;

/**
 * Verifies the correct usage of type cast keywords.
 *
 * Type casts should be:
 * - normalized, i.e. (float) not (real).
 *
 * Additionally, the use of the (unset) and (binary) casts is discouraged.
 *
 * @link    https://make.wordpress.org/core/handbook/best-practices/....
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   1.2.0
 * @since   2.0.0 No longer checks that type casts are lowercase or short form.
 *                Relevant PHPCS native sniffs have been included in the rulesets instead.
 */
class TypeCastsSniff extends Sniff {

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return array(
			\T_DOUBLE_CAST,
			\T_UNSET_CAST,
			\T_STRING_CAST,
			\T_BINARY_CAST,
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

		$token_code  = $this->tokens[ $stackPtr ]['code'];
		$typecast    = str_replace( ' ', '', $this->tokens[ $stackPtr ]['content'] );
		$typecast_lc = strtolower( $typecast );

		switch ( $token_code ) {
			case \T_DOUBLE_CAST:
				if ( '(float)' !== $typecast_lc ) {
					$fix = $this->phpcsFile->addFixableError(
						'Normalized type keywords must be used; expected "(float)" but found "%s"',
						$stackPtr,
						'DoubleRealFound',
						array( $typecast )
					);

					if ( true === $fix ) {
						$this->phpcsFile->fixer->replaceToken( $stackPtr, '(float)' );
					}
				}
				break;

			case \T_UNSET_CAST:
				$this->phpcsFile->addWarning(
					'Using the "(unset)" cast is strongly discouraged. Use the "unset()" language construct or assign "null" as the value to the variable instead.',
					$stackPtr,
					'UnsetFound'
				);
				break;

			case \T_STRING_CAST:
			case \T_BINARY_CAST:
				if ( \T_STRING_CAST === $token_code && '(binary)' !== $typecast_lc ) {
					break;
				}

				$this->phpcsFile->addWarning(
					'Using binary casting is strongly discouraged. Found: "%s"',
					$stackPtr,
					'BinaryFound',
					array( $typecast )
				);
				break;
		}
	}

}
