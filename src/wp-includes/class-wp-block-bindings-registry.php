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
 * Core class used for interacting with block bindings sources.
 *
 *  @since 6.5.0
 */
final class WP_Block_Bindings_Registry {

	/**
	 * Holds the registered block bindings sources, keyed by source identifier.
	 *
	 * @since 6.5.0
	 * @var WP_Block_Bindings_Source[]
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
	 * Allowed block that can use the block bindings API.
	 *
	 * @since 6.5.0
	 * @var WP_Block_Bindings_Registry[]
	 */
	public $allowed_blocks = array(
		'core/paragraph' => array( 'content' ),
		'core/heading'   => array( 'content' ),
		'core/image'     => array( 'url', 'title', 'alt' ),
		'core/button'    => array( 'url', 'text', 'linkTarget', 'rel' ),
	);

	/**
	 * Registers a new block bindings source.
	 *
	 * This is a low-level method. For most use cases, it is recommended to use
	 * the `register_block_bindings_source()` function instead.
	 *
	 * @see register_block_bindings_source()
	 *
	 * Sources are used to override block's original attributes with a value
	 * coming from the source. Once a source is registered, it can be used by a
	 * block by setting its `metadata.bindings` attribute to a value that refers
	 * to the source.
	 *
	 * @since 6.5.0
	 *
	 * @param string   $source_name       The name of the source. It must be a string containing a namespace prefix, i.e.
	 *                                    `my-plugin/my-custom-source`. It must only contain lowercase alphanumeric
	 *                                    characters, the forward slash `/` and dashes.
	 * @param array    $source_properties {
	 *     The array of arguments that are used to register a source.
	 *
	 *     @type string   $label              The label of the source.
	 *     @type callback $get_value_callback A callback executed when the source is processed during block rendering.
	 *                                        The callback should have the following signature:
	 *
	 *                                        `function ($source_args, $block_instance,$attribute_name): mixed`
	 *                                            - @param array    $source_args    Array containing source arguments
	 *                                                                              used to look up the override value,
	 *                                                                              i.e. {"key": "foo"}.
	 *                                            - @param WP_Block $block_instance The block instance.
	 *                                            - @param string   $attribute_name The name of the target attribute.
	 *                                        The callback has a mixed return type; it may return a string to override
	 *                                        the block's original value, null, false to remove an attribute, etc.
	 * }
	 * @return WP_Block_Bindings_Source|false Source when the registration was successful, or `false` on failure.
	 */
	public function register( string $source_name, array $source_properties ) {
		if ( ! is_string( $source_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Block bindings source name must be a string.' ),
				'6.5.0'
			);
			return false;
		}

		if ( preg_match( '/[A-Z]+/', $source_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Block bindings source names must not contain uppercase characters.' ),
				'6.5.0'
			);
			return false;
		}

		$name_matcher = '/^[a-z0-9-]+\/[a-z0-9-]+$/';
		if ( ! preg_match( $name_matcher, $source_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Block bindings source names must contain a namespace prefix. Example: my-plugin/my-custom-source' ),
				'6.5.0'
			);
			return false;
		}

		if ( $this->is_registered( $source_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Block bindings source name. */
				sprintf( __( 'Block bindings source "%s" already registered.' ), $source_name ),
				'6.5.0'
			);
			return false;
		}

		/* Validate that the source properties contain the label */
		if ( ! isset( $source_properties['label'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'The $source_properties must contain a "label".' ),
				'6.5.0'
			);
			return false;
		}

		/* Validate that the source properties contain the get_value_callback */
		if ( ! isset( $source_properties['get_value_callback'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'The $source_properties must contain a "get_value_callback".' ),
				'6.5.0'
			);
			return false;
		}

		/* Validate that the get_value_callback is a valid callback */
		if ( ! is_callable( $source_properties['get_value_callback'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'The "get_value_callback" parameter must be a valid callback.' ),
				'6.5.0'
			);
			return false;
		}

		$source = new WP_Block_Bindings_Source(
			$source_name,
			$source_properties
		);

		$this->sources[ $source_name ] = $source;

		return $source;
	}

	/**
	 * Unregisters a block bindings source.
	 *
	 * @since 6.5.0
	 *
	 * @param string $source_name Block bindings source name including namespace.
	 * @return WP_Block_Bindings_Source|false The unregistered block bindings source on success and `false` otherwise.
	 */
	public function unregister( string $source_name ) {
		if ( ! $this->is_registered( $source_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Block bindings source name. */
				sprintf( __( 'Block binding "%s" not found.' ), $source_name ),
				'6.5.0'
			);
			return false;
		}

		$unregistered_source = $this->sources[ $source_name ];
		unset( $this->sources[ $source_name ] );

		return $unregistered_source;
	}

	/**
	 * Retrieves the list of all registered block bindings sources.
	 *
	 * @since 6.5.0
	 *
	 * @return WP_Block_Bindings_Source[] The array of registered sources.
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
	 * @return WP_Block_Bindings_Source|null The registered block bindings source, or `null` if it is not registered.
	 */
	public function get_registered( string $source_name ) {
		if ( ! $this->is_registered( $source_name ) ) {
			return null;
		}

		return $this->sources[ $source_name ];
	}

	/**
	 * Checks if a block bindings source is registered.
	 *
	 * @since 6.5.0
	 *
	 * @param string $source_name The name of the source.
	 * @return bool `true` if the block bindings source is registered, `false` otherwise.
	 */
	public function is_registered( $source_name ) {
		return isset( $this->sources[ $source_name ] );
	}

	/**
	 * Wakeup magic method.
	 *
	 * @since 6.5.0
	 */
	public function __wakeup() {
		if ( ! $this->sources ) {
			return;
		}
		if ( ! is_array( $this->sources ) ) {
			throw new UnexpectedValueException();
		}
		foreach ( $this->sources as $value ) {
			if ( ! $value instanceof WP_Block_Bindings_Source ) {
				throw new UnexpectedValueException();
			}
		}
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
