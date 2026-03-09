<?php
/**
 * Connectors API.
 *
 * @package WordPress
 * @subpackage Connectors
 * @since 7.0.0
 */

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Registers a new connector.
 *
 * Must be called during the `wp_connectors_init` action.
 *
 * Example:
 *
 *     function my_plugin_register_connectors( WP_Connector_Registry $registry ): void {
 *         wp_register_connector(
 *             'my_custom_ai',
 *             array(
 *                 'name'           => __( 'My Custom AI', 'my-plugin' ),
 *                 'description'    => __( 'Custom AI provider integration.', 'my-plugin' ),
 *                 'type'           => 'ai_provider',
 *                 'authentication' => array(
 *                     'method'          => 'api_key',
 *                     'credentials_url' => 'https://example.com/api-keys',
 *                 ),
 *             )
 *         );
 *     }
 *     add_action( 'wp_connectors_init', 'my_plugin_register_connectors' );
 *
 * @since 7.0.0
 *
 * @see WP_Connector_Registry::register()
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
 */
function wp_register_connector( string $id, array $args ): ?array {
	if ( ! doing_action( 'wp_connectors_init' ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				/* translators: 1: wp_connectors_init, 2: string value of the connector ID. */
				__( 'Connectors must be registered on the %1$s action. The connector %2$s was not registered.' ),
				'<code>wp_connectors_init</code>',
				'<code>' . esc_html( $id ) . '</code>'
			),
			'7.0.0'
		);
		return null;
	}

	$registry = WP_Connector_Registry::get_instance();
	if ( null === $registry ) {
		return null;
	}

	return $registry->register( $id, $args );
}

/**
 * Unregisters a connector.
 *
 * Can be called at any time after the connector has been registered.
 *
 * @since 7.0.0
 *
 * @see WP_Connector_Registry::unregister()
 * @see wp_register_connector()
 *
 * @param string $id The connector identifier.
 * @return array|null The unregistered connector data on success, null on failure.
 */
function wp_unregister_connector( string $id ): ?array {
	$registry = WP_Connector_Registry::get_instance();
	if ( null === $registry ) {
		return null;
	}

	return $registry->unregister( $id );
}

/**
 * Checks if a connector is registered.
 *
 * @since 7.0.0
 *
 * @see WP_Connector_Registry::is_registered()
 *
 * @param string $id The connector identifier.
 * @return bool True if the connector is registered, false otherwise.
 */
function wp_is_connector_registered( string $id ): bool {
	$registry = WP_Connector_Registry::get_instance();
	if ( null === $registry ) {
		return false;
	}

	return $registry->is_registered( $id );
}

/**
 * Retrieves a registered connector.
 *
 * @since 7.0.0
 *
 * @see WP_Connector_Registry::get_registered()
 *
 * @param string $id The connector identifier.
 * @return array|null The registered connector data, or null if not registered.
 */
function wp_get_connector( string $id ): ?array {
	$registry = WP_Connector_Registry::get_instance();
	if ( null === $registry ) {
		return null;
	}

	return $registry->get_registered( $id );
}

/**
 * Retrieves all registered connectors.
 *
 * @since 7.0.0
 *
 * @see WP_Connector_Registry::get_all_registered()
 *
 * @return array[] An array of registered connectors keyed by connector ID.
 */
function wp_get_connectors(): array {
	$registry = WP_Connector_Registry::get_instance();
	if ( null === $registry ) {
		return array();
	}

	return $registry->get_all_registered();
}

/**
 * Registers default connectors from Core and the AI Client registry.
 *
 * @since 7.0.0
 * @access private
 */
