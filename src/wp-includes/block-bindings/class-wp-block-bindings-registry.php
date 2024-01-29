<?php
/**
 * Block Bindings API: WP_Block_Bindings_Registry class.
 *
 * Supports overriding content in blocks by connecting them to different sources.
 *
 * @package WordPress
 * @subpackage Block Bindings
 * @since 6.5.0
 */

/**
 * Core class used for interacting with block binding sources.
 *
 *  @since 6.5.0
 */
final class WP_Block_Bindings_Registry {

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
	 * Registers a new block binding source.
	 *
	 * Sources are used to override block's original attributes with a value
	 * coming from the source. Once a source is registered, it can be used by a
	 * block by setting its `metadata.bindings` attribute to a value that refers
	 * to the source.
	 *
	 * @since 6.5.0
	 *
	 * @param string   $source_name       The name of the source.
	 * @param array    $source_properties {
	 *     The array of arguments that are used to register a source. We use an array so that we can easily extend
	 *     the API to pass additional arguments in the future. For now, it should be comprised of two elements:
	 *
	 *     @type string $label                The label of the source.
	 *     @type callback $get_value_callback A callback executed when the source is processed during block rendering.
	 *                                        The callback should have the following signature:
	 *
	 *                                        `function (object $source_args, object $block_instance, string $attribute_name): mixed`
	 *                                            - @param object $source_args: Object containing source arguments used to look up the override value, i.e. {"key": "foo"}.
	 *                                            - @param object $block_instance: The block instance.
	 *                                            - @param string $attribute_name: The name of an attribute used to retrieve an override value from the block context.
	 *                                        The callback has a mixed return type; it may return a string to override the block's original value, null, false to remove an attribute, etc.
	 * }
	 * @return boolean Whether the registration was successful.
	 */
	public function register( $source_name, array $source_properties ) {
		if ( ! isset( $this->sources[ $source_name ] ) ) {
			$this->sources[ $source_name ] = $source_properties;
			return true;
		}
		return false;
	}

	/**
	 * Retrieves the list of all registered block bindings sources.
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
	 * @return array|null The registered block binding source, or `null` if it is not registered.
	 */
	public function get_registered( $source_name ) {
		if ( ! $this->is_registered( $source_name ) ) {
			return null;
		}

		return $this->sources[ $source_name ];
	}

	/**
	 * Checks if a block binding source is registered.
	 *
	 * @since 6.5.0
	 *
	 * @param string $source_name The name of the source.
	 * @return bool `true` if the block binding source is registered, `false` otherwise.
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
	 * @return WP_Block_Bindings_Registry The main instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
