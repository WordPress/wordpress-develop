<?php

/**
 * Tests for checking autoload handling when switching themes.
 *
 * @covers ::switch_theme
 */
class WP_Test_Autoload_Theme_Mods extends WP_UnitTestCase {

    public function test_on_switch_theme_previous_mods_should_not_be_autoload() {
        global $wpdb;

        $current_theme_stylesheet = get_stylesheet();

        // Set a theme mod for the current theme
        $new_theme_stylesheet = 'theme1';
        set_theme_mod( 'default-theme-option', 'a-value' );

        switch_theme( $new_theme_stylesheet );

        $this->assertEquals( 'no', $wpdb->get_var( "SELECT autoload FROM $wpdb->options WHERE option_name = 'theme_mods_$current_theme_stylesheet'" ) );
        $this->assertEquals( 'yes', $wpdb->get_var( "SELECT autoload FROM $wpdb->options WHERE option_name = 'theme_mods_$new_theme_stylesheet'" ) );

        // Make sure that autoloaded options are cached properly
        $autoloaded_options = wp_cache_get( 'alloptions', 'options' );
        $this->assertTrue( array_key_exists( "theme_mods_$new_theme_stylesheet", $autoloaded_options ) );
        $this->assertFalse( array_key_exists( "theme_mods_$current_theme_stylesheet", $autoloaded_options ) );

        switch_theme( $current_theme_stylesheet );

        $this->assertEquals( 'yes', $wpdb->get_var( "SELECT autoload FROM $wpdb->options WHERE option_name = 'theme_mods_$current_theme_stylesheet'" ) );
        $this->assertEquals( 'no', $wpdb->get_var( "SELECT autoload FROM $wpdb->options WHERE option_name = 'theme_mods_$new_theme_stylesheet'" ) );

        // Make sure that autoloaded options are cached properly
        $autoloaded_options = wp_cache_get( 'alloptions', 'options' );
        $this->assertFalse( array_key_exists( "theme_mods_$new_theme_stylesheet", $autoloaded_options ) );
        $this->assertTrue( array_key_exists( "theme_mods_$current_theme_stylesheet", $autoloaded_options ) );

        // And that we haven't lost the mods
        $this->assertEquals( 'a-value', get_theme_mod( 'default-theme-option' ) );
    }
}
