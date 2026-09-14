<?php
/**
 * Sitemaps: WP_Sitemaps_Stylesheet class
 *
 * This class is retained for backward compatibility.
 *
 * @package WordPress
 * @subpackage Sitemaps
 * @since 5.5.0
 */

/**
 * Stylesheet provider class.
 *
 * @since 5.5.0
 */
#[AllowDynamicProperties]
class WP_Sitemaps_Stylesheet {
	/**
	 * Renders the XSL stylesheet.
	 *
	 * @since 5.5.0
	 * @deprecated 7.2.0 Stylesheets are no longer supported.
	 *
	 * @param string $type Stylesheet type. Either 'sitemap' or 'index'.
	 * @return never
	 */
	public function render_stylesheet( $type ) {
		wp_die(
			sprintf(
				__( 'Function %1$s is <strong>deprecated</strong> since version %2$s with no alternative available.' ),
				__METHOD__,
				'7.2.0'
			)
		);
	}

	/**
	 * Returns the stylesheet for all sitemaps, except index.
	 *
	 * @since 5.5.0
	 * @deprecated 7.2.0 Stylesheets are no longer supported.
	 *
	 * @return string Empty string.
	 */
	public function get_sitemap_stylesheet() {
		return '';
	}

	/**
	 * Returns the stylesheet for the sitemap index.
	 *
	 * @since 5.5.0
	 * @deprecated 7.2.0 Stylesheets are no longer supported.
	 *
	 * @return string Empty string.
	 */
	public function get_sitemap_index_stylesheet() {
		return '';
	}

	/**
	 * Returns the stylesheet CSS.
	 *
	 * @since 5.5.0
	 * @deprecated 7.2.0 Stylesheets are no longer supported.
	 *
	 * @return string Empty string.
	 */
	public function get_stylesheet_css() {
		apply_filters_deprecated( 'wp_sitemaps_stylesheet_css', array( '' ), '7.2.0' );

		return '';
	}
}
