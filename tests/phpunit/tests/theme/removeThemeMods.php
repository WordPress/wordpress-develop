<?php

/**
 * Tests for the remove_theme_mods() function.
 *
 * @group themes
 * @group theme-mods
 *
 * @covers ::remove_theme_mods
 */
class Tests_Theme_RemoveThemeMods extends WP_UnitTestCase {

	/**
	 * Theme features, to restore after each test.
	 *
	 * @var array
	 */
	private $original_theme_features;

	public function set_up() {
		parent::set_up();

		global $_wp_theme_features;
		$this->original_theme_features = $_wp_theme_features;
	}

	public function tear_down() {
		global $_wp_theme_features;
		$_wp_theme_features = $this->original_theme_features;

		set_current_screen( 'front' );

		parent::tear_down();
	}

	/**
	 * Tests that the modifications of the current theme are removed.
	 *
	 * @ticket 66503
	 */
	public function test_should_remove_the_modifications_of_the_current_theme() {
		$option = 'theme_mods_' . get_option( 'stylesheet' );

		update_option( $option, array( 'background_color' => 'ffffff' ) );

		remove_theme_mods();

		$this->assertFalse( get_option( $option ), 'The option should be deleted.' );
		$this->assertSame( array(), get_theme_mods(), 'There should be no modifications.' );
		$this->assertFalse( get_theme_mod( 'background_color' ), 'The modification should not be found.' );
	}

	/**
	 * Tests that the deprecated option of the theme is removed too.
	 *
	 * @ticket 66503
	 */
	public function test_should_remove_the_deprecated_option() {
		update_option( 'current_theme', 'Old Theme' );
		update_option( 'mods_Old Theme', array( 'background_color' => 'abcdef' ) );

		remove_theme_mods();

		$this->assertFalse( get_option( 'mods_Old Theme' ) );
	}

	/**
	 * Tests that the name of the theme is used for the deprecated option when the current theme option is missing.
	 *
	 * @ticket 66503
	 */
	public function test_should_use_the_name_of_the_theme_for_the_deprecated_option_without_the_current_theme_option() {
		delete_option( 'current_theme' );

		$name = wp_get_theme()->get( 'Name' );
		update_option( "mods_$name", array( 'background_color' => 'fedcba' ) );

		remove_theme_mods();

		$this->assertFalse( get_option( "mods_$name" ) );
	}

	/**
	 * Tests that the modifications of other themes and the other options are not removed.
	 *
	 * @ticket 66503
	 */
	public function test_should_not_remove_the_modifications_of_other_themes() {
		update_option( 'theme_mods_' . get_option( 'stylesheet' ), array( 'background_color' => 'ffffff' ) );
		update_option( 'theme_mods_another-theme', array( 'background_color' => '000000' ) );
		update_option( 'mods_Another Theme', array( 'background_color' => '111111' ) );
		update_option( 'current_theme', 'Old Theme' );

		remove_theme_mods();

		$this->assertSame( array( 'background_color' => '000000' ), get_option( 'theme_mods_another-theme' ), 'The modifications of another theme should be kept.' );
		$this->assertSame( array( 'background_color' => '111111' ), get_option( 'mods_Another Theme' ), 'The deprecated modifications of another theme should be kept.' );
		$this->assertSame( 'Old Theme', get_option( 'current_theme' ), 'The name of the current theme should be kept.' );
	}

	/**
	 * Tests that the function does not fail when there are no modifications.
	 *
	 * @ticket 66503
	 */
	public function test_should_not_fail_when_there_are_no_modifications() {
		remove_theme_mods();
		remove_theme_mods();

		$this->assertSame( array(), get_theme_mods() );
	}

	/**
	 * Tests that removing the last modification removes all the modifications.
	 *
	 * @ticket 66503
	 */
	public function test_should_be_used_when_the_last_modification_is_removed() {
		set_theme_mod( 'background_color', 'ffffff' );

		remove_theme_mod( 'background_color' );

		$this->assertFalse( get_option( 'theme_mods_' . get_option( 'stylesheet' ) ) );
	}
}
