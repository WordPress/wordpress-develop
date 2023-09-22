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
 * Forbids the use of add_..._page() functions within Themes with the exception of `add_theme_page()`.
 *
 * @link  https://make.wordpress.org/themes/handbook/review/required/theme-check-plugin/#admin-menu
 *
 * @since 0.1.0
 */
class NoAddAdminPagesSniff extends AbstractFunctionRestrictionsSniff {

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
			'add_menu_pages' => [
				'type'      => 'error',
				'message'   => 'Themes should use add_theme_page() for adding admin pages. Found %s.',
				'functions' => [
					// Menu Pages.
					'add_menu_page',
					'add_object_page',
					'add_utility_page',

					// SubMenu Pages.
					'add_submenu_page',

					// WordPress Administration Menus.
					'add_dashboard_page',
					'add_posts_page',
					'add_links_page',
					'add_media_page',
					'add_pages_page',
					'add_comments_page',
					'add_plugins_page',
					'add_users_page',
					'add_management_page',
					'add_options_page',
				],
			],
		];
	}

}
