<?php
/**
 * Webfonts API: Provider Registry
 *
 * @package WordPress
 * @subpackage Webfonts
 * @since 5.9.0
 */

/**
 * Provider Registry.
 *
 * This registry exists to handle all providers.
 *
 * It handles the following within the API:
 *  - loads the bundled provider files into memory;
 *  - registers each provider with the API by:
 *       1. creating an instance (object);
 *       2. storing it in-memory (by its unique provider ID) for use with the API;
 *  - handles generating the linked resources `<link>` for all providers.
 */
class WP_Webfonts_Provider_Registry {

	/**
	 * An in-memory storage container that holds all registered providers
	 * for use within the API.
	 *
	 * Keyed by the respective provider's unique provider ID:
	 *
	 *      @type string $provider_id => @type WP_Webfonts_Provider Provider instance.
	 *
	 * @since 5.9.0
	 *
	 * @var WP_Webfonts_Provider[]
	 */
	private $registered = array();

	/**
	 * Gets all registered providers.
	 *
	 * @since 5.9.0
	 *
	 * @return WP_Webfonts_Provider[] All registered providers each keyed by their unique provider ID.
	 */
	public function get_all_registered() {
		return $this->registered;
	}

	/**
	 * Initializes the registry.
	 *
	 * @since 5.9.0
	 */
	public function init() {
		$this->register_core_providers();
	}

	/**
	 * Registers the core providers.
	 *
	 * Loads each bundled provider's file into memory and
	 * then registers it for use with the API.
	 *
	 * @since 5.9.0
	 */
	private function register_core_providers() {
		// Load the abstract class into memory.
		require_once __DIR__ . '/providers/class-wp-webfonts-provider.php';

		// Register the Google Provider.
		require_once __DIR__ . '/providers/class-wp-webfonts-google-provider.php';
		$this->register( WP_Webfonts_Google_Provider::class );

		// Register the Local Provider.
		require_once __DIR__ . '/providers/class-wp-webfonts-local-provider.php';
		$this->register( WP_Webfonts_Local_Provider::class );
	}

	/**
	 * Registers the given provider.
	 *
	 * @since 5.9.0
	 *
	 * @param string $classname The provider's class name.
	 * @return bool True when registered. False when provider does not exist.
	 */
	public function register( $classname ) {
		// If the class does not exist in memory, or is not a subclass of WP_Webfonts_Provider, bail out.
		if ( ! class_exists( $classname ) || ! is_subclass_of( $classname, 'WP_Webfonts_Provider' ) ) {
			return false;
		}

		/*
		 * Create an instance of the provider.
		 * This API uses one instance of each provider.
		 */
		$provider = new $classname;
		$id       = $provider->get_id();

		// Store the provider's instance by its unique provider ID.
		if ( ! isset( $this->registered[ $id ] ) ) {
			$this->registered[ $id ] = $provider;
		}

		return true;
	}

	/**
	 * Gets the HTML `<link>` for each provider.
	 *
	 * @since 5.9.0
	 *
	 * @return string HTML links for each provider.
	 */
	public function get_links() {
		/*
		 * Store each `<link>` by its provider ID. Why?
		 * To ensure only one link is created per provider.
		 */
		static $links = array();

		foreach ( $this->get_all_registered() as $provider_id => $provider ) {
			// Skip if the provider already added the link.
			if ( isset( $links[ $provider_id ] ) ) {
				continue;
			}

			$links[ $provider_id ] = $this->generate_links( $provider );
		}

		// Combine `<link>` elements and return them as a string.
		return implode( '', $links );
	}

	/**
	 * Generate the `<link> element(s) for the given provider.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_Webfonts_Provider $provider Instance of the provider.
	 * @return string The `<link>` element(s).
	 */
	private function generate_links( WP_Webfonts_Provider $provider ) {
		$link_attributes = $provider->get_link_attributes();

		/*
		 * Bail out if there are no attributes for this provider
		 * (i.e. no `<link>` is needed).
		 */
		if ( ! is_array( $link_attributes ) || empty( $link_attributes ) ) {
			return '';
		}

		/*
		 * This provider needs multiple `<link>` elements.
		 * Loop through each array and pass its attributes
		 * to create each of its `<link>` elements.
		 */
		if ( is_array( current( $link_attributes ) ) ) {
			$links = '';
			foreach ( $link_attributes as $attributes ) {
				$links .= $this->create_link_element( $attributes );
			}

			return $links;
		}

		/*
		 * This provider needs one `<link>` element.
		 * Pass its attributes to create its `<link>` element.
		 */
		return $this->create_link_element( $link_attributes );
	}

	/**
	 * Creates the `<link>` element and populates with the given attributes.
	 *
	 * @since 5.9.0
	 *
	 * @param string[] $attributes An array of attributes => values.
	 * @return string The `<link>` element.
	 */
	private function create_link_element( array $attributes ) {
		$link = '';

		foreach ( $attributes as $attribute => $value ) {
			// Checks if attribute is a nonempty string. If no, skip it.
			if ( ! is_string( $attribute ) || '' === $attribute ) {
				continue;
			}

			if ( 'href' === $attribute ) {
				$link .= ' href="' . esc_url( $value ) . '"';
			} elseif ( is_bool( $value ) ) {
				$link .= $value
					? ' ' . esc_attr( $attribute )
					: '';
			} else {
				$link .= ' ' . esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
			}
		}

		if ( '' === $link ) {
			return '';
		}

		return '<link rel="preconnect"' . $link . '>' . "\n";
	}
}
