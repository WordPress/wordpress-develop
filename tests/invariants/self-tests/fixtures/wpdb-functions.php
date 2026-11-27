<?php
/**
 * The WordPress functions wpdb::prepare() calls, for the self-tests, which do not load WordPress.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

if ( ! function_exists( 'wp_load_translations_early' ) ) {
	/**
	 * Does nothing.
	 */
	function wp_load_translations_early() {
	}
}

if ( ! function_exists( '_doing_it_wrong' ) ) {
	/**
	 * Does nothing.
	 */
	function _doing_it_wrong() {
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Returns the text untranslated.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'has_filter' ) ) {
	/**
	 * Says every filter is already added.
	 *
	 * @return bool
	 */
	function has_filter() {
		return true;
	}
}
