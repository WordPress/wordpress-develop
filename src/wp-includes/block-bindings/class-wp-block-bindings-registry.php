<?php
/**
 * Block Bindings API: WP_Block_Bindings_Registry class.
 *
 * Support for overriding content in blocks by connecting them to different sources.
 *
 * @package WordPress
 * @subpackage Block Bindings
 */

/**
 * Core class used to define supported blocks, register sources, and populate HTML with content from those sources.
 *
 *  @since 6.5.0
 */
class WP_Block_Bindings_Registry {

	/**
	 * Holds the registered block bindings sources, keyed by source identifier.
	 *
	 * @since 6.5.0
	 *
	 * @var array
	 */
	private $sources = array();

	/**
	 * Container for the main instance of the class.
	 *
	 * @since 6.5.0
	 * @var WP_Block_Bindings_Registry|null
	 */
	private static $instance = null;

	/**
	 * Function to register a new block binding source.
	 *
	 * Sources are used to override block's original attributes with a value
	 * coming from the source. Once a source is registered, it can be used by a
	 * block by setting its `metadata.bindings` attribute to a value that refers
	 * to the source.
	 *
	 * @since 6.5.0
	 *
	 * @param string   $source_name   The name of the source.
	 * @param array    $source_properties   The array of arguments that are used to register a source. The array has two elements:
	 *                                1. string   $label        The label of the source.
	 *                                2. callback $apply        A callback
	 *                                executed when the source is processed during
	 *                                block rendering. The callback should have the
	 *                                following signature:
	 *
	 *                                  `function (object $source_attrs, object $block_instance, string $attribute_name): string`
	 *                                          - @param object $source_attrs: Object containing source ID used to look up the override value, i.e. {"value": "{ID}"}.
	 *                                          - @param object $block_instance: The block instance.
	 *                                          - @param string $attribute_name: The name of an attribute used to retrieve an override value from the block context.
	 *                                 The callback should return a string that will be used to override the block's original value.
	 *
	 * @return void
	 */
	public function register( $source_name, array $source_properties ) {
		$this->sources[ $source_name ] = $source_properties;
	}

	/**
	 * Retrieves the list of registered block bindings sources.
	 *
	 * @since 6.5.0
	 *
	 * @return array The array of registered sources.
	 */
	public function get_all_registered() {
		return $this->sources;
	}

	/**
	 * Retrieves a registered block bindings source.
	 *
	 * @since 6.5.0
	 *
	 * @param string $source_name The name of the source.
	 * @return bool True if the source is registered, false otherwise.
	 */
	public function get_registered( $source_name ) {
		if ( ! $this->is_registered( $source_name ) ) {
			return null;
		}

		return $this->sources[ $source_name ];
	}

	/**
	 * Checks if a block source is registered.
	 *
	 * @since 6.5.0
	 *
	 * @param string $source_name The name of the source.
	 * @return bool True if the source is registered, false otherwise.
	 */
	public function is_registered( $source_name ) {
		return isset( $this->sources[ $source_name ] );
	}

	/**
	 * Utility method to retrieve the main instance of the class.
	 *
	 * The instance will be created if it does not exist yet.
	 *
	 * @since 6.5.0
	 *
	 * @return WP_Block_Bindings_Registry The WP_Block_Bindings_Registry instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
