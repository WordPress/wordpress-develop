<?php
/**
 * Add filter to hide the admin bar.
 *
 * This filter is used to hide the admin bar in classic theme site previews in the site editor.
 *
 * @since 6.8.0
 */
function wp_initialize_site_preview_hooks() {
	if ( isset( $_GET['wp_site_preview'] ) && 1 === (int) $_GET['wp_site_preview'] ) {
		add_filter( 'show_admin_bar', '__return_false' );
	}
}
