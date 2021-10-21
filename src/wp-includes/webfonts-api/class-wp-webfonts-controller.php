<?php
/**
 * Webfonts API: Webfonts Controller
 *
 * @package WordPress
 * @subpackage Webfonts
 * @since 5.9.0
 */

/**
 * Webfonts Controller.
 *
 * Receives the incoming requests and handles the processing.
 */
class WP_Webfonts_Controller {

	/**
	 * Instance of the webfonts registry.
	 *
	 * @since 5.9.0
	 *
	 * @var WP_Webfonts_Registry
	 */
	private $webfonts_registry;

	/**
	 * Instance of the provider's registry.
	 *
	 * @since 5.9.0
	 *
	 * @var WP_Webfonts_Provider_Registry
	 */
	private $providers;

	/**
	 * Stylesheet handle.
	 *
	 * @since 5.9.0
	 *
	 * @var string
	 */
	private $stylesheet_handle = '';

	/**
	 * Create the controller.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_Webfonts_Registry          $webfonts_registry Instance of the webfonts registry.
	 * @param WP_Webfonts_Provider_Registry $provider_registry Instance of the providers registry.
	 */
	public function __construct(
		WP_Webfonts_Registry $webfonts_registry,
		WP_Webfonts_Provider_Registry $provider_registry
	) {
		$this->webfonts_registry = $webfonts_registry;
		$this->providers         = $provider_registry;
	}

	/**
	 * Initializes the controller.
	 *
	 * @since 5.9.0
	 */
	public function init() {
		$this->providers->init();

		// Register callback to generate and enqueue styles.
		if ( did_action( 'wp_enqueue_scripts' ) ) {
			$this->stylesheet_handle = 'webfonts-footer';
			$hook                    = 'wp_print_footer_scripts';
		} else {
			$this->stylesheet_handle = 'webfonts';
			$hook                    = 'wp_enqueue_scripts';
		}
		add_action( $hook, array( $this, 'generate_and_enqueue_styles' ) );

		// Enqueue webfonts in the block editor.
		add_action( 'admin_init', array( $this, 'generate_and_enqueue_editor_styles' ) );
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
	 * @return array[] Registered webfonts.
	 */
	public function get_webfonts() {
		return $this->webfonts_registry->get_all_registered();
	}

	/**
	 * Gets the registered webfonts for the given provider organized by font-family.
	 *
	 * @since 5.9.0
	 *
	 * @param string $provider_id Provider ID to fetch.
	 * @return array[] Registered webfonts.
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
	 * @return array[] Registered webfonts.
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
		return $this->providers->get_all_registered();
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
		return $this->providers->register( $classname );
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

	/**
	 * Generate and enqueue editor styles.
	 *
	 * @since 5.9.0
	 */
	public function generate_and_enqueue_editor_styles() {
		wp_add_inline_style( 'wp-block-library', $this->generate_styles() );
	}

	/**
	 * Generate styles for webfonts.
	 *
	 * @since 5.9.0
	 *
	 * @return string $styles Generated styles.
	 */
	private function generate_styles() {
		$styles = '';
		foreach ( $this->get_registered_providers() as $provider_id => $provider ) {
			$registered_webfonts = $this->webfonts_registry->get_by_provider( $provider_id );

			if ( empty( $registered_webfonts ) ) {
				continue;
			}

			add_action( 'wp_head', array( $this, 'render_links' ) );

			$provider->set_webfonts( $registered_webfonts );
			$styles .= $provider->get_css();
		}
		return $styles;
	}

	/**
	 * Renders the HTML `<link>` for each provider into `<head>` for enqueued webfonts.
	 *
	 * @since 5.9.0
	 */
	public function render_links() {
		echo $this->providers->get_links();
	}

	/**
	 * Get the webfonts registry.
	 *
	 * @since 5.9.0
	 *
	 * @return WP_Webfonts_Registry
	 */
	public function get_webfonts_registry() {
		return $this->webfonts_registry;
	}

	/**
	 * Get the providers registry.
	 *
	 * @since 5.9.0
	 *
	 * @return WP_Webfonts_Provider_Registry
	 */
	public function get_providers() {
		return $this->providers;
	}
}
