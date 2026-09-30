<?php
/**
 * Trait for creating mock providers for testing.
 *
 * @package WordPress
 * @subpackage AI
 */

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\AbstractProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;

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
			'mock-connectors-test',
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
 * Mock model metadata directory that lists models over HTTP.
 *
 * Built on the same base class as the official provider plugins, so requests go
 * through the WP AI Client HTTP transporter and can be mocked with the
 * `pre_http_request` filter.
 *
 * @since 7.2.0
 */
class Mock_Connectors_Test_Http_Model_Metadata_Directory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * Creates a request to the mock provider API.
	 *
	 * @param HttpMethodEnum $method  The HTTP method.
	 * @param string         $path    The API path.
	 * @param array          $headers The request headers.
	 * @param mixed          $data    The request data.
	 * @return Request The request.
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		return new Request( $method, Mock_Connectors_Test_Http_Provider::url( $path ), $headers, $data );
	}

	/**
	 * Parses the list models response.
	 *
	 * @param Response $response The response.
	 * @return ModelMetadata[] The listed models.
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		$data   = $response->getData();
		$models = array();
		foreach ( $data['data'] ?? array() as $model ) {
			$models[] = new ModelMetadata( $model['id'], $model['id'], array( CapabilityEnum::textGeneration() ), array() );
		}
		return $models;
	}
}

/**
 * Mock provider that checks its availability by listing models over HTTP,
 * like the official provider plugins.
 *
 * @since 7.2.0
 */
class Mock_Connectors_Test_Http_Provider extends AbstractApiProvider {

	/**
	 * Returns the base URL of the mock provider API.
	 *
	 * @return string
	 */
	protected static function baseUrl(): string {
		return 'https://api.example.com/v1';
	}

	/**
	 * Creates the provider metadata.
	 *
	 * @return ProviderMetadata
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			'mock-connectors-http-test',
			'Mock Connectors HTTP Test',
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
		return new ListModelsApiBasedProviderAvailability( static::modelMetadataDirectory() );
	}

	/**
	 * Creates the model metadata directory.
	 *
	 * @return ModelMetadataDirectoryInterface
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new Mock_Connectors_Test_Http_Model_Metadata_Directory();
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
		$ai_registry = AiClient::defaultRegistry();
		if ( ! $ai_registry->hasProvider( 'mock-connectors-test' ) ) {
			$ai_registry->registerProvider( Mock_Connectors_Test_Provider::class );
		}

		// Also register in the WP connector registry if not already present.
		$connector_registry = WP_Connector_Registry::get_instance();
		if ( null !== $connector_registry && ! $connector_registry->is_registered( 'mock-connectors-test' ) ) {
			$connector_registry->register(
				'mock-connectors-test',
				array(
					'name'           => 'Mock Connectors Test',
					'description'    => '',
					'type'           => 'ai_provider',
					'authentication' => array(
						'method'          => 'api_key',
						'credentials_url' => null,
						'setting_name'    => 'connectors_ai_mock_connectors_test_api_key',
					),
				)
			);
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

	/**
	 * How the HTTP mock provider's models endpoint responds.
	 *
	 * @var int|WP_Error HTTP status code, or a WP_Error to simulate a network failure.
	 */
	private $mock_models_endpoint_response = 200;

	/**
	 * API keys sent to the HTTP mock provider's models endpoint, in request order.
	 *
	 * @var string[]
	 */
	private array $mock_models_endpoint_api_keys = array();

	/**
	 * Registers the HTTP mock provider in the AI Client registry.
	 *
	 * Safe to call multiple times; skips registration if already done.
	 * Must be called from set_up_before_class() after parent::set_up_before_class().
	 */
	private static function register_mock_connectors_http_provider(): void {
		$ai_registry = AiClient::defaultRegistry();
		if ( ! $ai_registry->hasProvider( 'mock-connectors-http-test' ) ) {
			$ai_registry->registerProvider( Mock_Connectors_Test_Http_Provider::class );
		}
	}

	/**
	 * Sets how the HTTP mock provider's models endpoint responds.
	 *
	 * @param int|WP_Error $response HTTP status code, or a WP_Error to simulate a network failure.
	 */
	private function mock_models_endpoint_response( $response ): void {
		$this->mock_models_endpoint_response = $response;
		add_filter( 'pre_http_request', array( $this, 'filter_mock_models_endpoint_request' ), 10, 3 );
	}

	/**
	 * Responds to requests to the HTTP mock provider's models endpoint.
	 *
	 * @param false|array|WP_Error $response    A preemptive return value of an HTTP request.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url         The request URL.
	 * @return false|array|WP_Error The mocked models endpoint response, otherwise the unchanged value.
	 */
	public function filter_mock_models_endpoint_request( $response, $parsed_args, $url ) {
		if ( Mock_Connectors_Test_Http_Provider::url( 'models' ) !== $url ) {
			return $response;
		}

		$this->mock_models_endpoint_api_keys[] = str_replace( 'Bearer ', '', $parsed_args['headers']['Authorization'] ?? '' );

		if ( is_wp_error( $this->mock_models_endpoint_response ) ) {
			return $this->mock_models_endpoint_response;
		}

		$status = $this->mock_models_endpoint_response;

		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => 200 === $status ? '{"data":[{"id":"mock-model"}]}' : '{"error":{"message":"Mock error."}}',
			'response' => array(
				'code'    => $status,
				'message' => get_status_header_desc( $status ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
