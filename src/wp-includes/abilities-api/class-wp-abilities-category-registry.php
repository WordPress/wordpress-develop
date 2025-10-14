<?php
/**
 * Abilities API
 *
 * Defines WP_Abilities_Category_Registry class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 0.3.0
 */

declare( strict_types = 1 );

/**
 * Manages the registration and lookup of ability categories.
 *
 * @since 0.3.0
 * @access private
 */
final class WP_Abilities_Category_Registry {
	/**
	 * The singleton instance of the registry.
	 *
	 * @since 0.3.0
	 * @var ?self
	 */
	private static $instance = null;

	/**
	 * Holds the registered categories.
	 *
	 * @since 0.3.0
	 * @var \WP_Ability_Category[]
	 */
	private $registered_categories = array();

	/**
	 * Registers a new category.
	 *
	 * Do not use this method directly. Instead, use the `wp_register_ability_category()` function.
	 *
	 * @since 0.3.0
	 *
	 * @see wp_register_ability_category()
	 *
	 * @param string              $slug The unique slug for the category. Must contain only lowercase
	 *                                  alphanumeric characters and dashes.
	 * @param array<string,mixed> $args An associative array of arguments for the category. See wp_register_ability_category() for
	 *                                  details.
	 * @return ?\WP_Ability_Category The registered category instance on success, null on failure.
	 *
 * @phpstan-param array{
 *   label: string,
 *   description: string,
 *   meta?: array<string,mixed>,
 *   ...<string, mixed>
 * } $args
	 */
	public function register( string $slug, array $args ): ?WP_Ability_Category {
		if ( ! doing_action( 'abilities_api_categories_init' ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: 1: abilities_api_categories_init, 2: category slug. */
					esc_html__( 'Categories must be registered during the %1$s action. The category %2$s was not registered.' ),
					'<code>abilities_api_categories_init</code>',
					'<code>' . esc_html( $slug ) . '</code>'
				),
				'0.3.0'
			);
			return null;
		}

		if ( $this->is_registered( $slug ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Category slug. */
				esc_html( sprintf( __( 'Category "%s" is already registered.' ), $slug ) ),
				'0.3.0'
			);
			return null;
		}

		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Category slug must contain only lowercase alphanumeric characters and dashes.' ),
				'0.3.0'
			);
			return null;
		}

		/**
		 * Filters the category arguments before they are validated and used to instantiate the category.
		 *
		 * @since 0.3.0
		 *
		 * @param array<string,mixed> $args The arguments used to instantiate the category.
		 * @param string              $slug The slug of the category.
		 */
		$args = apply_filters( 'register_ability_category_args', $args, $slug );

		try {
			// WP_Ability_Category::prepare_properties() will throw an exception if the properties are invalid.
			$category = new WP_Ability_Category( $slug, $args );
		} catch ( \InvalidArgumentException $e ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html( $e->getMessage() ),
				'0.3.0'
			);
			return null;
		}

		$this->registered_categories[ $slug ] = $category;
		return $category;
	}

	/**
	 * Unregisters a category.
	 *
	 * Do not use this method directly. Instead, use the `wp_unregister_ability_category()` function.
	 *
	 * @since 0.3.0
	 *
	 * @see wp_unregister_ability_category()
	 *
	 * @param string $slug The slug of the registered category.
	 * @return ?\WP_Ability_Category The unregistered category instance on success, null on failure.
	 */
	public function unregister( string $slug ): ?WP_Ability_Category {
		if ( ! $this->is_registered( $slug ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Ability category slug. */
				sprintf( esc_html__( 'Ability category "%s" not found.' ), esc_attr( $slug ) ),
				'0.3.0'
			);
			return null;
		}

		$unregistered_category = $this->registered_categories[ $slug ];
		unset( $this->registered_categories[ $slug ] );

		return $unregistered_category;
	}

	/**
	 * Retrieves the list of all registered categories.
	 *
	 * Do not use this method directly. Instead, use the `wp_get_ability_categories()` function.
	 *
	 * @since 0.3.0
	 *
	 * @see wp_get_ability_categories()
	 *
	 * @return array<string,\WP_Ability_Category> The array of registered categories.
	 */
	public function get_all_registered(): array {
		return $this->registered_categories;
	}

	/**
	 * Checks if a category is registered.
	 *
	 * @since 0.3.0
	 *
	 * @param string $slug The slug of the category.
	 * @return bool True if the category is registered, false otherwise.
	 */
	public function is_registered( string $slug ): bool {
		return isset( $this->registered_categories[ $slug ] );
	}

	/**
	 * Retrieves a registered category.
	 *
	 * Do not use this method directly. Instead, use the `wp_get_ability_category()` function.
	 *
	 * @since 0.3.0
	 *
	 * @see wp_get_ability_category()
	 *
	 * @param string $slug The slug of the registered category.
	 * @return ?\WP_Ability_Category The registered category instance, or null if it is not registered.
	 */
	public function get_registered( string $slug ): ?WP_Ability_Category {
		if ( ! $this->is_registered( $slug ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Ability category slug. */
				sprintf( esc_html__( 'Ability category "%s" not found.' ), esc_attr( $slug ) ),
				'0.3.0'
			);
			return null;
		}
		return $this->registered_categories[ $slug ];
	}

	/**
	 * Utility method to retrieve the main instance of the registry class.
	 *
	 * The instance will be created if it does not exist yet.
	 *
	 * @since 0.3.0
	 *
	 * @return \WP_Abilities_Category_Registry The main registry instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();

			/**
			 * Fires when preparing ability categories registry.
			 *
			 * Categories should be registered on this action to ensure they're available when needed.
			 *
			 * @since 0.3.0
			 *
			 * @param \WP_Abilities_Category_Registry $instance Categories registry object.
			 */
			do_action( 'abilities_api_categories_init', self::$instance );
		}

		return self::$instance;
	}

	/**
	 * Wakeup magic method.
	 *
	 * @since 0.3.0
	 * @throws \LogicException If the registry is unserialized. This is a security hardening measure to prevent unserialization of the registry.
	 */
	public function __wakeup(): void {
		throw new \LogicException( self::class . ' must not be unserialized.' );
	}

	/**
	 * Serialization magic method.
	 *
	 * @since 0.3.0
	 * @throws \LogicException If the registry is serialized. This is a security hardening measure to prevent serialization of the registry.
	 */
	public function __sleep(): array {
		throw new \LogicException( self::class . ' must not be serialized.' );
	}
}
