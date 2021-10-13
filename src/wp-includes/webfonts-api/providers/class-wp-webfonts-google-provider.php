<?php
/**
 * Webfonts API provider for Google fonts.
 *
 * @package    WordPress
 * @subpackage WebFonts
 * @since      5.9.0
 */

/**
 * Webfonts API provider for Google Fonts.
 */
class WP_Webfonts_Google_Provider extends WP_Webfonts_Provider {

	/**
	 * The provider's unique ID.
	 *
	 * @since 5.9.0
	 * @var string
	 */
	protected $id = 'google';

	/**
	 * An array of URLs to preconnect to.
	 *
	 * @since 5.9.0
	 * @var array
	 */
	protected $preconnect_urls = array(
		array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => true,
		),
		array(
			'href'        => 'https://fonts.googleapis.com',
			'crossorigin' => false,
		),
	);

	/**
	 * The provider's root URL.
	 *
	 * @since 5.9.0
	 * @var string
	 */
	protected $root_url = 'https://fonts.googleapis.com/css2';

	/**
	 * An array of API parameters which will not be added to the @font-face.
	 *
	 * @since 5.9.0
	 * @var array
	 */
	protected $api_params = array(
		'subset',
		'text',
		'effect',
	);

	/**
	 * Build the API URL for a collection of fonts.
	 *
	 * @access protected
	 * @since 5.9.0
	 * @param array $fonts
	 * @return string
	 */
	protected function build_collection_api_urls( $fonts ) {
		$font_families_urls = array();

		// Validate all fonts.
		foreach ( $fonts as $key => $font ) {
			$fonts[ $key ] = $this->get_validated_params( $font );
		}

		// Group by font-display.
		// Each font-display will need to be a separate request.
		$font_display_groups = array();
		foreach ( $fonts as $font ) {
			$font['font-display'] = isset( $font['font-display'] ) ? $font['font-display'] : 'fallback';
			if ( ! isset( $font_display_groups[ $font['font-display'] ] ) ) {
				$font_display_groups[ $font['font-display'] ] = array();
			}
			$font_display_groups[ $font['font-display'] ][] = $font;
		}

		// Iterate over each font-display group and group by font-family.
		// Multiple font-families can be combined in the same request, but their params need to be grouped.
		foreach ( $font_display_groups as $font_display => $font_display_group ) {
			$font_families = array();
			foreach ( $font_display_group as $font ) {
				if ( ! isset( $font_families[ $font['font-family'] ] ) ) {
					$font_families[ $font['font-family'] ] = array();
				}
				$font_families[ $font['font-family'] ][] = $font;
			}
			$font_display_groups[ $font_display ] = $font_families;
		}

		// Iterate over each font-family group and build the API URL partial for that font-family.
		foreach ( $font_display_groups as $font_display => $font_families ) {
			$font_display_url_parts = array();
			foreach ( $font_families as $font_family => $fonts ) {
				$normal_weights = array();
				$italic_weights = array();
				$url_part       = urlencode( $font_family );

				// Build an array of font-weights for italics and default styles.
				foreach ( $fonts as $font ) {
					if ( 'italic' === $font['font-style'] ) {
						$italic_weights[] = $font['font-weight'];
					} else {
						$normal_weights[] = $font['font-weight'];
					}
				}

				if ( empty( $italic_weights ) && ! empty( $normal_weights ) ) {
					$url_part .= ':wght@' . implode( ';', $normal_weights );
				} elseif ( ! empty( $italic_weights ) && empty( $normal_weights ) ) {
					$url_part .= ':ital,wght@1,' . implode( ';', $normal_weights );
				} elseif ( ! empty( $italic_weights ) && ! empty( $normal_weights ) ) {
					$url_part .= ':ital,wght@0,' . implode( ';0,', $normal_weights ) . ';1,' . implode( ';1,', $italic_weights );
				}

				$font_display_url_parts[] = $url_part;
			}

			$font_families_urls[] = $this->root_url . '?family=' . implode( '&family=', $font_display_url_parts ) . '&display=' . $font_display;
		}

		return $font_families_urls;
	}

	/**
	 * Get the CSS for a collection of fonts.
	 *
	 * @access public
	 * @since 5.9.0
	 * @return string
	 */
	public function get_css() {
		$css  = '';
		$urls = $this->build_collection_api_urls( $this->params );

		foreach ( $urls as $url ) {
			$css .= $this->get_cached_remote_styles( 'google_fonts_' . md5( $url ), $url );
		}

		return $css;
	}
}
