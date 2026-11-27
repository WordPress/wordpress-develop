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
 * In the local Docker environment, set them in the `.env` file and run the command below. They are
 * passed to the PHP container, which also serves the local site, but only these tests read them:
 *
 *     npm run test:php -- --group external-http --test-suffix .php tests/phpunit/manual
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
	 * Provider classes and the namespaces of their packages, keyed by provider ID.
	 *
	 * @var array<string, array{class: class-string, namespace: string}>
	 */
	private const PROVIDERS = array(
		'anthropic' => array(
			'class'     => AnthropicProvider::class,
			'namespace' => 'WordPress\\AnthropicAiProvider\\',
		),
		'google'    => array(
			'class'     => GoogleProvider::class,
			'namespace' => 'WordPress\\GoogleAiProvider\\',
		),
		'openai'    => array(
			'class'     => OpenAiProvider::class,
			'namespace' => 'WordPress\\OpenAiAiProvider\\',
		),
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
	 * Loads the provider packages, creates the users, the post and the option the questions are about,
	 * and registers the core abilities.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		global $wp_current_filter;

		self::register_provider_autoloaders();

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

		// The core abilities and their categories can only be registered while their registration actions run.
		// Pop in finally so a setup exception cannot leave the simulated actions on the stack.
		array_push( $wp_current_filter, 'wp_abilities_api_categories_init', 'wp_abilities_api_init' );
		try {
			wp_register_core_ability_categories();
			wp_register_core_abilities();
		} finally {
			array_splice( $wp_current_filter, -2 );
		}
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

		$this->assertSameSets( $abilities, $called, "Every ability given to the model should be called. $context" );
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
	 * rate limited, returns a server error or cannot be reached. Every other error fails the test.
	 *
	 * @param string   $provider  Provider ID.
	 * @param string[] $abilities Names of the abilities given to the model.
	 * @param string   $question  The question.
	 * @return array{0: string, 1: string[], 2: string} The answer, the names of the abilities
	 *                                                  that ran and the model ID.
	 */
	private function ask( string $provider, array $abilities, string $question ): array {
		$provider_class = self::PROVIDERS[ $provider ]['class'];
		$prefix         = 'WP_TESTS_AI_' . strtoupper( $provider );
		$key_var        = "{$prefix}_API_KEY";
		$model_var      = "{$prefix}_MODEL";
		$api_key        = self::get_setting( $key_var );
		$model          = self::get_setting( $model_var );

		if ( ! $api_key ) {
			$this->markTestSkipped( "The $key_var environment variable or constant is not set." );
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
		} catch ( ClientException | NetworkException | ServerException $e ) {
			if ( $e instanceof ClientException && 429 !== $e->getCode() ) {
				$this->fail( "The model list request failed, check the $key_var: " . $e->getMessage() );
			}
			$this->markTestSkipped( 'The provider is unavailable: ' . $e->getMessage() );
		}

		if ( $model && ! $provider_class::modelMetadataDirectory()->hasModelMetadata( $model ) ) {
			$this->fail( "The provider has no \"$model\" model, check the $model_var." );
		}
		$model_instance = $model ? $registry->getProviderModel( $provider, $model ) : null;

		$resolver = new WP_AI_Client_Ability_Function_Resolver( ...$abilities );
		$messages = array( new UserMessage( array( new MessagePart( $question ) ) ) );
		$called   = array();

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
						$response          = $function_response->getResponse();
						$ability_name      = WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name( (string) $function_response->getName() );

						if ( is_array( $response ) && isset( $response['error'], $response['code'] ) ) {
							$data = isset( $response['data'] ) ? ' ' . wp_json_encode( $response['data'] ) : '';
							$this->fail( "The $ability_name ability returned an error: {$response['error']} ({$response['code']})$data" );
						}

						$called[ $ability_name ] = true;
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

	/**
	 * Loads the classes of each installed provider package that the Composer autoloader does not load.
	 *
	 * PHPUnit may run from a PHAR, which does not load the Composer autoloader of the checkout.
	 * That autoloader would also load the PHPUnit classes of the checkout, so only the classes
	 * of the provider packages are loaded, from their `src` directories.
	 */
	private static function register_provider_autoloaders(): void {
		foreach ( self::PROVIDERS as $provider => $package ) {
			$namespace   = $package['namespace'];
			$package_dir = dirname( __DIR__, 3 ) . "/vendor/wordpress/ai-provider-for-$provider/src/";

			if ( class_exists( $package['class'] ) || ! is_dir( $package_dir ) ) {
				continue;
			}

			spl_autoload_register(
				static function ( string $class_name ) use ( $namespace, $package_dir ) {
					if ( ! str_starts_with( $class_name, $namespace ) ) {
						return;
					}

					$file = $package_dir . str_replace( '\\', '/', substr( $class_name, strlen( $namespace ) ) ) . '.php';
					if ( file_exists( $file ) ) {
						require $file;
					}
				}
			);
		}
	}

	/**
	 * Gets a setting from an environment variable or, when that is empty or not set, a constant.
	 *
	 * @param string $name Name of the environment variable and the constant.
	 * @return string The value, or an empty string when neither is set.
	 */
	private static function get_setting( string $name ): string {
		$value = getenv( $name );
		if ( ! $value && defined( $name ) ) {
			$value = constant( $name );
		}
		return (string) $value;
	}
}
