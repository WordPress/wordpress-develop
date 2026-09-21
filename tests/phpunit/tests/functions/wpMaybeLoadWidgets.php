<?php

/**
 * Tests for the wp_maybe_load_widgets function.
 *
 * @group Functions
 *
 * @covers ::wp_maybe_load_widgets
 */
class Tests_Functions_wpMaybeLoadWidgets extends WP_UnitTestCase {

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
	 * @ticket 60180
	 */
	public function test_wp_maybe_load_widgets() {

		wp_maybe_load_widgets();

		$this->assertSame( 10, has_action( '_admin_menu', 'wp_widgets_add_menu' ) );
	}

	public function test_wp_maybe_load_widgets_no_default_widgets() {
		global $submenu;

		add_filter( 'load_default_widgets', '__return_false' );

		wp_maybe_load_widgets();

		remove_filter( 'load_default_widgets', '__return_false' );

		$this->assertSame( 10, has_action( '_admin_menu', 'wp_widgets_add_menu' ) );
	}

}
