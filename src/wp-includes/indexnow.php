<?php
/**
 * IndexNow: Public functions
 *
 *
 * @package WordPress
 * @subpackage IndexNow
 * @since 5.9.0
 */

/**
 * Retrieves the current indexnow instance.
 *
 * @since 5.9.0
 *
 * @global WP_IndexNow $wp_indexnow Global Core IndexNow instance.
 *
 * @return WP_IndexNow instance.
 */
function wp_indexnow_get_instance() {
	global $wp_indexnow;

	// If there isn't a global instance, set and bootstrap the indexnow system.
	if ( empty( $wp_indexnow ) && defined( 'WP_INDEXNOW' ) && true == WP_INDEXNOW ) {
		$wp_indexnow = new WP_IndexNow();
		$wp_indexnow->init();
	}

	return $wp_indexnow;
}

/**
 * Regenerates the indexnow api key and returns it.
 *
 * @since 5.9.0
 *
 * @return string|null.
 */
function wp_indexnow_regenerate_key() {
	$wp_indexnow = wp_indexnow_get_instance();

	return $wp_indexnow->refresh_indexnow_key();
}

/**
 * Returns the indexnow api key.
 *
 * @since 5.9.0
 *
 * @return string|null.
 */
function wp_indexnow_get_api_key() {
	$wp_indexnow = wp_indexnow_get_instance();

	return $wp_indexnow->get_api_key();
}


/**
 * Adds url subpath to be ignored by indexnow.
 *
 * @example for all urls under /abc/, i.e, <site_url>/abc/*
 * pass regex '/\/^abc\//' in below function.
 *
 * @since 5.9.0
 *
 * @return bool.
 */
function wp_indexnow_ignore_path( $path ) {
	$wp_indexnow = wp_indexnow_get_instance();

	return $wp_indexnow->ignore_path( $path );
}

/**
 * Remove url subpath added earlier using wp_indexnow_ignore_path().
 *
 * @since 5.9.0
 *
 * @return bool.
 */
function wp_indexnow_remove_path( $path ) {
	$wp_indexnow = wp_indexnow_get_instance();

	return $wp_indexnow->remove_path( $path );
}
