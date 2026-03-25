<?php
/**
 * Ollama Local AI Provider for WordPress AI Security Edition.
 *
 * @package WordPress
 * @subpackage AI_Connector_Pro
 * @since 7.1.0
 */

declare( strict_types = 1 );

namespace WordPress\AiClient\Providers\AiConnectorPro;

use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Ollama Provider class (local models).
 *
 * @since 7.1.0
 */
class Ollama_Provider extends Abstract_Connector_Provider {

	/**
	 * Provider ID.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $provider_id = 'ollama';

	/**
	 * Provider name.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $provider_name = 'Ollama';

	/**
	 * API base URL (default).
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $api_base_url = 'http://localhost:11434';

	/**
	 * Credentials URL.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $credentials_url = 'https://ollama.com/download';

	/**
	 * Description.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $description = 'Ollama - Run AI models locally on your machine (Llama, Mistral, Phi, etc.)';

	/**
	 * Available models.
	 *
	 * @since 7.1.0
	 * @var array<string, string>
	 */
	protected static array $models = array(
		'llama3.3'           => AbstractOpenAiCompatibleTextGenerationModel::class,
		'llama3.2'           => AbstractOpenAiCompatibleTextGenerationModel::class,
		'llama3.1'           => AbstractOpenAiCompatibleTextGenerationModel::class,
		'llama3'             => AbstractOpenAiCompatibleTextGenerationModel::class,
		'llama2'             => AbstractOpenAiCompatibleTextGenerationModel::class,
		'mistral'           => AbstractOpenAiCompatibleTextGenerationModel::class,
		'mixtral'           => AbstractOpenAiCompatibleTextGenerationModel::class,
		'phi3'              => AbstractOpenAiCompatibleTextGenerationModel::class,
		'phi4'              => AbstractOpenAiCompatibleTextGenerationModel::class,
		'qwen2.5'           => AbstractOpenAiCompatibleTextGenerationModel::class,
		'codellama'         => AbstractOpenAiCompatibleTextGenerationModel::class,
		'deepseek-coder'    => AbstractOpenAiCompatibleTextGenerationModel::class,
		'gemma2'            => AbstractOpenAiCompatibleTextGenerationModel::class,
		'command-r'         => AbstractOpenAiCompatibleTextGenerationModel::class,
		'command-r-plus'    => AbstractOpenAiCompatibleTextGenerationModel::class,
	);

	/**
	 * Model requirements.
	 *
	 * @since 7.1.0
	 * @var array<string, array>
	 */
	protected static array $model_requirements = array(
		'llama3.3' => array(
			'context_window'      => 128000,
			'max_output_tokens'   => 4096,
			'supports_vision'     => false,
			'supports_functions'  => true,
			'supports_streaming'  => true,
		),
		'mistral' => array(
			'context_window'      => 8192,
			'max_output_tokens'   => 4096,
			'supports_vision'     => false,
			'supports_functions'  => true,
			'supports_streaming'  => true,
		),
		'codellama' => array(
			'context_window'      => 16384,
			'max_output_tokens'   => 4096,
			'supports_vision'     => false,
			'supports_functions'  => true,
			'supports_streaming'  => true,
		),
	);

	/**
	 * Check if provider is local.
	 *
	 * @since 7.1.0
	 * @return bool
	 */
	protected static function is_local(): bool {
		return true;
	}

	/**
	 * Get provider availability - checks if Ollama is running.
	 *
	 * @since 7.1.0
	 * @return \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface
	 */
	public static function availability(): \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface {
		return new class implements \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface {
			public function is_available(): bool {
				$endpoint = get_option( 'ai_connector_pro_ollama_endpoint', 'http://localhost:11434' );
				$response = wp_remote_get( $endpoint . '/api/tags', array( 'timeout' => 5 ) );
				return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
			}
			public function get_status(): string {
				return $this->is_available() ? 'available' : 'unavailable';
			}
		};
	}
}