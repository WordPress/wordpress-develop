<?php
/**
 * OpenRouter AI Provider for WordPress AI Security Edition.
 *
 * @package WordPress
 * @subpackage AI_Connector_Pro
 * @since 7.1.0
 */

declare( strict_types = 1 );

namespace WordPress\AiClient\Providers\AiConnectorPro;

use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * OpenRouter Provider class.
 *
 * @since 7.1.0
 */
class OpenRouter_Provider extends Abstract_Connector_Provider {

	/**
	 * Provider ID.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $provider_id = 'openrouter';

	/**
	 * Provider name.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $provider_name = 'OpenRouter';

	/**
	 * API base URL.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $api_base_url = 'https://openrouter.ai/api/v1';

	/**
	 * Credentials URL.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $credentials_url = 'https://openrouter.ai/keys';

	/**
	 * Description.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $description = 'OpenRouter aggregates 100+ AI models through a single API.';

	/**
	 * Available models.
	 *
	 * @since 7.1.0
	 * @var array<string, string>
	 */
	protected static array $models = array(
		// Anthropic
		'anthropic/claude-3.5-sonnet'      => AbstractOpenAiCompatibleTextGenerationModel::class,
		'anthropic/claude-3-opus'          => AbstractOpenAiCompatibleTextGenerationModel::class,
		'anthropic/claude-3-haiku'          => AbstractOpenAiCompatibleTextGenerationModel::class,
		// OpenAI
		'openai/gpt-4o'                    => AbstractOpenAiCompatibleTextGenerationModel::class,
		'openai/gpt-4o-mini'               => AbstractOpenAiCompatibleTextGenerationModel::class,
		'openai/gpt-4-turbo'               => AbstractOpenAiCompatibleTextGenerationModel::class,
		// Google
		'google/gemini-2.0-flash-exp'      => AbstractOpenAiCompatibleTextGenerationModel::class,
		'google/gemini-1.5-pro'            => AbstractOpenAiCompatibleTextGenerationModel::class,
		'google/gemini-1.5-flash'          => AbstractOpenAiCompatibleTextGenerationModel::class,
		// Meta
		'meta-llama/llama-3.3-70b-instruct'   => AbstractOpenAiCompatibleTextGenerationModel::class,
		'meta-llama/llama-3.1-405b-instruct' => AbstractOpenAiCompatibleTextGenerationModel::class,
		// Mistral
		'mistralai/mistral-large'          => AbstractOpenAiCompatibleTextGenerationModel::class,
		'mistralai/codestral'              => AbstractOpenAiCompatibleTextGenerationModel::class,
		// DeepSeek
		'deepseek/deepseek-chat'           => AbstractOpenAiCompatibleTextGenerationModel::class,
		// Qwen
		'qwen/qwen-2.5-72b-instruct'       => AbstractOpenAiCompatibleTextGenerationModel::class,
	);

	/**
	 * Model requirements.
	 *
	 * @since 7.1.0
	 * @var array<string, array>
	 */
	protected static array $model_requirements = array(
		'anthropic/claude-3.5-sonnet' => array(
			'context_window'      => 200000,
			'max_output_tokens'   => 8192,
			'supports_vision'     => true,
			'supports_functions'  => true,
			'supports_streaming'  => true,
		),
		'openai/gpt-4o' => array(
			'context_window'      => 128000,
			'max_output_tokens'   => 16384,
			'supports_vision'     => true,
			'supports_functions'  => true,
			'supports_streaming'  => true,
		),
		'google/gemini-1.5-flash' => array(
			'context_window'      => 1000000,
			'max_output_tokens'    => 8192,
			'supports_vision'      => true,
			'supports_functions'   => true,
			'supports_streaming'   => true,
		),
	);
}