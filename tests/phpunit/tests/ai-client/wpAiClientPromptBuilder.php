<?php
/**
 * Tests for WP_AI_Client_Prompt_Builder.
 *
 * @group ai-client
 * @covers WP_AI_Client_Prompt_Builder
 */

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\DTO\ProviderModelsMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\ModelRequirements;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AiClient\Builders\PromptBuilder;
use WordPress\AiClient\Common\Exception\InvalidArgumentException as AiClientInvalidArgumentException;
use WordPress\AiClient\Common\Exception\TokenLimitReachedException;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\Http\Exception\NetworkException;
use WordPress\AiClient\Providers\Http\Exception\ServerException;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

require_once dirname( __DIR__, 2 ) . '/includes/wp-ai-client-mock-model-creation-trait.php';
require_once dirname( __DIR__, 2 ) . '/includes/wp-ai-client-test-abilities-trait.php';

class Tests_AI_Client_PromptBuilder extends WP_UnitTestCase {
	use WP_AI_Client_Mock_Model_Creation_Trait;
	use WP_AI_Client_Test_Abilities_Trait;

	/**
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $registry;

	/**
	 * Creates a test provider metadata instance.
	 *
	 * @return ProviderMetadata
	 */
	private function create_test_provider_metadata(): ProviderMetadata {
		return new ProviderMetadata( 'test-provider', 'Test Provider', ProviderTypeEnum::cloud() );
	}

	/**
	 * Creates text model metadata supporting any input modalities.
	 *
	 * @param string $id The model identifier.
	 * @return ModelMetadata
	 */
	private function create_text_model_metadata_with_input_support( string $id ): ModelMetadata {
		return new ModelMetadata(
			$id,
			'Test Text Model',
			array( CapabilityEnum::textGeneration() ),
			array(
				new SupportedOption( OptionEnum::inputModalities() ),
				new SupportedOption( OptionEnum::outputModalities() ),
			)
		);
	}

	/**
	 * Makes a ReflectionProperty or ReflectionMethod accessible on PHP < 8.1.
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
	 * Gets the value of a protected or private property from the wrapped prompt builder.
	 *
	 * @param WP_AI_Client_Prompt_Builder $builder  The WordPress prompt builder instance.
	 * @param string                      $property Property to get value for.
	 * @return mixed The property value.
	 */
	private function get_wrapped_prompt_builder_property_value( WP_AI_Client_Prompt_Builder $builder, string $property ) {
		$reflection_class = new ReflectionClass( WP_AI_Client_Prompt_Builder::class );
		$builder_property = $reflection_class->getProperty( 'builder' );
		self::set_accessible( $builder_property );
		$wrapped_builder = $builder_property->getValue( $builder );

		$reflection_class2 = new ReflectionClass( get_class( $wrapped_builder ) );
		$the_property      = $reflection_class2->getProperty( $property );
		self::set_accessible( $the_property );

		return $the_property->getValue( $wrapped_builder );
	}

