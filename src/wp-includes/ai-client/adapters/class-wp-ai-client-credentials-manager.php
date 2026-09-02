<?php
/**
 * WP AI Client: WP_AI_Client_Credentials_Manager class
 *
 * @package WordPress
 * @subpackage AI
 * @since 7.0.0
 */

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Manages AI API credentials for the various providers.
 *
 * Collects provider metadata from the SDK, registers settings for storing
 * API keys, and passes stored credentials to the SDK for authentication.
 *
 * @since 7.0.0
 */
class WP_AI_Client_Credentials_Manager {

	/**
	 * Option group for AI settings.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	const OPTION_GROUP = 'ai';

	/**
	 * Option name for storing provider credentials.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	const OPTION_PROVIDER_CREDENTIALS = 'wp_ai_client_provider_credentials';

	/**
	 * Collects metadata for all registered providers in the PHP AI Client SDK.
	 *
	 * Since the PHP AI Client SDK can be loaded multiple times, including with
	 * different namespace or class name prefixes, this method ensures that the
	 * provider metadata is collected only once across all instances.
	 *
	 * Uses a global variable to store provider metadata keyed by provider ID,
	 * along with a map of AiClient class names where each provider is registered.
	 *
	 * @since 7.0.0
	 */
	public function collect_providers() {
		global $wp_ai_client_providers_metadata;

		if ( ! isset( $wp_ai_client_providers_metadata ) ) {
			$wp_ai_client_providers_metadata = array();
		}

		$registry = AiClient::defaultRegistry();

		$provider_ids = $registry->getRegisteredProviderIds();
		foreach ( $provider_ids as $provider_id ) {
			// If the provider was already found via another client class, just add this client class name.
			if ( isset( $wp_ai_client_providers_metadata[ $provider_id ] ) ) {
				if ( ! is_array( $wp_ai_client_providers_metadata[ $provider_id ]['ai_client_classnames'] ) ) {
					_doing_it_wrong(
						__METHOD__,
						__( 'Invalid format for collected provider AI client class names.' ),
						'7.0.0'
					);
					continue;
				}
				$wp_ai_client_providers_metadata[ $provider_id ]['ai_client_classnames'][ AiClient::class ] = true;
				continue;
			}

			// Get the provider metadata and add it to the global.
			$provider_class_name = $registry->getProviderClassName( $provider_id );
			$provider_metadata   = $provider_class_name::metadata();

			$wp_ai_client_providers_metadata[ $provider_id ] = array_merge(
				$provider_metadata->toArray(),
				array(
					'ai_client_classnames' => array( AiClient::class => true ),
				)
			);
		}
	}

	/**
	 * Returns the metadata for all registered providers across all instances of the PHP AI Client SDK.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string, WordPress\AiClient\Providers\DTO\ProviderMetadata> Array of provider metadata objects,
	 *                                                                          keyed by provider ID.
	 */
	public function get_all_providers_metadata() {
		global $wp_ai_client_providers_metadata;

		if ( ! isset( $wp_ai_client_providers_metadata ) ) {
			$wp_ai_client_providers_metadata = array();
		}

		return array_map(
			static function ( array $provider_metadata ) {
				unset( $provider_metadata['ai_client_classnames'] );
				return \WordPress\AiClient\Providers\DTO\ProviderMetadata::fromArray( $provider_metadata );
			},
			$wp_ai_client_providers_metadata
		);
	}

	/**
	 * Returns the metadata for all registered cloud providers across all instances of the PHP AI Client SDK.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string, WordPress\AiClient\Providers\DTO\ProviderMetadata> Array of cloud provider metadata objects,
	 *                                                                          keyed by provider ID.
	 */
	public function get_all_cloud_providers_metadata() {
		$all_providers = $this->get_all_providers_metadata();

		return array_filter(
			$all_providers,
			static function ( $metadata ) {
				return $metadata->getType()->isCloud();
			}
		);
	}

	/**
	 * Registers the settings for storing the API credentials.
	 *
	 * The setting will only be registered once, even if called multiple times.
	 *
	 * @since 7.0.0
	 */
	public function register_settings() {
		// Avoid registering the setting multiple times.
		$registered_settings = get_registered_settings();
		if ( isset( $registered_settings[ self::OPTION_PROVIDER_CREDENTIALS ] ) ) {
			return;
		}

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_PROVIDER_CREDENTIALS,
			array(
				'type'              => 'object',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_credentials' ),
			)
		);
	}

	/**
	 * Sanitizes the provider credentials before saving.
	 *
	 * Filters out unknown providers and sanitizes each API key value.
	 *
	 * @since 7.0.0
	 *
	 * @param mixed $credentials The raw credentials input.
	 * @return array Sanitized credentials array.
	 */
	public function sanitize_credentials( $credentials ) {
		if ( ! is_array( $credentials ) ) {
			return array();
		}

		// Assume that all cloud providers require an API key.
		$providers_metadata_keyed_by_ids = $this->get_all_cloud_providers_metadata();

		$credentials = array_intersect_key( $credentials, $providers_metadata_keyed_by_ids );
		foreach ( $credentials as $provider_id => $api_key ) {
			if ( ! is_string( $api_key ) ) {
				unset( $credentials[ $provider_id ] );
				continue;
			}
			$credentials[ $provider_id ] = sanitize_text_field( $api_key );
		}
		return $credentials;
	}

	/**
	 * Passes the stored API credentials to the PHP AI Client SDK.
	 *
	 * This method should be called on every request, before any API requests
	 * are made via the PHP AI Client SDK.
	 *
	 * @since 7.0.0
	 */
	public function pass_credentials_to_client() {
		$credentials = get_option( self::OPTION_PROVIDER_CREDENTIALS, array() );
		if ( ! is_array( $credentials ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Invalid format for stored provider credentials option.' ),
				'7.0.0'
			);
			return;
		}

		$registry = AiClient::defaultRegistry();

		foreach ( $credentials as $provider_id => $api_key ) {
			if ( ! is_string( $api_key ) || '' === $api_key ) {
				continue;
			}

			if ( ! $registry->hasProvider( $provider_id ) ) {
				continue;
			}

			$registry->setProviderRequestAuthentication(
				$provider_id,
				new ApiKeyRequestAuthentication( $api_key )
			);
		}
	}
}
