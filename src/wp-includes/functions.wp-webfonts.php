<?php
/**
 * Dependencies API: Webfonts functions
 *
 * @since 5.9.0
 *
 * @package WordPress
 * @subpackage Dependencies
 */

/**
 * Registers a font-collection.
 *
 * @since 5.9.0
 *
 * @param array $fonts An array of fonts to be registered.
 */
function wp_register_font_collection( $fonts ) {
	// Get the stylesheet handle.
	$stylesheet_handle = 'webfonts';
	$hook              = 'wp_enqueue_scripts';
	if ( did_action( 'wp_enqueue_scripts' ) ) {
		$stylesheet_handle = 'webfonts-footer';
		$hook              = 'wp_print_footer_scripts';
	}

	add_action(
		$hook,
		function() use ( $stylesheet_handle, $fonts ) {
			// Generate the styles.
			$styles = wp_webfonts_collection_generate_styles( $fonts );

			// Enqueue the stylesheet.
			wp_register_style( $stylesheet_handle, '' );
			wp_enqueue_style( $stylesheet_handle );

			// Add the styles to the stylesheet.
			wp_add_inline_style( $stylesheet_handle, $styles );
		}
	);
}

/**
 * Generate styles for a webfonts collection.
 *
 * @since 5.9.0
 *
 * @param array $fonts An array of webfonts.
 *
 * @return string The generated styles.
 */
function wp_webfonts_collection_generate_styles( $fonts ) {
	$styles = '';
	foreach ( $fonts as $font ) {
		$styles .= wp_webfont_generate_styles( $font );

		// Add preconnect links for external webfonts.
		_wp_webfont_add_preconnect_links( $font );
	}
	return $styles;
}

/**
 * Generate styles for a webfont.
 *
 * @since 5.9.0
 *
 * @param array $params The webfont parameters.
 *
 * @return string The generated styles.
 */
function wp_webfont_generate_styles( $params ) {
	// Get the array of providers.
	$providers = wp_get_webfont_providers();

	// Fallback to local provider if none is specified.
	$provider_id = isset( $params['provider'] ) ? $params['provider'] : 'local';
	if ( ! isset( $providers[ $provider_id ] ) ) {
		return '';
	}
	$provider = $providers[ $provider_id ];

	// Set the $params to the object.
	$provider->set_params( $params );

	// Get the CSS.
	return $provider->get_css();
}

/**
 * Add preconnect links to <head> for enqueued webfonts.
 *
 * @since 5.9.0
 *
 * @param array $params The webfont parameters.
 *
 * @return void
 */
function _wp_webfont_add_preconnect_links( $params ) {

	$provider_id = isset( $params['provider'] ) ? $params['provider'] : 'local';
	if ( ! isset( $providers[ $provider_id ] ) ) {
		return;
	}
	$provider = $providers[ $provider_id ];
	$provider->set_params( $params );

	// Store a static var to avoid adding the same preconnect links multiple times.
	static $preconnect_urls_added_from_api = array();

	// Add preconnect links.
	add_action(
		'wp_head',
		function() use ( $provider, &$preconnect_urls_added_from_api ) {

			// Early exit if the provider has already added preconnect links.
			if ( in_array( $provider->get_id(), $preconnect_urls_added_from_api ) ) {
				return;
			}

			// Add the preconnect links.
			$preconnect_urls = $provider->get_preconnect_urls();
			foreach ( $preconnect_urls as $preconnection ) {
				echo '<link rel="preconnect"';
				foreach ( $preconnection as $key => $value ) {
					if ( 'href' === $key ) {
						echo ' href="' . esc_url( $value ) . '"';
					} elseif ( true === $value || false === $value ) {
						echo $value ? ' ' . esc_attr( $key ) : '';
					} else {
						echo ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
					}
				}
				echo '>' . "\n";
			}
			$preconnect_urls_added_from_api[] = $provider->get_id();
		}
	);
}

/**
 * Register a webfont provider.
 *
 * @since 5.9.0
 *
 * @param string $id The provider ID.
 * @param string $class The provider class name.
 */
function wp_register_webfont_provider( $id, $class ) {
	global $wp_webfonts_providers;
	if ( ! $wp_webfonts_providers ) {
		$wp_webfonts_providers = array();
	}
	$wp_webfonts_providers[ $id ] = $class;
}

/**
 * Get webfonts providers.
 *
 * @since 5.9.0
 *
 * @return array
 */
function wp_get_webfont_providers() {
	global $wp_webfonts_providers;
	if ( ! $wp_webfonts_providers ) {
		$wp_webfonts_providers = array(
			'local'  => 'WP_Fonts_Provider_Local',
			'google' => 'WP_Fonts_Provider_Google',
		);
	}

	$providers = array();
	foreach ( $wp_webfonts_providers as $id => $class_name ) {
		if ( ! class_exists( $class_name ) ) {
			continue;
		}
		$providers[ $id ] = new $class_name();
	}

	/**
	 * Filters the list of registered webfont providers.
	 *
	 * @since 5.9.0
	 *
	 * @param array $wp_webfonts_providers An array of registered webfont providers.
	 *
	 * @return array
	 */
	return apply_filters( 'wp_webfonts_providers', $providers );
}
