<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `wp_widgets_add_menu()` function.
 *
 * @group functions
 *
 * @covers ::wp_widgets_add_menu
 */
class WpWidgetsAddMenuTest extends WP_UnitTestCase {

	public $submenu;

	public function set_up() {
		parent::set_up();
		global $submenu;
		$this->submenu = $submenu;
		$submenu       = null;
	}

	public function tear_down() {
		global $submenu;
		$submenu = $this->submenu;
		parent::tear_down();
	}

	/**
	 * @ticket 60179
	 */
	public function test_wp_widgets_add_menu() {
		global $submenu;
		wp_widgets_add_menu();

		$expected['themes.php'][8] = array( __( 'Widgets' ), 'edit_theme_options', 'widgets.php' );
		$this->assertEqualSets( $expected, $submenu );
	}

	/**
	 * @ticket 60179
	 */
	public function test_wp_widgets_add_menu_no_widget_support() {
		global $submenu;

		add_filter( 'current_theme_supports-widgets', '__return_false' );

		wp_widgets_add_menu();

		remove_filter( 'current_theme_supports-widgets', '__return_false' );

		$this->assertNull( $submenu );
	}

	/**
	 * @ticket 60179
	 */
	public function test_wp_widgets_add_menu_block_theme() {
		global $submenu;

		switch_theme( 'block-theme' );

		wp_widgets_add_menu();

		$expected['themes.php'][] = array( __( 'Widgets' ), 'edit_theme_options', 'widgets.php' );
		$this->assertEqualSets( $expected, $submenu );
	}
}
