<?php

/**
 * Tests for the get_theme_mods() function.
 *
 * @group themes
 * @group theme-mods
 *
 * @covers ::get_theme_mods
 */
class Tests_Theme_GetThemeMods extends WP_UnitTestCase {

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
	 * Tests that the modifications of the current theme are returned.
	 *
	 * @ticket 66503
	 */
	public function test_should_return_the_modifications_of_the_current_theme() {
		$mods = array(
			'background_color' => 'ffffff',
			'custom_logo'      => 5,
		);

		update_option( 'theme_mods_' . get_option( 'stylesheet' ), $mods );

		$this->assertSame( $mods, get_theme_mods() );
	}

	/**
	 * Tests that the modifications of another theme are not returned.
	 *
	 * @ticket 66503
	 */
	public function test_should_not_return_the_modifications_of_another_theme() {
		update_option( 'theme_mods_another-theme', array( 'background_color' => '000000' ) );

		$this->assertSame( array(), get_theme_mods() );
	}

	/**
	 * Tests that the modifications of the theme of the stylesheet option are returned when the theme changes.
	 *
	 * @ticket 66503
	 */
	public function test_should_follow_the_stylesheet_option() {
		update_option( 'theme_mods_first-theme', array( 'name' => 'first' ) );
		update_option( 'theme_mods_second-theme', array( 'name' => 'second' ) );

		update_option( 'stylesheet', 'first-theme' );
		$this->assertSame( array( 'name' => 'first' ), get_theme_mods(), 'The modifications of the first theme should be returned.' );

		update_option( 'stylesheet', 'second-theme' );
		$this->assertSame( array( 'name' => 'second' ), get_theme_mods(), 'The modifications of the second theme should be returned.' );
	}

	/**
	 * Tests that an empty array is returned when the modifications are not saved.
	 *
	 * @ticket 66503
	 */
	public function test_should_return_an_empty_array_when_the_modifications_are_not_saved() {
		$this->assertSame( array(), get_theme_mods() );
	}

	/**
	 * Tests that an empty array is returned when the saved modifications are not an array.
	 *
	 * @ticket 66503
	 *
	 * @dataProvider data_values_that_are_not_arrays
	 *
	 * @param mixed $value Value of the option.
	 */
	public function test_should_return_an_empty_array_when_the_saved_value_is_not_an_array( $value ) {
		update_option( 'theme_mods_' . get_option( 'stylesheet' ), $value );

		$this->assertSame( array(), get_theme_mods() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_values_that_are_not_arrays() {
		return array(
			'string'       => array( 'not an array' ),
			'integer'      => array( 5 ),
			'empty string' => array( '' ),
			'true'         => array( true ),
		);
	}

	/**
	 * Tests that the modifications of the deprecated option are returned.
	 *
	 * @ticket 66503
	 */
	public function test_should_return_the_modifications_of_the_deprecated_option() {
		update_option( 'current_theme', 'Old Theme' );
		update_option( 'mods_Old Theme', array( 'background_color' => 'abcdef' ) );

		$this->assertSame( array( 'background_color' => 'abcdef' ), get_theme_mods() );
	}

	/**
	 * Tests that the deprecated option is not migrated outside the admin.
	 *
	 * @ticket 66503
	 */
	public function test_should_not_migrate_the_deprecated_option_outside_the_admin() {
		update_option( 'current_theme', 'Old Theme' );
		update_option( 'mods_Old Theme', array( 'background_color' => 'abcdef' ) );

		get_theme_mods();

		$this->assertSame( array( 'background_color' => 'abcdef' ), get_option( 'mods_Old Theme' ), 'The deprecated option should be kept.' );
		$this->assertFalse( get_option( 'theme_mods_' . get_option( 'stylesheet' ) ), 'The current option should not be created.' );
	}

	/**
	 * Tests that the deprecated option is migrated to the option of the current theme in the admin.
	 *
	 * @ticket 66503
	 */
	public function test_should_migrate_the_deprecated_option_in_the_admin() {
		set_current_screen( 'dashboard' );

		update_option( 'current_theme', 'Old Theme' );
		update_option( 'mods_Old Theme', array( 'background_color' => 'abcdef' ) );

		$this->assertSame( array( 'background_color' => 'abcdef' ), get_theme_mods(), 'The modifications should be returned.' );
		$this->assertSame( array( 'background_color' => 'abcdef' ), get_option( 'theme_mods_' . get_option( 'stylesheet' ) ), 'The modifications should be saved for the current theme.' );
		$this->assertFalse( get_option( 'mods_Old Theme' ), 'The deprecated option should be deleted.' );
	}

	/**
	 * Tests that the deprecated option is not used when the modifications of the theme are saved.
	 *
	 * @ticket 66503
	 */
	public function test_should_prefer_the_modifications_of_the_current_option() {
		set_current_screen( 'dashboard' );

		update_option( 'current_theme', 'Old Theme' );
		update_option( 'mods_Old Theme', array( 'background_color' => 'abcdef' ) );
		update_option( 'theme_mods_' . get_option( 'stylesheet' ), array( 'background_color' => '123456' ) );

		$this->assertSame( array( 'background_color' => '123456' ), get_theme_mods(), 'The modifications of the current option should be returned.' );
		$this->assertSame( array( 'background_color' => 'abcdef' ), get_option( 'mods_Old Theme' ), 'The deprecated option should be kept.' );
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

		$this->assertSame( array( 'background_color' => 'fedcba' ), get_theme_mods() );
	}
}
