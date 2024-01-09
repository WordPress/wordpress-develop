<?php

require_once __DIR__ . '/base.php';

/**
 * Test autoload for theme mods.
 *
 * @package WordPress
 * @subpackage Theme
 *
 * @group themes
 */
class Tests_Autoload_Theme_Mods extends WP_Theme_UnitTestCase {

	/**
	 * Tests that theme mods should not autoloaded after switch_theme.
	 *
	 * @ticket 39537
	 */
	public function test_that_on_switch_theme_previous_theme_mods_should_not_be_autoload() {
		global $wpdb;

		$current_theme_stylesheet = get_stylesheet();

		// Set a theme mod for the current theme.
		$new_theme_stylesheet = 'block-theme';
		set_theme_mod( 'foo-bar-option', 'a-value' );

		switch_theme( $new_theme_stylesheet );

		$this->assertSame( 'off', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", "theme_mods_$current_theme_stylesheet" ) ), 'Theme mods autoload value not set to no in database' );
		$this->assertSame( 'on', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", "theme_mods_$new_theme_stylesheet" ) ), 'Theme mods autoload value not set to yes in database' );

		switch_theme( $current_theme_stylesheet );

		$this->assertSame( 'on', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", "theme_mods_$current_theme_stylesheet" ) ), 'Theme mods autoload value not set to yes in database' );
		$this->assertSame( 'off', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", "theme_mods_$new_theme_stylesheet" ) ), 'Theme mods autoload value not set to no in database' );

		// Basic assertion to make sure that we haven't lost the mods.
		$this->assertSame( 'a-value', get_theme_mod( 'foo-bar-option' ) );
	}
}
