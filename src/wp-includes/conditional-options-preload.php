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

if ( ! function_exists( 'conditional_options_preload' ) ) {

	function conditional_options_preload( $force_cache = false ) {
		return xwp\conditional_options\conditional_options::conditional_options_preload( $force_cache );
	}
}
//
if ( ! function_exists( 'conditional_get_options' ) ) {
	function conditional_get_options( $option, $default ) {
		return xwp\conditional_options\conditional_options::get_option( $option, $default );
	}
}
if ( ! function_exists( 'conditional_get_options_runing' ) ) {
	function conditional_get_options_runing() {
		return xwp\conditional_options\conditional_options::runing();
	}
}
