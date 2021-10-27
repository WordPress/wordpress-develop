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
 *
 * @since 5.9.0
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
}
