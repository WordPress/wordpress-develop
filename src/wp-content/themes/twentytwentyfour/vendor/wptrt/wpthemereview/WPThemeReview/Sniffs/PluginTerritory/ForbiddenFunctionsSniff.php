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
 * Restricts the use of various functions that are plugin territory.
 *
 * @link  https://make.wordpress.org/themes/handbook/review/required/#presentation-vs-functionality
 *
 * @since 0.1.0
 * @since 0.2.0 Added the `editor-blocks` group.
 *              Added the `cron-functionality` group.
 */
class ForbiddenFunctionsSniff extends AbstractFunctionRestrictionsSniff {

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
			'plugin-territory' => [
				'type'      => 'error',
				'message'   => 'Function %s() is not allowed because it is plugin territory.',
				'functions' => [
					'register_post_type',
					'register_taxonomy',
					'add_shortcode',
					'register_taxonomy_for_object_type',
					'flush_rewrite_rules',
				],
			],

			'editor-blocks' => [
				'type'      => 'error',
				'message'   => 'Registering and deregistering editor blocks should be done in a plugin, not in a theme. Found %s().',
				'functions' => [
					'register_block_*',
					'unregister_block_*',
				],
			],

			'cron-functionality' => [
				'type'      => 'error',
				'message'   => 'Themes should not be running regular (Cron) tasks. Found %s().',
				'functions' => [
					'wp_clear_scheduled_hook',
					'wp_cron',
					'wp_reschedule_event',
					'wp_schedule_*',
					'wp_unschedule_*',
				],
			],
		];
	}

}
