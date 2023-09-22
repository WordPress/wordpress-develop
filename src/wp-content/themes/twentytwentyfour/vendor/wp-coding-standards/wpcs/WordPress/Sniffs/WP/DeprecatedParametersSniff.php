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

/**
 * Check for usage of deprecated parameters in WP functions and suggest alternative based on the parameter passed.
 *
 * This sniff will throw an error when usage of deprecated parameters is
 * detected if the parameter was deprecated before the minimum supported
 * WP version; a warning otherwise.
 * By default, it is set to presume that a project will support the current
 * WP version and up to three releases before.
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.12.0
 * @since   0.13.0 Class name changed: this class is now namespaced.
 * @since   0.14.0 Now has the ability to handle minimum supported WP version
 *                 being provided via the command-line or as as <config> value
 *                 in a custom ruleset.
 *
 * @uses    \WordPressCS\WordPress\Sniff::$minimum_supported_version
 */
class DeprecatedParametersSniff extends AbstractFunctionParameterSniff {

	/**
	 * The group name for this group of functions.
	 *
	 * @since 0.12.0
	 *
	 * @var string
	 */
	protected $group_name = 'wp_deprecated_parameters';

	/**
	 * Array of function, argument, and default value for deprecated argument.
	 *
	 * The functions are ordered alphabetically.
	 * Last updated for WordPress 4.8.0.
	 *
	 * @since 0.12.0
	 *
	 * @var array Multidimensional array with parameter details.
	 *    $target_functions = array(
	 *        (string) Function name. => array(
	 *            (int) Target parameter position, 1-based. => array(
	 *                'value'   => (mixed) Expected default value for the
	 *                              deprecated parameter. Currently the default
	 *                              values: true, false, null, empty arrays and
	 *                              both empty and non-empty strings can be
	 *                              handled correctly by the process_parameters()
	 *                              method. When an additional default value is
	 *                              added, the relevant code in the
	 *                              process_parameters() method will need to be
	 *                              adjusted.
	 *                'version' => (int) The WordPress version when deprecated.
	 *            )
	 *         )
	 *    );
	 */
	protected $target_functions = array(

		'add_option' => array(
			3 => array(
				'value'   => '',
				'version' => '2.3.0',
			),
		),
		'comments_link' => array(
			1 => array(
				'value'   => '',
				'version' => '0.72',
			),
			2 => array(
				'value'   => '',
				'version' => '1.3.0',
			),
		),
		'comments_number' => array(
			4 => array(
				'value'   => '',
				'version' => '1.3.0',
			),
		),
		'convert_chars' => array(
			2 => array(
				'value'   => '',
				'version' => '0.71',
			),
		),
		'discover_pingback_server_uri' => array(
			2 => array(
				'value'   => '',
				'version' => '2.7.0',
			),
		),
		'get_category_parents' => array(
			5 => array(
				'value'   => array(),
				'version' => '4.8.0',
			),
		),
		'get_delete_post_link' => array(
			2 => array(
				'value'   => '',
				'version' => '3.0.0',
			),
		),
		'get_last_updated' => array(
			1 => array(
				'value'   => '',
				'version' => '3.0.0', // Was previously part of MU.
			),
		),
		'get_the_author' => array(
			1 => array(
				'value'   => '',
				'version' => '2.1.0',
			),
		),
		'get_user_option' => array(
			3 => array(
				'value'   => '',
				'version' => '2.3.0',
			),
		),
		'get_wp_title_rss' => array(
			1 => array(
				'value'   => '&#8211;',
				'version' => '4.4.0',
			),
		),
		'is_email' => array(
			2 => array(
				'value'   => false,
				'version' => '3.0.0',
			),
		),
		'load_plugin_textdomain' => array(
			2 => array(
				'value'   => false,
				'version' => '2.7.0',
			),
		),
		'safecss_filter_attr' => array(
			2 => array(
				'value'   => '',
				'version' => '2.8.1',
			),
		),
		'the_attachment_link' => array(
			3 => array(
				'value'   => false,
				'version' => '2.5.0',
			),
		),
		'the_author' => array(
			1 => array(
				'value'   => '',
				'version' => '2.1.0',
			),
			2 => array(
				'value'   => true,
				'version' => '1.5.0',
			),
		),
		'the_author_posts_link' => array(
			1 => array(
				'value'   => '',
				'version' => '2.1.0',
			),
		),
		'trackback_rdf' => array(
			1 => array(
				'value'   => '',
				'version' => '2.5.0',
			),
		),
		'trackback_url' => array(
			1 => array(
				'value'   => true,
				'version' => '2.5.0',
			),
		),
		'update_blog_option' => array(
			4 => array(
				'value'   => null,
				'version' => '3.1.0',
			),
		),
		'update_blog_status' => array(
			4 => array(
				'value'   => null,
				'version' => '3.1.0',
			),
		),
		'update_user_status' => array(
			4 => array(
				'value'   => null,
				'version' => '3.0.2',
			),
		),
		'unregister_setting' => array(
			4 => array(
				'value'   => '',
				'version' => '4.7.0',
			),
		),
		'wp_get_http_headers' => array(
			2 => array(
				'value'   => false,
				'version' => '2.7.0',
			),
		),
		'wp_get_sidebars_widgets' => array(
			1 => array(
				'value'   => true,
				'version' => '2.8.1',
			),
		),
		'wp_install' => array(
			5 => array(
				'value'   => '',
				'version' => '2.6.0',
			),
		),
		'wp_new_user_notification' => array(
			2 => array(
				'value'   => null,
				'version' => '4.3.1',
			),
		),
		'wp_notify_postauthor' => array(
			2 => array(
				'value'   => null,
				'version' => '3.8.0',
			),
		),
		'wp_title_rss' => array(
			1 => array(
				'value'   => '&#8211;',
				'version' => '4.4.0',
			),
		),
		'wp_upload_bits' => array(
			2 => array(
				'value'   => null,
				'version' => '2.0.0',
			),
		),
		'xfn_check' => array(
			3 => array(
				'value'   => '',
				'version' => '2.5.0',
			),
		),
	);

