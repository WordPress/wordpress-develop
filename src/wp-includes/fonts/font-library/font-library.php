<?php
/**
 * Font Library initialization.
 *
 * This file contains Font Library init calls.
 *
 * @package    WordPress
 * @subpackage Font Library
 * @since      6.4.0
 */

if ( ! function_exists( 'wp_register_font_collection' ) ) {
	/**
	 * Registers a new Font Collection in the Font Library.
	 *
	 * @since 6.4.0
	 *
	 * @param string[] $config {
	 *     Font collection associative array of configuration options.
	 *
	 *     @type string $id             The font collection's unique ID.
	 *     @type string $src The font collection's data JSON file.
	 * }
	 * @return WP_Font_Collection|WP_Error A font collection is it was registered
	 *                                     successfully, else WP_Error.
	 */
	function wp_register_font_collection( $config ) {
		return WP_Font_Library::register_font_collection( $config );
	}
}

add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_add_inline_script( 'wp-block-editor', 'window.__experimentalFontLibrary = true', 'before' );
	}
);

$default_font_collection = array(
	'id'          => 'default-font-collection',
	'name'        => 'Google Fonts',
	'description' => __( 'Add from Google Fonts. Fonts are copied to and served from your site.', 'default' ),
	'src'         => 'https://raw.githubusercontent.com/WordPress/google-fonts-to-wordpress-collection/main/output/google-fonts-with-previews.json',
);

wp_register_font_collection( $default_font_collection );
