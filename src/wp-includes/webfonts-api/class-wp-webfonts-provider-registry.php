<?php

final class WP_Webfonts_Provider_Registry {

	/**
	 * Array of registered providers.
	 *
	 * @since 5.9.0
	 *
	 * @var WP_Webfonts_Provider[]
	 */
	private $registry = array();

	/**
	 * Gets the provider registry.
	 *
	 * @since 5.9.0
	 *
	 * @return WP_Webfonts_Provider[] Registered providers.
	 */
	public function get_registry() {
		return $this->registry;
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
	 * @param string $classname The provider class name.
	 * @return bool True when registered. False when provider does not exist.
	 */
	public function register( $classname ) {
		if ( ! class_exists( $classname ) ) {
			return '';
		}

		$provider = new $classname;
		$id       = $provider->get_id();

		if ( ! isset( $this->providers[ $id ] ) ) {
			$this->registry[ $id ] = $provider;
		}

		return $id;
	}

	public function get_preconnect_links() {
		// Store a static var to avoid adding the same preconnect links multiple times.
		static $generated = array();

		$links = '';

		foreach ( $this->registry as $provider_id => $provider ) {
			// Skip if the provider already added preconnect links.
			if ( isset( $generated[ $provider_id ] ) ) {
				continue;
			}

			$links .= $this->get_preconnect_link( $provider );

			$added[ $provider_id ] = true;
		}

		return $links;
	}

	private function get_preconnect_link( $provider ) {
		$link = '';

		foreach ( $provider->get_preconnect_urls() as $preconnection ) {
			$link .= '<link rel="preconnect"';

			foreach ( $preconnection as $key => $value ) {
				if ( 'href' === $key ) {
					$link .= ' href="' . esc_url( $value ) . '"';
				} elseif ( true === $value || false === $value ) {
					$link .= $value ? ' ' . esc_attr( $key ) : '';
				} else {
					$link .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
				}
			}
			$link .= '>' . PHP_EOL;
		}

		return $link;
	}
}
