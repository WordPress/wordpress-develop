<?php

class WP_Webfonts_Controller {

	/**
	 * Instance of the webfonts registry.
	 *
	 * @var WP_Webfonts_Registry
	 */
	private $webfonts_registry;

	/**
	 * Instance of the provider registry.
	 *
	 * @var WP_Webfonts_Provider_Registry
	 */
	private $providers_registry;

	/**
	 * Stylesheet handle.
	 *
	 * @var string
	 */
	private $stylesheet_handle = '';

	/**
	 * Create the controller.
	 *
	 * @param WP_Webfonts_Registry          $webfonts_registry Instance of the webfonts registry.
	 * @param WP_Webfonts_Provider_Registry $provider_registry Instance of the providers registry.
	 */
	public function __construct(
		WP_Webfonts_Registry $webfonts_registry,
		WP_Webfonts_Provider_Registry $provider_registry
	) {
		$this->webfonts_registry  = $webfonts_registry;
		$this->providers_registry = $provider_registry;
	}

	/**
	 * Initializes the controller.
	 *
	 * @since 5.9.0
	 */
	public function init() {
		$this->providers_registry->init();

		// Register callback to generate and enqueue styles.
		if ( did_action( 'wp_enqueue_scripts' ) ) {
			$this->stylesheet_handle = 'webfonts-footer';
			$hook                    = 'wp_print_footer_scripts';
		} else {
			$this->stylesheet_handle = 'webfonts';
			$hook                    = 'wp_enqueue_scripts';
		}
		add_action( $hook, array( $this, 'generate_and_enqueue_styles' ) );
	}

	/**
	 * Registers a webfont collection.
	 *
	 * @since 5.9.0
	 *
	 * @param string[][] $webfonts Webfonts to be registered.
	 */
	public function register_webfonts( array $webfonts ) {
		// Bail out if no webfonts collection was injected.
		if ( empty( $webfonts ) ) {
			return;
		}

		array_walk( $webfonts, array( $this, 'register_webfont' ) );
	}

	/**
	 * Registers the given webfont if its schema is valid.
	 *
	 * @since 5.9.0
	 *
	 * @param string[] $webfont Webfont definition.
	 */
	public function register_webfont( array $webfont ) {
		$this->webfonts_registry->register( $webfont );
	}

	/**
	 * Gets the registered webfonts.
	 *
	 * @since 5.9.0
	 *
	 * @return string[][] Registered webfonts.
	 */
	public function get_webfonts() {
		return $this->webfonts_registry->get_registry();
	}

	/**
	 * Gets the registered webfonts for the given provider.
	 *
	 * @since 5.9.0
	 *
	 * @param string $provider_id Provider ID to fetch.
	 * @return string[][] Registered webfonts.
	 */
	public function get_webfonts_by_provider( $provider_id ) {
		return $this->webfonts_registry->get_by_provider( $provider_id );
	}

	/**
	 * Gets the registered webfonts for the given font-family.
	 *
	 * @since 5.9.0
	 *
	 * @param string $font_family Family font to fetch.
	 * @return string[][] Registered webfonts.
	 */
	public function get_webfonts_by_font_family( $font_family ) {
		return $this->webfonts_registry->get_by_font_family( $font_family );
	}

	/**
	 * Gets the registered providers.
	 *
	 * @since 5.9.0
	 *
	 * @return WP_Webfonts_Provider[] Registered providers.
	 */
	public function get_registered_providers() {
		return $this->providers_registry->get_registry();
	}

	/**
	 * Registers the given provider.
	 *
	 * @since 5.9.0
	 *
	 * @param string $classname The provider class name.
	 * @return bool True when registered. False when provider does not exist.
	 */
	public function register_provider( $classname ) {
		return $this->providers_registry->register( $classname );
	}

	/**
	 * Generate and enqueue webfonts styles.
	 *
	 * @since 5.9.0
	 */
	public function generate_and_enqueue_styles() {
		// Generate the styles.
		$styles = $this->generate_styles();

		// Enqueue the stylesheet.
		wp_register_style( $this->stylesheet_handle, '' );
		wp_enqueue_style( $this->stylesheet_handle );

		// Add the styles to the stylesheet.
		wp_add_inline_style( $this->stylesheet_handle, $styles );
	}

	private function generate_styles() {
		$styles = '';
		foreach ( $this->get_registered_providers() as $provider_id => $provider ) {
			$registered_webfonts = $this->webfonts_registry->get_by_provider( $provider_id );

			if ( empty( $registered_webfonts ) ) {
				continue;
			}

			add_action( 'wp_head', array( $this, 'add_preconnect_links' ) );

			$provider->set_webfonts( $registered_webfonts );
			$styles .= $provider->get_css();
		}
		return $styles;
	}

	/**
	 * Add preconnect links to <head> for enqueued webfonts.
	 *
	 * @since 5.9.0
	 */
	public function add_preconnect_links() {
		echo $this->providers_registry->generate_preconnect_links();
	}
}
