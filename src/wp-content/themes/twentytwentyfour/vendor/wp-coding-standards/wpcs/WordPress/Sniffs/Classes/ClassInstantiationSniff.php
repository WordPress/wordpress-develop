<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\Classes;

use WordPressCS\WordPress\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Verifies object instantiation statements.
 *
 * - Demand the use of parenthesis.
 * - Demand no space between the class name and the parenthesis.
 * - Forbid assigning new by reference.
 *
 * {@internal Note: This sniff currently does not examine the parenthesis of new object
 * instantiations where the class name is held in a variable variable.}}
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.12.0
 * @since   0.13.0 Class name changed: this class is now namespaced.
 */
class ClassInstantiationSniff extends Sniff {

	/**
	 * A list of tokenizers this sniff supports.
	 *
	 * @var array
	 */
	public $supportedTokenizers = array(
		'PHP',
		'JS',
	);

	/**
	 * Tokens which can be part of a "classname".
	 *
	 * Set from within the register() method.
	 *
	 * @var array
	 */
	protected $classname_tokens = array();

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		/*
		 * Set the $classname_tokens property.
		 *
		 * Currently does not account for classnames passed as a variable variable.
		 */
		$this->classname_tokens                    = Tokens::$emptyTokens;
		$this->classname_tokens[ \T_NS_SEPARATOR ] = \T_NS_SEPARATOR;
		$this->classname_tokens[ \T_STRING ]       = \T_STRING;
		$this->classname_tokens[ \T_SELF ]         = \T_SELF;
		$this->classname_tokens[ \T_STATIC ]       = \T_STATIC;
		$this->classname_tokens[ \T_PARENT ]       = \T_PARENT;
		$this->classname_tokens[ \T_ANON_CLASS ]   = \T_ANON_CLASS;

		// Classname in a variable.
		$this->classname_tokens[ \T_VARIABLE ]                 = \T_VARIABLE;
		$this->classname_tokens[ \T_DOUBLE_COLON ]             = \T_DOUBLE_COLON;
		$this->classname_tokens[ \T_OBJECT_OPERATOR ]          = \T_OBJECT_OPERATOR;
		$this->classname_tokens[ \T_OPEN_SQUARE_BRACKET ]      = \T_OPEN_SQUARE_BRACKET;
		$this->classname_tokens[ \T_CLOSE_SQUARE_BRACKET ]     = \T_CLOSE_SQUARE_BRACKET;
		$this->classname_tokens[ \T_CONSTANT_ENCAPSED_STRING ] = \T_CONSTANT_ENCAPSED_STRING;
		$this->classname_tokens[ \T_LNUMBER ]                  = \T_LNUMBER;

		return array(
			\T_NEW,
			\T_STRING, // JS.
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
		// Make sure we have the right token, JS vs PHP.
		if ( ( 'PHP' === $this->phpcsFile->tokenizerType && \T_NEW !== $this->tokens[ $stackPtr ]['code'] )
			|| ( 'JS' === $this->phpcsFile->tokenizerType
				&& ( \T_STRING !== $this->tokens[ $stackPtr ]['code']
				|| 'new' !== strtolower( $this->tokens[ $stackPtr ]['content'] ) ) )
		) {
			return;
		}

		/*
		 * Check for new by reference used in PHP files.
		 */
		if ( 'PHP' === $this->phpcsFile->tokenizerType ) {
			$prev_non_empty = $this->phpcsFile->findPrevious(
				Tokens::$emptyTokens,
				( $stackPtr - 1 ),
				null,
				true
			);

			if ( false !== $prev_non_empty && 'T_BITWISE_AND' === $this->tokens[ $prev_non_empty ]['type'] ) {
				$this->phpcsFile->recordMetric( $stackPtr, 'Assigning new by reference', 'yes' );

				$this->phpcsFile->addError(
					'Assigning the return value of new by reference is no longer supported by PHP.',
					$stackPtr,
					'NewByReferenceFound'
				);
			} else {
				$this->phpcsFile->recordMetric( $stackPtr, 'Assigning new by reference', 'no' );
			}
		}

		/*
		 * Check for parenthesis & correct placement thereof.
		 */
		$next_non_empty_after_class_name = $this->phpcsFile->findNext(
			$this->classname_tokens,
			( $stackPtr + 1 ),
			null,
			true,
			null,
			true
		);

		if ( false === $next_non_empty_after_class_name ) {
			// Live coding.
			return;
		}

		// Walk back to the last part of the class name.
		$has_comment = false;
		for ( $classname_ptr = ( $next_non_empty_after_class_name - 1 ); $classname_ptr >= $stackPtr; $classname_ptr-- ) {
			if ( ! isset( Tokens::$emptyTokens[ $this->tokens[ $classname_ptr ]['code'] ] ) ) {
				// Prevent a false positive on variable variables, disregard them for now.
				if ( $stackPtr === $classname_ptr ) {
					return;
				}

				break;
			}

			if ( \T_WHITESPACE !== $this->tokens[ $classname_ptr ]['code'] ) {
				$has_comment = true;
			}
		}

		if ( \T_OPEN_PARENTHESIS !== $this->tokens[ $next_non_empty_after_class_name ]['code'] ) {
			$this->phpcsFile->recordMetric( $stackPtr, 'Object instantiation with parenthesis', 'no' );

			$fix = $this->phpcsFile->addFixableError(
				'Parenthesis should always be used when instantiating a new object.',
				$classname_ptr,
				'MissingParenthesis'
			);

			if ( true === $fix ) {
				$this->phpcsFile->fixer->addContent( $classname_ptr, '()' );
			}
		} else {
			$this->phpcsFile->recordMetric( $stackPtr, 'Object instantiation with parenthesis', 'yes' );

			if ( ( $next_non_empty_after_class_name - 1 ) !== $classname_ptr ) {
				$this->phpcsFile->recordMetric(
					$stackPtr,
					'Space between classname and parenthesis',
					( $next_non_empty_after_class_name - $classname_ptr )
				);

				$error      = 'There must be no spaces between the class name and the open parenthesis when instantiating a new object.';
				$error_code = 'SpaceBeforeParenthesis';

				if ( false === $has_comment ) {
					$fix = $this->phpcsFile->addFixableError( $error, $next_non_empty_after_class_name, $error_code );

					if ( true === $fix ) {
						$this->phpcsFile->fixer->beginChangeset();
						for ( $i = ( $next_non_empty_after_class_name - 1 ); $i > $classname_ptr; $i-- ) {
							$this->phpcsFile->fixer->replaceToken( $i, '' );
						}
						$this->phpcsFile->fixer->endChangeset();
					}
				} else {
					$this->phpcsFile->addError( $error, $next_non_empty_after_class_name, $error_code );
				}
			} else {
				$this->phpcsFile->recordMetric( $stackPtr, 'Space between classname and parenthesis', 0 );
			}
		}
	}

}
