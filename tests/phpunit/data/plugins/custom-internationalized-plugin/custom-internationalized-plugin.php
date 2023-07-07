<?php
/*
Plugin Name: Custom Dummy Plugin
Plugin URI: https://wordpress.org/
Description: For testing purposes only.
Version: 1.0.0
Text Domain: custom-internationalized-plugin
*/

load_plugin_textdomain( 'custom-internationalized-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

function custom_i18n_plugin_test() {
	return __( 'This is a dummy plugin', 'custom-internationalized-plugin' );
}
