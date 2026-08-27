<?php
/**
 * Tests for the WP_AI_Client_Embedding_Builder class.
 *
 * @group ai-client
 *
 * @coversDefaultClass WP_AI_Client_Embedding_Builder
 */

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\InvalidArgumentException as AiClientInvalidArgumentException;
use WordPress\AiClient\Common\Exception\TokenLimitReachedException;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\Http\Exception\NetworkException;
use WordPress\AiClient\Providers\Http\Exception\ServerException;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Results\DTO\Embedding;
use WordPress\AiClient\Results\DTO\EmbeddingResult;
use WordPress\AiClient\Results\DTO\TokenUsage;

class Tests_AI_Client_EmbeddingBuilder extends WP_UnitTestCase {

	/**
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $registry;

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->registry = $this->createMock( ProviderRegistry::class );
	}

	/**
	 * Makes a reflector accessible on PHP versions that require it.
	 *
	 * Since PHP 8.1 all reflection-accessed members are accessible by default,
	 * and PHP 8.5 deprecates the setAccessible() call.
	 *
	 * @param ReflectionProperty|ReflectionMethod $reflector The reflector to make accessible.
	 */
	private static function set_accessible( $reflector ) {
		if ( PHP_VERSION_ID < 80100 ) {
			$reflector->setAccessible( true );
		}
	}

	/**
	 * Gets the value of a protected or private property from the wrapped embedding builder.
	 *
	 * Model selection state (e.g. `model`, `requestOptions`) lives on the builder's
	 * ModelResolver, so properties not found on the wrapped builder are looked up there.
	 *
	 * @param WP_AI_Client_Embedding_Builder $builder  The WordPress embedding builder instance.
	 * @param string                         $property Property to get value for.
	 * @return mixed The property value.
	 */
	private function get_wrapped_builder_property_value( WP_AI_Client_Embedding_Builder $builder, string $property ) {
		$reflection_class = new ReflectionClass( WP_AI_Client_Embedding_Builder::class );
		$builder_property = $reflection_class->getProperty( 'builder' );
		self::set_accessible( $builder_property );
		$wrapped_builder = $builder_property->getValue( $builder );

		$reflection_class2 = new ReflectionClass( get_class( $wrapped_builder ) );
		if ( ! $reflection_class2->hasProperty( $property ) ) {
			$resolver_property = $reflection_class2->getProperty( 'modelResolver' );
			self::set_accessible( $resolver_property );
			$wrapped_builder = $resolver_property->getValue( $wrapped_builder );

			$reflection_class2 = new ReflectionClass( get_class( $wrapped_builder ) );
		}

		$the_property = $reflection_class2->getProperty( $property );
		self::set_accessible( $the_property );

		return $the_property->getValue( $wrapped_builder );
	}

	/**
	 * Invokes the protected throwable_to_wp_error() method on a builder.
	 *
	 * @param WP_AI_Client_Embedding_Builder $builder   The builder to invoke the method on.
	 * @param Throwable                       $throwable The throwable to convert.
 * @return WP_Error The resulting WP_Error.
	 */
	private function invoke_throwable_to_wp_error( WP_AI_Client_Embedding_Builder $builder, Throwable $throwable ): WP_Error {
		$reflection = new ReflectionClass( WP_AI_Client_Embedding_Builder::class );
		$method     = $reflection->getMethod( 'throwable_to_wp_error' );
		self::set_accessible( $method );

		return $method->invoke( $builder, $throwable );
	}

	/**
	 * Creates a test model metadata instance for embedding generation.
	 *
	 * @return ModelMetadata
	 */
	private function create_test_embedding_model_metadata(): ModelMetadata {
		return new ModelMetadata(
			'test-embedding-model',
			'Test Embedding Model',
			array( CapabilityEnum::embeddingGeneration() ),
			array( new SupportedOption( OptionEnum::inputModalities() ) )
		);
	}

	/**
	 * Creates a test EmbeddingResult with the given number of embeddings.
	 *
	 * @param int $count      The number of embeddings in the result.
	 * @param int $dimensions Optional. The dimensions of each embedding. Default 3.
	 * @return EmbeddingResult
	 */
	private function create_test_embedding_result( int $count, int $dimensions = 3 ): EmbeddingResult {
		$embeddings = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$embeddings[] = new Embedding( array_fill( 0, $dimensions, 0.1 * ( $i + 1 ) ), $dimensions );
		}

