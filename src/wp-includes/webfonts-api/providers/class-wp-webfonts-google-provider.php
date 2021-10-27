<?php
/**
 * Webfonts API: Google Fonts provider.
 *
 * @since      5.9.0
 * @subpackage WebFonts
 * @package    WordPress
 */

/**
 * Webfonts API provider for Google Fonts.
 *
 * @since 5.9.0
 */
class WP_Webfonts_Google_Provider extends WP_Webfonts_Provider {

	/**
	 * The provider's unique ID.
	 *
	 * @since 5.9.0
	 *
	 * @var string
	 */
	protected $id = 'google';

	/**
	 * The provider's root URL.
	 *
	 * @since 5.9.0
	 * @var string
	 */
	protected $root_url = 'https://fonts.googleapis.com/css2';

	/**
	 * The object's constructor.
	 *
	 * @since 5.9.0
	 */
	public function __construct() {

		// Add preconnect links.
		add_filter( 'wp_resource_hints', array( $this, 'add_preconnect_urls' ), 10, 2 );
	}

	/**
	 * Get the CSS for a collection of fonts.
	 *
	 * @access public
	 * @since  5.9.0
	 * @return string
	 */
	public function get_css() {
		$css  = '';
		$urls = $this->build_collection_api_urls();

		foreach ( $urls as $url ) {
			$css .= $this->get_cached_remote_styles( 'google_fonts_' . md5( $url ), $url );
		}

		return $css;
	}

	/**
	 * Build the API URL for a collection of fonts.
	 *
	 * @since 5.9.0
	 *
	 * @return array Collection of font-family urls.
	 */
	protected function build_collection_api_urls() {
		$font_families_urls = array();

		// Iterate over each font-family group and build the API URL partial for that font-family.
		foreach ( $this->organize_webfonts() as $font_display => $font_families ) {
			$font_display_url_parts = array();
			foreach ( $font_families as $font_family => $webfonts ) {
				$normal_weights = array();
				$italic_weights = array();
				$url_part       = urlencode( $font_family );

				// Build an array of font-weights for italics and default styles.
				foreach ( $webfonts as $font ) {
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
	 * Organizes the webfonts by font-display.
	 *
	 * @since 5.8.0
	 *
	 * @return array Sorted by font-display.
	 */
	private function organize_webfonts() {
		$font_display_groups = array();

		/*
		 * Group by font-display.
		 * Each font-display will need to be a separate request.
		 */
		foreach ( $this->webfonts as $webfont ) {
			if ( ! isset( $font['font-display'] ) ) {
				$webfont['font-display'] = 'fallback';
			}

			if ( ! isset( $font_display_groups[ $webfont['font-display'] ] ) ) {
				$font_display_groups[ $webfont['font-display'] ] = array();
			}
			$font_display_groups[ $webfont['font-display'] ][] = $webfont;
		}

		/*
		 * Iterate over each font-display group and group by font-family.
		 * Multiple font-families can be combined in the same request,
		 * but their params need to be grouped.
		 */
		foreach ( $font_display_groups as $font_display => $font_display_group ) {
			$font_families = array();

			foreach ( $font_display_group as $webfont ) {
				if ( ! isset( $font_families[ $webfont['font-family'] ] ) ) {
					$font_families[ $webfont['font-family'] ] = array();
				}
				$font_families[ $webfont['font-family'] ][] = $webfont;
			}

			$font_display_groups[ $font_display ] = $font_families;
		}

		return $font_display_groups;
	}

	/**
	 * Adds preconnect URLs for webfonts providers.
	 *
	 * @since 5.9.0
	 *
	 * @param array  $urls {
	 *     Array of resources and their attributes, or URLs to print for resource hints.
	 *
	 *     @type array|string ...$0 {
	 *         Array of resource attributes, or a URL string.
	 *
	 *         @type string $href        URL to include in resource hints. Required.
	 *         @type string $as          How the browser should treat the resource
	 *                                   (`script`, `style`, `image`, `document`, etc).
	 *         @type string $crossorigin Indicates the CORS policy of the specified resource.
	 *         @type float  $pr          Expected probability that the resource hint will be used.
	 *         @type string $type        Type of the resource (`text/html`, `text/css`, etc).
	 *     }
	 * }
	 * @param string $relation_type The relation type the URLs are printed for,
	 *                              e.g. 'preconnect' or 'prerender'.
	 */
	public function add_preconnect_urls( $urls, $relation_type ) {
		if ( 'preconnect' !== $relation_type ) {
			return $urls;
		}

		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
		return $urls;
	}
}
