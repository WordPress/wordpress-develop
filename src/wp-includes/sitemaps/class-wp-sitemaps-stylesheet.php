<?php
/**
 * Sitemaps: WP_Sitemaps_Stylesheet class
 *
 * This class is retained for backward compatibility.
 *
 * @package WordPress
 * @subpackage Sitemaps
 * @since 5.5.0
 * @deprecated 7.2.0 Stylesheets are no longer supported.
 */

/**
 * Stylesheet provider class.
 *
 * @since 5.5.0
 * @deprecated 7.2.0 Stylesheets are no longer supported.
 */
#[AllowDynamicProperties]
class WP_Sitemaps_Stylesheet {
	/**
	 * Renders the XSL stylesheet.
	 *
	 * @since 5.5.0
	 * @deprecated 7.2.0 Stylesheets are no longer supported.
	 *
	 * @since 5.5.0
	 *
	 * @param string $type Stylesheet type. Either 'sitemap' or 'index'.
	 * @return never
	 */
	public function render_stylesheet( $type ) {
		_deprecated_function( __METHOD__, '7.2.0' );
		exit;
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
		_deprecated_function( __METHOD__, '7.2.0' );
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
		_deprecated_function( __METHOD__, '7.2.0' );
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
		_deprecated_function( __METHOD__, '7.2.0' );

		/**
		 * Filters the CSS only for the sitemap stylesheet.
		 *
		 * @since 5.5.0
		 * @deprecated 7.2.0 Stylesheets are no longer supported.
		 *
		 * @param string $css CSS to be applied to default XSL file.
		 */
		apply_filters_deprecated( 'wp_sitemaps_stylesheet_css', array( '' ), '7.2.0' );

		return '';
	}
}
