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

	public function test_a_sidebar_should_be_registered() {
		global $wp_registered_sidebars;

		$sidebar_id = array_key_first( $wp_registered_sidebars );
		$this->assertNotEmpty( $sidebar_id );
	}

	/**
	 * @ticket 57531
	 */
	public function test_should_reregister_previous_theme_sidebar() {
		global $wp_registered_sidebars;

		$sidebar_id = array_key_first( $wp_registered_sidebars );

		switch_theme( 'block-theme' );
		unregister_sidebar( $sidebar_id );

		// Test before.
		$this->assertArrayNotHasKey(
			$sidebar_id,
			$wp_registered_sidebars,
			'Sidebar should not be in registered sidebars after unregister'
		);

		_wp_block_theme_register_classic_sidebars();

		// Test after.
		$this->assertArrayHasKey(
			$sidebar_id,
			$wp_registered_sidebars,
			'Sidebar should be in registered sidebars after invoking _wp_block_theme_register_classic_sidebars()'
		);
	}

	/**
	 * @ticket 57531
	 */
	public function test_should_bail_out_when_theme_mod_is_empty() {
		global $wp_registered_sidebars;

		// Test state before invoking.
		$this->assertFalse(
			get_theme_mod( 'wp_classic_sidebars' ),
			'Theme mod should not be set before invoking _wp_block_theme_register_classic_sidebars()'
		);

		$before = $wp_registered_sidebars;
		_wp_block_theme_register_classic_sidebars();

		// Test state after invoking.
		$this->assertSameSetsWithIndex(
			$before,
			$wp_registered_sidebars,
			'No change should happen after invoking _wp_block_theme_register_classic_sidebars()'
		);
	}
}
