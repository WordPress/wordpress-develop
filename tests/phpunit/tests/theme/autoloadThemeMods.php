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

        // Check that the autoload value is set to 'no' for the previous theme
        $this->assertEquals( 'no', $wpdb->get_var( "SELECT autoload FROM $wpdb->options WHERE option_name = 'theme_mods_$current_theme_stylesheet'" ) );

        // Check that the autoload value is set to 'yes' for the switched theme
        $this->assertEquals( 'yes', wp_cache_get( "theme_mods_$new_theme_stylesheet", 'options' ) );

        switch_theme( $current_theme_stylesheet );

        // Check that the autoload value is set back to 'yes' for the previous theme
        $this->assertEquals( 'yes', wp_cache_get( "theme_mods_$current_theme_stylesheet", 'options' ) );

        // Check that the autoload value is removed for the switched theme
        $this->assertFalse( wp_cache_get( "theme_mods_$new_theme_stylesheet", 'options' ) );

        // And that we haven't lost the mods
        $this->assertEquals( 'a-value', get_theme_mod( 'default-theme-option' ) );
    }
}
