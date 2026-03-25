<?php
/**
 * DeepSeek AI Provider for WordPress AI Security Edition.
 *
 * @package WordPress
 * @subpackage AI_Connector_Pro
 * @since 7.1.0
 */

declare( strict_types = 1 );

namespace WordPress\AiClient\Providers\AiConnectorPro;

use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * DeepSeek Provider class.
 *
 * @since 7.1.0
 */
class DeepSeek_Provider extends Abstract_Connector_Provider {

	/**
	 * Provider ID.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $provider_id = 'deepseek';

	/**
	 * Provider name.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $provider_name = 'DeepSeek';

	/**
	 * API base URL.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $api_base_url = 'https://api.deepseek.com';

	/**
	 * Credentials URL.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $credentials_url = 'https://platform.deepseek.com/api-keys';

	/**
	 * Description.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $description = 'DeepSeek open-source AI models for code completion and chat.';

	/**
	 * Available models.
	 *
	 * @since 7.1.0
	 * @var array<string, string>
	 */
	protected static array $models = array(
		'deepseek-chat'     => AbstractOpenAiCompatibleTextGenerationModel::class,
		'deepseek-coder'    => AbstractOpenAiCompatibleTextGenerationModel::class,
		'deepseek-reasoner' => AbstractOpenAiCompatibleTextGenerationModel::class,
	);

	/**
	 * Model requirements.
	 *
	 * @since 7.1.0
	 * @var array<string, array>
	 */
	protected static array $model_requirements = array(
		'deepseek-chat' => array(
			'context_window'      => 64000,
			'max_output_tokens'   => 4096,
			'supports_vision'     => false,
			'supports_functions' => true,
			'_supports_streaming' => true,
		),
		'deepseek-coder' => array(
			'context_window'      => 64000,
			'max_output_tokens'   => 4096,
			'supports_vision'     => false,
			'supports_functions'  => true,
			'supports_streaming'  => true,
		),
		'deepseek-reasoner' => array(
			'context_window'      => 64000,
			'max_output_tokens'   => 4096,
			'supports_vision'     => false,
			'supports_functions'  => false,
			'supports_streaming'  => false,
		),
	);
}