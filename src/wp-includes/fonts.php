<?php
/**
 * Fonts functions.
 *
 * @package    WordPress
 * @subpackage Fonts
 * @since      6.4.0
 */

/**
 * Generates and prints font-face styles for given fonts or theme.json fonts.
 *
 * @since 6.4.0
 *
 * @param array[][] $fonts {
 *     Optional. The font-families and their font faces. Default empty array.
 *
 *     @type array {
 *         An indexed or associative (keyed by font-family) array of font variations for this font-family.
 *         Each font face has the following structure.
 *
 *         @type array {
 *             @type string          $font-family             The font-family property.
 *             @type string|string[] $src                     The URL(s) to each resource containing the font data.
 *             @type string          $font-style              Optional. The font-style property. Default 'normal'.
 *             @type string          $font-weight             Optional. The font-weight property. Default '400'.
 *             @type string          $font-display            Optional. The font-display property. Default 'fallback'.
 *             @type string          $ascent-override         Optional. The ascent-override property.
 *             @type string          $descent-override        Optional. The descent-override property.
 *             @type string          $font-stretch            Optional. The font-stretch property.
 *             @type string          $font-variant            Optional. The font-variant property.
 *             @type string          $font-feature-settings   Optional. The font-feature-settings property.
 *             @type string          $font-variation-settings Optional. The font-variation-settings property.
 *             @type string          $line-gap-override       Optional. The line-gap-override property.
 *             @type string          $size-adjust             Optional. The size-adjust property.
 *             @type string          $unicode-range           Optional. The unicode-range property.
 *         }
 *     }
 * }
 */
function wp_print_font_faces( $fonts = array() ) {

	if ( empty( $fonts ) ) {
		$fonts = WP_Font_Face_Resolver::get_fonts_from_theme_json();
	}

	if ( empty( $fonts ) ) {
		return;
	}

	$wp_font_face = new WP_Font_Face();
	$wp_font_face->generate_and_print( $fonts );
}

/**
 * Registers a new font collection in the font library.
 *
 * See {@link https://schemas.wp.org/trunk/font-collection.json} for the schema
 * the font collection data must adhere to.
 *
 * @since 6.5.0
 *
 * @param string $slug Font collection slug. May only contain alphanumeric characters, dashes,
 *                     and underscores. See sanitize_title().
 * @param array  $args {
 *     Font collection data.
 *
 *     @type string       $name          Required. Name of the font collection shown in the Font Library.
 *     @type string       $description   Optional. A short descriptive summary of the font collection. Default empty.
 *     @type array|string $font_families Required. Array of font family definitions that are in the collection,
 *                                       or a string containing the path or URL to a JSON file containing the font collection.
 *     @type array        $categories    Optional. Array of categories, each with a name and slug, that are used by the
 *                                       fonts in the collection. Default empty.
 * }
 * @return WP_Font_Collection|WP_Error A font collection if it was registered
 *                                     successfully, or WP_Error object on failure.
 */
function wp_register_font_collection( string $slug, array $args ) {
	return WP_Font_Library::get_instance()->register_font_collection( $slug, $args );
}

/**
 * Unregisters a font collection from the Font Library.
 *
 * @since 6.5.0
 *
 * @param string $slug Font collection slug.
 * @return bool True if the font collection was unregistered successfully, else false.
 */
function wp_unregister_font_collection( string $slug ) {
	return WP_Font_Library::get_instance()->unregister_font_collection( $slug );
}

/**
 * Lightweight, don't create directory.
 *
 * @todo Get in a committable state if it's decided to do this.
 *
 * @return array
 */
function wp_get_font_dir() {
	return wp_font_dir( false );
}

/**
 * Get and create the font directory.
 *
 * @todo Get in a committable state if it's decided to do this.
 *
 * @param bool $create_dir
 * @param bool $refresh_cache
 * @return array
 */
function wp_font_dir( $create_dir = true ) {
	/*
	 * Allow extenders to manipulate the font directory consistently.
	 *
	 * This ensures the upload_dir filter is fired both when using
	 * this function directly and when using the upload directory
	 * is filtered in the Font Face REST API endpoint.
	 */
	add_filter( 'upload_dir', 'wp_apply_font_dir_filter' );
	$font_dir = wp_upload_dir( null, $create_dir, false );
	remove_filter( 'upload_dir', 'wp_apply_font_dir_filter' );
	return $font_dir;
}

/**
 * Apply the filter.
 *
 * @todo Get in a committable state if it's decided to do this.
 *
 * @param mixed $font_dir
 * @return mixed
 */
function wp_apply_font_dir_filter( $font_dir ) {
	if ( doing_filter( 'font_dir' ) ) {
		// Avoid an infinite loop.
		return $font_dir;
	}

	return apply_filters( 'font_dir', $font_dir );
}

/**
 * Default font filter.
 *
 * @todo Get in a committable state if it's decided to do this.
 *
 * @param mixed $font_dir
 * @return void
 */
function wp_default_font_dir_filter() {
	$site_path = '';
	if ( is_multisite() && ! ( is_main_network() && is_main_site() ) ) {
		$site_path = '/sites/' . get_current_blog_id();
	}

	return array(
		'path'    => path_join( WP_CONTENT_DIR, 'fonts' ) . $site_path,
		'url'     => untrailingslashit( content_url( 'fonts' ) ) . $site_path,
		'subdir'  => '',
		'basedir' => path_join( WP_CONTENT_DIR, 'fonts' ) . $site_path,
		'baseurl' => untrailingslashit( content_url( 'fonts' ) ) . $site_path,
		'error'   => false,
	);
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
	$font_dir   = wp_get_font_dir()['path'];

	foreach ( $font_files as $font_file ) {
		wp_delete_file( $font_dir . '/' . $font_file );
	}
}

/**
 * Register the default font collections.
 *
 * @access private
 * @since 6.5.0
 */
function _wp_register_default_font_collections() {
	wp_register_font_collection(
		'google-fonts',
		array(
			'name'          => _x( 'Google Fonts', 'font collection name' ),
			'description'   => __( 'Install from Google Fonts. Fonts are copied to and served from your site.' ),
			'font_families' => 'https://s.w.org/images/fonts/17.7/collections/google-fonts-with-preview.json',
			'categories'    => array(
				array(
					'name' => _x( 'Sans Serif', 'font category' ),
					'slug' => 'sans-serif',
				),
				array(
					'name' => _x( 'Display', 'font category' ),
					'slug' => 'display',
				),
				array(
					'name' => _x( 'Serif', 'font category' ),
					'slug' => 'serif',
				),
				array(
					'name' => _x( 'Handwriting', 'font category' ),
					'slug' => 'handwriting',
				),
				array(
					'name' => _x( 'Monospace', 'font category' ),
					'slug' => 'monospace',
				),
			),
		)
	);
}
