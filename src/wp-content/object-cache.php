<?php
/**
 * @package conditional-options-preload
 * @version beta
 */
/*
Plugin Name: conditional-options-preload
Plugin URI: http://wordpress.org/plugins/conditional-options-preload/
Description: conditional-options-preload test.
Author:pbearne
Version: beta
Author URI: http://xwp.io/
*/

include 'class-conditional-options.php';
new xwp\conditional_options\conditional_options_cache();

if ( ! function_exists( 'conditional_options_preload' ) ) {

	add_filter( 'pre_get_alloptions', 'conditional_options_preload' );

	function conditional_options_preload() {
		return xwp\conditional_options\conditional_options_cache::conditional_options_preload();
	}
}
//
if ( ! function_exists( 'conditional_get_options' ) ) {

	add_filter( 'pre_option_all', 'conditional_get_options', 10, 3 );

	function conditional_get_options( $pre, $option, $default ) {
		return xwp\conditional_options\conditional_options_cache::get_option( $pre, $option, $default );
	}
}
if ( ! function_exists( 'conditional_get_options_running' ) ) {

	function conditional_get_options_running() {
		return xwp\conditional_options\conditional_options_cache::running();
	}
}
