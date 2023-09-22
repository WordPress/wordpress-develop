<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\DateTime;

use WordPressCS\WordPress\AbstractFunctionRestrictionsSniff;

/**
 * Forbids the use of various native DateTime related PHP/WP functions and suggests alternatives.
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since 2.2.0
 */
class RestrictedFunctionsSniff extends AbstractFunctionRestrictionsSniff {

	/**
	 * Groups of functions to restrict.
	 *
	 * @return array
	 */
	public function getGroups() {
		return array(

			/*
			 * Disallow the changing the timezone.
			 *
			 * @link https://vip.wordpress.com/documentation/vip-go/code-review-blockers-warnings-notices/#manipulating-the-timezone-server-side
			 */
			'timezone_change' => array(
				'type'      => 'error',
				'message'   => 'Using %s() and similar isn\'t allowed, instead use WP internal timezone support.',
				'functions' => array(
					'date_default_timezone_set',
				),
			),

			/*
			 * Use gmdate(), not date().
			 * Don't rely on the current PHP time zone as it might have been changed by third party code.
			 *
			 * @link https://make.wordpress.org/core/2019/09/23/date-time-improvements-wp-5-3/
			 * @link https://core.trac.wordpress.org/ticket/46438
			 * @link https://github.com/WordPress/WordPress-Coding-Standards/issues/1713
			 */
			'date' => array(
				'type'      => 'error',
				'message'   => '%s() is affected by runtime timezone changes which can cause date/time to be incorrectly displayed. Use gmdate() instead.',
				'functions' => array(
					'date',
				),
			),
		);
	}

}
