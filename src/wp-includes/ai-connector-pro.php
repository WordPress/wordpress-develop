<?php
/**
 * AI Connector Pro - Provider Registration.
 *
 * Registers DeepSeek, OpenRouter, Ollama, xAI, and Mistral providers
 * with the WordPress AI Client.
 *
 * @package WordPress
 * @subpackage AI_Connector_Pro
 * @since 7.1.0
 */

declare( strict_types = 1 );

namespace WordPress\AIConnectorPro;

/**
 * Register AI Connector Pro providers with WordPress AI Client.
 *
 * @since 7.1.0
 */
function register_providers(): void {
	$registry = \WordPress\AiClient\AiClient::defaultRegistry();
	if ( null === $registry ) {
		return;
	}

	// Register our providers
	$providers = array(
		\WordPress\AiClient\Providers\AiConnectorPro\DeepSeek_Provider::class,
		\WordPress\AiClient\Providers\AiConnectorPro\OpenRouter_Provider::class,
		\WordPress\AiClient\Providers\AiConnectorPro\Ollama_Provider::class,
		\WordPress\AiClient\Providers\AiConnectorPro\XAI_Provider::class,
		\WordPress\AiClient\Providers\AiConnectorPro\Mistral_Provider::class,
	);

	foreach ( $providers as $provider_class ) {
		if ( class_exists( $provider_class ) ) {
			$registry->registerProvider( $provider_class );
		}
	}
}

// Register when WordPress is loaded enough
add_action( 'init', __NAMESPACE__ . '\\register_providers', 5 );