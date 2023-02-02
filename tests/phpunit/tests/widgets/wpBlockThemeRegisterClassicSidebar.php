<?php
/**
 * Tests for _wp_block_theme_register_classic_sidebars().
 *
 * @group widgets
 * @covers ::_wp_block_theme_register_classic_sidebars
 */
class Tests_Widgets_WpBlockThemeRegisterClassicSidebars extends WP_UnitTestCase {
	/**
	 * Original global $wp_registered_sidebars.
	 *
	 * @var array
	 */
	private static $wp_registered_sidebars;

	public static function set_up_before_class() {
		global $wp_registered_sidebars;
		parent::set_up_before_class();

		// Store the original global before running tests.
		static::$wp_registered_sidebars = $wp_registered_sidebars;
	}

	public function tear_down() {
		// Restore the global after each test.
		global $wp_registered_sidebars;
		$wp_registered_sidebars = static::$wp_registered_sidebars;

		parent::tear_down();
	}

	/**
	 * @ticket 57531
	 */
	public function test_should_reregister_previous_theme_sidebar() {
		global $wp_registered_sidebars;

		switch_theme( 'block-theme' );
		unregister_sidebar( 'sidebar-1' );

		$this->assertArrayNotHasKey( 'sidebar-1', $wp_registered_sidebars, 'Sidebar 1 should not be in registered sidebars after unregister' );
		_wp_block_theme_register_classic_sidebars();

		$this->assertArrayHasKey( 'sidebar-1', $wp_registered_sidebars, 'Sidebar 1 should be in registered sidebars after invoking _wp_block_theme_register_classic_sidebars()' );
	}

	/**
	 * @ticket 57531
	 */
	public function test_should_bail_out_when_theme_mod_is_empty() {
		global $wp_registered_sidebars;

		$this->assertFalse( get_theme_mod( 'wp_classic_sidebars' ) );

		$before = $wp_registered_sidebars;
		_wp_block_theme_register_classic_sidebars();

		$this->assertSameSetsWithIndex( $before, $wp_registered_sidebars, 'No change should happen after invoking _wp_block_theme_register_classic_sidebars()' );
	}
}
