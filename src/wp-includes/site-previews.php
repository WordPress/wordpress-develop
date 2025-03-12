<?php
/**
 * Initialize site preview.
 *
 * This function sets IFRAME_REQUEST to true if the site preview parameter is set.
 *
 * @since 6.8.0
 */
function wp_initialize_site_preview_hooks() {
	if ( ! defined( 'IFRAME_REQUEST' ) && isset( $_GET['wp_site_preview'] ) && 1 === (int) $_GET['wp_site_preview'] ) {
		define( 'IFRAME_REQUEST', true );
	}
}
