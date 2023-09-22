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
 * Perl compatible regular expressions (PCRE, preg_ functions) should be used in preference
 * to their POSIX counterparts.
 *
 * @link    https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/#regular-expressions
 * @link    http://php.net/manual/en/ref.regex.php
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.10.0 Previously this check was contained within the
 *                 `WordPress.VIP.RestrictedFunctions` and the
 *                 `WordPress.PHP.DiscouragedPHPFunctions` sniffs.
 * @since   0.13.0 Class name changed: this class is now namespaced.
 */
class POSIXFunctionsSniff extends AbstractFunctionRestrictionsSniff {

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
			'ereg' => array(
				'type'      => 'error',
				'message'   => '%s() has been deprecated since PHP 5.3 and removed in PHP 7.0, please use preg_match() instead.',
				'functions' => array(
					'ereg',
					'eregi',
					'sql_regcase',
				),
			),

			'ereg_replace' => array(
				'type'      => 'error',
				'message'   => '%s() has been deprecated since PHP 5.3 and removed in PHP 7.0, please use preg_replace() instead.',
				'functions' => array(
					'ereg_replace',
					'eregi_replace',
				),
			),

			'split' => array(
				'type'      => 'error',
				'message'   => '%s() has been deprecated since PHP 5.3 and removed in PHP 7.0, please use explode(), str_split() or preg_split() instead.',
				'functions' => array(
					'split',
					'spliti',
				),
			),

		);
	}

}
