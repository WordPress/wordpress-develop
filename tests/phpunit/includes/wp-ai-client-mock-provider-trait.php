<?php
/**
 * Trait for creating mock providers for testing.
 *
 * @package WordPress
 * @subpackage AI
 */

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\AbstractProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Mock provider availability with a controllable flag.
 *
 * @since 7.0.0
 */
class Mock_Connectors_Test_Provider_Availability implements ProviderAvailabilityInterface {

	/**
	 * Whether the provider should report as configured.
	 *
	 * @var bool
	 */
	public static bool $is_configured = true;

	/**
	 * Checks if the provider is configured.
	 *
	 * @return bool
	 */
	public function isConfigured(): bool {
		return self::$is_configured;
	}
}

/**
 * Mock model metadata directory that returns an empty list.
 *
 * @since 7.0.0
 */
class Mock_Connectors_Test_Model_Metadata_Directory implements ModelMetadataDirectoryInterface {

	/**
	 * Lists model metadata.
	 *
	 * @return array Empty array.
	 */
	public function listModelMetadata(): array {
		return array();
	}

	/**
	 * Checks if a model exists.
	 *
	 * @param string $model_id The model ID.
	 * @return bool Always false.
	 */
	public function hasModelMetadata( string $model_id ): bool {
		return false;
	}

	/**
	 * Gets model metadata.
	 *
	 * @param string $model_id The model ID.
	 * @throws \InvalidArgumentException Always, as no models are available.
	 */
	public function getModelMetadata( string $model_id ): ModelMetadata {
		throw new \InvalidArgumentException( 'No models available.' );
	}
}

/**
 * Minimal mock provider for testing connector functions that interact
 * with the AI Client registry.
 *
 * Uses API key authentication and delegates availability to
 * Mock_Connectors_Test_Provider_Availability so tests can toggle
 * the configured state.
 *
 * @since 7.0.0
 */
class Mock_Connectors_Test_Provider extends AbstractProvider {

	/**
	 * Creates the provider metadata.
	 *
	 * @return ProviderMetadata
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			'mock_connectors_test',
			'Mock Connectors Test',
			ProviderTypeEnum::cloud(),
			null,
			RequestAuthenticationMethod::apiKey()
		);
	}

	/**
	 * Creates the provider availability checker.
	 *
	 * @return ProviderAvailabilityInterface
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new Mock_Connectors_Test_Provider_Availability();
	}

	/**
	 * Creates the model metadata directory.
	 *
	 * @return ModelMetadataDirectoryInterface
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new Mock_Connectors_Test_Model_Metadata_Directory();
	}

	/**
	 * Creates a model instance.
	 *
	 * @param ModelMetadata    $model_metadata    The model metadata.
	 * @param ProviderMetadata $provider_metadata The provider metadata.
	 * @throws \RuntimeException Always, as model creation is not needed for these tests.
	 */
	protected static function createModel(
		ModelMetadata $model_metadata,
		ProviderMetadata $provider_metadata
	): ModelInterface {
		throw new \RuntimeException( 'Not implemented.' );
	}
}

/**
 * Trait providing a mock AI provider for testing connector functions.
 *
 * Registers a mock provider in the AI Client singleton registry with
 * controllable availability. Tests can toggle the configured state via
 * set_mock_provider_configured().
 *
 * @since 7.0.0
 */
trait WP_AI_Client_Mock_Provider_Trait {

	/**
	 * Registers the mock provider in the AI Client registry.
	 *
	 * Safe to call multiple times; skips registration if already done.
	 * Must be called from set_up_before_class() after parent::set_up_before_class().
	 */
	private static function register_mock_connectors_provider(): void {
		$registry = AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( 'mock_connectors_test' ) ) {
			$registry->registerProvider( Mock_Connectors_Test_Provider::class );
		}
	}

	/**
	 * Sets whether the mock provider reports as configured.
	 *
	 * @param bool $is_configured Whether the provider should be configured.
	 */
	private static function set_mock_provider_configured( bool $is_configured ): void {
		Mock_Connectors_Test_Provider_Availability::$is_configured = $is_configured;
	}

	/**
	 * Unregisters the mock provider's connector setting.
	 *
	 * Reverses the side effect of _wp_register_default_connector_settings()
	 * for the mock provider so that subsequent test classes start with a clean slate.
	 * Must be called from tear_down_after_class() after running tests.
	 */
	private static function unregister_mock_connector_setting(): void {
		$setting_name = 'connectors_ai_mock_connectors_test_api_key';
		unregister_setting( 'connectors', $setting_name );
		remove_filter( "option_{$setting_name}", '_wp_connectors_mask_api_key' );
	}
}