function _wp_register_default_connectors(): void {
	// Built-in connectors.
	$defaults = array(
		'anthropic' => array(
			'name'           => 'Anthropic',
			'description'    => __( 'Text generation with Claude.' ),
			'type'           => 'ai_provider',
			'plugin'         => array(
				'slug' => 'ai-provider-for-anthropic',
			),
			'authentication' => array(
				'method'          => 'api_key',
				'credentials_url' => 'https://platform.claude.com/settings/keys',
			),
		),
		'google'    => array(
			'name'           => 'Google',
			'description'    => __( 'Text and image generation with Gemini and Imagen.' ),
			'type'           => 'ai_provider',
			'plugin'         => array(
				'slug' => 'ai-provider-for-google',
			),
			'authentication' => array(
				'method'          => 'api_key',
				'credentials_url' => 'https://aistudio.google.com/api-keys',
			),
		),
		'openai'    => array(
			'name'           => 'OpenAI',
			'description'    => __( 'Text and image generation with GPT and Dall-E.' ),
			'type'           => 'ai_provider',
			'plugin'         => array(
				'slug' => 'ai-provider-for-openai',
			),
			'authentication' => array(
				'method'          => 'api_key',
				'credentials_url' => 'https://platform.openai.com/api-keys',
			),
		),
	);

	// Merge AI Client registry data on top of defaults.
	// Registry values (from provider plugins) take precedence over hardcoded fallbacks.
	$ai_registry = AiClient::defaultRegistry();

	foreach ( $ai_registry->getRegisteredProviderIds() as $connector_id ) {
		$provider_class_name = $ai_registry->getProviderClassName( $connector_id );
		$provider_metadata   = $provider_class_name::metadata();

		$auth_method = $provider_metadata->getAuthenticationMethod();
		$is_api_key  = null !== $auth_method && $auth_method->isApiKey();

		if ( $is_api_key ) {
			$credentials_url = $provider_metadata->getCredentialsUrl();
			$authentication  = array(
				'method'          => 'api_key',
				'credentials_url' => $credentials_url ? $credentials_url : null,
			);
		} else {
			$authentication = array( 'method' => 'none' );
		}

		$name        = $provider_metadata->getName();
		$description = $provider_metadata->getDescription();

		if ( isset( $defaults[ $connector_id ] ) ) {
			// Override fields with non-empty registry values.
			if ( $name ) {
				$defaults[ $connector_id ]['name'] = $name;
			}
			if ( $description ) {
				$defaults[ $connector_id ]['description'] = $description;
			}
			// Always update auth method; keep existing credentials_url as fallback.
			$defaults[ $connector_id ]['authentication']['method'] = $authentication['method'];
			if ( ! empty( $authentication['credentials_url'] ) ) {
				$defaults[ $connector_id ]['authentication']['credentials_url'] = $authentication['credentials_url'];
			}
		} else {
			$defaults[ $connector_id ] = array(
				'name'           => $name ? $name : ucwords( $connector_id ),
				'description'    => $description ? $description : '',
				'type'           => 'ai_provider',
				'authentication' => $authentication,
			);
		}
	}

	// Register all connectors.
	foreach ( $defaults as $id => $args ) {
		wp_register_connector( $id, $args );
	}
}

/**
 * Masks an API key, showing only the last 4 characters.
 *
 * @since 7.0.0
 * @access private
 *
 * @param string $key The API key to mask.
 * @return string The masked key, e.g. "************fj39".
 */
function _wp_connectors_mask_api_key( string $key ): string {
	if ( strlen( $key ) <= 4 ) {
		return $key;
	}

	return str_repeat( "\u{2022}", min( strlen( $key ) - 4, 16 ) ) . substr( $key, -4 );
}

/**
 * Checks whether an API key is valid for a given provider.
 *
 * @since 7.0.0
 * @access private
 *
 * @param string $key         The API key to check.
 * @param string $provider_id The WP AI client provider ID.
 * @return bool|null True if valid, false if invalid, null if unable to determine.
 */
function _wp_connectors_is_ai_api_key_valid( string $key, string $provider_id ): ?bool {
	try {
		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( $provider_id ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				sprintf(
					/* translators: %s: AI provider ID. */
					__( 'The provider "%s" is not registered in the AI client registry.' ),
					$provider_id
				),
				'7.0.0'
			);
			return null;
		}

		$registry->setProviderRequestAuthentication(
			$provider_id,
			new ApiKeyRequestAuthentication( $key )
		);

		return $registry->isProviderConfigured( $provider_id );
	} catch ( Exception $e ) {
		wp_trigger_error( __FUNCTION__, $e->getMessage() );
		return null;
	}
}

