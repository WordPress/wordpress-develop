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
	public function __construct( WP_Webfonts_Registry $webfonts_registry, WP_Webfonts_Provider_Registry $provider_registry ) {
		$this->webfonts_registry  = $webfonts_registry;
		$this->providers_registry = $provider_registry;
	}

	/**
	 * Initializes the controller.
	 *
	 * @since 5.9.0
	 */
	public function init() {
		$this->provider->init();

		// Register enqueue callback to
		if ( did_action( 'wp_enqueue_scripts' ) ) {
			$this->stylesheet_handle = 'webfonts-footer';
			$hook                    = 'wp_print_footer_scripts';
		} else {
			$this->stylesheet_handle = 'webfonts';
			$hook                    = 'wp_enqueue_scripts';
		}
		add_action( $hook, array( $this, 'enqueue' ) );
	}

	/**
	 * Registers a webfont collection.
	 *
	 * @since 5.9.0
	 *
	 * @param array[] $webfonts Webfonts to be registered.
	 */
	public function register_webfonts( array $webfonts ) {
		array_walk( $webfonts, array( $this, 'register_webfont' ) );
	}


	/**
	 * Registers a webfont.
	 *
	 * @param string[] $webfont Webfont definition.
	 */
	public function register_webfont( array $webfont ) {
		$registration_key = $this->webfonts_registry->register( $webfont );
		if ( '' === $registration_key ) {
			return;
		}

		// Register the webfont's registration key to its provider.
		$this->providers_registry->register_webfont( $webfont['provider'], $registration_key );
	}

	public function get_registered_providers() {
		return $this->providers_registry->get_registry();
	}

	public function register_provider( $classname ) {
		return $this->providers_registry->register( $classname );
	}

	public function enqueue() {
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
