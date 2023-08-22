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
 * @param array $fonts Optional. The fonts to generate and print @font-face styles.
 *                     Default empty array.
 */
function wp_print_font_faces( $fonts = array() ) {
	static $wp_font_face = null;

	if ( empty( $fonts ) ) {
		$fonts = WP_Font_Face_Resolver::get_fonts_from_theme_json();
	}

	if ( empty( $fonts ) ) {
		return;
	}

	if (
		null === $wp_font_face ||

		// Ignore cache when automated test suites are running.
		( defined( 'WP_RUN_CORE_TESTS' ) && WP_RUN_CORE_TESTS )
	) {
		$wp_font_face = new WP_Font_Face();
	}

	$wp_font_face->generate_and_print( $fonts );
}
