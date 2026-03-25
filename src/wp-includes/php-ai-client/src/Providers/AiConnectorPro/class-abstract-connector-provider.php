<?php
/**
 * AI Connector Pro - Base Provider for WordPress AI Security Edition.
 *
 * @package WordPress
 * @subpackage AI_Connector_Pro
 * @since 7.1.0
 */

declare( strict_types = 1 );

namespace WordPress\AiClient\Providers\AiConnectorPro;

use WordPress\AiClient\Providers\Contracts\ProviderInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\ModelRequirements;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;

/**
 * Base provider class for AI Connector Pro providers.
 *
 * @since 7.1.0
 */
abstract class Abstract_Connector_Provider implements ProviderInterface {

	/**
	 * Provider ID.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $provider_id = '';

	/**
	 * Provider name.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $provider_name = '';

	/**
	 * API base URL.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $api_base_url = '';

	/**
	 * Credentials URL.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $credentials_url = '';

	/**
	 * Description.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	protected static string $description = '';

	/**
	 * Available models.
	 *
	 * @since 7.1.0
	 * @var array<string, string>
	 */
	protected static array $models = array();

	/**
	 * Model requirements per model.
	 *
	 * @since 7.1.0
	 * @var array<string, array>
	 */
	protected static array $model_requirements = array();

	/**
	 * Get provider metadata.
	 *
	 * @since 7.1.0
	 * @return ProviderMetadata
	 */
	public static function metadata(): ProviderMetadata {
		$auth_method = null;
		if ( ! static::is_local() ) {
			$auth_method = new RequestAuthenticationMethod( RequestAuthenticationMethod::API_KEY );
		}

		return new ProviderMetadata(
			static::$provider_id,
			static::$provider_name,
			static::is_local() ? ProviderTypeEnum::SERVER : ProviderTypeEnum::CLOUD,
			static::$credentials_url,
			$auth_method,
			static::$description
		);
	}

	/**
	 * Check if provider is local (like Ollama).
	 *
	 * @since 7.1.0
	 * @return bool
	 */
	protected static function is_local(): bool {
		return false;
	}

	/**
	 * Get available models.
	 *
	 * @since 7.1.0
	 * @return array<string, string>
	 */
	public static function get_available_models(): array {
		return static::$models;
	}

	/**
	 * Create a model instance.
	 *
	 * @since 7.1.0
	 * @param string $model_id Model identifier.
	 * @param ModelConfig|null $config Model configuration.
	 * @return ModelInterface
	 * @throws InvalidArgumentException If model not found.
	 */
	public static function model( string $model_id, ?ModelConfig $config = null ): ModelInterface {
		$models = static::get_available_models();

		if ( ! isset( $models[ $model_id ] ) ) {
			throw new InvalidArgumentException(
				sprintf(
					__( 'Model "%s" not found for provider "%s".', 'default' ),
					$model_id,
					static::$provider_name
				)
			);
		}

		$model_class = $models[ $model_id ];

		if ( ! class_exists( $model_class ) ) {
			throw new InvalidArgumentException(
				sprintf(
					__( 'Model class "%s" not found.', 'default' ),
					$model_class
				)
			);
		}

		return new $model_class( $model_id, $config );
	}

	/**
	 * Get provider availability.
	 *
	 * @since 7.1.0
	 * @return \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface
	 */
	public static function availability(): \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface {
		return new class implements \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface {
			public function is_available(): bool {
				return true;
			}
			public function get_status(): string {
				return 'available';
			}
		};
	}

	/**
	 * Get model metadata directory.
	 *
	 * @since 7.1.0
	 * @return \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface
	 */
	public static function modelMetadataDirectory(): \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface {
		$requirements = static::$model_requirements;

		return new class( $requirements ) implements \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface {
			private array $requirements;

			public function __construct( array $requirements ) {
				$this->requirements = $requirements;
			}

			public function get( string $model_id ): ?ModelMetadata {
				if ( ! isset( $this->requirements[ $model_id ] ) ) {
					return null;
				}
				return ModelMetadata::fromArray( $this->requirements[ $model_id ] );
			}

			public function has( string $model_id ): bool {
				return isset( $this->requirements[ $model_id ] );
			}

			public function all(): array {
				$all = array();
				foreach ( $this->requirements as $model_id => $data ) {
					$all[ $model_id ] = ModelMetadata::fromArray( $data );
				}
				return $all;
			}
		};
	}

	/**
	 * Get API base URL.
	 *
	 * @since 7.1.0
	 * @return string
	 */
	public static function get_api_base_url(): string {
		return static::$api_base_url;
	}

	/**
	 * Get authentication instance.
	 *
	 * @since 7.1.0
	 * @param string $api_key API key.
	 * @return \WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface
	 */
	public static function get_authentication( string $api_key ): \WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface {
		return new ApiKeyRequestAuthentication( $api_key );
	}
}