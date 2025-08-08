<?php declare( strict_types = 1 );

/**
 * Abilities API
 *
 * Defines WP_Abilities_Registry class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 0.1.0
 */

/**
 * Manages the registration and lookup of abilities.
 *
 * @since 0.1.0
 * @access private
 */
final class WP_Abilities_Registry {
	/**
	 * Holds the registered abilities.
	 *
	 * @since 0.1.0
	 * @var WP_Ability[]
	 */
	private $registered_abilities = array();

	/**
	 * Container for the main instance of the class.
	 *
	 * @since 0.1.0
	 * @var ?WP_Abilities_Registry
	 */
	private static $instance = null;

	/**
	 * Registers a new ability.
	 *
	 * Do not use this method directly. Instead, use the `wp_register_ability()` function.
	 *
	 * @see wp_register_ability()
	 *
	 * @since 0.1.0
	 *
	 * @param string|WP_Ability $name       The name of the ability, or WP_Ability instance. The name must be a string
	 *                                      containing a namespace prefix, i.e. `my-plugin/my-ability`. It can only
	 *                                      contain lowercase alphanumeric characters, dashes and the forward slash.
	 * @param array             $properties Optional. An associative array of properties for the ability. This should
	 *                                      include `label`, `description`, `input_schema`, `output_schema`,
	 *                                      `execute_callback`, `permission_callback`, and `meta`.
	 * @return ?WP_Ability The registered ability instance on success, null on failure.
	 */
	public function register( $name, array $properties = array() ): ?WP_Ability {
		$ability = null;
		if ( $name instanceof WP_Ability ) {
			$ability = $name;
			$name    = $ability->get_name();
		}

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

		// If the ability is already an instance, we can skip the rest of the validation.
		if ( null !== $ability ) {
			$this->registered_abilities[ $name ] = $ability;
			return $ability;
		}

		if ( empty( $properties['label'] ) || ! is_string( $properties['label'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The ability properties must contain a `label` string.' ),
				'0.1.0'
			);
			return null;
		}

		if ( empty( $properties['description'] ) || ! is_string( $properties['description'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The ability properties must contain a `description` string.' ),
				'0.1.0'
			);
			return null;
		}

		if ( isset( $properties['input_schema'] ) && ! is_array( $properties['input_schema'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The ability properties should provide a valid `input_schema` definition.' ),
				'0.1.0'
			);
			return null;
		}

		if ( isset( $properties['output_schema'] ) && ! is_array( $properties['output_schema'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The ability properties should provide a valid `output_schema` definition.' ),
				'0.1.0'
			);
			return null;
		}

		if ( empty( $properties['execute_callback'] ) || ! is_callable( $properties['execute_callback'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The ability properties must contain a valid `execute_callback` function.' ),
				'0.1.0'
			);
			return null;
		}

		if ( isset( $properties['permission_callback'] ) && ! is_callable( $properties['permission_callback'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The ability properties should provide a valid `permission_callback` function.' ),
				'0.1.0'
			);
			return null;
		}

		if ( isset( $properties['meta'] ) && ! is_array( $properties['meta'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The ability properties should provide a valid `meta` array.' ),
				'0.1.0'
			);
			return null;
		}

		$ability = new WP_Ability(
			$name,
			array(
				'label'               => $properties['label'],
				'description'         => $properties['description'],
				'input_schema'        => $properties['input_schema'] ?? array(),
				'output_schema'       => $properties['output_schema'] ?? array(),
				'execute_callback'    => $properties['execute_callback'],
				'permission_callback' => $properties['permission_callback'] ?? null,
				'meta'                => $properties['meta'] ?? array(),
			)
		);
		$this->registered_abilities[ $name ] = $ability;
		return $ability;
	}

	/**
	 * Unregisters an ability.
	 *
	 * Do not use this method directly. Instead, use the `wp_unregister_ability()` function.
	 *
	 * @see wp_unregister_ability()
	 *
	 * @since 0.1.0
	 *
	 * @param string $name The name of the registered ability, with its namespace.
	 * @return ?WP_Ability The unregistered ability instance on success, null on failure.
	 */
	public function unregister( $name ): ?WP_Ability {
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
	 * @see wp_get_abilities()
	 *
	 * @since 0.1.0
	 *
	 * @return WP_Ability[] The array of registered abilities.
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
	public function is_registered( $name ): bool {
		return isset( $this->registered_abilities[ $name ] );
	}

	/**
	 * Retrieves a registered ability.
	 *
	 * Do not use this method directly. Instead, use the `wp_get_ability()` function.
	 *
	 * @see wp_get_ability()
	 *
	 * @since 0.1.0
	 *
	 * @param string $name The name of the registered ability, with its namespace.
	 * @return ?WP_Ability The registered ability instance, or null if it is not registered.
	 */
	public function get_registered( $name ): ?WP_Ability {
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
	 * @return WP_Abilities_Registry The main registry instance.
	 */
	public static function get_instance(): WP_Abilities_Registry {
		/* @var WP_Abilities_Registry $wp_abilities */
		global $wp_abilities;

		if ( empty( $wp_abilities ) ) {
			$wp_abilities = new self();
			/**
			 * Fires when preparing abilities registry.
			 *
			 * Abilities should be created and register their hooks on this action rather
			 * than another action to ensure they're only loaded when needed.
			 *
			 * @since 0.1.0
			 *
			 * @param WP_Abilities_Registry $instance Abilities registry object.
			 */
			do_action( 'abilities_api_init', $wp_abilities );
		}

		return $wp_abilities;
	}

	/**
	 * Wakeup magic method.
	 *
	 * @since 0.1.0
	 */
	public function __wakeup(): void {
		if ( empty( $this->registered_abilities ) ) {
			return;
		}

		foreach ( $this->registered_abilities as $ability ) {
			if ( ! $ability instanceof WP_Ability ) {
				throw new UnexpectedValueException();
			}
		}
	}
}
