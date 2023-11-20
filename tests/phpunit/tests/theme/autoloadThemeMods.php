<?php

/**
 * Tests for checking autoload handling when switching themes.
 *
 * @covers ::switch_theme
 */
class Tests_Autoload_Theme_Mods extends WP_UnitTestCase {

	/**
	 * Test switching themes and verifying autoload values.
	 */
	public function test_switch_theme_autoload() {
		// Set up the old theme.
		$old_theme_stylesheet = 'old-theme';
		$old_theme_mods = array(
			'option1' => 'value1',
			'option2' => 'value2',
		);
		update_option( "theme_mods_$old_theme_stylesheet", $old_theme_mods );

		// Set up the new theme.
		$new_theme_stylesheet = 'new-theme';
		$new_theme_mods = array(
			'option3' => 'value3',
			'option4' => 'value4',
		);
		update_option( "theme_mods_$new_theme_stylesheet", $new_theme_mods );

		// Switch themes.
		switch_theme( $new_theme_stylesheet );

		// Verify autoload values.
		$autoload_old_theme = get_option( "theme_mods_$old_theme_stylesheet_autoload" );
		$autoload_new_theme = get_option( "theme_mods_$new_theme_stylesheet_autoload" );

		// Expect autoload value for the old theme to be 'no'.
		$this->assertEquals( 'no', $autoload_old_theme );

		// Expect autoload value for the new theme to be 'yes'.
		$this->assertEquals( 'yes', $autoload_new_theme );

		// Clean up.
		delete_option( "theme_mods_$old_theme_stylesheet" );
		delete_option( "theme_mods_$new_theme_stylesheet" );
		delete_option( 'theme_switched' );
		delete_option( 'current_theme' );
	}

	/**
	 * Test switching themes and verifying that options are loaded properly.
	 */
	public function test_switch_theme_options_load() {
		// Set up the new theme.
		$new_theme_stylesheet = 'new-theme';
		$new_theme_mods = array(
			'option5' => 'value5',
			'option6' => 'value6',
		);
		update_option( "theme_mods_$new_theme_stylesheet", $new_theme_mods );

		// Switch themes.
		switch_theme( $new_theme_stylesheet );

		// Verify that options are loaded properly for the new theme.
		$option5_value = get_theme_mod( 'option5' );
		$option6_value = get_theme_mod( 'option6' );

		// Expect the values to match the ones set during setup.
		$this->assertEquals( 'value5', $option5_value );
		$this->assertEquals( 'value6', $option6_value );

		// Clean up.
		delete_option( "theme_mods_$new_theme_stylesheet" );
		delete_option( 'theme_switched' );
		delete_option( 'current_theme' );
	}
}
