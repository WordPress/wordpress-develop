<?php
/**
 * Noop functions for load-scripts.php and load-styles.php.
 *
 * These stubs allow the script/style loader classes to be bootstrapped
 * without loading the full WordPress stack. Each stub returns a type-safe
 * empty value that matches what the real function would return, preventing
 * PHP 8 type errors when the return value is used downstream.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 4.4.0
 * @deprecated 7.1.0 Script/style concatenation via load-scripts.php and load-styles.php is deprecated.
 */

/**
 * @ignore
 * @return string
 */
function __() {
	return '';
}

/**
 * @ignore
 * @return string
 */
function _x() {
	return '';
}

/**
 * @ignore
 * @return void
 */
function add_filter() {}

/**
 * @ignore
 * @return false
 */
function has_filter() {
	return false;
}

/**
 * @ignore
 * @param string $text
 * @return string
 */
function esc_attr( $text = '' ) {
	return (string) $text;
}

/**
 * @ignore
 * @return mixed Returns the first argument passed, matching the real apply_filters() contract.
 */
function apply_filters() {
	$args = func_get_args();
	// Return the second argument (the value), mirroring the real apply_filters() behaviour.
	return isset( $args[1] ) ? $args[1] : null;
}

/**
 * @ignore
 * @return false
 */
function get_option() {
	return false;
}

/**
 * @ignore
 * @return false
 */
function is_lighttpd_before_150() {
	return false;
}

/**
 * @ignore
 * @return void
 */
function add_action() {}

/**
 * @ignore
 * @return false
 */
function did_action() {
	return false;
}

/**
 * @ignore
 * @return void
 */
function do_action_ref_array() {}

/**
 * @ignore
 * @return string
 */
function get_bloginfo() {
	return '';
}

/**
 * @ignore
 * @return true
 */
function is_admin() {
	return true;
}

/**
 * @ignore
 * @return string
 */
function site_url() {
	return '';
}

/**
 * @ignore
 * @return string
 */
function admin_url() {
	return '';
}

/**
 * @ignore
 * @return string
 */
function home_url() {
	return '';
}

/**
 * @ignore
 * @return string
 */
function includes_url() {
	return '';
}

/**
 * @ignore
 * @return string
 */
function wp_guess_url() {
	return '';
}

/**
 * Returns the contents of the given file path, or an empty string if the file
 * does not exist or cannot be read.
 *
 * @param string $path Absolute filesystem path to the file.
 * @return string File contents, or empty string on failure.
 */
function get_file( $path ) {

	$path = realpath( $path );

	if ( ! $path || ! is_file( $path ) ) {
		return '';
	}

	$contents = file_get_contents( $path );

	return false !== $contents ? $contents : '';
}
