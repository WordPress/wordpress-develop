<?php
/**
 * Tests for wp_widgets_add_menu function.
 *
 * @group functions.php
 *
 * @covers ::wp_widgets_add_menu
 */
class Tests_Functions_WpWidgetsAddMenu extends WP_UnitTestCase {

	/**
	 * @ticket 57224
	 */
	public function test_wp_widgets_add_menu_if_no_widget_support_should_not_add_submenu() {
		global $submenu;
		$widget_support  = current_theme_supports( 'widgets' );
		$current_submenu = $submenu;
		// Have to use the private call and the public does not allow to remove the widgets support.
		_remove_theme_support( 'widgets' );

		wp_widgets_add_menu();
		$update_submenu = $submenu;
		if ( $widget_support ) {
			add_theme_support( 'widgets' );
		}
		// Reset the global variable.
		$submenu = $current_submenu;
		$this->assertSame( $current_submenu, $update_submenu, 'theme did not support widgets so menu item not added' );
	}

	/**
	 * @ticket 57224
	 */
	public function test_wp_widgets_add_menu_if__widget_support_should_add_submenu() {
		global $submenu;
		$current_submenu = $submenu;
		$widget_support  = current_theme_supports( 'widgets' );

		add_theme_support( 'widgets' );
		wp_widgets_add_menu();
		$update_submenu = $submenu;
		if ( ! $widget_support ) {
			remove_theme_support( 'widgets' );
		}
		// Reset the global variable.
		$submenu                   = $current_submenu;
		$expected['themes.php'][7] = array( __( 'Widgets' ), 'edit_theme_options', 'widgets.php' );
		$this->assertSame( $update_submenu, $expected, 'theme support widgets so menu item added' );
	}

	/**
	 * @ticket 57224
	 */
	public function test_wp_widgets_add_menu_if_block_template_parts_should_add_submenu() {
		global $submenu;
		$current_submenu = $submenu;
		$block_support   = current_theme_supports( 'block-template-parts' );

		add_theme_support( 'block-template-parts' );
		wp_widgets_add_menu();
		$update_submenu = $submenu;
		if ( ! $block_support ) {
			remove_theme_support( 'block-template-parts' );
		}
		// Reset the global variable.
		$submenu                  = $current_submenu;
		$expected['themes.php'][] = array( __( 'Widgets' ), 'edit_theme_options', 'widgets.php' );
		$this->assertSame( $update_submenu, $expected, 'theme support widgets so menu item added' );
	}
}
