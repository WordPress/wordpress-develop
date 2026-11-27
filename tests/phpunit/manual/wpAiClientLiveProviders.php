<?php
/**
 * Tests that live AI providers can call the core abilities through the AI Client.
 *
 * These tests send real requests to paid APIs, so they are outside the test suites and only run
 * when this directory is named. The tests of a provider only run when its API key is in the
 * `WP_TESTS_AI_ANTHROPIC_API_KEY`, `WP_TESTS_AI_GOOGLE_API_KEY` or `WP_TESTS_AI_OPENAI_API_KEY`
 * environment variable or constant:
 *
 *     WP_TESTS_AI_ANTHROPIC_API_KEY=your-key vendor/bin/phpunit --group external-http --test-suffix .php tests/phpunit/manual
 *
 * A provider uses its default model, unless its `WP_TESTS_AI_*_MODEL` environment variable or
 * constant, such as `WP_TESTS_AI_GOOGLE_MODEL`, names another one.
 *
 * @group ai-client
 * @group abilities-api
 * @group external-http
 *
 * @covers WP_AI_Client_Prompt_Builder
 * @covers WP_AI_Client_Ability_Function_Resolver
 */

use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\Http\Exception\NetworkException;
use WordPress\AiClient\Providers\Http\Exception\ServerException;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AnthropicAiProvider\Provider\AnthropicProvider;
use WordPress\GoogleAiProvider\Provider\GoogleProvider;
use WordPress\OpenAiAiProvider\Provider\OpenAiProvider;

class Tests_AI_Client_LiveProviders extends WP_UnitTestCase {

	/**
	 * Provider classes keyed by provider ID.
	 *
	 * @var array<string, class-string>
	 */
	private const PROVIDERS = array(
		'anthropic' => AnthropicProvider::class,
		'google'    => GoogleProvider::class,
		'openai'    => OpenAiProvider::class,
	);

	/**
	 * For each core ability, a question no other ability answers and the value the answer must contain.
	 *
	 * @var array<string, array{question: string, answer: string}>
	 */
	private const QUESTIONS = array(
		'core/get-site-info'        => array(
			'question' => 'What is the character encoding of this site?',
			'answer'   => 'UTF-8',
		),
		'core/get-user-info'        => array(
			'question' => 'What is my display name?',
			'answer'   => 'Barnaby Quillfeather',
		),
		'core/get-environment-info' => array(
			'question' => 'Which PHP version does this site run?',
			// Without the suffix some builds add to `phpversion()`, such as `-1ubuntu2`, which a model may leave out.
			'answer'   => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION,
		),
		'core/settings-get'         => array(
			'question' => 'What is the timezone setting of this site?',
			'answer'   => 'Pacific/Chatham',
		),
		'core/users-query'          => array(
			'question' => 'What is the name of the user with the username "live_provider_editor"?',
			'answer'   => 'Marigold Featherstone',
		),
		'core/content-query'        => array(
			'question' => 'What is the title of the post with the slug "live-provider-post"?',
			'answer'   => 'Quokka Lighthouse Notes',
		),
	);

