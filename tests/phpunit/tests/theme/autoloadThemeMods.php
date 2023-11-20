<?php

/**
 * @group themes
 * @group temp
 */

class Tests_Autoload_Theme_Mods extends WP_UnitTestCase {

    function setUp() {
            parent::setUp();
            $this->theme_root = realpath( DIR_TESTDATA . '/themedir1' );

            $this->orig_theme_dir            = $GLOBALS['wp_theme_directories'];
            $GLOBALS['wp_theme_directories'] = array( $this->theme_root );

            add_filter( 'theme_root', array( $this, '_theme_root' ) );
            add_filter( 'stylesheet_root', array( $this, '_theme_root' ) );
            add_filter( 'template_root', array( $this, '_theme_root' ) );
            // clear caches
            wp_clean_themes_cache();
            unset( $GLOBALS['wp_themes'] );
    }

    function tearDown() {
            $GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;
            remove_filter( 'theme_root', array( $this, '_theme_root' ) );
            remove_filter( 'stylesheet_root', array( $this, '_theme_root' ) );
            remove_filter( 'template_root', array( $this, '_theme_root' ) );
            wp_clean_themes_cache();
            unset( $GLOBALS['wp_themes'] );
            parent::tearDown();
    }

    // replace the normal theme root dir with our premade test dir
    function _theme_root( $dir ) {
            return $this->theme_root;
    }

    function test_on_switch_theme_previous_mods_should_not_be_autoload() {
        global $wpdb;

        $current_theme_stylesheet = get_stylesheet();

        // Set a theme mod for the current theme
        $new_theme_stylesheet = 'theme1';
        set_theme_mod( 'default-theme-option', 'a-value' );

        // Switch to the new theme
        switch_theme( $new_theme_stylesheet );

        // Verify autoload settings in the options table
        $this->assertEquals( 'no', $wpdb->get_var( "SELECT autoload FROM $wpdb->options WHERE option_name = 'theme_mods_$current_theme_stylesheet'" ) );
        $this->assertEquals( 'yes', $wpdb->get_var( "SELECT autoload FROM $wpdb->options WHERE option_name = 'theme_mods_$new_theme_stylesheet'" ) );

        // Verify that autoloaded options are cached properly
        $autoloaded_options = wp_cache_get( 'alloptions', 'options' );
        $this->assertTrue( array_key_exists( "theme_mods_$new_theme_stylesheet", $autoloaded_options ) );
        $this->assertFalse( array_key_exists( "theme_mods_$current_theme_stylesheet", $autoloaded_options ) );

        // Switch back to the current theme
        switch_theme( $current_theme_stylesheet );

        // Verify autoload settings in the options table
        $this->assertEquals( 'yes', $wpdb->get_var( "SELECT autoload FROM $wpdb->options WHERE option_name = 'theme_mods_$current_theme_stylesheet'" ) );
        $this->assertEquals( 'no', $wpdb->get_var( "SELECT autoload FROM $wpdb->options WHERE option_name = 'theme_mods_$new_theme_stylesheet'" ) );

        // Verify that autoloaded options are cached properly
        $autoloaded_options = wp_cache_get( 'alloptions', 'options' );
        $this->assertFalse( array_key_exists( "theme_mods_$new_theme_stylesheet", $autoloaded_options ) );
        $this->assertTrue( array_key_exists( "theme_mods_$current_theme_stylesheet", $autoloaded_options ) );

        // Check that we haven't lost the mods
        $this->assertEquals( 'a-value', get_theme_mod( 'default-theme-option' ) );
    }
}