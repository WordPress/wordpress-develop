<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\PHP;

use WordPressCS\WordPress\AbstractFunctionParameterSniff;

/**
 * Flag calling in_array(), array_search() and array_keys() without true as the third parameter.
 *
 * @link    https://vip.wordpress.com/documentation/vip-go/code-review-blockers-warnings-notices/#using-in_array-without-strict-parameter
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.9.0
 * @since   0.10.0 - This sniff not only checks for `in_array()`, but also `array_search()`
 *                   and `array_keys()`.
 *                 - The sniff no longer needlessly extends the `ArrayAssignmentRestrictionsSniff`
 *                   class which it didn't use.
 * @since   0.11.0 Refactored to extend the new WordPressCS native `AbstractFunctionParameterSniff` class.
 * @since   0.13.0 Class name changed: this class is now namespaced.
 */
class StrictInArraySniff extends AbstractFunctionParameterSniff {

	/**
	 * The group name for this group of functions.
	 *
	 * @since 0.11.0
	 *
	 * @var string
	 */
	protected $group_name = 'strict';

	/**
	 * List of array functions to which a $strict parameter can be passed.
	 *
	 * The $strict parameter is the third and last parameter for each of these functions.
	 *
	 * The array_keys() function only requires the $strict parameter when the optional
	 * second parameter $search has been set.
	 *
	 * @link http://php.net/in-array
	 * @link http://php.net/array-search
	 * @link http://php.net/array-keys
	 *
	 * @since 0.10.0
	 * @since 0.11.0 Renamed from $array_functions to $target_functions.
	 *
	 * @var array <string function_name> => <bool always needed ?>
	 */
	protected $target_functions = array(
		'in_array'     => true,
		'array_search' => true,
		'array_keys'   => false,
	);

	/**
	 * Process the parameters of a matched function.
	 *
	 * @since 0.11.0
	 *
	 * @param int    $stackPtr        The position of the current token in the stack.
	 * @param string $group_name      The name of the group which was matched.
	 * @param string $matched_content The token content (function name) which was matched.
	 * @param array  $parameters      Array with information about the parameters.
	 *
	 * @return void
	 */
	public function process_parameters( $stackPtr, $group_name, $matched_content, $parameters ) {
		// Check if the strict check is actually needed.
		if ( false === $this->target_functions[ $matched_content ] ) {
			if ( \count( $parameters ) === 1 ) {
				return;
			}
		}

		// We're only interested in the third parameter.
		if ( false === isset( $parameters[3] ) || 'true' !== strtolower( $parameters[3]['raw'] ) ) {
			$errorcode = 'MissingTrueStrict';

			/*
			 * Use a different error code when `false` is found to allow for excluding
			 * the warning as this will be a conscious choice made by the dev.
			 */
			if ( isset( $parameters[3] ) && 'false' === strtolower( $parameters[3]['raw'] ) ) {
				$errorcode = 'FoundNonStrictFalse';
			}

			$this->phpcsFile->addWarning(
				'Not using strict comparison for %s; supply true for third argument.',
				( isset( $parameters[3]['start'] ) ? $parameters[3]['start'] : $parameters[1]['start'] ),
				$errorcode,
				array( $matched_content )
			);
			return;
		}
	}

}
