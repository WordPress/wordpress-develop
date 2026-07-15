<?php
/**
 * WordPress AI Client API.
 *
 * @package WordPress
 * @subpackage AI
 * @since 7.0.0
 */

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;

/**
 * Returns whether AI features are supported in the current environment.
 *
 * @since 7.0.0
 *
 * @return bool Whether AI features are supported.
 */
function wp_supports_ai(): bool {
	// Return early if AI is disabled by the current environment.
	if ( defined( 'WP_AI_SUPPORT' ) && ! WP_AI_SUPPORT ) {
		return false;
	}

	/**
	 * Filters whether the current request can use AI.
	 *
	 * This allows plugins and 3rd-party code to disable AI features on a per-request basis, or to even override explicit
	 * preferences defined by the site owner.
	 *
	 * @since 7.0.0
	 *
	 * @param bool $is_enabled Whether AI is available. Default to true.
	 */
	return (bool) apply_filters( 'wp_supports_ai', true );
}

/**
 * Creates a new AI prompt builder using the default provider registry.
 *
 * This is the main entry point for generating AI content in WordPress. It returns
 * a fluent builder that can be used to configure and execute AI prompts.
 *
 * The prompt can be provided as a simple string for basic text prompts, or as more
 * complex types for advanced use cases like multi-modal content or conversation history.
 *
 * @since 7.0.0
 *
 * @param string|MessagePart|Message|array|list<string|MessagePart|array>|list<Message>|null $prompt Optional. Initial prompt content.
 *                                                                                                   A string for simple text prompts,
 *                                                                                                   a MessagePart or Message object for
 *                                                                                                   structured content, an array for a
 *                                                                                                   message array shape, or a list of
 *                                                                                                   parts or messages for multi-turn
 *                                                                                                   conversations. Default null.
 * @return WP_AI_Client_Prompt_Builder The prompt builder instance.
 */
function wp_ai_client_prompt( $prompt = null ): WP_AI_Client_Prompt_Builder {
	return new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry(), $prompt );
}

/**
 * Creates a new AI embedding builder using the default provider registry.
 *
 * This is the main entry point for generating embeddings in WordPress. It returns
 * a fluent builder that can be used to configure and generate embedding vectors.
 *
 * Each input can be provided as a simple string for basic text inputs, or as more
 * complex types. Pass multiple arguments to embed multiple inputs at once, each
 * producing its own embedding vector.
 *
 * @since 7.1.0
 *
 * @param string|MessagePart|File|array ...$input Optional. Initial input(s) to embed.
 *                                                A string for simple text inputs,
 *                                                a MessagePart or File object for
 *                                                structured content, or an array for
 *                                                a message part array shape.
 * @return WP_AI_Client_Embedding_Builder The embedding builder instance.
 */
function wp_ai_client_embedding( ...$input ): WP_AI_Client_Embedding_Builder {
	return new WP_AI_Client_Embedding_Builder( AiClient::defaultRegistry(), array() === $input ? null : $input );
}
