<?php
/**
 * Blocks API: WP_Block_Type_Registry class
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.0.0
 */

/**
 * Core class used for interacting with block types.
 *
 * @since 5.0.0
 */
#[AllowDynamicProperties]
final class WP_Block_Type_Registry {
	/**
	 * Registered block types, as `$name => $instance` pairs.
	 *
	 * @since 5.0.0
	 * @var WP_Block_Type[]
	 */
	private $registered_block_types = array();


	/**
	 * Registered block aliases, as `$alias_name => $instance` pairs.
	 *
	 * @since 6.6.0
	 * var WP_Block_Type[]
	 */
	private $registered_block_type_aliases = array();

	/**
	 * Container for the main instance of the class.
	 *
	 * @since 5.0.0
	 * @var WP_Block_Type_Registry|null
	 */
	private static $instance = null;

	/**
	 * Registers a block type.
	 *
	 * @since 5.0.0
	 *
	 * @see WP_Block_Type::__construct()
	 *
	 * @param string|WP_Block_Type $name Block type name including namespace, or alternatively
	 *                                   a complete WP_Block_Type instance. In case a WP_Block_Type
	 *                                   is provided, the $args parameter will be ignored.
	 * @param array                $args Optional. Array of block type arguments. Accepts any public property
	 *                                   of `WP_Block_Type`. See WP_Block_Type::__construct() for information
	 *                                   on accepted arguments. Default empty array.
	 * @return WP_Block_Type|false The registered block type on success, or false on failure.
	 */
	public function register( $name, $args = array() ) {
		$block_type = null;
		if ( $name instanceof WP_Block_Type ) {
			$block_type = $name;
			$name       = $block_type->name;
		}

		if ( ! is_string( $name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Block type names must be strings.' ),
				'5.0.0'
			);
			return false;
		}

		if ( preg_match( '/[A-Z]+/', $name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Block type names must not contain uppercase characters.' ),
				'5.0.0'
			);
			return false;
		}

		$name_matcher = '/^[a-z0-9-]+\/[a-z0-9-]+$/';
		if ( ! preg_match( $name_matcher, $name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Block type names must contain a namespace prefix. Example: my-plugin/my-custom-block-type' ),
				'5.0.0'
			);
			return false;
		}

		if ( $this->is_registered( $name ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Block name. */
				sprintf( __( 'Block type "%s" is already registered.' ), $name ),
				'5.0.0'
			);
			return false;
		}

		if ( ! $block_type ) {
			$block_type = new WP_Block_Type( $name, $args );
		}

		// Register aliases for the block type.
		$aliases = $block_type->get_aliases();
		if ( ! empty( $aliases ) ) {
			foreach ( $aliases as $alias_name => $alias_args ) {
				if ( ! isset( $this->registered_block_type_aliases[ $alias_name ] ) ) {
					// Add the alias to the alias registry.
					$alias_block_type                                   = new WP_Block_Type( $alias_name, array_merge( $args, $alias_args ) );
					$alias_block_type->alias_of                         = $name;
					$this->registered_block_type_aliases[ $alias_name ] = $alias_block_type;
				}
			}
		}

		$this->registered_block_types[ $name ] = $block_type;

		return $block_type;
	}

	/**
	 * Unregisters a block type.
	 *
	 * @since 5.0.0
	 *
	 * @param string|WP_Block_Type $name Block type name including namespace, or alternatively
	 *                                   a complete WP_Block_Type instance.
	 * @return WP_Block_Type|false The unregistered block type on success, or false on failure.
	 */
	public function unregister( $name ) {
		if ( $name instanceof WP_Block_Type ) {
			$name = $name->name;
		}

		if ( ! $this->is_registered( $name ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Block name. */
				sprintf( __( 'Block type "%s" is not registered.' ), $name ),
				'5.0.0'
			);
			return false;
		}

		$unregistered_block_type = $this->registered_block_types[ $name ];
		unset( $this->registered_block_types[ $name ] );

		return $unregistered_block_type;
	}

	/**
	 * Retrieves a registered block type.
	 *
	 * @since 5.0.0
	 *
	 * @param string $name Block type name including namespace.
	 * @return WP_Block_Type|null The registered block type, or null if it is not registered.
	 */
	public function get_registered( $name ) {
		if ( ! $this->is_registered( $name ) ) {
			return null;
		}

		return $this->registered_block_types[ $name ];
	}

	/**
	 * Retrieves all registered block types.
	 *
	 * @since 5.0.0
	 *
	 * @return WP_Block_Type[] Associative array of `$block_type_name => $block_type` pairs.
	 */
	public function get_all_registered() {
		return $this->registered_block_types;
	}

	/**
	 * Checks if a block type is registered.
	 *
	 * @since 5.0.0
	 *
	 * @param string $name Block type name including namespace.
	 * @return bool True if the block type is registered, false otherwise.
	 */
	public function is_registered( $name ) {
		return isset( $this->registered_block_types[ $name ] );
	}

	public function __wakeup() {
		if ( ! $this->registered_block_types ) {
			return;
		}
		if ( ! is_array( $this->registered_block_types ) ) {
			throw new UnexpectedValueException();
		}
		foreach ( $this->registered_block_types as $value ) {
			if ( ! $value instanceof WP_Block_Type ) {
				throw new UnexpectedValueException();
			}
		}
	}

	/**
	 * Utility method to retrieve the main instance of the class.
	 *
	 * The instance will be created if it does not exist yet.
	 *
	 * @since 5.0.0
	 *
	 * @return WP_Block_Type_Registry The main instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
