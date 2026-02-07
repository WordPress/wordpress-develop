<?php
/**
 * WordPress AI Client API.
 *
 * @package WordPress
 * @subpackage AI
 * @since 7.0.0
 */

use WordPress\AiClient\AiClient;

/**
 * Creates a new AI prompt builder using the default provider registry.
 *
 * @since 7.0.0
 *
 * @param mixed $prompt Optional. Initial prompt content. Default null.
 * @return WP_AI_Client_Prompt_Builder The prompt builder instance.
 */
function wp_ai_client_prompt( $prompt = null ) {
	return new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry(), $prompt );
}