/**
 * Retrieves the real (unmasked) value of a connector API key.
 *
 * Temporarily removes the masking filter, reads the option, then re-adds it.
 *
 * @since 7.0.0
 * @access private
 *
 * @param string   $option_name   The option name for the API key.
 * @param callable $mask_callback The mask filter function.
 * @return string The real API key value.
 */
function _wp_connectors_get_real_api_key( string $option_name, callable $mask_callback ): string {
	remove_filter( "option_{$option_name}", $mask_callback );
	$value = get_option( $option_name, '' );
	add_filter( "option_{$option_name}", $mask_callback );
	return (string) $value;
}

/**
 * Gets the registered connector settings.
 *
 * @since 7.0.0
 * @access private
 *
 * @return array {
 *     Connector settings keyed by connector ID.
 *
 *     @type array ...$0 {
 *         Data for a single connector.
 *
 *         @type string $name           The connector's display name.
 *         @type string $description    The connector's description.
 *         @type string $type           The connector type. Currently, only 'ai_provider' is supported.
 *         @type array  $plugin         Optional. Plugin data for install/activate UI.
 *             @type string $slug       The WordPress.org plugin slug.
 *         }
 *         @type array  $authentication {
 *             Authentication configuration. When method is 'api_key', includes
 *             credentials_url and setting_name. When 'none', only method is present.
 *
 *             @type string      $method          The authentication method: 'api_key' or 'none'.
 *             @type string|null $credentials_url Optional. URL where users can obtain API credentials.
 *             @type string      $setting_name    Optional. The setting name for the API key.
 *         }
 *     }
 * }
 */
function _wp_connectors_get_connector_settings(): array {
	$connectors = wp_get_connectors();
	ksort( $connectors );
	return $connectors;
}

/**
 * Validates connector API keys in the REST response when explicitly requested.
 *
 * Runs on `rest_post_dispatch` for `/wp/v2/settings` requests that include connector
 * fields via `_fields`. For each requested connector field, it validates the unmasked
 * key against the provider and replaces the response value with `invalid_key` if
 * validation fails.
 *
 * @since 7.0.0
 * @access private
 *
 * @param WP_REST_Response $response The response object.
 * @param WP_REST_Server   $server   The server instance.
 * @param WP_REST_Request  $request  The request object.
 * @return WP_REST_Response The potentially modified response.
 */
function _wp_connectors_validate_keys_in_rest( WP_REST_Response $response, WP_REST_Server $server, WP_REST_Request $request ): WP_REST_Response {
	if ( '/wp/v2/settings' !== $request->get_route() ) {
		return $response;
	}

	$fields = $request->get_param( '_fields' );
	if ( ! $fields ) {
		return $response;
	}

	if ( is_array( $fields ) ) {
		$requested = $fields;
	} else {
		$requested = array_map( 'trim', explode( ',', $fields ) );
	}

	$data = $response->get_data();
	if ( ! is_array( $data ) ) {
		return $response;
	}

	foreach ( _wp_connectors_get_connector_settings() as $connector_id => $connector_data ) {
		$auth = $connector_data['authentication'];
		if ( 'ai_provider' !== $connector_data['type'] || 'api_key' !== $auth['method'] || empty( $auth['setting_name'] ) ) {
			continue;
		}

		$setting_name = $auth['setting_name'];
		if ( ! in_array( $setting_name, $requested, true ) ) {
			continue;
		}

		$real_key = _wp_connectors_get_real_api_key( $setting_name, '_wp_connectors_mask_api_key' );
		if ( '' === $real_key ) {
			continue;
		}

		if ( true !== _wp_connectors_is_ai_api_key_valid( $real_key, $connector_id ) ) {
			$data[ $setting_name ] = 'invalid_key';
		}
	}

	$response->set_data( $data );
	return $response;
}
add_filter( 'rest_post_dispatch', '_wp_connectors_validate_keys_in_rest', 10, 3 );