	/**
	 * ID of the administrator who asks the questions.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Creates the users, the post and the option the questions are about, and registers the core abilities.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		global $wp_current_filter;

		self::$admin_id = $factory->user->create(
			array(
				'role'         => 'administrator',
				'display_name' => 'Barnaby Quillfeather',
			)
		);

		// On multisite, only a super admin may read the environment information.
		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}

		$factory->user->create(
			array(
				'role'         => 'editor',
				'user_login'   => 'live_provider_editor',
				'display_name' => 'Marigold Featherstone',
			)
		);
		$factory->post->create(
			array(
				'post_name'  => 'live-provider-post',
				'post_title' => 'Quokka Lighthouse Notes',
			)
		);
		update_option( 'timezone_string', 'Pacific/Chatham' );

		// The core abilities can only be registered while their registration actions run.
		$wp_current_filter[] = 'wp_abilities_api_categories_init';
		wp_register_core_ability_categories();
		array_pop( $wp_current_filter );

		$wp_current_filter[] = 'wp_abilities_api_init';
		wp_register_core_abilities();
		array_pop( $wp_current_filter );
	}

	/**
	 * Cleans up the registered abilities and categories.
	 */
	public static function wpTearDownAfterClass(): void {
		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}
	}

	/**
	 * Signs in the administrator and allows slow responses.
	 */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::$admin_id );

		// The request that lists the models of a provider has no timeout of its own.
		add_filter( 'http_request_timeout', static fn () => 30 );
		add_filter( 'wp_ai_client_default_request_timeout', static fn () => 120.0 );
	}

	/**
	 * Tests that a provider answers questions by calling the core abilities it is given.
	 *
	 * @ticket 66707
	 *
	 * @dataProvider data_providers_and_abilities
	 *
	 * @param string   $provider  Provider ID.
	 * @param string[] $abilities Names of the abilities given to the model.
	 */
	public function test_provider_answers_with_core_abilities( string $provider, array $abilities ): void {
		$questions = array_intersect_key( self::QUESTIONS, array_flip( $abilities ) );

		list( $answer, $called, $model ) = $this->ask( $provider, $abilities, implode( ' ', array_column( $questions, 'question' ) ) );

		// A model may quote a value as it is in the JSON it received, where slashes are escaped.
		$answer  = str_replace( '\/', '/', $answer );
		$missing = array_filter( array_column( $questions, 'answer' ), static fn ( $value ) => false === stripos( $answer, $value ) );
		$context = "Model: $model. Answer: $answer";

		$this->assertSameSets( $abilities, $called, "Every ability given to the model should run without an error. $context" );
		$this->assertSame( array(), $missing, "The answer should contain every expected value. $context" );
	}

	/**
	 * Data provider.
	 *
	 * For every provider, each core ability on its own and then all of them together.
	 *
	 * @return array<string, array{0: string, 1: string[]}>
	 */
	public static function data_providers_and_abilities(): array {
		$data = array();

		foreach ( array_keys( self::PROVIDERS ) as $provider ) {
			foreach ( array_keys( self::QUESTIONS ) as $ability ) {
				$data[ "$provider, $ability" ] = array( $provider, array( $ability ) );
			}
			$data[ "$provider, all abilities" ] = array( $provider, array_keys( self::QUESTIONS ) );
		}

		return $data;
	}

	/**
	 * Asks a provider a question and runs the abilities the model calls until it answers.
	 *
	 * The test is skipped when the provider has no API key, its package is not installed or it is
	 * unavailable: it limits the requests, has a server error or cannot be reached. Every other
	 * error fails the test.
	 *
	 * @param string   $provider  Provider ID.
	 * @param string[] $abilities Names of the abilities given to the model.
	 * @param string   $question  The question.
	 * @return array{0: string, 1: string[], 2: string} The answer, the names of the abilities
	 *                                                  that ran without an error and the model ID.
	 */
	private function ask( string $provider, array $abilities, string $question ): array {
		$provider_class = self::PROVIDERS[ $provider ];
		$key_name       = 'WP_TESTS_AI_' . strtoupper( $provider ) . '_API_KEY';
		$model_name     = 'WP_TESTS_AI_' . strtoupper( $provider ) . '_MODEL';
		$api_key        = defined( $key_name ) ? constant( $key_name ) : getenv( $key_name );
		$model          = defined( $model_name ) ? constant( $model_name ) : getenv( $model_name );

		if ( ! $api_key ) {
			$this->markTestSkipped( "The $key_name environment variable or constant is not set." );
		}

		// PHPUnit may run from a PHAR, which does not load the Composer autoloader of the checkout.
		$autoloader = dirname( __DIR__, 3 ) . '/vendor/autoload.php';
		if ( ! class_exists( $provider_class ) && file_exists( $autoloader ) ) {
			require_once $autoloader;
		}
		if ( ! class_exists( $provider_class ) ) {
			$this->markTestSkipped( 'The AI provider packages are not installed. Run `composer update`, or `npm run env:composer -- update` in the local Docker environment.' );
		}

		$registry = new ProviderRegistry();
		$registry->registerProvider( $provider_class );
		$registry->setProviderRequestAuthentication( $provider, new ApiKeyRequestAuthentication( $api_key ) );

		/*
		 * The AI Client reports a provider whose models cannot be listed as one without models,
		 * so list them here to get the reason. A successful list is cached for the requests below.
		 * Any other exception, such as a response the provider changed, is an error of the test.
		 */
		try {
			$provider_class::modelMetadataDirectory()->listModelMetadata();
		} catch ( ClientException $e ) {
			if ( 429 !== $e->getCode() ) {
				$this->fail( "The model list request failed, check the $key_name: " . $e->getMessage() );
			}
			$this->markTestSkipped( 'The provider is unavailable: ' . $e->getMessage() );
		} catch ( NetworkException | ServerException $e ) {
			$this->markTestSkipped( 'The provider is unavailable: ' . $e->getMessage() );
		}

		if ( $model && ! $provider_class::modelMetadataDirectory()->hasModelMetadata( $model ) ) {
			$this->fail( "The provider has no \"$model\" model, check the $model_name." );
		}
		$model_instance = $model ? $registry->getProviderModel( $provider, $model ) : null;

		$resolver = new WP_AI_Client_Ability_Function_Resolver( ...$abilities );
		$messages = array( new UserMessage( array( new MessagePart( $question ) ) ) );
		$called   = array();

		// The action only fires after an ability ran without an error, so only those count as called.
		add_action(
			'wp_after_execute_ability',
			static function ( string $ability_name ) use ( &$called ) {
				$called[ $ability_name ] = true;
			}
		);

		// A model may call one ability per response: a request for each, one to answer and a spare one.
		$max_rounds = count( $abilities ) + 2;

		for ( $round = 0; $round < $max_rounds; $round++ ) {
			$builder = ( new WP_AI_Client_Prompt_Builder( $registry, $messages ) )
				->using_system_instruction( 'Answer every question with the abilities. Quote the values they return unchanged.' )
				->using_abilities( ...$abilities );

			if ( $model_instance ) {
				$builder->using_model( $model_instance );
			}

			$result = $builder->generate_text_result();

			if ( is_wp_error( $result ) ) {
				$status = $result->get_error_data()['status'] ?? 0;

				// Only a rate limit, a server error of the provider and a network error skip the test.
				if ( 429 === $status || in_array( $result->get_error_code(), array( 'prompt_network_error', 'prompt_upstream_server_error' ), true ) ) {
					$this->markTestSkipped( 'The provider is unavailable: ' . $result->get_error_message() );
				}
				$this->fail( 'The request failed: ' . $result->get_error_message() );
			}

			// OpenAI returns each function call as its own candidate, so keep the message of each one.
			$has_calls = false;
			$answer    = '';
			foreach ( $result->toMessages() as $message ) {
				$messages[] = $message;

				// An answer may be split into several text parts.
				foreach ( $message->getParts() as $part ) {
					if ( $part->getChannel()->isContent() && null !== $part->getText() ) {
						$answer .= $part->getText();
					}
				}

				if ( $resolver->has_ability_calls( $message ) ) {
					$response_message = $resolver->execute_abilities( $message );

					// The model could retry after an error and still answer, so an error fails the test.
					foreach ( $response_message->getParts() as $part ) {
						$function_response = $part->getFunctionResponse();
						$response          = $function_response ? $function_response->getResponse() : null;

						if ( is_array( $response ) && isset( $response['error'], $response['code'] ) ) {
							$ability_name = WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name( (string) $function_response->getName() );
							$this->fail( "The $ability_name ability returned an error: {$response['error']} ({$response['code']})" );
						}
					}

					$messages[] = $response_message;
					$has_calls  = true;
				}
			}

			if ( ! $has_calls ) {
				return array( $answer, array_keys( $called ), $result->getModelMetadata()->getId() );
			}
		}

		$this->fail( 'The model kept calling abilities without answering. Abilities that ran: ' . implode( ', ', array_keys( $called ) ) );
	}
}
