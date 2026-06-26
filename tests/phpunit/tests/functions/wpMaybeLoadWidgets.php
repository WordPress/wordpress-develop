<?php
/**
 * Tests for wp_maybe_load_widgets function.
 *
 * @group functions.php
 *
 * @covers ::wp_maybe_load_widgets
 */#
class Tests_Functions_WpMaybeLoadWidgets extends WP_UnitTestCase {

	/**
	 * @ticket 57201
	 */
	public function test_wp_maybe_load_widgets() {
		// If the class already exists, we can't test the initial "not loaded" state.
		if ( ! class_exists( 'WP_Nav_Menu_Widget' ) ) {
			$this->assertFalse( class_exists( 'WP_Nav_Menu_Widget' ), 'WP_Nav_Menu_Widget class should not be loaded initially.' );
			$this->assertFalse( has_action( '_admin_menu', 'wp_widgets_add_menu' ), 'wp_widgets_add_menu should not be hooked to _admin_menu initially.' );

			add_filter( 'load_default_widgets', '__return_false' );
			wp_maybe_load_widgets();
			remove_filter( 'load_default_widgets', '__return_false' );

			$this->assertFalse( class_exists( 'WP_Nav_Menu_Widget' ), 'WP_Nav_Menu_Widget class should not be loaded when load_default_widgets filter returns false.' );
			$this->assertFalse( has_action( '_admin_menu', 'wp_widgets_add_menu' ), 'wp_widgets_add_menu should not be hooked to _admin_menu when load_default_widgets filter returns false.' );
		}

		wp_maybe_load_widgets();

		$this->assertTrue( class_exists( 'WP_Nav_Menu_Widget' ), 'WP_Nav_Menu_Widget class should be loaded.' );
		$this->assertNotFalse( has_action( '_admin_menu', 'wp_widgets_add_menu' ), 'wp_widgets_add_menu should be hooked to _admin_menu.' );
	}
}
