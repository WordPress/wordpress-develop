<?php
/**
 * Mistral AI Provider for WordPress AI Security Edition.
 *
 * @package WordPress
 * @subpackage AI_Connector_Pro
 * @since 7.1.0
 */

declare( strict_types = 1 );

namespace WordPress\AiClient\Providers\AiConnectorPro;

use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Mistral Provider class.
 *
 * @since 7.1.0
 */
class Mistral_Provider extends Abstract_Connector_Provider {

	/**
	 * Provider ID.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $provider_id = 'mistral';

	/**
	 * Provider name.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $provider_name = 'Mistral AI';

	/**
	 * API base URL.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $api_base_url = 'https://api.mistral.ai/v1';

	/**
	 * Credentials URL.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $credentials_url = 'https://console.mistral.ai/api-keys';

	/**
	 * Description.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $description = 'Mistral AI - French AI startup with strong coding models.';

	/**
	 * Available models.
	 *
	 * @since 7.1.0
	 * @var array<string, string>
	 */
	protected static array $models = array(
		'mistral-large-latest'   => AbstractOpenAiCompatibleTextGenerationModel::class,
		'mistral-medium-latest'  => AbstractOpenAiCompatibleTextGenerationModel::class,
		'mistral-small-latest'   => AbstractOpenAiCompatibleTextGenerationModel::class,
		'codestral-latest'       => AbstractOpenAiCompatibleTextGenerationModel::class,
		'codestral-mamba'        => AbstractOpenAiCompatibleTextGenerationModel::class,
		'pixtral-large-latest'  => AbstractOpenAiCompatibleTextGenerationModel::class,
		'pixtral-12b-2409'       => AbstractOpenAiCompatibleTextGenerationModel::class,
	);

	/**
	 * Model requirements.
	 *
	 * @since 7.1.0
	 * @var array<string, array>
	 */
	protected static array $model_requirements = array(
		'mistral-large-latest' => array(
			'context_window'      => 128000,
			'max_output_tokens'    => 16384,
			'supports_vision'      => false,
			'supports_functions'  => true,
			'supports_streaming'   => true,
		),
		'mistral-small-latest' => array(
			'context_window'       => 32000,
			'max_output_tokens'    => 4096,
			'supports_vision'      => false,
			'supports_functions'   => true,
			'supports_streaming'   => true,
		),
		'codestral-latest' => array(
			'context_window'       => 64000,
			'max_output_tokens'    => 4096,
			'supports_vision'      => false,
			'supports_functions'   => true,
			'supports_streaming'   => true,
		),
	);
}