/**
 * Registers default connector settings and mask/sanitize filters.
 *
 * @since 7.0.0
 * @access private
 */
function _wp_register_default_connector_settings(): void {
	$ai_registry = AiClient::defaultRegistry();

	foreach ( _wp_connectors_get_connector_settings() as $connector_id => $connector_data ) {
		$auth = $connector_data['authentication'];
		if ( 'ai_provider' !== $connector_data['type'] || 'api_key' !== $auth['method'] || empty( $auth['setting_name'] ) ) {
			continue;
		}

		// Skip registering the setting if the provider is not in the registry.
		if ( ! $ai_registry->hasProvider( $connector_id ) ) {
			continue;
		}

		$setting_name = $auth['setting_name'];
		register_setting(
			'connectors',
			$setting_name,
			array(
				'type'              => 'string',
				'label'             => sprintf(
					/* translators: %s: AI provider name. */
					__( '%s API Key' ),
					$connector_data['name']
				),
				'description'       => sprintf(
					/* translators: %s: AI provider name. */
					__( 'API key for the %s AI provider.' ),
					$connector_data['name']
				),
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => static function ( string $value ) use ( $connector_id ): string {
					$value = sanitize_text_field( $value );
					if ( '' === $value ) {
						return $value;
					}

					$valid = _wp_connectors_is_ai_api_key_valid( $value, $connector_id );
					return true === $valid ? $value : '';
				},
			)
		);
		add_filter( "option_{$setting_name}", '_wp_connectors_mask_api_key' );
	}
}
add_action( 'init', '_wp_register_default_connector_settings', 20 );

/**
 * Passes stored connector API keys to the WP AI client.
 *
 * @since 7.0.0
 * @access private
 */
function _wp_connectors_pass_default_keys_to_ai_client(): void {
	try {
		$ai_registry = AiClient::defaultRegistry();
		foreach ( _wp_connectors_get_connector_settings() as $connector_id => $connector_data ) {
			if ( 'ai_provider' !== $connector_data['type'] ) {
				continue;
			}

			$auth = $connector_data['authentication'];
			if ( 'api_key' !== $auth['method'] || empty( $auth['setting_name'] ) ) {
				continue;
			}

			if ( ! $ai_registry->hasProvider( $connector_id ) ) {
				continue;
			}

			$api_key = _wp_connectors_get_real_api_key( $auth['setting_name'], '_wp_connectors_mask_api_key' );
			if ( '' === $api_key ) {
				continue;
			}

			$ai_registry->setProviderRequestAuthentication(
				$connector_id,
				new ApiKeyRequestAuthentication( $api_key )
			);
		}
	} catch ( Exception $e ) {
			wp_trigger_error( __FUNCTION__, $e->getMessage() );
	}
}
add_action( 'init', '_wp_connectors_pass_default_keys_to_ai_client', 20 );

/**
 * Exposes connector settings to the connectors-wp-admin script module.
 *
 * @since 7.0.0
 * @access private
 *
 * @param array $data Existing script module data.
 * @return array Script module data with connectors added.
 */
function _wp_connectors_get_connector_script_module_data( array $data ): array {
	$connectors = array();
	foreach ( _wp_connectors_get_connector_settings() as $connector_id => $connector_data ) {
		$auth     = $connector_data['authentication'];
		$auth_out = array( 'method' => $auth['method'] );

		if ( 'api_key' === $auth['method'] ) {
			$auth_out['settingName']    = $auth['setting_name'] ?? '';
			$auth_out['credentialsUrl'] = $auth['credentials_url'] ?? null;
		}

		$connector_out = array(
			'name'           => $connector_data['name'],
			'description'    => $connector_data['description'],
			'type'           => $connector_data['type'],
			'authentication' => $auth_out,
		);

		if ( ! empty( $connector_data['plugin'] ) ) {
			$connector_out['plugin'] = $connector_data['plugin'];
		}

		$connectors[ $connector_id ] = $connector_out;
	}
	$data['connectors'] = $connectors;
	return $data;
}
add_filter( 'script_module_data_options-connectors-wp-admin', '_wp_connectors_get_connector_script_module_data' );
