<?php
/**
 * WPThemeReview Coding Standard.
 *
 * @package WPTRT\WPThemeReview
 * @link    https://github.com/WPTRT/WPThemeReview
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WPThemeReview\Sniffs\PluginTerritory;

use WordPressCS\WordPress\AbstractFunctionRestrictionsSniff;

/**
 * Discourages the use of session functions.
 *
 * @link  https://make.wordpress.org/themes/handbook/review/...... @todo
 *
 * @since WPCS 0.3.0
 * @since WPCS 0.11.0 Extends the WPCS native `AbstractFunctionRestrictionsSniff` class
 *                    instead of the upstream `Generic.PHP.ForbiddenFunctions` sniff.
 * @since WPCS 0.13.0 Class name changed: this class is now namespaced.
 *
 * @since TRTCS 0.1.0 As this sniff will be removed from WPCS in version 2.0, the
 *                    sniff has been cherry-picked into the WPThemeReview standard.
 */
class SessionFunctionsUsageSniff extends AbstractFunctionRestrictionsSniff {

	/**
	 * Groups of functions to restrict.
	 *
	 * Example: groups => [
	 *  'lambda' => [
	 *      'type'      => 'error' | 'warning',
	 *      'message'   => 'Use anonymous functions instead please!',
	 *      'functions' => [ 'file_get_contents', 'create_function' ],
	 *  ]
	 * ]
	 *
	 * @return array
	 */
	public function getGroups() {
		return [
			'session' => [
				'type'      => 'error',
				'message'   => 'The use of PHP session function %s() is prohibited.',
				'functions' => [
					'session_abort',
					'session_cache_expire',
					'session_cache_limiter',
					'session_commit',
					'session_create_id',
					'session_decode',
					'session_destroy',
					'session_encode',
					'session_gc',
					'session_get_cookie_params',
					'session_id',
					'session_is_registered',
					'session_module_name',
					'session_name',
					'session_regenerate_id',
					'session_register_shutdown',
					'session_register',
					'session_reset',
					'session_save_path',
					'session_set_cookie_params',
					'session_set_save_handler',
					'session_start',
					'session_status',
					'session_unregister',
					'session_unset',
					'session_write_close',
				],
			],
		];
	}

}
