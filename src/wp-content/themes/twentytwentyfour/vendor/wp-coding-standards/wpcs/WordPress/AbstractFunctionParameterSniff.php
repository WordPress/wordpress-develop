<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress;

use WordPressCS\WordPress\AbstractFunctionRestrictionsSniff;

/**
 * Advises about parameters used in function calls.
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.11.0
 */
abstract class AbstractFunctionParameterSniff extends AbstractFunctionRestrictionsSniff {

	/**
	 * The group name for this group of functions.
	 *
	 * Intended to be overruled in the child class.
	 *
	 * @var string
	 */
	protected $group_name = 'restricted_parameters';

	/**
	 * Functions this sniff is looking for. Should be defined in the child class.
	 *
	 * @var array The only requirement for this array is that the top level
	 *            array keys are the names of the functions you're looking for.
	 *            Other than that, the array can have arbitrary content
	 *            depending on your needs.
	 */
	protected $target_functions = array();

	/**
	 * Groups of functions to restrict.
	 *
	 * @return array
	 */
	public function getGroups() {
		if ( empty( $this->target_functions ) ) {
			return array();
		}

		return array(
			$this->group_name => array(
				'functions' => array_keys( $this->target_functions ),
			),
		);
	}

	/**
	 * Process a matched token.
	 *
	 * @param int    $stackPtr        The position of the current token in the stack.
	 * @param string $group_name      The name of the group which was matched.
	 * @param string $matched_content The token content (function name) which was matched.
	 *
	 * @return int|void Integer stack pointer to skip forward or void to continue
	 *                  normal file processing.
	 */
	public function process_matched_token( $stackPtr, $group_name, $matched_content ) {

		$parameters = $this->get_function_call_parameters( $stackPtr );

		if ( empty( $parameters ) ) {
			return $this->process_no_parameters( $stackPtr, $group_name, $matched_content );
		} else {
			return $this->process_parameters( $stackPtr, $group_name, $matched_content, $parameters );
		}
	}

	/**
	 * Process the parameters of a matched function.
	 *
	 * This method has to be made concrete in child classes.
	 *
	 * @param int    $stackPtr        The position of the current token in the stack.
	 * @param string $group_name      The name of the group which was matched.
	 * @param string $matched_content The token content (function name) which was matched.
	 * @param array  $parameters      Array with information about the parameters.
	 *
	 * @return int|void Integer stack pointer to skip forward or void to continue
	 *                  normal file processing.
	 */
	abstract public function process_parameters( $stackPtr, $group_name, $matched_content, $parameters );

	/**
	 * Process the function if no parameters were found.
	 *
	 * Defaults to doing nothing. Can be overloaded in child classes to handle functions
	 * were parameters are expected, but none found.
	 *
	 * @param int    $stackPtr        The position of the current token in the stack.
	 * @param string $group_name      The name of the group which was matched.
	 * @param string $matched_content The token content (function name) which was matched.
	 *
	 * @return int|void Integer stack pointer to skip forward or void to continue
	 *                  normal file processing.
	 */
	public function process_no_parameters( $stackPtr, $group_name, $matched_content ) {
		return;
	}

}
