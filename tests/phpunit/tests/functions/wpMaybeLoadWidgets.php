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
		$this->assertFalse( class_exists( 'WP_Nav_Menu_Widget' ) );
		$this->assertFalse( has_action( '_admin_menu', 'wp_widgets_add_menu' ) );

		add_filter( 'load_default_widgets', '__return_false' );
		wp_maybe_load_widgets();
		remove_filter( 'load_default_widgets', '__return_false' );

		$this->assertFalse( class_exists( 'WP_Nav_Menu_Widget' ) );
		$this->assertFalse( has_action( '_admin_menu', 'wp_widgets_add_menu' ) );

		wp_maybe_load_widgets();

		$this->assertTrue( class_exists( 'WP_Nav_Menu_Widget' ) );
		$this->assertTrue( has_action( '_admin_menu', 'wp_widgets_add_menu' ) );

	}
}
