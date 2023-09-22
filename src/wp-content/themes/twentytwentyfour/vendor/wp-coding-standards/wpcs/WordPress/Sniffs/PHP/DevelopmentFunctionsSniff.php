<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\PHP;

use WordPressCS\WordPress\AbstractFunctionRestrictionsSniff;

/**
 * Restrict the use of various development functions.
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.11.0
 * @since   0.13.0 Class name changed: this class is now namespaced.
 */
class DevelopmentFunctionsSniff extends AbstractFunctionRestrictionsSniff {

	/**
	 * Groups of functions to restrict.
	 *
	 * Example: groups => array(
	 *  'lambda' => array(
	 *      'type'      => 'error' | 'warning',
	 *      'message'   => 'Use anonymous functions instead please!',
	 *      'functions' => array( 'file_get_contents', 'create_function' ),
	 *  )
	 * )
	 *
	 * @return array
	 */
	public function getGroups() {
		return array(
			'error_log' => array(
				'type'      => 'warning',
				'message'   => '%s() found. Debug code should not normally be used in production.',
				'functions' => array(
					'error_log',
					'var_dump',
					'var_export',
					'print_r',
					'trigger_error',
					'set_error_handler',
					'debug_backtrace',
					'debug_print_backtrace',
					'wp_debug_backtrace_summary',
				),
			),

			'prevent_path_disclosure' => array(
				'type'      => 'warning',
				'message'   => '%s() can lead to full path disclosure.',
				'functions' => array(
					'error_reporting',
					'phpinfo',
				),
			),
		);
	}

}
