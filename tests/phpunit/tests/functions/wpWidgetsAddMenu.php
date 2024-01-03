<?php

/**
 * Tests for the wp_widgets_add_menu function.
 *
 * @group functions
 *
 * @covers ::wp_widgets_add_menu
 */
class Tests_Functions_wpWidgetsAddMenu extends WP_UnitTestCase {

	public $submenu;

	public function set_up() {
		global $submenu;
		$this->submenu = $submenu;
		$submenu       = null;
	}

	public function tear_down() {
		global $submenu;
		$submenu = $this->submenu;
	}

	/**
	 * @ticket 60179
	 */
	public function test_wp_widgets_add_menu() {
		global $submenu;
		wp_widgets_add_menu();

		$expected['themes.php'][7] = array( __( 'Widgets' ), 'edit_theme_options', 'widgets.php' );
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
	public function test_wp_widgets_add_menu_block_template_parts_supported() {
		global $submenu, $_wp_theme_features;

		$_wp_theme_features['block-template-parts'] = true;
		add_filter( 'current_theme_supports-block-template-parts', '__return_true' );

		wp_widgets_add_menu();

		unset( $_wp_theme_features['block-template-parts'] );
		remove_filter( 'current_theme_supports-block-template-parts', '__return_true' );

		$expected['themes.php'][] = array( __( 'Widgets' ), 'edit_theme_options', 'widgets.php' );
		$this->assertEqualSets( $expected, $submenu );
	}
}