	/**
	 * Process the parameters of a matched function.
	 *
	 * @since 0.12.0
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

		$paramCount = \count( $parameters );
		foreach ( $this->target_functions[ $matched_content ] as $position => $parameter_args ) {

			// Check that number of parameters defined is not less than the position to check.
			if ( $position > $paramCount ) {
				break;
			}

			// The list will need to updated if the default value is not supported.
			switch ( $parameters[ $position ]['raw'] ) {
				case 'true':
					$matched_parameter = true;
					break;
				case 'false':
					$matched_parameter = false;
					break;
				case 'null':
					$matched_parameter = null;
					break;
				case 'array()':
				case '[]':
					$matched_parameter = array();
					break;
				default:
					$matched_parameter = $this->strip_quotes( $parameters[ $position ]['raw'] );
					break;
			}

			if ( $parameter_args['value'] === $matched_parameter ) {
				continue;
			}

			$message  = 'The parameter "%s" at position #%s of %s() has been deprecated since WordPress version %s.';
			$is_error = version_compare( $parameter_args['version'], $this->minimum_supported_version, '<' );
			$code     = $this->string_to_errorcode( ucfirst( $matched_content ) . 'Param' . $position . 'Found' );

			$data = array(
				$parameters[ $position ]['raw'],
				$position,
				$matched_content,
				$parameter_args['version'],
			);

			if ( isset( $parameter_args['value'] ) && $position < $paramCount ) {
				$message .= ' Use "%s" instead.';
				$data[]   = (string) $parameter_args['value'];
			} else {
				$message .= ' Instead do not pass the parameter.';
			}

			$this->addMessage( $message, $stackPtr, $is_error, $code, $data, 0 );
		}
	}

}
