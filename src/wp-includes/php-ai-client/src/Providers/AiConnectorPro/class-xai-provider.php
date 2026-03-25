<?php
/**
 * xAI (Grok) AI Provider for WordPress AI Security Edition.
 *
 * @package WordPress
 * @subpackage AI_Connector_Pro
 * @since 7.1.0
 */

declare( strict_types = 1 );

namespace WordPress\AiClient\Providers\AiConnectorPro;

use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * xAI Provider class.
 *
 * @since 7.1.0
 */
class XAI_Provider extends Abstract_Connector_Provider {

	/**
	 * Provider ID.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $provider_id = 'xai';

	/**
	 * Provider name.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $provider_name = 'xAI (Grok)';

	/**
	 * API base URL.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $api_base_url = 'https://api.x.ai/v1';

	/**
	 * Credentials URL.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $credentials_url = 'https://console.x.ai/';

	/**
	 * Description.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $description = 'xAI Grok models - conversational AI with real-time knowledge.';

	/**
	 * Available models.
	 *
	 * @since 7.1.0
	 * @var array<string, string>
	 */
	protected static array $models = array(
		'grok-2-1212'       => AbstractOpenAiCompatibleTextGenerationModel::class,
		'grok-2'            => AbstractOpenAiCompatibleTextGenerationModel::class,
		'grok-beta'         => AbstractOpenAiCompatibleTextGenerationModel::class,
		'grok-vision-beta'  => AbstractOpenAiCompatibleTextGenerationModel::class,
	);

	/**
	 * Model requirements.
	 *
	 * @since 7.1.0
	 * @var array<string, array>
	 */
	protected static array $model_requirements = array(
		'grok-2' => array(
			'context_window'      => 131072,
			'max_output_tokens'   => 8192,
			'supports_vision'     => false,
			'supports_functions'  => true,
			'supports_streaming'  => true,
		),
		'grok-vision-beta' => array(
			'context_window'      => 32768,
			'max_output_tokens'   => 4096,
			'supports_vision'     => true,
			'supports_functions'  => false,
			'supports_streaming'  => true,
		),
	);
}