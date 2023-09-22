<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\DB;

use WordPressCS\WordPress\AbstractFunctionRestrictionsSniff;

/**
 * Verifies that no database related PHP functions are used.
 *
 * "Avoid touching the database directly. If there is a defined function that can get
 *  the data you need, use it. Database abstraction (using functions instead of queries)
 *  helps keep your code forward-compatible and, in cases where results are cached in memory,
 *  it can be many times faster."
 *
 * @link    https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/#database-queries
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.10.0
 * @since   0.13.0 Class name changed: this class is now namespaced.
 */
class RestrictedFunctionsSniff extends AbstractFunctionRestrictionsSniff {

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

			'mysql' => array(
				'type'      => 'error',
				'message'   => 'Accessing the database directly should be avoided. Please use the $wpdb object and associated functions instead. Found: %s.',
				'functions' => array(
					'mysql_*',
					'mysqli_*',
					'mysqlnd_ms_*',
					'mysqlnd_qc_*',
					'mysqlnd_uh_*',
					'mysqlnd_memcache_*',
					'maxdb_*',
				),
				'whitelist' => array(
					'mysql_to_rfc3339' => true,
				),
			),
		);
	}

}
