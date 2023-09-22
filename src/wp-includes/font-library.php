
<?php
/**
 * Font Library functions.
 *
 * @package    WordPress
 * @subpackage Fonts
 * @since      6.4.0
 */

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