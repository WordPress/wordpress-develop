<?php
/**
 * Connectors API
 *
 * Defines WP_Connector_Registry class.
 *
 * @package WordPress
 * @subpackage Connectors
 * @since 7.0.0
 */

/**
 * Manages the registration and lookup of connectors.
 *
 * @since 7.0.0
 * @access private
 *
 * @phpstan-type Connector array{ name: string, description: string, type: string, authentication: array{ method: string, credentials_url?: string|null }, plugin?: array{ slug: string } }
 */
final class WP_Connector_Registry {
	/**
	 * The singleton instance of the registry.
	 *
	 * @since 7.0.0
	 * @var self|null
	 */
	private static ?WP_Connector_Registry $instance = null;

	/**
	 * Holds the registered connectors.
	 *
	 * Each connector is stored as an associative array with keys:
	 * name, description, type, authentication, and optionally plugin.
	 *
	 * @since 7.0.0
	 * @var array<string, array>
	 * @phpstan-var array<string, Connector>
	 */
	private array $registered_connectors = array();

	/**
	 * Registers a new connector.
	 *
	 * Do not use this method directly. Instead, use the `wp_register_connector()` function.
	 *
	 * @since 7.0.0
	 *
	 * @see wp_register_connector()
	 *
	 * @param string $id   The unique connector identifier. Must contain only lowercase
	 *                     alphanumeric characters and underscores.
	 * @param array  $args {
	 *     An associative array of arguments for the connector.
	 *
	 *     @type string $name           Required. The connector's display name.
	 *     @type string $description    Optional. The connector's description. Default empty string.
	 *     @type string $type           Required. The connector type. Currently, only 'ai_provider' is supported.
	 *     @type array  $authentication {
	 *         Required. Authentication configuration.
	 *
	 *         @type string      $method          Required. The authentication method: 'api_key' or 'none'.
	 *         @type string|null $credentials_url Optional. URL where users can obtain API credentials.
	 *     }
	 *     @type array  $plugin         Optional. Plugin data for install/activate UI.
	 *         @type string $slug       The WordPress.org plugin slug.
	 *     }
	 * }
	 * @return array|null The registered connector data on success, null on failure.
	 *
	 * @phpstan-param Connector $args
	 * @phpstan-return Connector|null
	 */
	public function register( string $id, array $args ): ?array {
		if ( ! preg_match( '/^[a-z0-9_]+$/', $id ) ) {
			_doing_it_wrong(
				__METHOD__,
				__(
					'Connector ID must contain only lowercase alphanumeric characters and underscores.'
				),
				'7.0.0'
			);
			return null;
		}

		if ( $this->is_registered( $id ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Connector ID. */
				sprintf( __( 'Connector "%s" is already registered.' ), esc_html( $id ) ),
				'7.0.0'
			);
			return null;
		}

		// Validate required fields.
		if ( empty( $args['name'] ) || ! is_string( $args['name'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Connector ID. */
				sprintf( __( 'Connector "%s" requires a non-empty "name" string.' ), esc_html( $id ) ),
				'7.0.0'
			);
			return null;
		}

		if ( empty( $args['type'] ) || ! is_string( $args['type'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Connector ID. */
				sprintf( __( 'Connector "%s" requires a non-empty "type" string.' ), esc_html( $id ) ),
				'7.0.0'
			);
			return null;
		}

		if ( ! isset( $args['authentication'] ) || ! is_array( $args['authentication'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Connector ID. */
				sprintf( __( 'Connector "%s" requires an "authentication" array.' ), esc_html( $id ) ),
				'7.0.0'
			);
			return null;
		}

		if ( empty( $args['authentication']['method'] ) || ! in_array( $args['authentication']['method'], array( 'api_key', 'none' ), true ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Connector ID. */
				sprintf( __( 'Connector "%s" authentication method must be "api_key" or "none".' ), esc_html( $id ) ),
				'7.0.0'
			);
			return null;
		}

		$connector = array(
			'name'           => $args['name'],
			'description'    => isset( $args['description'] ) && is_string( $args['description'] ) ? $args['description'] : '',
			'type'           => $args['type'],
			'authentication' => array(
				'method' => $args['authentication']['method'],
			),
		);

		if ( 'api_key' === $args['authentication']['method'] ) {
			$connector['authentication']['credentials_url'] = $args['authentication']['credentials_url'] ?? null;
			$connector['authentication']['setting_name']    = "connectors_ai_{$id}_api_key";
		}

		if ( ! empty( $args['plugin'] ) && is_array( $args['plugin'] ) ) {
			$connector['plugin'] = $args['plugin'];
		}

		$this->registered_connectors[ $id ] = $connector;
		return $connector;
	}

	/**
	 * Unregisters a connector.
	 *
	 * Do not use this method directly. Instead, use the `wp_unregister_connector()` function.
	 *
	 * @since 7.0.0
	 *
	 * @see wp_unregister_connector()
	 *
	 * @param string $id The connector identifier.
	 * @return array|null The unregistered connector data on success, null on failure.
	 *
	 * @phpstan-return Connector|null
	 */
	public function unregister( string $id ): ?array {
		if ( ! $this->is_registered( $id ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Connector ID. */
				sprintf( __( 'Connector "%s" not found.' ), esc_html( $id ) ),
				'7.0.0'
			);
			return null;
		}

		$unregistered = $this->registered_connectors[ $id ];
		unset( $this->registered_connectors[ $id ] );

		return $unregistered;
	}

	/**
	 * Retrieves the list of all registered connectors.
	 *
	 * Do not use this method directly. Instead, use the `wp_get_connectors()` function.
	 *
	 * @since 7.0.0
	 *
	 * @see wp_get_connectors()
	 *
	 * @return array<string, array> The array of registered connectors keyed by connector ID.
	 * @phpstan-return array<string, Connector>
	 */
	public function get_all_registered(): array {
		return $this->registered_connectors;
	}

	/**
	 * Checks if a connector is registered.
	 *
	 * Do not use this method directly. Instead, use the `wp_has_connector()` function.
	 *
	 * @since 7.0.0
	 *
	 * @see wp_has_connector()
	 *
	 * @param string $id The connector identifier.
	 * @return bool True if the connector is registered, false otherwise.
	 */
	public function is_registered( string $id ): bool {
		return isset( $this->registered_connectors[ $id ] );
	}

	/**
	 * Retrieves a registered connector.
	 *
	 * Do not use this method directly. Instead, use the `wp_get_connector()` function.
	 *
	 * @since 7.0.0
	 *
	 * @see wp_get_connector()
	 *
	 * @param string $id The connector identifier.
	 * @return array|null The registered connector data, or null if it is not registered.
	 * @phpstan-return Connector|null
	 */
	public function get_registered( string $id ): ?array {
		if ( ! $this->is_registered( $id ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Connector ID. */
				sprintf( __( 'Connector "%s" not found.' ), esc_html( $id ) ),
				'7.0.0'
			);
			return null;
		}
		return $this->registered_connectors[ $id ];
	}

	/**
	 * Utility method to retrieve the main instance of the registry class.
	 *
	 * The instance will be created if it does not exist yet.
	 *
	 * @since 7.0.0
	 *
	 * @return WP_Connector_Registry|null The main registry instance, or null when `init` action has not fired.
	 */
	public static function get_instance(): ?self {
		if ( ! did_action( 'init' ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: init action. */
					__( 'Connector registry should not be initialized before the %s action has fired.' ),
					'<code>init</code>'
				),
				'7.0.0'
			);
			return null;
		}

		if ( null === self::$instance ) {
			self::$instance = new self();

			/**
			 * Fires when preparing connector registry.
			 *
			 * Connectors should be registered on this action rather
			 * than another action to ensure they're only loaded when needed.
			 *
			 * @since 7.0.0
			 *
			 * @param WP_Connector_Registry $instance Connector registry object.
			 */
			do_action( 'wp_connectors_init', self::$instance );
		}

		return self::$instance;
	}

	/**
	 * Wakeup magic method.
	 *
	 * @since 7.0.0
	 * @throws LogicException If the registry object is unserialized.
	 */
	public function __wakeup(): void {
		throw new LogicException( __CLASS__ . ' should never be unserialized.' );
	}

	/**
	 * Sleep magic method.
	 *
	 * @since 7.0.0
	 * @throws LogicException If the registry object is serialized.
	 */
	public function __sleep(): array {
		throw new LogicException( __CLASS__ . ' should never be serialized.' );
	}
}
