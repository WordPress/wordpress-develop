<?php
/**
 * Tests for the admin menu.
 *
 * @package WordPress\Tests
 * @subpackage Admin
 * @covers ::_wp_sort_submenu_items
 */
class Tests_Admin_wpAdminMenu extends WP_UnitTestCase {
	/**
	 * Tests that submenu items added by menu callbacks are sorted after the existing items.
	 *
	 * @ticket 65996
	 */
	public function test_public_submenu_items_are_sorted_after_existing_items() {
		$this->load_admin_menu_sorting_function();

		$original_submenu                   = array(
			'options-general.php' => array(
				10 => array( 'General', 'manage_options', 'options-general.php' ),
				20 => array( 'Reading', 'manage_options', 'options-reading.php' ),
			),
		);
		$submenu                            = $original_submenu;
		$submenu['options-general.php'][30] = array( 'Zebra', 'manage_options', 'zebra' );
		$submenu['options-general.php'][40] = array( 'Apple', 'manage_options', 'apple' );

		_wp_sort_submenu_items( $submenu, $original_submenu );

		$this->assertSame(
			array(
				array( 'General', 'manage_options', 'options-general.php' ),
				array( 'Reading', 'manage_options', 'options-reading.php' ),
				array( '', '', 'wp-submenu-separator', '', 'wp-submenu-separator' ),
				array( 'Apple', 'manage_options', 'apple' ),
				array( 'Zebra', 'manage_options', 'zebra' ),
			),
			array_values( $submenu['options-general.php'] )
		);
	}

	/**
	 * Tests that a late-registered Core submenu item remains with the existing items.
	 *
	 * @ticket 65996
	 */
	public function test_late_core_submenu_items_remain_before_the_divider() {
		$this->load_admin_menu_sorting_function();

		$original_submenu          = array(
			'tools.php' => array(
				10 => array( 'Available Tools', 'edit_posts', 'tools.php' ),
			),
		);
		$submenu                   = $original_submenu;
		$submenu['tools.php'][99]  = array( 'Plugin Settings', 'manage_options', 'plugin-settings' );
		$submenu['tools.php'][101] = array( 'Theme File Editor', 'edit_themes', 'theme-editor.php' );

		_wp_sort_submenu_items( $submenu, $original_submenu );

		$this->assertSame( 'theme-editor.php', $submenu['tools.php'][1][2] );
		$this->assertSame( 'wp-submenu-separator', $submenu['tools.php'][2][2] );
		$this->assertSame( 'plugin-settings', $submenu['tools.php'][3][2] );
	}

	/**
	 * Loads the admin menu functions file so its private sorting helper is available.
	 */
	private function load_admin_menu_sorting_function() {
		require_once ABSPATH . 'wp-admin/includes/menu-functions.php';
	}
}
