<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\WP;

use WordPressCS\WordPress\AbstractFunctionParameterSniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Check for usage of deprecated parameter values in WP functions and provide alternative based on the parameter passed.
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   1.0.0
 *
 * @uses    \WordPressCS\WordPress\Sniff::$minimum_supported_version
 */
class DeprecatedParameterValuesSniff extends AbstractFunctionParameterSniff {

	/**
	 * The group name for this group of functions.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $group_name = 'wp_deprecated_parameter_values';

	/**
	 * Array of function, argument, and replacement function for deprecated argument.
	 *
	 * The list of deprecated parameter values can be found by
	 * looking for `_deprecated_argument()`.
	 * The list is sorted alphabetically by function name.
	 * Last updated for WordPress 4.9.6.
	 *
	 * @since 1.0.0
	 *
	 * @var array Multidimensional array with parameter details.
	 *     $target_functions = array(
	 *         (string) Function name. => array(
	 *             (int) Target parameter position, 1-based. => array(
	 *                 (string) Parameter value. => array(
	 *                     'alt'     => (string) Suggested alternative.
	 *                     'version' => (int) The WordPress version when deprecated.
	 *                 )
	 *             )
	 *         )
	 *     );
	 */
	protected $target_functions = array(
		'add_settings_field' => array(
			4 => array(
				'misc' => array(
					'alt'     => 'another settings group',
					'version' => '3.0.0',
				),
				'privacy' => array(
					'alt'     => 'another settings group',
					'version' => '3.5.0',
				),
			),
		),
		'add_settings_section' => array(
			4 => array(
				'misc' => array(
					'alt'     => 'another settings group',
					'version' => '3.0.0',
				),
				'privacy' => array(
					'alt'     => 'another settings group',
					'version' => '3.5.0',
				),
			),
		),
		'bloginfo' => array(
			1 => array(
				'home' => array(
					'alt'     => 'the "url" argument',
					'version' => '2.2.0',
				),
				'siteurl' => array(
					'alt'     => 'the "url" argument',
					'version' => '2.2.0',
				),
				'text_direction' => array(
					'alt'     => 'is_rtl()',
					'version' => '2.2.0',
				),
			),
		),
		'get_bloginfo' => array(
			1 => array(
				'home' => array(
					'alt'     => 'the "url" argument',
					'version' => '2.2.0',
				),
				'siteurl' => array(
					'alt'     => 'the "url" argument',
					'version' => '2.2.0',
				),
				'text_direction' => array(
					'alt'     => 'is_rtl()',
					'version' => '2.2.0',
				),
			),
		),
		'register_setting' => array(
			1 => array(
				'misc' => array(
					'alt'     => 'another settings group',
					'version' => '3.0.0',
				),
				'privacy' => array(
					'alt'     => 'another settings group',
					'version' => '3.5.0',
				),
			),
		),
		'unregister_setting' => array(
			1 => array(
				'misc' => array(
					'alt'     => 'another settings group',
					'version' => '3.0.0',
				),
				'privacy' => array(
					'alt'     => 'another settings group',
					'version' => '3.5.0',
				),
			),
		),
	);

	/**
	 * Process the parameters of a matched function.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $stackPtr        The position of the current token in the stack.
	 * @param string $group_name      The name of the group which was matched.
	 * @param string $matched_content The token content (function name) which was matched.
	 * @param array  $parameters      Array with information about the parameters.
	 *
	 * @return void
	 */
	public function process_parameters( $stackPtr, $group_name, $matched_content, $parameters ) {
		$this->get_wp_version_from_cl();
		$param_count = \count( $parameters );
		foreach ( $this->target_functions[ $matched_content ] as $position => $parameter_args ) {

			// Stop if the position is higher then the total number of parameters.
			if ( $position > $param_count ) {
				break;
			}

			$this->process_parameter( $matched_content, $parameters[ $position ], $parameter_args );
		}
	}

	/**
	 * Process the parameter of a matched function.
	 *
	 * @since 1.0.0
	 *
	 * @param string $matched_content The token content (function name) which was matched.
	 * @param array  $parameter       Array with start and end token positon of the parameter.
	 * @param array  $parameter_args  Array with alternative and WordPress deprecation version of the parameter.
	 *
	 * @return void
	 */
	protected function process_parameter( $matched_content, $parameter, $parameter_args ) {

		$parameter_position = $this->phpcsFile->findNext(
			Tokens::$emptyTokens,
			$parameter['start'],
			$parameter['end'] + 1,
			true
		);

		if ( false === $parameter_position ) {
			return;
		}

		$matched_parameter = $this->strip_quotes( $this->tokens[ $parameter_position ]['content'] );
		if ( ! isset( $parameter_args[ $matched_parameter ] ) ) {
			return;
		}

		$message = 'The parameter value "%s" has been deprecated since WordPress version %s.';
		$data    = array(
			$matched_parameter,
			$parameter_args[ $matched_parameter ]['version'],
		);

		if ( ! empty( $parameter_args[ $matched_parameter ]['alt'] ) ) {
			$message .= ' Use %s instead.';
			$data[]   = $parameter_args[ $matched_parameter ]['alt'];
		}

		$is_error = version_compare( $parameter_args[ $matched_parameter ]['version'], $this->minimum_supported_version, '<' );
		$this->addMessage(
			$message,
			$parameter_position,
			$is_error,
			$this->string_to_errorcode( 'Found' ),
			$data
		);
	}

}
