<?php
/**
 * Abilities API
 *
 * Defines WP_Abilities_Registry class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 0.1.0
 */

declare( strict_types = 1 );

/**
 * Manages the registration and lookup of abilities.
 *
 * @since 0.1.0
 * @access private
 */
final class WP_Abilities_Registry {
	/**
	 * The singleton instance of the registry.
	 *
	 * @since 0.1.0
	 * @var ?self
	 */
	private static $instance = null;

	/**
	 * Holds the registered abilities.
	 *
	 * @since 0.1.0
	 * @var \WP_Ability[]
	 */
	private $registered_abilities = array();

	/**
	 * Registers a new ability.
	 *
	 * Do not use this method directly. Instead, use the `wp_register_ability()` function.
	 *
	 * @since 0.1.0
	 *
	 * @see wp_register_ability()
	 *
	 * @param string              $name The name of the ability. The name must be a string containing a namespace
	 *                                  prefix, i.e. `my-plugin/my-ability`. It can only contain lowercase
	 *                                  alphanumeric characters, dashes and the forward slash.
	 * @param array<string,mixed> $args An associative array of arguments for the ability. See wp_register_ability() for
	 *                                  details.
	 * @return ?\WP_Ability The registered ability instance on success, null on failure.
	 *
	 * @phpstan-param array{
	 *   label?: string,
	 *   description?: string,
	 *   category?: string,
	 *   execute_callback?: callable( mixed $input= ): (mixed|\WP_Error),
	 *   permission_callback?: callable( mixed $input= ): (bool|\WP_Error),
	 *   input_schema?: array<string,mixed>,
	 *   output_schema?: array<string,mixed>,
	 *   meta?: array{
	 *     annotations?: array<string,(bool|string)>,
	 *     show_in_rest?: bool,
	 *     ...<string, mixed>
	 *   },
	 *   ability_class?: class-string<\WP_Ability>,
	 *   ...<string, mixed>
	 * } $args
	 */
	public function register( string $name, array $args ): ?WP_Ability {
		if ( ! preg_match( '/^[a-z0-9-]+\/[a-z0-9-]+$/', $name ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__(
					'Ability name must be a string containing a namespace prefix, i.e. "my-plugin/my-ability". It can only contain lowercase alphanumeric characters, dashes and the forward slash.'
				),
				'0.1.0'
			);
			return null;
		}

		if ( $this->is_registered( $name ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Ability name. */
				esc_html( sprintf( __( 'Ability "%s" is already registered.' ), $name ) ),
				'0.1.0'
			);
			return null;
		}

		/**
		 * Filters the ability arguments before they are validated and used to instantiate the ability.
		 *
		 * @since 0.2.0
		 *
		 * @param array<string,mixed> $args The arguments used to instantiate the ability.
		 * @param string              $name The name of the ability, with its namespace.
		 */
		$args = apply_filters( 'register_ability_args', $args, $name );

		// Validate category exists if provided (will be validated as required in WP_Ability).
		if ( isset( $args['category'] ) ) {
			$category_registry = WP_Abilities_Category_Registry::get_instance();
			if ( ! $category_registry->is_registered( $args['category'] ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						/* translators: %1$s: ability category slug, %2$s: ability name */
						esc_html__( 'Ability category "%1$s" is not registered. Please register the category before assigning it to ability "%2$s".' ),
						esc_attr( $args['category'] ),
						esc_attr( $name )
					),
					'0.3.0'
				);
				return null;
			}
		}

		// The class is only used to instantiate the ability, and is not a property of the ability itself.
		if ( isset( $args['ability_class'] ) && ! is_a( $args['ability_class'], WP_Ability::class, true ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The ability args should provide a valid `ability_class` that extends WP_Ability.' ),
				'0.1.0'
			);
			return null;
		}

		/** @var class-string<\WP_Ability> */
		$ability_class = $args['ability_class'] ?? WP_Ability::class;
		unset( $args['ability_class'] );

		try {
			// WP_Ability::prepare_properties() will throw an exception if the properties are invalid.
			$ability = new $ability_class( $name, $args );
		} catch ( \InvalidArgumentException $e ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html( $e->getMessage() ),
				'0.1.0'
			);
			return null;
		}

		$this->registered_abilities[ $name ] = $ability;
		return $ability;
	}

	/**
	 * Unregisters an ability.
	 *
	 * Do not use this method directly. Instead, use the `wp_unregister_ability()` function.
	 *
	 * @since 0.1.0
	 *
	 * @see wp_unregister_ability()
	 *
	 * @param string $name The name of the registered ability, with its namespace.
	 * @return ?\WP_Ability The unregistered ability instance on success, null on failure.
	 */
	public function unregister( string $name ): ?WP_Ability {
		if ( ! $this->is_registered( $name ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Ability name. */
				sprintf( esc_html__( 'Ability "%s" not found.' ), esc_attr( $name ) ),
				'0.1.0'
			);
			return null;
		}

		$unregistered_ability = $this->registered_abilities[ $name ];
		unset( $this->registered_abilities[ $name ] );

		return $unregistered_ability;
	}

	/**
	 * Retrieves the list of all registered abilities.
	 *
	 * Do not use this method directly. Instead, use the `wp_get_abilities()` function.
	 *
	 * @since 0.1.0
	 *
	 * @see wp_get_abilities()
	 *
	 * @return \WP_Ability[] The array of registered abilities.
	 */
	public function get_all_registered(): array {
		return $this->registered_abilities;
	}

	/**
	 * Checks if an ability is registered.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name The name of the registered ability, with its namespace.
	 * @return bool True if the ability is registered, false otherwise.
	 */
	public function is_registered( string $name ): bool {
		return isset( $this->registered_abilities[ $name ] );
	}

	/**
	 * Retrieves a registered ability.
	 *
	 * Do not use this method directly. Instead, use the `wp_get_ability()` function.
	 *
	 * @since 0.1.0
	 *
	 * @see wp_get_ability()
	 *
	 * @param string $name The name of the registered ability, with its namespace.
	 * @return ?\WP_Ability The registered ability instance, or null if it is not registered.
	 */
	public function get_registered( string $name ): ?WP_Ability {
		if ( ! $this->is_registered( $name ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Ability name. */
				sprintf( esc_html__( 'Ability "%s" not found.' ), esc_attr( $name ) ),
				'0.1.0'
			);
			return null;
		}
		return $this->registered_abilities[ $name ];
	}

	/**
	 * Utility method to retrieve the main instance of the registry class.
	 *
	 * The instance will be created if it does not exist yet.
	 *
	 * @since 0.1.0
	 *
	 * @return \WP_Abilities_Registry The main registry instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();

			// Ensure category registry is initialized first to allow categories to be registered
			// before abilities that depend on them.
			WP_Abilities_Category_Registry::get_instance();

			/**
			 * Fires when preparing abilities registry.
			 *
			 * Abilities should be created and register their hooks on this action rather
			 * than another action to ensure they're only loaded when needed.
			 *
			 * @since 0.1.0
			 *
			 * @param \WP_Abilities_Registry $instance Abilities registry object.
			 */
			do_action( 'abilities_api_init', self::$instance );
		}

		return self::$instance;
	}

	/**
	 * Wakeup magic method.
	 *
	 * @since 0.1.0
	 * @throws \UnexpectedValueException If any of the registered abilities is not an instance of WP_Ability.
	 */
	public function __wakeup(): void {
		foreach ( $this->registered_abilities as $ability ) {
			if ( ! $ability instanceof WP_Ability ) {
				throw new \UnexpectedValueException();
			}
		}
	}
}
