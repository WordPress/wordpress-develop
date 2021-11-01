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
 *
 * @since 5.9.0
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

		// Add resources hints.
		add_filter( 'wp_resource_hints', array( $this, 'get_resource_hints' ), 10, 2 );
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
		$styles    = '';
		$providers = $this->get_providers()->get_all_registered();
		foreach ( $providers as $provider_id => $provider ) {
			$registered_webfonts = $this->webfonts_registry->get_by_provider( $provider_id );

			if ( empty( $registered_webfonts ) ) {
				continue;
			}

			$provider->set_webfonts( $registered_webfonts );
			$styles .= $provider->get_css();
		}
		return $styles;
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

	/**
	 * Get the resource hints.
	 *
	 * @since 5.9.0
	 *
	 * @param array  $urls {
	 *     Array of resources and their attributes, or URLs to print for resource hints.
	 *
	 *     @type array|string ...$0 {
	 *         Array of resource attributes, or a URL string.
	 *
	 *         @type string $href        URL to include in resource hints. Required.
	 *         @type string $as          How the browser should treat the resource
	 *                                   (`script`, `style`, `image`, `document`, etc).
	 *         @type string $crossorigin Indicates the CORS policy of the specified resource.
	 *         @type float  $pr          Expected probability that the resource hint will be used.
	 *         @type string $type        Type of the resource (`text/html`, `text/css`, etc).
	 *     }
	 * }
	 * @param string $relation_type The relation type the URLs are printed for,
	 *                              e.g. 'preconnect' or 'prerender'.
	 *
	 * @return array URLs to print for resource hints.
	 */
	public function get_resource_hints( $urls, $relation_type ) {
		$providers = $this->get_providers()->get_all_registered();
		foreach ( $providers as $provider_id => $provider ) {
			$hints = $provider->get_resource_hints();
			foreach ( $hints as $relation => $relation_hints ) {
				if ( $relation !== $relation_type ) {
					continue;
				}
				foreach ( $relation_hints as $hint ) {
					$urls[] = $hint;
				}
			}
		}

		return $urls;
	}
}
