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
 * Registers the Connectors menu item under Settings.
 *
 * @since 7.0.0
 * @access private
 */
function _wp_connectors_add_settings_menu_item(): void {
	if ( ! class_exists( '\WordPress\AiClient\AiClient' ) || ! function_exists( 'wp_connectors_wp_admin_render_page' ) ) {
		return;
	}

	add_submenu_page(
		'options-general.php',
		__( 'Connectors' ),
		__( 'Connectors' ),
		'manage_options',
		'connectors-wp-admin',
		'wp_connectors_wp_admin_render_page',
		1
	);
}
add_action( 'admin_menu', '_wp_connectors_add_settings_menu_item' );

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
function _wp_connectors_is_api_key_valid( string $key, string $provider_id ): ?bool {
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
 * Sets API key authentication for a provider in the WP AI Client registry.
 *
 * @since 7.0.0
 * @access private
 *
 * @param string $key         The API key.
 * @param string $provider_id The WP AI client provider ID.
 * @return bool True if the key was set successfully, false otherwise.
 */
function _wp_connectors_set_provider_api_key( string $key, string $provider_id ): bool {
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
			return false;
		}

		$registry->setProviderRequestAuthentication(
			$provider_id,
			new ApiKeyRequestAuthentication( $key )
		);

		return true;
	} catch ( Exception $e ) {
		wp_trigger_error( __FUNCTION__, $e->getMessage() );
		return false;
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
 * Gets the provider connectors.
 *
 * @since 7.0.0
 * @access private
 *
 * @return array<string, array{provider: string, mask: callable, sanitize: callable}> Connectors.
 */
function _wp_connectors_get_connectors(): array {
	$providers = array(
		'google'    => 'connectors_gemini_api_key',
		'openai'    => 'connectors_openai_api_key',
		'anthropic' => 'connectors_anthropic_api_key',
	);

	$connectors = array();
	foreach ( $providers as $provider => $option_name ) {
		$connectors[ $option_name ] = array(
			'provider' => $provider,
			'mask'     => '_wp_connectors_mask_api_key',
			'sanitize' => static function ( string $value ) use ( $provider ): string {
				$value = sanitize_text_field( $value );
				if ( '' === $value ) {
					return $value;
				}

				$valid = _wp_connectors_is_api_key_valid( $value, $provider );
				return true === $valid ? $value : '';
			},
		);
	}
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

	if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
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

	foreach ( _wp_connectors_get_connectors() as $option_name => $config ) {
		if ( ! in_array( $option_name, $requested, true ) ) {
			continue;
		}

		$real_key = _wp_connectors_get_real_api_key( $option_name, $config['mask'] );
		if ( '' === $real_key ) {
			continue;
		}

		if ( true !== _wp_connectors_is_api_key_valid( $real_key, $config['provider'] ) ) {
			$data[ $option_name ] = 'invalid_key';
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
	if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
		return;
	}

	foreach ( _wp_connectors_get_connectors() as $option_name => $config ) {
		register_setting(
			'connectors',
			$option_name,
			array(
				'type'              => 'string',
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => $config['sanitize'],
			)
		);
		add_filter( "option_{$option_name}", $config['mask'] );
	}
}
add_action( 'init', '_wp_register_default_connector_settings' );

/**
 * Passes stored connector API keys to the WP AI client.
 *
 * @since 7.0.0
 * @access private
 */
function _wp_connectors_pass_default_keys_to_ai_client(): void {
	if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
		return;
	}

	foreach ( _wp_connectors_get_connectors() as $option_name => $config ) {
		$api_key = _wp_connectors_get_real_api_key( $option_name, $config['mask'] );
		if ( '' !== $api_key ) {
			_wp_connectors_set_provider_api_key( $api_key, $config['provider'] );
		}
	}
}
add_action( 'init', '_wp_connectors_pass_default_keys_to_ai_client' );
