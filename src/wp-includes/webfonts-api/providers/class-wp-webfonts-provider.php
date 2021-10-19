<?php
/**
 * Webfonts API: Provider abstract class.
 *
 * Individual webfonts providers should extend this class and implement.
 *
 * @package    WordPress
 * @subpackage WebFonts
 * @since      5.9.0
 */

/**
 * Abstract class for Webfonts API providers.
 */
abstract class WP_Webfonts_Provider {

	/**
	 * The provider's unique ID.
	 *
	 * @since 5.9.0
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * The `<link>` element's attributes for each linked resource.
	 *
	 * @since 5.9.0
	 *
	 * @var array[] {
	 *     An array of linked resources.
	 *
	 *     @type array() {
	 *          An array of attributes for this linked resource.
	 *
	 *          @type string $attribute => @type string $attribute_value
	 *     }
	 * }
	 */
	protected $link_attributes = array();

	/**
	 * The provider's root URL.
	 *
	 * @since 5.9.0
	 *
	 * @var string
	 */
	protected $root_url = '';

	/**
	 * Webfonts to be processed.
	 *
	 * @since 5.9.0
	 *
	 * @var array[]
	 */
	protected $webfonts = array();

	/**
	 * Get the provider's unique ID.
	 *
	 * @since 5.9.0
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get the root URL for the provider.
	 *
	 * @since 5.9.0
	 *
	 * @return string
	 */
	public function get_root_url() {
		return $this->root_url;
	}

	/**
	 * Get the `<link>` attributes.
	 *
	 * @since 5.9.0
	 *
	 * @return array[]
	 */
	public function get_link_attributes() {
		return $this->link_attributes;
	}

	/**
	 * Sets the webfonts.
	 *
	 * The webfonts have been validated, are in kebab_case, and
	 * are arranged by provider.
	 *
	 * @since 5.9.0
	 *
	 * @param array[] $webfonts Registered webfonts.
	 */
	public function set_webfonts( array $webfonts ) {
		$this->webfonts = $webfonts;

		foreach ( $this->webfonts as $registered_key => $webfont ) {
			$this->webfonts[ $registered_key ] = $this->prepare( $webfont );
		}
	}

	/**
	 * Prepares the given webfont.
	 *
	 * @since 5.9.0
	 *
	 * @param array $webfont Webfont to validate.
	 * @return array
	 */
	protected function prepare( array $webfont ) {
		return $webfont;
	}

	/**
	 * Get the CSS for the font.
	 *
	 * @since 5.9.0
	 *
	 * @return string Webfonts CSS.
	 */
	abstract public function get_css();

	/**
	 * Get cached styles from a remote URL.
	 *
	 * @since 5.9.0
	 *
	 * @param string $id               An ID used to cache the styles.
	 * @param string $url              The URL to fetch.
	 * @param array  $args             The arguments to pass to wp_remote_get().
	 * @param array  $additional_props Additional properties to add to the @font-face styles.
	 * @return string The styles.
	 */
	public function get_cached_remote_styles( $id, $url, array $args = array(), array $additional_props = array() ) {
		$css = get_site_transient( $id );

		// Get remote response and cache the CSS if it hasn't been cached already.
		if ( false === $css ) {
			$css = $this->get_remote_styles( $url, $args );

			// Early return if the request failed.
			// Cache an empty string for 60 seconds to avoid bottlenecks.
			if ( empty( $css ) ) {
				set_site_transient( $id, '', 60 );
				return '';
			}

			// Cache the CSS for a month.
			set_site_transient( $id, $css, MONTH_IN_SECONDS );
		}

		// If there are additional props not included in the CSS provided by the API, add them to the final CSS.
		foreach ( $additional_props as $prop ) {
			$css = str_replace(
				'@font-face {',
				'@font-face {' . $prop . ':' . $this->params[ $prop ] . ';',
				$css
			);
		}

		return $css;
	}

	/**
	 * Get styles from a remote URL.
	 *
	 * @since 5.9.0
	 *
	 * @param string $url  The URL to fetch.
	 * @param array  $args The arguments to pass to wp_remote_get().
	 * @return string The styles on success. Empty string on failure.
	 */
	public function get_remote_styles( $url, array $args = array() ) {
		// Use a modern user-agent, to get woff2 files.
		$args['user-agent'] = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0';

		// Get the remote URL contents.
		$response = wp_remote_get( $url, $args );

		// Early return if the request failed.
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		// Get the response body.
		return wp_remote_retrieve_body( $response );
	}
}
