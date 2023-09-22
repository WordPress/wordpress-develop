<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\CodeAnalysis;

use WordPressCS\WordPress\AbstractFunctionParameterSniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flag calls to escaping functions which look like they may have been intended
 * as calls to the "translate + escape" sister-function due to the presence of
 * more than one parameter.
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   2.2.0
 */
class EscapedNotTranslatedSniff extends AbstractFunctionParameterSniff {

	/**
	 * The group name for this group of functions.
	 *
	 * @since 2.2.0
	 *
	 * @var string
	 */
	protected $group_name = 'escapednottranslated';

	/**
	 * List of functions to examine.
	 *
	 * @link https://developer.wordpress.org/reference/functions/esc_html/
	 * @link https://developer.wordpress.org/reference/functions/esc_html__/
	 * @link https://developer.wordpress.org/reference/functions/esc_attr/
	 * @link https://developer.wordpress.org/reference/functions/esc_attr__/
	 *
	 * @since 2.2.0
	 *
	 * @var array <string function_name> => <string alternative function>
	 */
	protected $target_functions = array(
		'esc_html' => 'esc_html__',
		'esc_attr' => 'esc_attr__',
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
		if ( \count( $parameters ) === 1 ) {
			return;
		}

		/*
		 * We already know that there will be a valid open+close parenthesis, otherwise the sniff
		 * would have bowed out long before.
		 */
		$opener = $this->phpcsFile->findNext( Tokens::$emptyTokens, ( $stackPtr + 1 ), null, true );
		$closer = $this->tokens[ $opener ]['parenthesis_closer'];

		$data = array(
			$matched_content,
			$this->target_functions[ $matched_content ],
			$this->phpcsFile->getTokensAsString( $stackPtr, ( $closer - $stackPtr + 1 ) ),
		);

		$this->phpcsFile->addWarning(
			'%s() expects only one parameter. Did you mean to use %s() ? Found: %s',
			$stackPtr,
			'Found',
			$data
		);
	}

}
