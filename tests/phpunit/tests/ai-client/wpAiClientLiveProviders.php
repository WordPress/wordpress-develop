<?php
/**
 * Tests that live AI providers can call the core abilities through the AI Client.
 *
 * These tests send real requests. The tests of a provider only run when its API key is in
 * the `ANTHROPIC_API_KEY`, `GOOGLE_API_KEY` or `OPENAI_API_KEY` environment variable:
 *
 *     ANTHROPIC_API_KEY=… vendor/bin/phpunit --group external-http --filter Tests_AI_Client_LiveProviders
 *
 * A provider uses its default model, unless a `WP_TESTS_AI_{PROVIDER}_MODEL` environment
 * variable, such as `WP_TESTS_AI_GOOGLE_MODEL`, names another one.
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
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\Http\Exception\NetworkException;
use WordPress\AiClient\Providers\Http\Exception\ServerException;
use WordPress\AiClient\Providers\ProviderRegistry;

class Tests_AI_Client_LiveProviders extends WP_UnitTestCase {

	/**
	 * Provider classes keyed by provider ID.
	 */
	private const PROVIDERS = array(
		'anthropic' => 'WordPress\AnthropicAiProvider\Provider\AnthropicProvider',
		'google'    => 'WordPress\GoogleAiProvider\Provider\GoogleProvider',
		'openai'    => 'WordPress\OpenAiAiProvider\Provider\OpenAiProvider',
	);

	/**
	 * For each core ability, a question no other ability answers and the value the answer must contain.
	 */
	private const QUESTIONS = array(
		'core/get-site-info'        => array( 'What is the character encoding of this site?', 'UTF-8' ),
		'core/get-user-info'        => array( 'What is my display name?', 'Barnaby Quillfeather' ),
		'core/get-environment-info' => array( 'Which PHP version does this site run?', PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION ),
		'core/settings-get'         => array( 'What is the timezone setting of this site?', 'Pacific/Chatham' ),
		'core/users-query'          => array( 'What is the display name of the user with the username "live_provider_editor"?', 'Marigold Featherstone' ),
		'core/content-query'        => array( 'What is the title of the post with the slug "live-provider-post"?', 'Quokka Lighthouse Notes' ),
	);

	/**
	 * ID of the administrator who asks the questions.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Creates the content the questions are about and registers the core abilities.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
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
				'post_author' => self::$admin_id,
				'post_name'   => 'live-provider-post',
				'post_title'  => 'Quokka Lighthouse Notes',
			)
		);

		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}

		remove_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		remove_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		add_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		add_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );

		/*
		 * Restore the hooks right away instead of after the class. The first test of a run
		 * snapshots the hooks and every test resets them to that snapshot, so changes left
		 * here would leak into every later test whenever this class runs first.
		 */
		remove_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		remove_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		add_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		add_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );
	}

	/**
	 * Revokes the super admin status and cleans up the registered abilities and categories.
	 */
	public static function wpTearDownAfterClass(): void {
		if ( is_multisite() ) {
			revoke_super_admin( self::$admin_id );
		}

		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}
	}

	/**
	 * Signs in the administrator, sets the option one of the questions is about and allows slow responses.
	 */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::$admin_id );
		update_option( 'timezone_string', 'Pacific/Chatham' );

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
		$called    = array();
		$answer    = $this->ask( $provider, $abilities, implode( ' ', array_column( $questions, 0 ) ), $called );

		// A model may quote a value as it is in the JSON it received, where slashes are escaped.
		$answer  = str_replace( '\/', '/', $answer );
		$missing = array_filter( array_column( $questions, 1 ), static fn ( $value ) => false === stripos( $answer, $value ) );

		$this->assertSameSets( $abilities, array_unique( $called ), 'The model should call every ability it is given.' );
		$this->assertSame( array(), array_values( $missing ), "The answer should contain every expected value. Answer: $answer" );
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
	 * The test is skipped when the provider has no API key or is unavailable: it limits the
	 * requests, has a server error or cannot be reached. Every other error fails the test.
	 *
	 * @param string   $provider  Provider ID.
	 * @param string[] $abilities Names of the abilities given to the model.
	 * @param string   $question  The question.
	 * @param string[] $called    Receives the name of the ability of each call.
	 * @return string The answer.
	 */
	private function ask( string $provider, array $abilities, string $question, array &$called ): string {
		$key_name = strtoupper( $provider ) . '_API_KEY';
		if ( ! getenv( $key_name ) ) {
			$this->markTestSkipped( "The $key_name environment variable is not set." );
		}
		if ( ! class_exists( self::PROVIDERS[ $provider ] ) ) {
			$this->markTestSkipped( 'The AI provider packages are not installed. Run `composer update`.' );
		}

		// A registry of its own keeps the provider out of the default registry other tests use.
		$registry = new ProviderRegistry();
		$registry->registerProvider( self::PROVIDERS[ $provider ] );

		/*
		 * The AI Client reports a provider whose models cannot be listed as one without models,
		 * so list them here to get the reason. A successful list is cached for the requests below.
		 * Any other exception, such as a response the provider changed, is an error of the test.
		 */
		try {
			self::PROVIDERS[ $provider ]::modelMetadataDirectory()->listModelMetadata();
		} catch ( NetworkException | ServerException $e ) {
			$this->markTestSkipped( 'The provider is unavailable: ' . $e->getMessage() );
		} catch ( ClientException $e ) {
			if ( 429 === $e->getCode() ) {
				$this->markTestSkipped( 'The provider is unavailable: ' . $e->getMessage() );
			}
			$this->fail( "The provider rejected the $key_name: " . $e->getMessage() );
		}

		$resolver = new WP_AI_Client_Ability_Function_Resolver( ...$abilities );
		$messages = array( new UserMessage( array( new MessagePart( $question ) ) ) );

		for ( $round = 0; $round < 6; $round++ ) {
			$builder = ( new WP_AI_Client_Prompt_Builder( $registry, $messages ) )
				->using_provider( $provider )
				->using_system_instruction( 'Answer every question with the abilities. Quote the values they return unchanged.' )
				->using_abilities( ...$abilities );

			$model = getenv( 'WP_TESTS_AI_' . strtoupper( $provider ) . '_MODEL' );
			if ( $model ) {
				$builder->using_model_preference( $model );
			}

			$result = $builder->generate_text_result();

			if ( is_wp_error( $result ) ) {
				$status  = $result->get_error_data()['status'] ?? 0;
				$message = "($status): " . $result->get_error_message();

				// Only a rate limit, a server error of the provider and a network error skip the test.
				if ( 429 === $status || in_array( $result->get_error_code(), array( 'prompt_network_error', 'prompt_upstream_server_error' ), true ) ) {
					$this->markTestSkipped( "The provider is unavailable $message" );
				}
				$this->fail( "The request failed $message" );
			}

			// OpenAI returns each function call as its own candidate, so keep them all, not just toMessage().
			$has_calls = false;
			foreach ( $result->getCandidates() as $candidate ) {
				$message    = $candidate->getMessage();
				$messages[] = $message;

				if ( ! $resolver->has_ability_calls( $message ) ) {
					continue;
				}

				foreach ( $message->getParts() as $part ) {
					if ( $part->getFunctionCall() ) {
						$called[] = WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name( $part->getFunctionCall()->getName() );
					}
				}
				$messages[] = $resolver->execute_abilities( $message );
				$has_calls  = true;
			}

			if ( ! $has_calls ) {
				return implode( "\n", $result->toTexts() );
			}
		}

		$this->fail( 'The model kept calling abilities without answering.' );
	}
}
