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
	 * @param string $type Stylesheet type.
	 */
	public function render_stylesheet( $type ) {}

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
		return '';
	}
}
