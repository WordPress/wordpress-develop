<?php

/**
 * Tests for the wp_maybe_load_widgets() function.
 *
 * @group functions
 *
 * @covers ::wp_maybe_load_widgets
 */
class Tests_Functions_WpMaybeLoadWidgets extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		remove_action( '_admin_menu', 'wp_widgets_add_menu' );
	}

	public function tear_down() {
		parent::tear_down();
	}

	/**
	 * Tests that wp_maybe_load_widgets() registers wp_widgets_add_menu to _admin_menu.
	 *
	 * @ticket 60180
	 */
	public function test_wp_maybe_load_widgets(): void {
		$this->assertFalse( has_action( '_admin_menu', 'wp_widgets_add_menu' ) );

		wp_maybe_load_widgets();

		$this->assertSame( 10, has_action( '_admin_menu', 'wp_widgets_add_menu' ) );
	}

	/**
	 * Tests that wp_maybe_load_widgets() short-circuits when load_default_widgets filter returns false.
	 *
	 * @ticket 60180
	 */
	public function test_wp_maybe_load_widgets_no_default_widgets(): void {
		$this->assertFalse( has_action( '_admin_menu', 'wp_widgets_add_menu' ) );

		add_filter( 'load_default_widgets', '__return_false' );

		wp_maybe_load_widgets();

		$this->assertFalse( has_action( '_admin_menu', 'wp_widgets_add_menu' ) );
	}
}