		return new EmbeddingResult(
			'test-embedding-result',
			$embeddings,
			$dimensions,
			new TokenUsage( 10, 0, 10 ),
			new ProviderMetadata( 'mock', 'Mock Provider', ProviderTypeEnum::cloud() ),
			$this->create_test_embedding_model_metadata()
		);
	}

	/**
	 * Creates a mock embedding generation model using an anonymous class.
	 *
	 * @param EmbeddingResult    $result   The result to return from generation.
	 * @param ModelMetadata|null $metadata Optional metadata.
	 * @return ModelInterface&EmbeddingGenerationModelInterface The mock model.
	 */
	private function create_mock_embedding_model(
		EmbeddingResult $result,
		?ModelMetadata $metadata = null
	): ModelInterface {
		$metadata = $metadata ?? $this->create_test_embedding_model_metadata();

		$provider_metadata = new ProviderMetadata(
			'mock',
			'Mock Provider',
			ProviderTypeEnum::cloud()
		);

		return new class( $metadata, $provider_metadata, $result ) implements ModelInterface, EmbeddingGenerationModelInterface {

			private ModelMetadata $metadata;
			private ProviderMetadata $provider_metadata;
			private EmbeddingResult $result;
			private ModelConfig $config;

			public function __construct(
				ModelMetadata $metadata,
				ProviderMetadata $provider_metadata,
				EmbeddingResult $result
			) {
				$this->metadata          = $metadata;
				$this->provider_metadata = $provider_metadata;
				$this->result            = $result;
				$this->config            = new ModelConfig();
			}

			public function metadata(): ModelMetadata {
				return $this->metadata;
			}

			public function providerMetadata(): ProviderMetadata {
				return $this->provider_metadata;
			}

			public function setConfig( ModelConfig $config ): void {
				$this->config = $config;
			}

			public function getConfig(): ModelConfig {
				return $this->config;
			}

			public function generateEmbeddingResult( array $inputs ): EmbeddingResult { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return $this->result;
			}
		};
	}

	/**
	 * Test that WP_AI_Client_Embedding_Builder can be instantiated.
	 *
	 * @ticket 64591
	 */
	public function test_instantiation() {
		$builder = new WP_AI_Client_Embedding_Builder( AiClient::defaultRegistry() );

		$this->assertInstanceOf( WP_AI_Client_Embedding_Builder::class, $builder );
	}

	/**
	 * Test that WP_AI_Client_Embedding_Builder can be instantiated with initial input.
	 *
	 * @ticket 64591
	 */
	public function test_instantiation_with_input() {
		$builder = new WP_AI_Client_Embedding_Builder( AiClient::defaultRegistry(), 'Test input' );

		$this->assertInstanceOf( WP_AI_Client_Embedding_Builder::class, $builder );
	}

	/**
	 * Test that the constructor parses a single string input into one message part.
	 *
	 * @ticket 64591
	 */
	public function test_constructor_with_string_input() {
		$builder = new WP_AI_Client_Embedding_Builder( $this->registry, 'Test input' );

		$inputs = $this->get_wrapped_builder_property_value( $builder, 'inputs' );

		$this->assertCount( 1, $inputs );
		$this->assertInstanceOf( MessagePart::class, $inputs[0] );
	}

	/**
	 * Test that the constructor parses a list of inputs into one message part each.
	 *
	 * @ticket 64591
	 */
	public function test_constructor_with_input_list() {
		$builder = new WP_AI_Client_Embedding_Builder( $this->registry, array( 'First', 'Second' ) );

		$inputs = $this->get_wrapped_builder_property_value( $builder, 'inputs' );

		$this->assertCount( 2, $inputs );
		$this->assertContainsOnlyInstancesOf( MessagePart::class, $inputs );
	}

	/**
	 * Test that the constructor sets the default request timeout.
	 *
	 * @ticket 64591
	 */
	public function test_constructor_sets_default_request_timeout() {
		$builder = new WP_AI_Client_Embedding_Builder( AiClient::defaultRegistry() );

		/** @var RequestOptions $request_options */
		$request_options = $this->get_wrapped_builder_property_value( $builder, 'requestOptions' );

		$this->assertInstanceOf( RequestOptions::class, $request_options );
		$this->assertSame( 30.0, $request_options->getTimeout() );
	}

	/**
	 * Test that the request timeout filter receives the embedding builder type.
	 *
	 * @ticket 64591
	 */
	public function test_request_timeout_filter_receives_builder_type() {
		$received_builder_type = null;

		add_filter(
			'wp_ai_client_default_request_timeout',
			static function ( $default_timeout, $builder_type ) use ( &$received_builder_type ) {
				$received_builder_type = $builder_type;
				return $default_timeout;
			},
			10,
			2
		);

		new WP_AI_Client_Embedding_Builder( AiClient::defaultRegistry() );

		$this->assertSame( WP_AI_Client_Embedding_Builder::class, $received_builder_type );
	}

	/**
	 * Test that the constructor allows overriding the default request timeout.
	 *
	 * @ticket 64591
	 */
	public function test_constructor_allows_overriding_request_timeout() {
		add_filter(
			'wp_ai_client_default_request_timeout',
			static function () {
				return 45.5;
			}
		);

		$builder = new WP_AI_Client_Embedding_Builder( AiClient::defaultRegistry() );

		/** @var RequestOptions $request_options */
		$request_options = $this->get_wrapped_builder_property_value( $builder, 'requestOptions' );

		$this->assertSame( 45.5, $request_options->getTimeout() );
	}

	/**
	 * Test that the constructor rejects an invalid request timeout, reporting the concrete class name.
	 *
	 * @ticket 64591
	 *
	 * @expectedIncorrectUsage WP_AI_Client_Embedding_Builder::__construct
	 */
	public function test_constructor_disallows_overriding_with_invalid_request_timeout() {
		add_filter( 'wp_ai_client_default_request_timeout', '__return_null' );

		$builder = new WP_AI_Client_Embedding_Builder( AiClient::defaultRegistry() );

		/** @var RequestOptions $request_options */
		$request_options = $this->get_wrapped_builder_property_value( $builder, 'requestOptions' );

		$this->assertSame( 30.0, $request_options->getTimeout() );
	}

	/**
	 * Test that fluent methods return the wrapper instance for method chaining.
	 *
	 * @ticket 64591
	 */
	public function test_method_chaining_returns_wrapper() {

		$builder = new WP_AI_Client_Embedding_Builder( $this->registry );

		$result = $builder->with_input( 'Test input' );
		$this->assertSame( $builder, $result, 'with_input() should return the wrapper instance' );

		$result = $builder->using_dimensions( 256 );
		$this->assertSame( $builder, $result, 'using_dimensions() should return the wrapper instance' );
	}

	/**
		* Test that a TypeError from a proxied SDK method is converted to a WP_Error.
	 *
	 * @ticket 65638
		*/
	public function test_type_error_from_using_dimensions_returns_wp_error() {

		$builder = new WP_AI_Client_Embedding_Builder( $this->registry );

		$result = $builder->using_dimensions( array( 256 ) );

		$this->assertSame( $builder, $result, 'A failed fluent call should return the wrapper instance' );

		$error = $builder->generate_embedding();

		$this->assertWPError( $error );
		$this->assertSame( 'embedding_builder_error', $error->get_error_code() );
		$this->assertSame( TypeError::class, $error->get_error_data()['exception_class'] );
	}
	/**
	 * Test that with_input() appends inputs to the wrapped builder.
	 *
	 * @ticket 64591
	 */
	public function test_with_input_appends_inputs() {
		$builder = new WP_AI_Client_Embedding_Builder( $this->registry, 'First' );
		$builder->with_input( 'Second', 'Third' );

		$inputs = $this->get_wrapped_builder_property_value( $builder, 'inputs' );

		$this->assertCount( 3, $inputs );
		$this->assertContainsOnlyInstancesOf( MessagePart::class, $inputs );
	}

	/**
	 * Test that using_dimensions() sets the dimensions on the model configuration.
	 *
	 * @ticket 64591
	 */
	public function test_using_dimensions_sets_model_config() {
		$builder = new WP_AI_Client_Embedding_Builder( $this->registry );
		$builder->using_dimensions( 256 );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_builder_property_value( $builder, 'modelConfig' );

		$this->assertSame( 256, $config->getDimensions() );
	}

	/**
	 * Test that the wrapper passes the AiClient event dispatcher to the wrapped builder.
	 *
	 * @ticket 64591
	 */
	public function test_passes_ai_client_event_dispatcher_to_wrapped_builder() {
		$builder = new WP_AI_Client_Embedding_Builder( $this->registry );

		$dispatcher = $this->get_wrapped_builder_property_value( $builder, 'eventDispatcher' );

		$this->assertSame( AiClient::getEventDispatcher(), $dispatcher );
	}

	/**
	 * Test that calling a nonexistent method puts the builder in an error state.
	 *
	 * @ticket 64591
	 */
	public function test_invalid_method_returns_wp_error() {
		$builder = new WP_AI_Client_Embedding_Builder( $this->registry );

		$builder->nonexistent_method();
		$result = $builder->generate_embeddings();

		$this->assertWPError( $result );
		$this->assertSame( 'embedding_builder_error', $result->get_error_code() );
	}

	/**
	 * Test that once in error state, subsequent fluent calls return the same instance.
	 *
	 * @ticket 64591
	 */
	public function test_error_state_fluent_calls_return_same_instance() {
		$builder = new WP_AI_Client_Embedding_Builder( $this->registry );

		// Trigger an error state by calling a nonexistent method.
		$builder->nonexistent_method();

		$result = $builder->with_input( 'Test' );
		$this->assertSame( $builder, $result, 'Fluent method should return same instance when in error state' );

		$result = $builder->using_dimensions( 256 );
		$this->assertSame( $builder, $result, 'Fluent method should return same instance when in error state' );
	}

	/**
	 * Test that is_supported() returns false when in error state.
	 *
	 * @ticket 64591
	 */
	public function test_support_check_method_returns_false_in_error_state() {
		$builder = new WP_AI_Client_Embedding_Builder( $this->registry );

		// Trigger an error state by calling a nonexistent method.
		$builder->nonexistent_method();

		$this->assertFalse( $builder->is_supported(), 'is_supported should return false when in error state' );
	}

	/**
	 * Test that an invalid constructor input puts the builder in an error state.
	 *
	 * @ticket 64591
	 */
	public function test_constructor_with_invalid_input_enters_error_state() {
		$builder = new WP_AI_Client_Embedding_Builder( $this->registry, '' );

		$result = $builder->generate_embeddings();

		$this->assertWPError( $result );
		$this->assertSame( 'embedding_invalid_argument', $result->get_error_code() );
	}

	/**
	 * Test that generating without any inputs returns a WP_Error.
	 *
	 * @ticket 64591
	 */
	public function test_generate_embeddings_without_input_returns_wp_error() {
		$builder = new WP_AI_Client_Embedding_Builder( $this->registry );

		$result = $builder->generate_embeddings();

		$this->assertWPError( $result );
		$this->assertSame( 'embedding_invalid_argument', $result->get_error_code() );
	}

	/**
	 * Test that is_supported() returns false when AI is not supported.
	 *
	 * @ticket 64591
	 */
	public function test_is_supported_returns_false_when_ai_not_supported() {
		add_filter( 'wp_supports_ai', '__return_false' );

		$builder = new WP_AI_Client_Embedding_Builder( AiClient::defaultRegistry(), 'Test input' );

		$this->assertFalse( $builder->is_supported() );
	}

	/**
	 * Test that is_supported() returns false when the prevent embedding filter returns true.
	 *
	 * @ticket 64591
	 */
	public function test_is_supported_returns_false_when_filter_prevents_embedding() {
		add_filter( 'wp_ai_client_prevent_embedding', '__return_true' );

		$builder = new WP_AI_Client_Embedding_Builder( AiClient::defaultRegistry(), 'Test input' );

		$this->assertFalse( $builder->is_supported() );
	}

	/**
	 * Test that is_supported() returns true for a model with the embedding generation capability.
	 *
	 * @ticket 64591
	 */
	public function test_is_supported_with_capable_model() {
		$model   = $this->create_mock_embedding_model( $this->create_test_embedding_result( 1 ) );
		$builder = new WP_AI_Client_Embedding_Builder( $this->registry, 'Test input' );

		$this->assertTrue( $builder->using_model( $model )->is_supported() );
	}

	/**
	 * Test that generating returns a WP_Error when the prevent embedding filter returns true.
	 *
	 * @ticket 64591
	 */
	public function test_generate_embeddings_returns_wp_error_when_filter_prevents_embedding() {
		add_filter( 'wp_ai_client_prevent_embedding', '__return_true' );

		$builder = new WP_AI_Client_Embedding_Builder( AiClient::defaultRegistry(), 'Test input' );

		$result = $builder->generate_embeddings();

		$this->assertWPError( $result );
		$this->assertSame( 'embedding_prevented', $result->get_error_code() );
		$this->assertSame( 'Embedding generation was prevented by a filter.', $result->get_error_message() );
		$this->assertSame( 503, $result->get_error_data()['status'] );
	}

	/**
	 * Test that generating returns a WP_Error when AI is not supported.
	 *
	 * @ticket 64591
	 */
	public function test_generate_embeddings_returns_wp_error_when_ai_not_supported() {
		add_filter( 'wp_supports_ai', '__return_false' );

		$builder = new WP_AI_Client_Embedding_Builder( AiClient::defaultRegistry(), 'Test input' );

		$result = $builder->generate_embeddings();

		$this->assertWPError( $result );
		$this->assertSame( 'embedding_prevented', $result->get_error_code() );
		$this->assertSame( 'AI features are not supported in this environment.', $result->get_error_message() );
	}

	/**
	 * Test that the prevent embedding filter receives a clone of the builder instance.
	 *
	 * @ticket 64591
	 */
	public function test_prevent_embedding_filter_receives_cloned_builder_instance() {
		$captured_builder = null;

		add_filter(
			'wp_ai_client_prevent_embedding',
			static function ( $prevent, $builder ) use ( &$captured_builder ) {
				$captured_builder = $builder;
				return $prevent;
			},
			10,
			2
		);

		$builder = new WP_AI_Client_Embedding_Builder( AiClient::defaultRegistry(), 'Test input' );

		// Test with is_supported().
		$builder->is_supported();
		$this->assertNotSame( $builder, $captured_builder, 'Filter should receive a clone, not the same instance' );
		$this->assertInstanceOf( WP_AI_Client_Embedding_Builder::class, $captured_builder );

		// Reset and test with generate_embeddings().
		$captured_builder = null;
		$builder2         = new WP_AI_Client_Embedding_Builder( AiClient::defaultRegistry(), 'Test input' );
		$builder2->generate_embeddings();
		$this->assertNotSame( $builder2, $captured_builder, 'Filter should receive a clone, not the same instance' );
		$this->assertInstanceOf( WP_AI_Client_Embedding_Builder::class, $captured_builder );
	}

	/**
	 * Test that generate_embedding_result() returns the result from the model.
	 *
	 * @ticket 64591
	 */
	public function test_generate_embedding_result() {
		$result = $this->create_test_embedding_result( 1 );
		$model  = $this->create_mock_embedding_model( $result );

		$builder = new WP_AI_Client_Embedding_Builder( $this->registry, 'Test input' );
		$builder->using_model( $model );

		$actual_result = $builder->generate_embedding_result();

		$this->assertSame( $result, $actual_result );
	}

	/**
	 * Test that generate_embedding() returns a single embedding.
	 *
	 * @ticket 64591
	 */
	public function test_generate_embedding() {
		$result = $this->create_test_embedding_result( 1 );
		$model  = $this->create_mock_embedding_model( $result );

		$builder = new WP_AI_Client_Embedding_Builder( $this->registry, 'Test input' );
		$builder->using_model( $model );

		$embedding = $builder->generate_embedding();

		$this->assertInstanceOf( Embedding::class, $embedding );
		$this->assertSame( $result->getEmbedding(), $embedding );
	}

	/**
	 * Test that generate_embeddings() returns one embedding per input.
	 *
	 * @ticket 64591
	 */
	public function test_generate_embeddings() {
		$result = $this->create_test_embedding_result( 2 );
		$model  = $this->create_mock_embedding_model( $result );

		$builder = new WP_AI_Client_Embedding_Builder( $this->registry, array( 'First', 'Second' ) );
		$builder->using_model( $model );

		$embeddings = $builder->generate_embeddings();

		$this->assertIsArray( $embeddings );
		$this->assertCount( 2, $embeddings );
		$this->assertSame( $result->getEmbeddings(), $embeddings );
	}

	/**
	 * Test that generate_embedding() returns a WP_Error when multiple inputs are configured.
	 *
	 * @ticket 64591
	 */
	public function test_generate_embedding_returns_wp_error_with_multiple_inputs() {
		$builder = new WP_AI_Client_Embedding_Builder( $this->registry, array( 'First', 'Second' ) );

		$result = $builder->generate_embedding();

		$this->assertWPError( $result );
		$this->assertSame( 'embedding_invalid_argument', $result->get_error_code() );
	}

	/**
	 * Test that a mismatched embedding count from the model results in a WP_Error.
	 *
	 * @ticket 64591
	 */
	public function test_generate_embeddings_returns_wp_error_on_count_mismatch() {
		$result = $this->create_test_embedding_result( 1 );
		$model  = $this->create_mock_embedding_model( $result );

		$builder = new WP_AI_Client_Embedding_Builder( $this->registry, array( 'First', 'Second' ) );
		$builder->using_model( $model );

		$error = $builder->generate_embeddings();

		$this->assertWPError( $error );
		$this->assertSame( 'embedding_builder_error', $error->get_error_code() );
	}

	/**
	 * Test that throwable_to_wp_error() maps exceptions to embedding-prefixed error codes.
	 *
	 * @ticket 64591
	 *
	 * @dataProvider data_throwable_to_wp_error_mapping
	 *
	 * @param Throwable $throwable       The throwable to convert.
	 * @param string    $expected_code   The expected WP_Error code.
	 * @param int       $expected_status The expected HTTP status in the error data.
	 */
	public function test_throwable_to_wp_error_mapping( Throwable $throwable, string $expected_code, int $expected_status ) {
		$builder = new WP_AI_Client_Embedding_Builder( AiClient::defaultRegistry() );
		$error   = $this->invoke_throwable_to_wp_error( $builder, $throwable );

		$this->assertSame( $expected_code, $error->get_error_code() );
		$this->assertSame( $throwable->getMessage(), $error->get_error_message() );
		$this->assertSame( $expected_status, $error->get_error_data()['status'] );
		$this->assertSame( get_class( $throwable ), $error->get_error_data()['exception_class'] );
	}

	/**
	 * Data provider for {@see self::test_throwable_to_wp_error_mapping()}.
	 *
	 * @return array<string, array{0: Throwable, 1: string, 2: int}>
	 */
	public static function data_throwable_to_wp_error_mapping(): array {
		return array(
			'NetworkException'             => array( new NetworkException( 'network error' ), 'embedding_network_error', 503 ),
			'ClientException with code'    => array( new ClientException( 'unauthorized', 401 ), 'embedding_client_error', 401 ),
			'ClientException without code' => array( new ClientException( 'bad request' ), 'embedding_client_error', 400 ),
			'ServerException with code'    => array( new ServerException( 'bad gateway', 502 ), 'embedding_upstream_server_error', 502 ),
			'ServerException without code' => array( new ServerException( 'server error' ), 'embedding_upstream_server_error', 500 ),
			'TokenLimitReachedException'   => array( new TokenLimitReachedException( 'token limit' ), 'embedding_token_limit_reached', 400 ),
			'InvalidArgumentException'     => array( new AiClientInvalidArgumentException( 'invalid arg' ), 'embedding_invalid_argument', 400 ),
			'generic Exception'            => array( new Exception( 'generic' ), 'embedding_builder_error', 500 ),
		);
	}
}
