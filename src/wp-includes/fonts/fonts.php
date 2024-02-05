<?php
/**
 * Font Library initialization.
 *
 * @package    WordPress
 * @subpackage Fonts
 * @since      6.5.0
 */

/**
 * Registers a new Font Collection in the Font Library.
 *
 * @since 6.5.0
 *
 * @param string       $slug Font collection slug. May only contain alphanumeric characters, dashes,
 *                     and underscores. See sanitize_title().
 * @param array|string $data_or_file {
 *     Font collection data array or a path/URL to a JSON file containing the font collection.
 *
 *     @link https://schemas.wp.org/trunk/font-collection.json
 *
 *     @type string $name           Required. Name of the font collection shown in the Font Library.
 *     @type string $description    Optional. A short descriptive summary of the font collection. Default empty.
 *     @type array  $font_families  Required. Array of font family definitions that are in the collection.
 *     @type array  $categories     Optional. Array of categories, each with a name and slug, that are used by the
 *                                  fonts in the collection. Default empty.
 * }
 * @return WP_Font_Collection|WP_Error A font collection if it was registered
 *                                     successfully, or WP_Error object on failure.
 */
function wp_register_font_collection( $slug, $data_or_file ) {
    return WP_Font_Library::register_font_collection( $slug, $data_or_file );
}

/**
 * Unregisters a font collection from the Font Library.
 *
 * @since 6.5.0
 *
 * @param string $slug Font collection slug.
 * @return bool True if the font collection was unregistered successfully, else false.
 */
function wp_unregister_font_collection( $slug ) {
    return WP_Font_Library::unregister_font_collection( $slug );
}

/**
 * Returns an array containing the current fonts upload directory's path and URL.
 *
 * @since 6.5.0
 *
 * @param array $defaults {
 *     Array of information about the upload directory.
 *
 *     @type string       $path    Base directory and subdirectory or full path to the fonts upload directory.
 *     @type string       $url     Base URL and subdirectory or absolute URL to the fonts upload directory.
 *     @type string       $subdir  Subdirectory
 *     @type string       $basedir Path without subdir.
 *     @type string       $baseurl URL path without subdir.
 *     @type string|false $error   False or error message.
 * }
 * @return array $defaults {
 *     Array of information about the upload directory.
 *
 *     @type string       $path    Base directory and subdirectory or full path to the fonts upload directory.
 *     @type string       $url     Base URL and subdirectory or absolute URL to the fonts upload directory.
 *     @type string       $subdir  Subdirectory
 *     @type string       $basedir Path without subdir.
 *     @type string       $baseurl URL path without subdir.
 *     @type string|false $error   False or error message.
 * }
 */
function wp_get_font_dir( $defaults = array() ) {
    // Multi site path
    $site_path = '';
    if ( is_multisite() && ! ( is_main_network() && is_main_site() ) ) {
        $site_path = '/sites/' . get_current_blog_id();
    }

    // Sets the defaults.
    $defaults['path']    = path_join( WP_CONTENT_DIR, 'fonts' ) . $site_path;
    $defaults['url']     = untrailingslashit( content_url( 'fonts' ) ) . $site_path;
    $defaults['subdir']  = '';
    $defaults['basedir'] = path_join( WP_CONTENT_DIR, 'fonts' ) . $site_path;
    $defaults['baseurl'] = untrailingslashit( content_url( 'fonts' ) ) . $site_path;
    $defaults['error']   = false;

    /**
     * Filters the fonts directory data.
     *
     * This filter allows developers to modify the fonts directory data.
     *
     * @since 6.5.0
     *
     * @param array $defaults The original fonts directory data.
     */
    return apply_filters( 'font_dir', $defaults );
}

/**
 * Deletes child font faces when a font family is deleted.
 *
 * @access private
 * @since 6.5.0
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function _wp_after_delete_font_family( $post_id, $post ) {
    if ( 'wp_font_family' !== $post->post_type ) {
        return;
    }

    $font_faces = get_children(
        array(
            'post_parent' => $post_id,
            'post_type'   => 'wp_font_face',
        )
    );

    foreach ( $font_faces as $font_face ) {
        wp_delete_post( $font_face->ID, true );
    }
}

/**
 * Deletes associated font files when a font face is deleted.
 *
 * @access private
 * @since 6.5.0
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function _wp_before_delete_font_face( $post_id, $post ) {
    if ( 'wp_font_face' !== $post->post_type ) {
        return;
    }

    $font_files = get_post_meta( $post_id, '_wp_font_face_file', false );

    foreach ( $font_files as $font_file ) {
        wp_delete_file( wp_get_font_dir()['path'] . '/' . $font_file );
    }
}