	/**
	 * Gets the function declarations from the builder's model config.
	 *
	 * @param WP_AI_Client_Prompt_Builder $builder The builder to get declarations from.
	 * @return list<FunctionDeclaration>|null The function declarations or null if not set.
	 */
	private function get_function_declarations( WP_AI_Client_Prompt_Builder $builder ): ?array {
		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );
		return $config->getFunctionDeclarations();
	}

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::register_test_abilities();
	}

	/**
	 * Tear down after class.
	 */
	public static function tear_down_after_class() {
		self::unregister_test_abilities();

		parent::tear_down_after_class();
	}

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->registry = $this->createMock( ProviderRegistry::class );
	}

	/**
	 * Test that WP_AI_Client_Prompt_Builder can be instantiated.
	 *
	 * @ticket 64591
	 */
	public function test_instantiation() {
		$registry       = AiClient::defaultRegistry();
		$prompt_builder = new WP_AI_Client_Prompt_Builder( $registry );

		$this->assertInstanceOf( WP_AI_Client_Prompt_Builder::class, $prompt_builder );
	}

	/**
	 * Test that WP_AI_Client_Prompt_Builder can be instantiated with initial prompt content.
	 *
	 * @ticket 64591
	 */
	public function test_instantiation_with_prompt() {
		$registry       = AiClient::defaultRegistry();
		$prompt_builder = new WP_AI_Client_Prompt_Builder( $registry, 'Initial prompt text' );

		$this->assertInstanceOf( WP_AI_Client_Prompt_Builder::class, $prompt_builder );
	}

	/**
	 * Test that the constructor sets the default request timeout.
	 *
	 * @ticket 64591
	 */
	public function test_constructor_sets_default_request_timeout() {
		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry() );

		/** @var RequestOptions $request_options */
		$request_options = $this->get_wrapped_prompt_builder_property_value( $builder, 'requestOptions' );

		$this->assertInstanceOf( RequestOptions::class, $request_options );
		$this->assertEquals( 30, $request_options->getTimeout() );
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
				return 45;
			}
		);

		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry() );

		/** @var RequestOptions $request_options */
		$request_options = $this->get_wrapped_prompt_builder_property_value( $builder, 'requestOptions' );

		$this->assertInstanceOf( RequestOptions::class, $request_options );
		$this->assertEquals( 45, $request_options->getTimeout() );
	}

	/**
	 * Test method chaining with fluent methods.
	 *
	 * @ticket 64591
	 */
	public function test_method_chaining_returns_decorator() {
		$registry       = AiClient::defaultRegistry();
		$prompt_builder = new WP_AI_Client_Prompt_Builder( $registry );

		$result = $prompt_builder->with_text( 'Test text' );
		$this->assertSame( $prompt_builder, $result, 'with_text should return the decorator instance' );
		$this->assertInstanceOf( WP_AI_Client_Prompt_Builder::class, $result );

		$result = $prompt_builder->using_system_instruction( 'System instruction' );
		$this->assertSame( $prompt_builder, $result, 'using_system_instruction should return the decorator instance' );

		$result = $prompt_builder->using_max_tokens( 100 );
		$this->assertSame( $prompt_builder, $result, 'using_max_tokens should return the decorator instance' );

		$result = $prompt_builder->using_temperature( 0.7 );
		$this->assertSame( $prompt_builder, $result, 'using_temperature should return the decorator instance' );

		$result = $prompt_builder->using_top_p( 0.9 );
		$this->assertSame( $prompt_builder, $result, 'using_top_p should return the decorator instance' );

		$result = $prompt_builder->using_top_k( 50 );
		$this->assertSame( $prompt_builder, $result, 'using_top_k should return the decorator instance' );

		$result = $prompt_builder->using_presence_penalty( 0.5 );
		$this->assertSame( $prompt_builder, $result, 'using_presence_penalty should return the decorator instance' );

		$result = $prompt_builder->using_frequency_penalty( 0.5 );
		$this->assertSame( $prompt_builder, $result, 'using_frequency_penalty should return the decorator instance' );

		$result = $prompt_builder->as_output_mime_type( 'application/json' );
		$this->assertSame( $prompt_builder, $result, 'as_output_mime_type should return the decorator instance' );
	}

	/**
	 * Test complex method chaining scenario.
	 *
	 * @ticket 64591
	 */
	public function test_complex_method_chaining() {
		$registry       = AiClient::defaultRegistry();
		$prompt_builder = new WP_AI_Client_Prompt_Builder( $registry );

		$result = $prompt_builder
			->with_text( 'Test prompt' )
			->using_system_instruction( 'You are a helpful assistant' )
			->using_max_tokens( 500 )
			->using_temperature( 0.7 )
			->using_top_p( 0.9 );

		$this->assertSame( $prompt_builder, $result, 'Chained methods should return the same decorator instance' );
		$this->assertInstanceOf( WP_AI_Client_Prompt_Builder::class, $result );
	}

	/**
	 * Test that boolean-returning methods do not return the decorator.
	 *
	 * @ticket 64591
	 */
	public function test_boolean_methods_return_boolean() {
		$registry       = AiClient::defaultRegistry();
		$prompt_builder = new WP_AI_Client_Prompt_Builder( $registry, 'Test text' );

		$result = $prompt_builder->is_supported_for_text_generation();
		$this->assertIsBool( $result, 'is_supported_for_text_generation should return a boolean' );
		$this->assertNotSame( $prompt_builder, $result, 'is_supported_for_text_generation should not return the decorator' );
	}

	/**
	 * Test that calling a non-existent method returns WP_Error on termination.
	 *
	 * @ticket 64591
	 */
	public function test_invalid_method_returns_wp_error() {
		$registry       = AiClient::defaultRegistry();
		$prompt_builder = new WP_AI_Client_Prompt_Builder( $registry );

		// Invalid method call stores error but returns $this for chaining.
		$result = $prompt_builder->non_existent_method();
		$this->assertSame( $prompt_builder, $result );

		// Calling a terminate method should return the stored WP_Error.
		$result = $prompt_builder->generate_text();
		$this->assertWPError( $result );
		$this->assertSame( 'prompt_builder_error', $result->get_error_code() );
		$this->assertStringContainsString( 'non_existent_method does not exist', $result->get_error_message() );
	}

	/**
	 * Test that the wrapped builder is properly configured with the registry.
	 *
	 * @ticket 64591
	 */
	public function test_wrapped_builder_has_correct_registry() {
		$registry       = AiClient::defaultRegistry();
		$prompt_builder = new WP_AI_Client_Prompt_Builder( $registry );

		$reflection_class = new ReflectionClass( WP_AI_Client_Prompt_Builder::class );
		$builder_property = $reflection_class->getProperty( 'builder' );
		self::set_accessible( $builder_property );
		$wrapped_builder = $builder_property->getValue( $prompt_builder );

		$wrapped_builder_reflection = new ReflectionClass( get_class( $wrapped_builder ) );
		$registry_property          = $wrapped_builder_reflection->getProperty( 'registry' );
		self::set_accessible( $registry_property );
		$this->assertSame( $registry, $registry_property->getValue( $wrapped_builder ), 'Wrapped builder should have the same registry' );
	}

	/**
	 * Test method chaining with with_history.
	 *
	 * @ticket 64591
	 */
	public function test_method_chaining_with_history() {
		$registry       = AiClient::defaultRegistry();
		$prompt_builder = new WP_AI_Client_Prompt_Builder( $registry );

		$message1 = Message::fromArray(
			array(
				'role'  => 'user',
				'parts' => array(
					array(
						'text' => 'Hello',
					),
				),
			)
		);
		$message2 = Message::fromArray(
			array(
				'role'  => 'user',
				'parts' => array(
					array(
						'text' => 'How are you?',
					),
				),
			)
		);

		$result = $prompt_builder->with_history( $message1, $message2 );
		$this->assertSame( $prompt_builder, $result, 'with_history should return the decorator instance' );
		$this->assertInstanceOf( WP_AI_Client_Prompt_Builder::class, $result );
	}

	/**
	 * Test method chaining with using_model_config.
	 *
	 * @ticket 64591
	 */
	public function test_method_chaining_with_model_config() {
		$registry       = AiClient::defaultRegistry();
		$prompt_builder = new WP_AI_Client_Prompt_Builder( $registry );

		$config = new ModelConfig( array( 'maxTokens' => 100 ) );

		$result = $prompt_builder->using_model_config( $config );
		$this->assertSame( $prompt_builder, $result, 'using_model_config should return the decorator instance' );
		$this->assertInstanceOf( WP_AI_Client_Prompt_Builder::class, $result );
	}

	/**
	 * Tests constructor with no prompt.
	 *
	 * @ticket 64591
	 */
	public function test_constructor_with_no_prompt() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );

		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );
		$this->assertEmpty( $messages );
	}

	/**
	 * Tests constructor with string prompt.
	 *
	 * @ticket 64591
	 */
	public function test_constructor_with_string_prompt() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Hello, world!' );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 1, $messages );
		$this->assertInstanceOf( Message::class, $messages[0] );
		$this->assertEquals( 'Hello, world!', $messages[0]->getParts()[0]->getText() );
	}

	/**
	 * Tests constructor with MessagePart prompt.
	 *
	 * @ticket 64591
	 */
	public function test_constructor_with_message_part_prompt() {
		$part    = new MessagePart( 'Test message' );
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, $part );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 1, $messages );
		$this->assertInstanceOf( Message::class, $messages[0] );
		$this->assertEquals( 'Test message', $messages[0]->getParts()[0]->getText() );
	}

	/**
	 * Tests constructor with Message prompt.
	 *
	 * @ticket 64591
	 */
	public function test_constructor_with_message_prompt() {
		$message = new UserMessage( array( new MessagePart( 'User message' ) ) );
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, $message );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 1, $messages );
		$this->assertSame( $message, $messages[0] );
	}

	/**
	 * Tests constructor with list of Messages.
	 *
	 * @ticket 64591
	 */
	public function test_constructor_with_messages_list() {
		$messages = array(
			new UserMessage( array( new MessagePart( 'First' ) ) ),
			new ModelMessage( array( new MessagePart( 'Second' ) ) ),
			new UserMessage( array( new MessagePart( 'Third' ) ) ),
		);
		$builder  = new WP_AI_Client_Prompt_Builder( $this->registry, $messages );

		/** @var list<Message> $actual_messages */
		$actual_messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 3, $actual_messages );
		$this->assertSame( $messages, $actual_messages );
	}

	/**
	 * Tests constructor with MessageArrayShape.
	 *
	 * @ticket 64591
	 */
	public function test_constructor_with_message_array_shape() {
		$message_array = array(
			'role'  => 'user',
			'parts' => array(
				array(
					'type' => 'text',
					'text' => 'Hello from array',
				),
			),
		);
		$builder       = new WP_AI_Client_Prompt_Builder( $this->registry, $message_array );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 1, $messages );
		$this->assertInstanceOf( Message::class, $messages[0] );
		$this->assertEquals( 'Hello from array', $messages[0]->getParts()[0]->getText() );
	}

	/**
	 * Tests withText method.
	 *
	 * @ticket 64591
	 */
	public function test_with_text() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->with_text( 'Some text' );

		$this->assertSame( $builder, $result );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 1, $messages );
		$this->assertEquals( 'Some text', $messages[0]->getParts()[0]->getText() );
	}

	/**
	 * Tests withText appends to existing user message.
	 *
	 * @ticket 64591
	 */
	public function test_with_text_appends_to_existing_user_message() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Initial text' );
		$builder->with_text( ' Additional text' );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 1, $messages );
		$parts = $messages[0]->getParts();
		$this->assertCount( 2, $parts );
		$this->assertEquals( 'Initial text', $parts[0]->getText() );
		$this->assertEquals( ' Additional text', $parts[1]->getText() );
	}

	/**
	 * Tests withFile method with base64 data.
	 *
	 * @ticket 64591
	 */
	public function test_with_inline_file() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$base64  = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
		$result  = $builder->with_file( $base64, 'image/png' );

		$this->assertSame( $builder, $result );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 1, $messages );
		$file = $messages[0]->getParts()[0]->getFile();
		$this->assertInstanceOf( File::class, $file );
		$this->assertEquals( 'data:image/png;base64,' . $base64, $file->getDataUri() );
		$this->assertEquals( 'image/png', $file->getMimeType() );
	}

	/**
	 * Tests withFile method with remote URL.
	 *
	 * @ticket 64591
	 */
	public function test_with_remote_file() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->with_file( 'https://example.com/image.jpg', 'image/jpeg' );

		$this->assertSame( $builder, $result );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 1, $messages );
		$file = $messages[0]->getParts()[0]->getFile();
		$this->assertInstanceOf( File::class, $file );
		$this->assertEquals( 'https://example.com/image.jpg', $file->getUrl() );
		$this->assertEquals( 'image/jpeg', $file->getMimeType() );
	}

	/**
	 * Tests withFile with data URI.
	 *
	 * @ticket 64591
	 */
	public function test_with_inline_file_data_uri() {
		$builder  = new WP_AI_Client_Prompt_Builder( $this->registry );
		$data_uri = 'data:image/jpeg;base64,/9j/4AAQSkZJRg==';
		$result   = $builder->with_file( $data_uri );

		$this->assertSame( $builder, $result );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 1, $messages );
		$file = $messages[0]->getParts()[0]->getFile();
		$this->assertInstanceOf( File::class, $file );
		$this->assertEquals( 'image/jpeg', $file->getMimeType() );
	}

	/**
	 * Tests withFile with URL without explicit MIME type.
	 *
	 * @ticket 64591
	 */
	public function test_with_remote_file_without_mime_type() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->with_file( 'https://example.com/audio.mp3' );

		$this->assertSame( $builder, $result );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 1, $messages );
		$file = $messages[0]->getParts()[0]->getFile();
		$this->assertInstanceOf( File::class, $file );
		$this->assertEquals( 'https://example.com/audio.mp3', $file->getUrl() );
		$this->assertEquals( 'audio/mpeg', $file->getMimeType() );
	}

	/**
	 * Tests withFunctionResponse method.
	 *
	 * @ticket 64591
	 */
	public function test_with_function_response() {
		$function_response = new FunctionResponse( 'func_id', 'func_name', array( 'result' => 'data' ) );
		$builder           = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result            = $builder->with_function_response( $function_response );

		$this->assertSame( $builder, $result );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 1, $messages );
		$this->assertSame( $function_response, $messages[0]->getParts()[0]->getFunctionResponse() );
	}

	/**
	 * Tests withMessageParts method.
	 *
	 * @ticket 64591
	 */
	public function test_with_message_parts() {
		$part1 = new MessagePart( 'Part 1' );
		$part2 = new MessagePart( 'Part 2' );
		$part3 = new MessagePart( 'Part 3' );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->with_message_parts( $part1, $part2, $part3 );

		$this->assertSame( $builder, $result );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 1, $messages );
		$parts = $messages[0]->getParts();
		$this->assertCount( 3, $parts );
		$this->assertEquals( 'Part 1', $parts[0]->getText() );
		$this->assertEquals( 'Part 2', $parts[1]->getText() );
		$this->assertEquals( 'Part 3', $parts[2]->getText() );
	}

	/**
	 * Tests withHistory method.
	 *
	 * @ticket 64591
	 */
	public function test_with_history() {
		$history = array(
			new UserMessage( array( new MessagePart( 'User 1' ) ) ),
			new ModelMessage( array( new MessagePart( 'Model 1' ) ) ),
			new UserMessage( array( new MessagePart( 'User 2' ) ) ),
		);

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->with_history( ...$history );

		$this->assertSame( $builder, $result );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 3, $messages );
		$this->assertEquals( 'User 1', $messages[0]->getParts()[0]->getText() );
		$this->assertEquals( 'Model 1', $messages[1]->getParts()[0]->getText() );
		$this->assertEquals( 'User 2', $messages[2]->getParts()[0]->getText() );
	}

	/**
	 * Tests usingModel method.
	 *
	 * @ticket 64591
	 */
	public function test_using_model() {
		$model_config = new ModelConfig();
		$model        = $this->createMock( ModelInterface::class );
		$model->method( 'getConfig' )->willReturn( $model_config );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_model( $model );

		$this->assertSame( $builder, $result );

		/** @var ModelInterface $actual_model */
		$actual_model = $this->get_wrapped_prompt_builder_property_value( $builder, 'model' );
		$this->assertSame( $model, $actual_model );
	}

	/**
	 * Tests constructor with list of string parts.
	 *
	 * @ticket 64591
	 */
	public function test_constructor_with_string_parts_list() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, array( 'Part 1', 'Part 2', 'Part 3' ) );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 1, $messages );
		$this->assertInstanceOf( Message::class, $messages[0] );
		$parts = $messages[0]->getParts();
		$this->assertCount( 3, $parts );
		$this->assertEquals( 'Part 1', $parts[0]->getText() );
		$this->assertEquals( 'Part 2', $parts[1]->getText() );
		$this->assertEquals( 'Part 3', $parts[2]->getText() );
	}

	/**
	 * Tests constructor with mixed parts list.
	 *
	 * @ticket 64591
	 */
	public function test_constructor_with_mixed_parts_list() {
		$part1       = new MessagePart( 'Part 1' );
		$part2_array = array(
			'type' => 'text',
			'text' => 'Part 2',
		);

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, array( 'String part', $part1, $part2_array ) );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );

		$this->assertCount( 1, $messages );
		$parts = $messages[0]->getParts();
		$this->assertCount( 3, $parts );
		$this->assertEquals( 'String part', $parts[0]->getText() );
		$this->assertEquals( 'Part 1', $parts[1]->getText() );
		$this->assertEquals( 'Part 2', $parts[2]->getText() );
	}

	/**
	 * Tests full method chaining.
	 *
	 * @ticket 64591
	 */
	public function test_method_chaining() {
		$model = $this->createMock( ModelInterface::class );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder
			->with_text( 'Start of prompt' )
			->with_file( 'https://example.com/img.jpg', 'image/jpeg' )
			->using_model( $model )
			->using_system_instruction( 'Be helpful' )
			->using_max_tokens( 500 )
			->using_temperature( 0.8 )
			->using_top_p( 0.95 )
			->using_top_k( 50 )
			->using_candidate_count( 2 )
			->as_json_response();

		$this->assertSame( $builder, $result );

		/** @var list<Message> $messages */
		$messages = $this->get_wrapped_prompt_builder_property_value( $builder, 'messages' );
		$this->assertCount( 1, $messages );
		$this->assertCount( 2, $messages[0]->getParts() );

		/** @var ModelInterface $actual_model */
		$actual_model = $this->get_wrapped_prompt_builder_property_value( $builder, 'model' );
		$this->assertSame( $model, $actual_model );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( 'Be helpful', $config->getSystemInstruction() );
		$this->assertEquals( 500, $config->getMaxTokens() );
		$this->assertEquals( 0.8, $config->getTemperature() );
		$this->assertEquals( 0.95, $config->getTopP() );
		$this->assertEquals( 50, $config->getTopK() );
		$this->assertEquals( 2, $config->getCandidateCount() );
		$this->assertEquals( 'application/json', $config->getOutputMimeType() );
	}

	/**
	 * Tests usingModelPreference skips unavailable model IDs and falls back.
	 *
	 * @ticket 64591
	 */
	public function test_using_model_preference_skips_unavailable_model_id() {
		$result            = $this->create_test_result( 'Fallback model result' );
		$other_metadata    = $this->create_text_model_metadata_with_input_support( 'other-id' );
		$fallback_metadata = $this->create_text_model_metadata_with_input_support( 'fallback-id' );
		$model             = $this->create_mock_text_generation_model( $result, $fallback_metadata );

		$this->registry->expects( $this->once() )
			->method( 'getProviderId' )
			->with( 'test-provider' )
			->willReturn( 'test-provider' );

		$this->registry->expects( $this->once() )
			->method( 'findProviderModelsMetadataForSupport' )
			->with( 'test-provider', $this->isInstanceOf( ModelRequirements::class ) )
			->willReturn( array( $other_metadata, $fallback_metadata ) );

		$this->registry->expects( $this->once() )
			->method( 'getProviderModel' )
			->with( 'test-provider', 'fallback-id', $this->isInstanceOf( ModelConfig::class ) )
			->willReturn( $model );

		$this->registry->expects( $this->never() )
			->method( 'findModelsMetadataForSupport' );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Test prompt' );
		$builder->using_provider( 'test-provider' );
		$builder->using_model_preference( 'missing-id', 'fallback-id' );

		$actual_result = $builder->generate_text_result();

		$this->assertSame( $result, $actual_result );
	}

	/**
	 * Tests usingModelPreference falls back to discovery when no preferences available.
	 *
	 * @ticket 64591
	 */
	public function test_using_model_preference_falls_back_to_discovery() {
		$result                   = $this->create_test_result( 'Discovered model result' );
		$metadata                 = $this->create_text_model_metadata_with_input_support( 'discovered-id' );
		$provider_metadata        = $this->create_test_provider_metadata();
		$provider_models_metadata = new ProviderModelsMetadata( $provider_metadata, array( $metadata ) );

		$model = $this->create_mock_text_generation_model( $result, $metadata );

		$this->registry->expects( $this->once() )
			->method( 'findModelsMetadataForSupport' )
			->with( $this->isInstanceOf( ModelRequirements::class ) )
			->willReturn( array( $provider_models_metadata ) );

		$this->registry->expects( $this->once() )
			->method( 'getProviderModel' )
			->with( $provider_metadata->getId(), 'discovered-id', $this->isInstanceOf( ModelConfig::class ) )
			->willReturn( $model );

		$this->registry->expects( $this->never() )
			->method( 'findProviderModelsMetadataForSupport' );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Test prompt' );
		$builder->using_model_preference( 'unavailable-model' );

		$actual_result = $builder->generate_text_result();

		$this->assertSame( $result, $actual_result );
	}

	/**
	 * Tests usingModelPreference respects priority order when multiple preferred models are available.
	 *
	 * @ticket 64591
	 */
	public function test_using_model_preference_respects_order_when_multiple_available() {
		$result                 = $this->create_test_result( 'Second choice result' );
		$second_choice_metadata = $this->create_text_model_metadata_with_input_support( 'second-choice' );
		$third_choice_metadata  = $this->create_text_model_metadata_with_input_support( 'third-choice' );
		$provider_metadata      = $this->create_test_provider_metadata();

		$model = $this->create_mock_text_generation_model( $result, $second_choice_metadata );

		$provider_models_metadata = new ProviderModelsMetadata(
			$provider_metadata,
			array( $third_choice_metadata, $second_choice_metadata )
		);

		$this->registry->expects( $this->once() )
			->method( 'findModelsMetadataForSupport' )
			->with( $this->isInstanceOf( ModelRequirements::class ) )
			->willReturn( array( $provider_models_metadata ) );

		$this->registry->expects( $this->once() )
			->method( 'getProviderModel' )
			->with( $provider_metadata->getId(), 'second-choice', $this->isInstanceOf( ModelConfig::class ) )
			->willReturn( $model );

		$this->registry->expects( $this->never() )
			->method( 'findProviderModelsMetadataForSupport' );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Test prompt' );
		$builder->using_model_preference( 'first-choice', 'second-choice', 'third-choice' );

		$actual_result = $builder->generate_text_result();

		$this->assertSame( $result, $actual_result );
	}

	/**
	 * Tests usingModelPreference rejects invalid preference types, returning WP_Error.
	 *
	 * @ticket 64591
	 */
	public function test_using_model_preference_with_invalid_type_returns_wp_error() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );

		$builder->using_model_preference( 123 );
		$result = $builder->generate_text_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_invalid_argument', $result->get_error_code() );
		$this->assertStringContainsString(
			'Model preferences must be model identifiers',
			$result->get_error_message()
		);
	}

	/**
	 * Tests usingModelPreference rejects malformed preference tuples, returning WP_Error.
	 *
	 * @ticket 64591
	 */
	public function test_using_model_preference_with_invalid_tuple_returns_wp_error() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );

		$builder->using_model_preference(
			array(
				'provider' => 'test',
				'model'    => 'id',
			)
		);
		$result = $builder->generate_text_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_invalid_argument', $result->get_error_code() );
		$this->assertStringContainsString(
			'Model preference tuple must contain model identifier and provider ID.',
			$result->get_error_message()
		);
	}

	/**
	 * Tests usingModelPreference rejects empty preference identifiers, returning WP_Error.
	 *
	 * @ticket 64591
	 */
	public function test_using_model_preference_with_empty_identifier_returns_wp_error() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );

		$builder->using_model_preference( '   ' );
		$result = $builder->generate_text_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_invalid_argument', $result->get_error_code() );
		$this->assertStringContainsString(
			'Model preference identifiers cannot be empty.',
			$result->get_error_message()
		);
	}

	/**
	 * Tests usingModelPreference rejects calls without preferences, returning WP_Error.
	 *
	 * @ticket 64591
	 */
	public function test_using_model_preference_without_arguments_returns_wp_error() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );

		$builder->using_model_preference();
		$result = $builder->generate_text_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_invalid_argument', $result->get_error_code() );
		$this->assertStringContainsString(
			'At least one model preference must be provided.',
			$result->get_error_message()
		);
	}

	/**
	 * Tests usingModelConfig method.
	 *
	 * @ticket 64591
	 */
	public function test_using_model_config() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );

		$builder->using_system_instruction( 'Builder instruction' )
				->using_max_tokens( 500 )
				->using_temperature( 0.5 );

		$config = new ModelConfig();
		$config->setSystemInstruction( 'Config instruction' );
		$config->setMaxTokens( 1000 );
		$config->setTopP( 0.9 );
		$config->setTopK( 40 );

		$result = $builder->using_model_config( $config );

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $merged_config */
		$merged_config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( 'Builder instruction', $merged_config->getSystemInstruction() );
		$this->assertEquals( 500, $merged_config->getMaxTokens() );
		$this->assertEquals( 0.5, $merged_config->getTemperature() );
		$this->assertEquals( 0.9, $merged_config->getTopP() );
		$this->assertEquals( 40, $merged_config->getTopK() );
	}

	/**
	 * Tests usingModelConfig with custom options.
	 *
	 * @ticket 64591
	 */
	public function test_using_model_config_with_custom_options() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );

		$config = new ModelConfig();
		$config->setCustomOption( 'stopSequences', array( 'CONFIG_STOP' ) );
		$config->setCustomOption( 'otherOption', 'value' );

		$builder->using_model_config( $config );

		/** @var ModelConfig $merged_config */
		$merged_config  = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );
		$custom_options = $merged_config->getCustomOptions();

		$this->assertArrayHasKey( 'stopSequences', $custom_options );
		$this->assertIsArray( $custom_options['stopSequences'] );
		$this->assertEquals( array( 'CONFIG_STOP' ), $custom_options['stopSequences'] );
		$this->assertArrayHasKey( 'otherOption', $custom_options );
		$this->assertEquals( 'value', $custom_options['otherOption'] );

		$builder->using_stop_sequences( 'STOP' );

		/** @var ModelConfig $merged_config */
		$merged_config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( array( 'STOP' ), $merged_config->getStopSequences() );

		$custom_options = $merged_config->getCustomOptions();
		$this->assertArrayHasKey( 'stopSequences', $custom_options );
		$this->assertEquals( array( 'CONFIG_STOP' ), $custom_options['stopSequences'] );
		$this->assertArrayHasKey( 'otherOption', $custom_options );
		$this->assertEquals( 'value', $custom_options['otherOption'] );
	}

	/**
	 * Tests usingProvider method.
	 *
	 * @ticket 64591
	 */
	public function test_using_provider() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_provider( 'test-provider' );

		$this->assertSame( $builder, $result );

		$actual_provider = $this->get_wrapped_prompt_builder_property_value( $builder, 'providerIdOrClassName' );
		$this->assertEquals( 'test-provider', $actual_provider );
	}

	/**
	 * Tests usingSystemInstruction method.
	 *
	 * @ticket 64591
	 */
	public function test_using_system_instruction() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_system_instruction( 'You are a helpful assistant.' );

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( 'You are a helpful assistant.', $config->getSystemInstruction() );
	}

	/**
	 * Tests usingMaxTokens method.
	 *
	 * @ticket 64591
	 */
	public function test_using_max_tokens() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_max_tokens( 1000 );

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( 1000, $config->getMaxTokens() );
	}

	/**
	 * Tests usingTemperature method.
	 *
	 * @ticket 64591
	 */
	public function test_using_temperature() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_temperature( 0.7 );

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( 0.7, $config->getTemperature() );
	}

	/**
	 * Tests usingTopP method.
	 *
	 * @ticket 64591
	 */
	public function test_using_top_p() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_top_p( 0.9 );

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( 0.9, $config->getTopP() );
	}

	/**
	 * Tests usingTopK method.
	 *
	 * @ticket 64591
	 */
	public function test_using_top_k() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_top_k( 40 );

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( 40, $config->getTopK() );
	}

	/**
	 * Tests usingStopSequences method.
	 *
	 * @ticket 64591
	 */
	public function test_using_stop_sequences() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_stop_sequences( 'STOP', 'END', '###' );

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( array( 'STOP', 'END', '###' ), $config->getStopSequences() );
	}

	/**
	 * Tests usingCandidateCount method.
	 *
	 * @ticket 64591
	 */
	public function test_using_candidate_count() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_candidate_count( 3 );

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( 3, $config->getCandidateCount() );
	}

	/**
	 * Tests asOutputMimeType method.
	 *
	 * @ticket 64591
	 */
	public function test_using_output_mime() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->as_output_mime_type( 'application/json' );

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( 'application/json', $config->getOutputMimeType() );
	}

	/**
	 * Tests asOutputSchema method.
	 *
	 * @ticket 64591
	 */
	public function test_using_output_schema() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string' ),
			),
		);

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->as_output_schema( $schema );

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( $schema, $config->getOutputSchema() );
	}

	/**
	 * Tests asOutputModalities method.
	 *
	 * @ticket 64591
	 */
	public function test_using_output_modalities() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->as_output_modalities(
			ModalityEnum::text(),
			ModalityEnum::image()
		);

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$modalities = $config->getOutputModalities();
		$this->assertCount( 2, $modalities );
		$this->assertTrue( $modalities[0]->isText() );
		$this->assertTrue( $modalities[1]->isImage() );
	}

	/**
	 * Tests asJsonResponse method.
	 *
	 * @ticket 64591
	 */
	public function test_as_json_response() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->as_json_response();

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( 'application/json', $config->getOutputMimeType() );
	}

	/**
	 * Tests asJsonResponse with schema.
	 *
	 * @ticket 64591
	 */
	public function test_as_json_response_with_schema() {
		$schema  = array( 'type' => 'array' );
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->as_json_response( $schema );

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( 'application/json', $config->getOutputMimeType() );
		$this->assertEquals( $schema, $config->getOutputSchema() );
	}

	/**
	 * Tests validateMessages with empty messages returns WP_Error.
	 *
	 * @ticket 64591
	 */
	public function test_validate_messages_empty_returns_wp_error() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );

		$result = $builder->generate_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_invalid_argument', $result->get_error_code() );
		$this->assertStringContainsString( 'Cannot generate from an empty prompt', $result->get_error_message() );
	}

	/**
	 * Tests validateMessages with non-user first message returns WP_Error.
	 *
	 * @ticket 64591
	 */
	public function test_validate_messages_non_user_first_returns_wp_error() {
		$builder = new WP_AI_Client_Prompt_Builder(
			$this->registry,
			array(
				new ModelMessage( array( new MessagePart( 'Model says hi' ) ) ),
				new UserMessage( array( new MessagePart( 'User response' ) ) ),
			)
		);

		$result = $builder->generate_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_invalid_argument', $result->get_error_code() );
		$this->assertStringContainsString( 'The first message must be from a user role', $result->get_error_message() );
	}

	/**
	 * Tests validateMessages with non-user last message returns WP_Error.
	 *
	 * @ticket 64591
	 */
	public function test_validate_messages_non_user_last_returns_wp_error() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$builder->with_text( 'Initial user message' );

		$builder->with_history(
			new UserMessage( array( new MessagePart( 'Historical user message' ) ) ),
			new ModelMessage( array( new MessagePart( 'Historical model response' ) ) )
		);

		// Manually add a model message as the last message.
		$reflection_class = new ReflectionClass( WP_AI_Client_Prompt_Builder::class );
		$builder_property = $reflection_class->getProperty( 'builder' );
		self::set_accessible( $builder_property );
		$wrapped_builder   = $builder_property->getValue( $builder );
		$reflection_class2 = new ReflectionClass( get_class( $wrapped_builder ) );
		$messages_property = $reflection_class2->getProperty( 'messages' );
		self::set_accessible( $messages_property );
		$messages   = $messages_property->getValue( $wrapped_builder );
		$messages[] = new ModelMessage( array( new MessagePart( 'Final model message' ) ) );
		$messages_property->setValue( $wrapped_builder, $messages );

		$result = $builder->generate_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_invalid_argument', $result->get_error_code() );
		$this->assertStringContainsString( 'The last message must be from a user role', $result->get_error_message() );
	}

	/**
	 * Tests parseMessage with empty string returns WP_Error on termination.
	 *
	 * @ticket 64591
	 */
	public function test_parse_message_empty_string_returns_wp_error() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, '   ' );
		$result  = $builder->generate_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_invalid_argument', $result->get_error_code() );
		$this->assertStringContainsString( 'Cannot create a message from an empty string', $result->get_error_message() );
	}

	/**
	 * Tests parseMessage with empty array returns WP_Error on termination.
	 *
	 * @ticket 64591
	 */
	public function test_parse_message_empty_array_returns_wp_error() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, array() );
		$result  = $builder->generate_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_invalid_argument', $result->get_error_code() );
		$this->assertStringContainsString( 'Cannot create a message from an empty array', $result->get_error_message() );
	}

	/**
	 * Tests parseMessage with invalid type returns WP_Error on termination.
	 *
	 * @ticket 64591
	 */
	public function test_parse_message_invalid_type_returns_wp_error() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 123 );
		$result  = $builder->generate_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_invalid_argument', $result->get_error_code() );
		$this->assertStringContainsString( 'Input must be a string, MessagePart, MessagePartArrayShape', $result->get_error_message() );
	}

	/**
	 * Tests that wp_ai_client_prompt() with an empty string does not throw.
	 *
	 * Constructor exceptions are caught and surfaced as WP_Error from
	 * generating methods, consistent with the __call() wrapping behavior.
	 *
	 * @ticket 64591
	 */
	public function test_wp_ai_client_prompt_empty_string_returns_wp_error() {
		$builder = wp_ai_client_prompt( '   ' );

		$this->assertInstanceOf( WP_AI_Client_Prompt_Builder::class, $builder );

		$result = $builder->generate_text();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_invalid_argument', $result->get_error_code() );
	}

	/**
	 * Tests generateResult with text output modality.
	 *
	 * @ticket 64591
	 */
	public function test_generate_result_with_text_modality() {
		$result = $this->createMock( GenerativeAiResult::class );

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_text_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Test prompt' );
		$builder->using_model( $model );

		$actual_result = $builder->generate_result();
		$this->assertSame( $result, $actual_result );
	}

	/**
	 * Tests generateResult with image output modality.
	 *
	 * @ticket 64591
	 */
	public function test_generate_result_with_image_modality() {
		$result = new GenerativeAiResult(
			'test-result',
			array(
				new Candidate(
					new ModelMessage( array( new MessagePart( new File( 'data:image/png;base64,iVBORw0KGgo=', 'image/png' ) ) ) ),
					FinishReasonEnum::stop()
				),
			),
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_image_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate an image' );
		$builder->using_model( $model );
		$builder->as_output_modalities( ModalityEnum::image() );

		$actual_result = $builder->generate_result();
		$this->assertSame( $result, $actual_result );
	}

	/**
	 * Tests generateResult with audio output modality.
	 *
	 * @ticket 64591
	 */
	public function test_generate_result_with_audio_modality() {
		$result = new GenerativeAiResult(
			'test-result',
			array(
				new Candidate(
					new ModelMessage( array( new MessagePart( new File( 'data:audio/wav;base64,UklGRigE=', 'audio/wav' ) ) ) ),
					FinishReasonEnum::stop()
				),
			),
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_speech_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate speech' );
		$builder->using_model( $model );
		$builder->as_output_modalities( ModalityEnum::audio() );

		$actual_result = $builder->generate_result();
		$this->assertSame( $result, $actual_result );
	}

	/**
	 * Tests generateResult with multimodal output.
	 *
	 * @ticket 64591
	 */
	public function test_generate_result_with_multimodal_output() {
		$result = new GenerativeAiResult(
			'test-result',
			array( new Candidate( new ModelMessage( array( new MessagePart( 'Generated text' ) ) ), FinishReasonEnum::stop() ) ),
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_text_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate multimodal' );
		$builder->using_model( $model );
		$builder->as_output_modalities( ModalityEnum::text(), ModalityEnum::image() );

		$actual_result = $builder->generate_result();
		$this->assertSame( $result, $actual_result );
	}

	/**
	 * Tests generateResult returns WP_Error when model does not support modality.
	 *
	 * @ticket 64591
	 */
	public function test_generate_result_returns_wp_error_for_unsupported_modality() {
		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->createMock( ModelInterface::class );
		$model->method( 'metadata' )->willReturn( $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Test prompt' );
		$builder->using_model( $model );

		$result = $builder->generate_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_builder_error', $result->get_error_code() );
		$this->assertStringContainsString( 'does not support text generation', $result->get_error_message() );
	}

	/**
	 * Tests generateResult returns WP_Error for unsupported output modality.
	 *
	 * @ticket 64591
	 */
	public function test_generate_result_returns_wp_error_for_unsupported_output_modality() {
		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->createMock( ModelInterface::class );
		$model->method( 'metadata' )->willReturn( $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Test prompt' );
		$builder->using_model( $model );
		$builder->as_output_modalities( ModalityEnum::video() );

		$result = $builder->generate_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_builder_error', $result->get_error_code() );
		$this->assertStringContainsString( 'does not support video generation', $result->get_error_message() );
	}

	/**
	 * Tests generateTextResult method.
	 *
	 * @ticket 64591
	 */
	public function test_generate_text_result() {
		$result = new GenerativeAiResult(
			'test-result',
			array( new Candidate( new ModelMessage( array( new MessagePart( 'Generated text' ) ) ), FinishReasonEnum::stop() ) ),
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_text_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Test prompt' );
		$builder->using_model( $model );

		$actual_result = $builder->generate_text_result();
		$this->assertSame( $result, $actual_result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$modalities = $config->getOutputModalities();
		$this->assertNotNull( $modalities );
		$this->assertTrue( $modalities[0]->isText() );
	}

	/**
	 * Tests that the wrapped PromptBuilder receives the same event dispatcher as AiClient.
	 *
	 * @ticket 64935
	 */
	public function test_prompt_builder_passes_ai_client_event_dispatcher_to_wrapped_builder() {
		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry(), 'Test prompt' );

		$wrapped_dispatcher = $this->get_wrapped_prompt_builder_property_value( $builder, 'eventDispatcher' );

		$this->assertSame( AiClient::getEventDispatcher(), $wrapped_dispatcher );
		$this->assertInstanceOf( WP_AI_Client_Event_Dispatcher::class, $wrapped_dispatcher );
	}

	/**
	 * Tests that generate_text_result fires wp_ai_client_before_generate_result and wp_ai_client_after_generate_result in order.
	 *
	 * @ticket 64935
	 */
	public function test_generate_text_result_fires_lifecycle_action_hooks() {
		$result = new GenerativeAiResult(
			'test-result',
			array( new Candidate( new ModelMessage( array( new MessagePart( 'Generated text' ) ) ), FinishReasonEnum::stop() ) ),
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_text_generation_model( $result, $metadata );

		$hook_order = array();

		add_action(
			'wp_ai_client_before_generate_result',
			static function () use ( &$hook_order ) {
				$hook_order[] = 'before';
			}
		);
		add_action(
			'wp_ai_client_after_generate_result',
			static function () use ( &$hook_order ) {
				$hook_order[] = 'after';
			}
		);

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Test prompt' );
		$builder->using_model( $model );

		$actual_result = $builder->generate_text_result();

		$this->assertSame( $result, $actual_result );
		$this->assertSame( array( 'before', 'after' ), $hook_order );
	}

	/**
	 * Tests generateImageResult method.
	 *
	 * @ticket 64591
	 */
	public function test_generate_image_result() {
		$result = new GenerativeAiResult(
			'test-result',
			array(
				new Candidate(
					new ModelMessage( array( new MessagePart( new File( 'data:image/png;base64,iVBORw0KGgo=', 'image/png' ) ) ) ),
					FinishReasonEnum::stop()
				),
			),
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_image_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate image' );
		$builder->using_model( $model );

		$actual_result = $builder->generate_image_result();
		$this->assertSame( $result, $actual_result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$modalities = $config->getOutputModalities();
		$this->assertNotNull( $modalities );
		$this->assertTrue( $modalities[0]->isImage() );
	}

	/**
	 * Tests generateText returns WP_Error when no candidates.
	 *
	 * @ticket 64591
	 */
	public function test_generate_text_returns_wp_error_when_no_candidates() {
		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_text_generation_model_with_exception(
			new RuntimeException( 'No candidates were generated' ),
			$metadata
		);

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate text' );
		$builder->using_model( $model );

		$result = $builder->generate_text();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_builder_error', $result->get_error_code() );
		$this->assertStringContainsString( 'No candidates were generated', $result->get_error_message() );
	}

	/**
	 * Tests generateText returns WP_Error when message has no parts.
	 *
	 * @ticket 64591
	 */
	public function test_generate_text_returns_wp_error_when_no_parts() {
		$message   = new ModelMessage( array() );
		$candidate = new Candidate( $message, FinishReasonEnum::stop() );

		$result = new GenerativeAiResult(
			'test-result',
			array( $candidate ),
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_text_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate text' );
		$builder->using_model( $model );

		$actual_result = $builder->generate_text();

		$this->assertWPError( $actual_result );
		$this->assertSame( 'prompt_builder_error', $actual_result->get_error_code() );
		$this->assertStringContainsString( 'No text content found in first candidate', $actual_result->get_error_message() );
	}

	/**
	 * Tests generateText returns WP_Error when part has no text.
	 *
	 * @ticket 64591
	 */
	public function test_generate_text_returns_wp_error_when_part_has_no_text() {
		$file         = new File( 'https://example.com/image.jpg', 'image/jpeg' );
		$message_part = new MessagePart( $file );
		$message      = new ModelMessage( array( $message_part ) );
		$candidate    = new Candidate( $message, FinishReasonEnum::stop() );

		$result = new GenerativeAiResult(
			'test-result',
			array( $candidate ),
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_text_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate text' );
		$builder->using_model( $model );

		$actual_result = $builder->generate_text();

		$this->assertWPError( $actual_result );
		$this->assertSame( 'prompt_builder_error', $actual_result->get_error_code() );
		$this->assertStringContainsString( 'No text content found in first candidate', $actual_result->get_error_message() );
	}

	/**
	 * Tests generateTexts method.
	 *
	 * @ticket 64591
	 */
	public function test_generate_texts() {
		$candidates = array(
			new Candidate(
				new ModelMessage( array( new MessagePart( 'Text 1' ) ) ),
				FinishReasonEnum::stop()
			),
			new Candidate(
				new ModelMessage( array( new MessagePart( 'Text 2' ) ) ),
				FinishReasonEnum::stop()
			),
			new Candidate(
				new ModelMessage( array( new MessagePart( 'Text 3' ) ) ),
				FinishReasonEnum::stop()
			),
		);

		$result = new GenerativeAiResult(
			'test-result-id',
			$candidates,
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_text_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate texts' );
		$builder->using_model( $model );

		$texts = $builder->generate_texts( 3 );

		$this->assertCount( 3, $texts );
		$this->assertEquals( 'Text 1', $texts[0] );
		$this->assertEquals( 'Text 2', $texts[1] );
		$this->assertEquals( 'Text 3', $texts[2] );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( 3, $config->getCandidateCount() );
	}

	/**
	 * Tests generateTexts returns WP_Error when no text generated.
	 *
	 * @ticket 64591
	 */
	public function test_generate_texts_returns_wp_error_when_no_text_generated() {
		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_text_generation_model_with_exception(
			new RuntimeException( 'No text was generated from any candidates' ),
			$metadata
		);

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate texts' );
		$builder->using_model( $model );

		$result = $builder->generate_texts();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_builder_error', $result->get_error_code() );
		$this->assertStringContainsString( 'No text was generated from any candidates', $result->get_error_message() );
	}

	/**
	 * Tests generateImage method.
	 *
	 * @ticket 64591
	 */
	public function test_generate_image() {
		$file         = new File( 'https://example.com/generated.jpg', 'image/jpeg' );
		$message_part = new MessagePart( $file );
		$message      = new ModelMessage( array( $message_part ) );
		$candidate    = new Candidate( $message, FinishReasonEnum::stop() );

		$result = new GenerativeAiResult(
			'test-result',
			array( $candidate ),
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_image_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate image' );
		$builder->using_model( $model );

		$generated_file = $builder->generate_image();
		$this->assertSame( $file, $generated_file );
	}

	/**
	 * Tests generateImage returns WP_Error when no image file.
	 *
	 * @ticket 64591
	 */
	public function test_generate_image_returns_wp_error_when_no_file() {
		$message_part = new MessagePart( 'Text instead of image' );
		$message      = new ModelMessage( array( $message_part ) );
		$candidate    = new Candidate( $message, FinishReasonEnum::stop() );

		$result = new GenerativeAiResult(
			'test-result',
			array( $candidate ),
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_image_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate image' );
		$builder->using_model( $model );

		$actual_result = $builder->generate_image();

		$this->assertWPError( $actual_result );
		$this->assertSame( 'prompt_builder_error', $actual_result->get_error_code() );
		$this->assertStringContainsString( 'No file content found in first candidate', $actual_result->get_error_message() );
	}

	/**
	 * Tests generateImages method.
	 *
	 * @ticket 64591
	 */
	public function test_generate_images() {
		$files = array(
			new File( 'https://example.com/img1.jpg', 'image/jpeg' ),
			new File( 'https://example.com/img2.jpg', 'image/jpeg' ),
		);

		$candidates = array();
		foreach ( $files as $file ) {
			$candidates[] = new Candidate(
				new Message( MessageRoleEnum::model(), array( new MessagePart( $file ) ) ),
				FinishReasonEnum::stop()
			);
		}

		$result = new GenerativeAiResult(
			'test-result-id',
			$candidates,
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_image_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate images' );
		$builder->using_model( $model );

		$generated_files = $builder->generate_images( 2 );

		$this->assertCount( 2, $generated_files );
		$this->assertSame( $files[0], $generated_files[0] );
		$this->assertSame( $files[1], $generated_files[1] );
	}

	/**
	 * Tests convertTextToSpeech method.
	 *
	 * @ticket 64591
	 */
	public function test_convert_text_to_speech() {
		$file         = new File( 'https://example.com/audio.mp3', 'audio/mp3' );
		$message_part = new MessagePart( $file );
		$message      = new Message( MessageRoleEnum::model(), array( $message_part ) );
		$candidate    = new Candidate( $message, FinishReasonEnum::stop() );

		$result = new GenerativeAiResult(
			'test-result',
			array( $candidate ),
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_text_to_speech_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Convert this text' );
		$builder->using_model( $model );

		$audio_file = $builder->convert_text_to_speech();
		$this->assertSame( $file, $audio_file );
	}

	/**
	 * Tests convertTextToSpeeches method.
	 *
	 * @ticket 64591
	 */
	public function test_convert_text_to_speeches() {
		$files = array(
			new File( 'https://example.com/audio1.mp3', 'audio/mp3' ),
			new File( 'https://example.com/audio2.mp3', 'audio/mp3' ),
		);

		$candidates = array();
		foreach ( $files as $file ) {
			$candidates[] = new Candidate(
				new Message( MessageRoleEnum::model(), array( new MessagePart( $file ) ) ),
				FinishReasonEnum::stop()
			);
		}

		$result = new GenerativeAiResult(
			'test-result-id',
			$candidates,
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_text_to_speech_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Convert this text' );
		$builder->using_model( $model );

		$audio_files = $builder->convert_text_to_speeches( 2 );

		$this->assertCount( 2, $audio_files );
		$this->assertSame( $files[0], $audio_files[0] );
		$this->assertSame( $files[1], $audio_files[1] );
	}

	/**
	 * Tests generateSpeech method.
	 *
	 * @ticket 64591
	 */
	public function test_generate_speech() {
		$file         = new File( 'https://example.com/speech.mp3', 'audio/mp3' );
		$message_part = new MessagePart( $file );
		$message      = new Message( MessageRoleEnum::model(), array( $message_part ) );
		$candidate    = new Candidate( $message, FinishReasonEnum::stop() );

		$result = new GenerativeAiResult(
			'test-result',
			array( $candidate ),
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_speech_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate speech' );
		$builder->using_model( $model );

		$speech_file = $builder->generate_speech();
		$this->assertSame( $file, $speech_file );
	}

	/**
	 * Tests generateSpeeches method.
	 *
	 * @ticket 64591
	 */
	public function test_generate_speeches() {
		$files = array(
			new File( 'https://example.com/speech1.mp3', 'audio/mp3' ),
			new File( 'https://example.com/speech2.mp3', 'audio/mp3' ),
			new File( 'https://example.com/speech3.mp3', 'audio/mp3' ),
		);

		$candidates = array();
		foreach ( $files as $file ) {
			$candidates[] = new Candidate(
				new Message( MessageRoleEnum::model(), array( new MessagePart( $file ) ) ),
				FinishReasonEnum::stop(),
				10
			);
		}

		$result = new GenerativeAiResult(
			'test-result-id',
			$candidates,
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_text_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_speech_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate speech' );
		$builder->using_model( $model );

		$speech_files = $builder->generate_speeches( 3 );

		$this->assertCount( 3, $speech_files );
		$this->assertSame( $files[0], $speech_files[0] );
		$this->assertSame( $files[1], $speech_files[1] );
		$this->assertSame( $files[2], $speech_files[2] );
	}

	/**
	 * Tests generateVideo method.
	 *
	 * @ticket 64591
	 */
	public function test_generate_video() {
		$file         = new File( 'https://example.com/video.mp4', 'video/mp4' );
		$message_part = new MessagePart( $file );
		$message      = new Message( MessageRoleEnum::model(), array( $message_part ) );
		$candidate    = new Candidate( $message, FinishReasonEnum::stop() );

		$result = new GenerativeAiResult(
			'test-result',
			array( $candidate ),
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_video_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_video_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate video' );
		$builder->using_model( $model );

		$video_file = $builder->generate_video();
		$this->assertSame( $file, $video_file );
	}

	/**
	 * Tests generateVideos method.
	 *
	 * @ticket 64591
	 */
	public function test_generate_videos() {
		$files = array(
			new File( 'https://example.com/video1.mp4', 'video/mp4' ),
			new File( 'https://example.com/video2.mp4', 'video/mp4' ),
		);

		$candidates = array();
		foreach ( $files as $file ) {
			$candidates[] = new Candidate(
				new Message( MessageRoleEnum::model(), array( new MessagePart( $file ) ) ),
				FinishReasonEnum::stop()
			);
		}

		$result = new GenerativeAiResult(
			'test-result-id',
			$candidates,
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_video_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_video_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate videos' );
		$builder->using_model( $model );

		$video_files = $builder->generate_videos( 2 );

		$this->assertCount( 2, $video_files );
		$this->assertSame( $files[0], $video_files[0] );
		$this->assertSame( $files[1], $video_files[1] );
	}

	/**
	 * Tests generateVideoResult method.
	 *
	 * @ticket 64591
	 */
	public function test_generate_video_result() {
		$result = new GenerativeAiResult(
			'test-result',
			array(
				new Candidate(
					new ModelMessage( array( new MessagePart( new File( 'data:video/mp4;base64,AAAAAA==', 'video/mp4' ) ) ) ),
					FinishReasonEnum::stop()
				),
			),
			new TokenUsage( 100, 50, 150 ),
			$this->create_test_provider_metadata(),
			$this->create_test_video_model_metadata()
		);

		$metadata = $this->createMock( ModelMetadata::class );
		$metadata->method( 'getId' )->willReturn( 'test-model' );

		$model = $this->create_mock_video_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Generate video' );
		$builder->using_model( $model );

		$actual_result = $builder->generate_video_result();
		$this->assertSame( $result, $actual_result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$modalities = $config->getOutputModalities();
		$this->assertNotNull( $modalities );
		$this->assertTrue( $modalities[0]->isVideo() );
	}

	/**
	 * Tests asOutputMediaOrientation method.
	 *
	 * @ticket 64591
	 */
	public function test_as_output_media_orientation() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->as_output_media_orientation( MediaOrientationEnum::landscape() );

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertTrue( $config->getOutputMediaOrientation()->isLandscape() );
	}

	/**
	 * Tests asOutputMediaAspectRatio method.
	 *
	 * @ticket 64591
	 */
	public function test_as_output_media_aspect_ratio() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->as_output_media_aspect_ratio( '16:9' );

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( '16:9', $config->getOutputMediaAspectRatio() );
	}

	/**
	 * Tests asOutputSpeechVoice method.
	 *
	 * @ticket 64591
	 */
	public function test_as_output_speech_voice() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->as_output_speech_voice( 'alloy' );

		$this->assertSame( $builder, $result );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( 'alloy', $config->getOutputSpeechVoice() );
	}

	/**
	 * Tests using_abilities with ability name string.
	 *
	 * @ticket 64591
	 */
	public function test_using_ability_with_string() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_abilities( 'wpaiclienttests/simple' );

		$this->assertSame( $builder, $result );

		$declarations = $this->get_function_declarations( $builder );

		$this->assertNotNull( $declarations );
		$this->assertCount( 1, $declarations );
		$this->assertEquals( 'wpab__wpaiclienttests__simple', $declarations[0]->getName() );
		$this->assertEquals( 'A simple test ability with no parameters.', $declarations[0]->getDescription() );
	}

	/**
	 * Tests using_abilities with WP_Ability object.
	 *
	 * @ticket 64591
	 */
	public function test_using_ability_with_wp_ability_object() {
		$ability = wp_get_ability( 'wpaiclienttests/with-params' );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_abilities( $ability );

		$this->assertSame( $builder, $result );

		$declarations = $this->get_function_declarations( $builder );

		$this->assertNotNull( $declarations );
		$this->assertCount( 1, $declarations );
		$this->assertEquals( 'wpab__wpaiclienttests__with-params', $declarations[0]->getName() );
		$this->assertEquals( 'A test ability that accepts parameters.', $declarations[0]->getDescription() );

		$params = $declarations[0]->getParameters();
		$this->assertNotNull( $params );
		$this->assertArrayHasKey( 'properties', $params );
		$this->assertArrayHasKey( 'title', $params['properties'] );
	}

	/**
	 * Tests using_abilities with multiple abilities.
	 *
	 * @ticket 64591
	 */
	public function test_using_ability_with_multiple_abilities() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_abilities(
			'wpaiclienttests/simple',
			'wpaiclienttests/with-params',
			'wpaiclienttests/returns-error'
		);

		$this->assertSame( $builder, $result );

		$declarations = $this->get_function_declarations( $builder );

		$this->assertNotNull( $declarations );
		$this->assertCount( 3, $declarations );
		$this->assertEquals( 'wpab__wpaiclienttests__simple', $declarations[0]->getName() );
		$this->assertEquals( 'wpab__wpaiclienttests__with-params', $declarations[1]->getName() );
		$this->assertEquals( 'wpab__wpaiclienttests__returns-error', $declarations[2]->getName() );
	}

	/**
	 * Tests using_abilities skips non-existent abilities.
	 *
	 * @ticket 64591
	 *
	 * @expectedIncorrectUsage WP_AI_Client_Prompt_Builder::using_abilities
	 */
	public function test_using_ability_skips_nonexistent_abilities() {
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_abilities(
			'wpaiclienttests/simple',
			'nonexistent/ability',
			'wpaiclienttests/with-params'
		);

		$this->assertSame( $builder, $result );

		$declarations = $this->get_function_declarations( $builder );

		$this->assertNotNull( $declarations );
		$this->assertCount( 2, $declarations );
		$this->assertEquals( 'wpab__wpaiclienttests__simple', $declarations[0]->getName() );
		$this->assertEquals( 'wpab__wpaiclienttests__with-params', $declarations[1]->getName() );
	}

	/**
	 * Tests using_abilities with empty arguments returns self.
	 *
	 * @ticket 64591
	 */
	public function test_using_ability_with_no_arguments_returns_self() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_abilities();

		$this->assertSame( $builder, $result );

		$declarations = $this->get_function_declarations( $builder );

		$this->assertNull( $declarations );
	}

	/**
	 * Tests using_abilities with mixed strings and WP_Ability objects.
	 *
	 * @ticket 64591
	 */
	public function test_using_ability_with_mixed_types() {
		$ability = wp_get_ability( 'wpaiclienttests/with-params' );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_abilities(
			'wpaiclienttests/simple',
			$ability
		);

		$this->assertSame( $builder, $result );

		$declarations = $this->get_function_declarations( $builder );

		$this->assertNotNull( $declarations );
		$this->assertCount( 2, $declarations );
		$this->assertEquals( 'wpab__wpaiclienttests__simple', $declarations[0]->getName() );
		$this->assertEquals( 'wpab__wpaiclienttests__with-params', $declarations[1]->getName() );
	}

	/**
	 * Tests using_abilities with hyphenated ability name.
	 *
	 * @ticket 64591
	 */
	public function test_using_ability_with_hyphenated_name() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder->using_abilities( 'wpaiclienttests/hyphen-test' );

		$this->assertSame( $builder, $result );

		$declarations = $this->get_function_declarations( $builder );

		$this->assertNotNull( $declarations );
		$this->assertCount( 1, $declarations );
		$this->assertEquals( 'wpab__wpaiclienttests__hyphen-test', $declarations[0]->getName() );
	}

	/**
	 * Tests using_abilities can be chained with other methods.
	 *
	 * @ticket 64591
	 */
	public function test_using_ability_method_chaining() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );
		$result  = $builder
			->with_text( 'Test prompt' )
			->using_abilities( 'wpaiclienttests/simple' )
			->using_system_instruction( 'You are a helpful assistant' )
			->using_max_tokens( 500 );

		$this->assertSame( $builder, $result );

		$declarations = $this->get_function_declarations( $builder );

		$this->assertNotNull( $declarations );
		$this->assertCount( 1, $declarations );
		$this->assertEquals( 'wpab__wpaiclienttests__simple', $declarations[0]->getName() );

		/** @var ModelConfig $config */
		$config = $this->get_wrapped_prompt_builder_property_value( $builder, 'modelConfig' );

		$this->assertEquals( 'You are a helpful assistant', $config->getSystemInstruction() );
		$this->assertEquals( 500, $config->getMaxTokens() );
	}

	/**
	 * Tests that is_supported returns false when prevent prompt filter returns true.
	 *
	 * @ticket 64591
	 */
	public function test_is_supported_returns_false_when_ai_not_supported() {
		add_filter( 'wp_supports_ai', '__return_false' );

		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry(), 'Test prompt' );

		$this->assertFalse( $builder->is_supported() );
	}

	/**
	 * Tests that is_supported returns false when prevent prompt filter returns true.
	 *
	 * @ticket 64591
	 */
	public function test_is_supported_returns_false_when_filter_prevents_prompt() {
		add_filter( 'wp_ai_client_prevent_prompt', '__return_true' );

		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry(), 'Test prompt' );

		$this->assertFalse( $builder->is_supported() );
	}
	/**
	 * Tests that generate_result returns WP_Error when prevent prompt filter returns true.
	 *
	 * @ticket 64591
	 */
	public function test_generate_result_returns_wp_error_when_filter_prevents_prompt() {
		add_filter( 'wp_ai_client_prevent_prompt', '__return_true' );

		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry(), 'Test prompt' );

		$result = $builder->generate_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_prevented', $result->get_error_code() );
		$this->assertSame( 'Prompt execution was prevented by a filter.', $result->get_error_message() );
	}

	/**
	 * Tests that prevent prompt filter receives a clone of the builder instance.
	 *
	 * @ticket 64591
	 */
	public function test_prevent_prompt_filter_receives_cloned_builder_instance() {
		$captured_builder = null;

		add_filter(
			'wp_ai_client_prevent_prompt',
			static function ( $prevent, $builder ) use ( &$captured_builder ) {
				$captured_builder = $builder;
				return $prevent;
			},
			10,
			2
		);

		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry(), 'Test prompt' );

		// Test with is_supported().
		$builder->is_supported();
		$this->assertNotSame( $builder, $captured_builder, 'Filter should receive a clone, not the same instance' );
		$this->assertInstanceOf( WP_AI_Client_Prompt_Builder::class, $captured_builder );

		// Reset and test with generate_result().
		$captured_builder = null;
		$builder2         = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry(), 'Test prompt' );
		$builder2->generate_result();
		$this->assertNotSame( $builder2, $captured_builder, 'Filter should receive a clone, not the same instance' );
		$this->assertInstanceOf( WP_AI_Client_Prompt_Builder::class, $captured_builder );
	}

	/**
	 * Tests that once in error state, subsequent fluent calls return the same instance.
	 *
	 * @ticket 64591
	 */
	public function test_error_state_fluent_calls_return_same_instance() {
		$registry       = AiClient::defaultRegistry();
		$prompt_builder = new WP_AI_Client_Prompt_Builder( $registry );

		// Trigger an error state by calling a nonexistent method.
		$prompt_builder->nonexistent_method();

		$result = $prompt_builder->with_text( 'Test' );
		$this->assertSame( $prompt_builder, $result, 'Fluent method should return same instance when in error state' );

		$result = $prompt_builder->using_max_tokens( 100 );
		$this->assertSame( $prompt_builder, $result, 'Fluent method should return same instance when in error state' );
	}

	/**
	 * Tests that support check methods return false when in error state.
	 *
	 * @ticket 64591
	 */
	public function test_support_check_methods_return_false_in_error_state() {
		$registry       = AiClient::defaultRegistry();
		$prompt_builder = new WP_AI_Client_Prompt_Builder( $registry );

		// Trigger an error state by calling a nonexistent method.
		$prompt_builder->nonexistent_method();

		$this->assertFalse( $prompt_builder->is_supported(), 'is_supported should return false when in error state' );
		$this->assertFalse( $prompt_builder->is_supported_for_text_generation(), 'is_supported_for_text_generation should return false when in error state' );
	}

	/**
	 * Tests that generating methods return WP_Error when in error state.
	 *
	 * @ticket 64591
	 */
	public function test_generating_methods_return_wp_error_in_error_state() {
		$registry       = AiClient::defaultRegistry();
		$prompt_builder = new WP_AI_Client_Prompt_Builder( $registry );

		// Trigger an error state by calling a nonexistent method.
		$prompt_builder->nonexistent_method();

		$result = $prompt_builder->generate_text();
		$this->assertWPError( $result, 'generate_text should return WP_Error when in error state' );
		$this->assertSame( 'prompt_builder_error', $result->get_error_code() );
	}

	/**
	 * Tests that exception in terminating method is caught and returned as WP_Error.
	 *
	 * @ticket 64591
	 */
	public function test_exception_in_terminating_method_caught_and_returned() {
		$registry       = AiClient::defaultRegistry();
		$prompt_builder = new WP_AI_Client_Prompt_Builder( $registry );

		$error = $prompt_builder->generate_text();

		$this->assertWPError( $error, 'generate_text should return WP_Error when exception occurs' );
		$this->assertSame( 'prompt_invalid_argument', $error->get_error_code() );

		$error_data = $error->get_error_data();
		$this->assertIsArray( $error_data );
		$this->assertArrayHasKey( 'exception_class', $error_data );
		$this->assertNotEmpty( $error_data['exception_class'] );
	}

	/**
	 * Tests that exception in chained method is caught and returned by the terminating method as WP_Error.
	 *
	 * @ticket 64591
	 */
	/**
	 * Tests that every public method on the SDK PromptBuilder has a resolvable snake_case equivalent.
	 *
	 * @ticket 64591
	 *
	 * @dataProvider data_sdk_public_methods
	 *
	 * @param string $snake_case The snake_case method name.
	 * @param string $camel_case The original camelCase method name.
	 */
	public function test_snake_case_resolves_to_sdk_method( string $snake_case, string $camel_case ) {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry );

		$reflection = new ReflectionClass( WP_AI_Client_Prompt_Builder::class );
		$method     = $reflection->getMethod( 'get_builder_callable' );
		self::set_accessible( $method );

		$callable = $method->invoke( $builder, $snake_case );
		$this->assertIsCallable( $callable, sprintf( 'snake_case method "%s" should resolve to SDK method "%s"', $snake_case, $camel_case ) );
	}

	/**
	 * Data provider for test_snake_case_resolves_to_sdk_method.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_sdk_public_methods(): array {
		$reflection = new ReflectionClass( PromptBuilder::class );
		$data       = array();

		foreach ( $reflection->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
			if ( $method->isConstructor() || PromptBuilder::class !== $method->class || str_starts_with( $method->getName(), '__' ) ) {
				continue;
			}

			$camel_case = $method->getName();
			$snake_case = strtolower( preg_replace( '/[A-Z]/', '_$0', $camel_case ) );

			$data[ $snake_case ] = array( $snake_case, $camel_case );
		}

		return $data;
	}

	/**
	 * Tests that exception in chained method is caught and returned by the terminating method as WP_Error.
	 *
	 * @ticket 64591
	 */
	public function test_exception_in_chained_method_caught_and_returned_by_terminating_method() {
		$registry       = AiClient::defaultRegistry();
		$prompt_builder = new WP_AI_Client_Prompt_Builder( $registry );

		$result = $prompt_builder
			->with_text( 'Start of prompt' )
			->with_file( 'https://example.com/img.jpg', 'image/jpeg' )
			// Invalid: Only provider and model ID must be given.
			->using_model_preference( array( 'test-provider', 'test-model', 'test-version' ) )
			->using_system_instruction( 'Be helpful' )
			->generate_text();

		$this->assertWPError( $result, 'generate_text should return WP_Error when exception occurs' );
		$this->assertSame( 'prompt_invalid_argument', $result->get_error_code() );
		$this->assertSame( 'Model preference tuple must contain model identifier and provider ID.', $result->get_error_message() );

		$error_data = $result->get_error_data();
		$this->assertIsArray( $error_data );
		$this->assertArrayHasKey( 'exception_class', $error_data );
		$this->assertNotEmpty( $error_data['exception_class'] );
	}

	/**
	 * Invokes the private exception_to_wp_error method via reflection.
	 *
	 * @param WP_AI_Client_Prompt_Builder $builder   The builder instance.
	 * @param Exception                   $exception The exception to convert.
	 * @return WP_Error The resulting WP_Error.
	 */
	private function invoke_exception_to_wp_error( WP_AI_Client_Prompt_Builder $builder, Exception $exception ): WP_Error {
		$reflection = new ReflectionClass( WP_AI_Client_Prompt_Builder::class );
		$method     = $reflection->getMethod( 'exception_to_wp_error' );
		self::set_accessible( $method );

		return $method->invoke( $builder, $exception );
	}

	/**
	 * Tests exception_to_wp_error maps NetworkException correctly.
	 *
	 * @ticket 64591
	 */
	public function test_exception_to_wp_error_network_exception() {
		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry() );
		$error   = $this->invoke_exception_to_wp_error(
			$builder,
			new NetworkException( 'Connection timed out' )
		);

		$this->assertSame( 'prompt_network_error', $error->get_error_code() );
		$this->assertSame( 'Connection timed out', $error->get_error_message() );
		$this->assertSame( 503, $error->get_error_data()['status'] );
		$this->assertSame( NetworkException::class, $error->get_error_data()['exception_class'] );
	}

	/**
	 * Tests exception_to_wp_error maps ClientException with a custom code.
	 *
	 * @ticket 64591
	 */
	public function test_exception_to_wp_error_client_exception_with_code() {
		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry() );
		$error   = $this->invoke_exception_to_wp_error(
			$builder,
			new ClientException( 'Unauthorized', 401 )
		);

		$this->assertSame( 'prompt_client_error', $error->get_error_code() );
		$this->assertSame( 'Unauthorized', $error->get_error_message() );
		$this->assertSame( 401, $error->get_error_data()['status'] );
		$this->assertSame( ClientException::class, $error->get_error_data()['exception_class'] );
	}

	/**
	 * Tests exception_to_wp_error maps ClientException without a code to 400.
	 *
	 * @ticket 64591
	 */
	public function test_exception_to_wp_error_client_exception_without_code() {
		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry() );
		$error   = $this->invoke_exception_to_wp_error(
			$builder,
			new ClientException( 'Bad request' )
		);

		$this->assertSame( 'prompt_client_error', $error->get_error_code() );
		$this->assertSame( 'Bad request', $error->get_error_message() );
		$this->assertSame( 400, $error->get_error_data()['status'] );
	}

	/**
	 * Tests exception_to_wp_error maps ServerException with a custom code.
	 *
	 * @ticket 64591
	 */
	public function test_exception_to_wp_error_server_exception_with_code() {
		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry() );
		$error   = $this->invoke_exception_to_wp_error(
			$builder,
			new ServerException( 'Bad gateway', 502 )
		);

		$this->assertSame( 'prompt_upstream_server_error', $error->get_error_code() );
		$this->assertSame( 'Bad gateway', $error->get_error_message() );
		$this->assertSame( 502, $error->get_error_data()['status'] );
		$this->assertSame( ServerException::class, $error->get_error_data()['exception_class'] );
	}

	/**
	 * Tests exception_to_wp_error maps ServerException without a code to 500.
	 *
	 * @ticket 64591
	 */
	public function test_exception_to_wp_error_server_exception_without_code() {
		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry() );
		$error   = $this->invoke_exception_to_wp_error(
			$builder,
			new ServerException( 'Internal server error' )
		);

		$this->assertSame( 'prompt_upstream_server_error', $error->get_error_code() );
		$this->assertSame( 'Internal server error', $error->get_error_message() );
		$this->assertSame( 500, $error->get_error_data()['status'] );
	}

	/**
	 * Tests exception_to_wp_error maps TokenLimitReachedException correctly.
	 *
	 * @ticket 64591
	 */
	public function test_exception_to_wp_error_token_limit_reached_exception() {
		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry() );
		$error   = $this->invoke_exception_to_wp_error(
			$builder,
			new TokenLimitReachedException( 'Token limit exceeded', 4096 )
		);

		$this->assertSame( 'prompt_token_limit_reached', $error->get_error_code() );
		$this->assertSame( 'Token limit exceeded', $error->get_error_message() );
		$this->assertSame( 400, $error->get_error_data()['status'] );
		$this->assertSame( TokenLimitReachedException::class, $error->get_error_data()['exception_class'] );
	}

	/**
	 * Tests exception_to_wp_error maps InvalidArgumentException correctly.
	 *
	 * @ticket 64591
	 */
	public function test_exception_to_wp_error_invalid_argument_exception() {
		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry() );
		$error   = $this->invoke_exception_to_wp_error(
			$builder,
			new AiClientInvalidArgumentException( 'Invalid model parameter' )
		);

		$this->assertSame( 'prompt_invalid_argument', $error->get_error_code() );
		$this->assertSame( 'Invalid model parameter', $error->get_error_message() );
		$this->assertSame( 400, $error->get_error_data()['status'] );
		$this->assertSame( AiClientInvalidArgumentException::class, $error->get_error_data()['exception_class'] );
	}

	/**
	 * Tests exception_to_wp_error maps a generic Exception to the fallback error.
	 *
	 * @ticket 64591
	 */
	public function test_exception_to_wp_error_generic_exception() {
		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry() );
		$error   = $this->invoke_exception_to_wp_error(
			$builder,
			new Exception( 'Something went wrong' )
		);

		$this->assertSame( 'prompt_builder_error', $error->get_error_code() );
		$this->assertSame( 'Something went wrong', $error->get_error_message() );
		$this->assertSame( 500, $error->get_error_data()['status'] );
		$this->assertSame( 'Exception', $error->get_error_data()['exception_class'] );
	}

	/**
	 * Tests exception_to_wp_error always includes status and exception_class in error data.
	 *
	 * @ticket 64591
	 *
	 * @dataProvider data_exception_to_wp_error_error_data_structure
	 *
	 * @param Exception $exception The exception to convert.
	 */
	public function test_exception_to_wp_error_error_data_structure( Exception $exception ) {
		$builder = new WP_AI_Client_Prompt_Builder( AiClient::defaultRegistry() );
		$error   = $this->invoke_exception_to_wp_error( $builder, $exception );

		$data = $error->get_error_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertIsInt( $data['status'] );
		$this->assertArrayHasKey( 'exception_class', $data );
		$this->assertIsString( $data['exception_class'] );
	}

	/**
	 * Data provider for test_exception_to_wp_error_error_data_structure.
	 *
	 * @return array<string, array{0: Exception}>
	 */
	public static function data_exception_to_wp_error_error_data_structure(): array {
		return array(
			'NetworkException'           => array( new NetworkException( 'network error' ) ),
			'ClientException'            => array( new ClientException( 'client error', 422 ) ),
			'ServerException'            => array( new ServerException( 'server error', 503 ) ),
			'TokenLimitReachedException' => array( new TokenLimitReachedException( 'token limit' ) ),
			'InvalidArgumentException'   => array( new AiClientInvalidArgumentException( 'invalid arg' ) ),
			'generic Exception'          => array( new Exception( 'generic' ) ),
		);
	}
}
