<?php
/**
 * Tests for the automatic ability resolution loop in WP_AI_Client_Prompt_Builder.
 *
 * @group ai-client
 * @covers WP_AI_Client_Prompt_Builder
 */

use WordPress\AiClient\Events\AfterGenerateResultEvent;
use WordPress\AiClient\Events\BeforeGenerateResultEvent;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

require_once dirname( __DIR__, 2 ) . '/includes/wp-ai-client-mock-model-creation-trait.php';
require_once dirname( __DIR__, 2 ) . '/includes/wp-ai-client-test-abilities-trait.php';

class Tests_AI_Client_AbilityResolution extends WP_UnitTestCase {
	use WP_AI_Client_Mock_Model_Creation_Trait;
	use WP_AI_Client_Test_Abilities_Trait;

	/**
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $registry;

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
	 * Creates a result whose message consists of the given function calls.
	 *
	 * @param array<int, array{0: string, 1: string, 2: array}> $calls List of id, function name, and arguments triples.
	 * @return GenerativeAiResult The result containing the function calls.
	 */
	private function create_function_call_result( array $calls ): GenerativeAiResult {
		$parts = array();
		foreach ( $calls as $call ) {
			$parts[] = new MessagePart( new FunctionCall( $call[0], $call[1], $call[2] ) );
		}

		$candidate = new Candidate(
			new ModelMessage( $parts ),
			FinishReasonEnum::toolCalls()
		);

		return new GenerativeAiResult(
			'function-call-result',
			array( $candidate ),
			new TokenUsage( 5, 7, 12 ),
			new ProviderMetadata( 'mock', 'Mock Provider', ProviderTypeEnum::cloud() ),
			$this->create_test_text_model_metadata()
		);
	}

	/**
	 * Creates a prompt builder backed by a scripted model with resolution enabled.
	 *
	 * @param array<int, GenerativeAiResult|Exception> $results          Scripted results or exceptions, in order.
	 * @param array                                    $captured_prompts Receives each model call's message list.
	 * @param string                                   ...$abilities     Ability names to register on the builder.
	 * @return WP_AI_Client_Prompt_Builder The prompt builder.
	 */
	private function create_resolution_builder( array $results, array &$captured_prompts, string ...$abilities ): WP_AI_Client_Prompt_Builder {
		$model = $this->create_scripted_text_generation_model( $results, $captured_prompts );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Test prompt' );
		$builder->using_model( $model );

		if ( ! empty( $abilities ) ) {
			$builder->using_abilities( ...$abilities );
		}

		return $builder;
	}

	/**
	 * Returns the function name for a test ability.
	 *
	 * @param string $ability_name The ability name.
	 * @return string The function name exposed to the model.
	 */
	private function function_name( string $ability_name ): string {
		return WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name( $ability_name );
	}

	/**
	 * Test that using_ability_resolution() is chainable.
	 *
	 * @ticket 64865
	 */
	public function test_using_ability_resolution_is_chainable() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Test prompt' );

		$this->assertSame( $builder, $builder->using_ability_resolution() );
	}

	/**
	 * Test that the scripted model requires at least one result.
	 *
	 * @ticket 64865
	 */
	public function test_scripted_model_requires_at_least_one_result() {
		$captured = array();

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'At least one scripted result is required.' );

		$this->create_scripted_text_generation_model( array(), $captured );
	}

	/**
	 * Test that an invalid max_iterations option is rejected.
	 *
	 * @ticket 64865
	 * @expectedIncorrectUsage WP_AI_Client_Prompt_Builder::using_ability_resolution
	 */
	public function test_using_ability_resolution_rejects_invalid_max_iterations() {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Test prompt' );

		$this->assertSame( $builder, $builder->using_ability_resolution( array( 'max_iterations' => 0 ) ) );
	}

	/**
	 * Test that an invalid option falls back to the default.
	 *
	 * @ticket 64865
	 * @expectedIncorrectUsage WP_AI_Client_Prompt_Builder::using_ability_resolution
	 */
	public function test_invalid_max_iterations_falls_back_to_default() {
		$captured    = array();
		$call_result = $this->create_function_call_result(
			array( array( 'call-1', $this->function_name( 'wpaiclienttests/simple' ), array() ) )
		);

		// The scripted model keeps returning the function call result.
		$builder = $this->create_resolution_builder( array( $call_result ), $captured, 'wpaiclienttests/simple' );
		$result  = $builder
			->using_ability_resolution( array( 'max_iterations' => 0 ) )
			->generate_text_result();

		$this->assertSame( 5, $result->getAdditionalData()['ability_resolution']['rounds'] );
	}

	/**
	 * Test that a response without function calls passes through with loop metadata.
	 *
	 * @ticket 64865
	 */
	public function test_result_without_function_calls_passes_through_with_metadata() {
		$captured = array();
		$builder  = $this->create_resolution_builder(
			array( $this->create_test_result( 'Plain answer' ) ),
			$captured,
			'wpaiclienttests/simple'
		);

		$result = $builder->using_ability_resolution()->generate_text_result();

		$this->assertInstanceOf( GenerativeAiResult::class, $result );
		$this->assertSame( 'Plain answer', $result->toText() );
		$this->assertCount( 1, $captured );

		$resolution = $result->getAdditionalData()['ability_resolution'];
		$this->assertSame( 'completed', $resolution['stop_reason'] );
		$this->assertSame( 0, $resolution['rounds'] );
		$this->assertSame( array(), $resolution['resolved_calls'] );
		$this->assertCount( 2, $resolution['messages'], 'The transcript should contain the prompt and the final response.' );
	}

	/**
	 * Test that an ability call is executed and the final answer is returned.
	 *
	 * @ticket 64865
	 */
	public function test_resolves_ability_call_and_returns_final_answer() {
		$captured = array();
		$builder  = $this->create_resolution_builder(
			array(
				$this->create_function_call_result(
					array( array( 'call-1', $this->function_name( 'wpaiclienttests/simple' ), array() ) )
				),
				$this->create_test_result( 'Final answer' ),
			),
			$captured,
			'wpaiclienttests/simple'
		);

		$result = $builder->using_ability_resolution()->generate_text_result();

		$this->assertInstanceOf( GenerativeAiResult::class, $result );
		$this->assertSame( 'Final answer', $result->toText() );
		$this->assertCount( 2, $captured );

		$resolution = $result->getAdditionalData()['ability_resolution'];
		$this->assertSame( 'completed', $resolution['stop_reason'] );
		$this->assertSame( 1, $resolution['rounds'] );
		$this->assertSame(
			array(
				array(
					'id'      => 'call-1',
					'ability' => 'wpaiclienttests/simple',
				),
			),
			$resolution['resolved_calls']
		);
		$this->assertCount( 4, $resolution['messages'], 'The transcript should contain the prompt, the call, the response, and the final answer.' );
	}

	/**
	 * Test that the follow-up request contains the expected conversation.
	 *
	 * @ticket 64865
	 */
	public function test_second_request_contains_expected_transcript() {
		$captured = array();
		$builder  = $this->create_resolution_builder(
			array(
				$this->create_function_call_result(
					array( array( 'call-1', $this->function_name( 'wpaiclienttests/simple' ), array() ) )
				),
				$this->create_test_result( 'Final answer' ),
			),
			$captured,
			'wpaiclienttests/simple'
		);

		$builder->using_ability_resolution()->generate_text_result();

		$messages = $captured[1];
		$this->assertCount( 3, $messages );
		$this->assertTrue( $messages[0]->getRole()->isUser(), 'The first message should be the user prompt.' );
		$this->assertTrue( $messages[1]->getRole()->isModel(), 'The second message should be the model response.' );
		$this->assertTrue( $messages[2]->getRole()->isUser(), 'The third message should carry the function responses.' );

		$parts = $messages[2]->getParts();
		$this->assertCount( 1, $parts );

		$response = $parts[0]->getFunctionResponse();
		$this->assertInstanceOf( FunctionResponse::class, $response );
		$this->assertSame( 'call-1', $response->getId() );
		$this->assertSame( $this->function_name( 'wpaiclienttests/simple' ), $response->getName() );
		$this->assertSame( array( 'success' => true ), $response->getResponse() );
	}

	/**
	 * Test that ability arguments from the model reach the ability.
	 *
	 * @ticket 64865
	 */
	public function test_resolves_ability_call_with_arguments() {
		$captured = array();
		$builder  = $this->create_resolution_builder(
			array(
				$this->create_function_call_result(
					array( array( 'call-1', $this->function_name( 'wpaiclienttests/with-params' ), array( 'title' => 'Hello' ) ) )
				),
				$this->create_test_result( 'Done' ),
			),
			$captured,
			'wpaiclienttests/with-params'
		);

		$builder->using_ability_resolution()->generate_text_result();

		$response = $captured[1][2]->getParts()[0]->getFunctionResponse();
		$this->assertSame(
			array(
				'success' => true,
				'title'   => 'Hello',
			),
			$response->getResponse()
		);
	}

	/**
	 * Test that all calls from one response are answered in a single message.
	 *
	 * @ticket 64865
	 */
	public function test_answers_all_calls_from_one_response() {
		$captured = array();
		$builder  = $this->create_resolution_builder(
			array(
				$this->create_function_call_result(
					array(
						array( 'call-1', $this->function_name( 'wpaiclienttests/simple' ), array() ),
						array( 'call-2', $this->function_name( 'wpaiclienttests/with-params' ), array( 'title' => 'Hello' ) ),
					)
				),
				$this->create_test_result( 'Done' ),
			),
			$captured,
			'wpaiclienttests/simple',
			'wpaiclienttests/with-params'
		);

		$builder->using_ability_resolution()->generate_text_result();

		$parts = $captured[1][2]->getParts();
		$this->assertCount( 2, $parts );
		$this->assertSame( 'call-1', $parts[0]->getFunctionResponse()->getId() );
		$this->assertSame( 'call-2', $parts[1]->getFunctionResponse()->getId() );
	}

	/**
	 * Test that the loop stops after the configured number of rounds.
	 *
	 * @ticket 64865
	 */
	public function test_stops_after_max_iterations() {
		$captured    = array();
		$call_result = $this->create_function_call_result(
			array( array( 'call-1', $this->function_name( 'wpaiclienttests/simple' ), array() ) )
		);

		// The scripted model keeps returning the function call result.
		$builder = $this->create_resolution_builder( array( $call_result ), $captured, 'wpaiclienttests/simple' );
		$result  = $builder
			->using_ability_resolution( array( 'max_iterations' => 2 ) )
			->generate_text_result();

		$this->assertInstanceOf( GenerativeAiResult::class, $result );
		$this->assertCount( 3, $captured, 'The model should be called once initially and once per allowed round.' );

		$resolution = $result->getAdditionalData()['ability_resolution'];
		$this->assertSame( 'max_iterations', $resolution['stop_reason'] );
		$this->assertSame( 2, $resolution['rounds'] );
	}

	/**
	 * Test that generate_text() returns an error when the loop is incomplete.
	 *
	 * @ticket 64865
	 */
	public function test_generate_text_returns_error_when_max_iterations_reached() {
		$captured    = array();
		$call_result = $this->create_function_call_result(
			array( array( 'call-1', $this->function_name( 'wpaiclienttests/simple' ), array() ) )
		);

		$builder = $this->create_resolution_builder( array( $call_result ), $captured, 'wpaiclienttests/simple' );
		$result  = $builder
			->using_ability_resolution( array( 'max_iterations' => 1 ) )
			->generate_text();

		$this->assertWPError( $result );
		$this->assertSame( 'ability_resolution_incomplete', $result->get_error_code() );
		$this->assertSame( 'max_iterations', $result->get_error_data()['stop_reason'] );
	}

	/**
	 * Test that generate_text() returns the final answer through the loop.
	 *
	 * @ticket 64865
	 */
	public function test_generate_text_returns_final_answer() {
		$captured = array();
		$builder  = $this->create_resolution_builder(
			array(
				$this->create_function_call_result(
					array( array( 'call-1', $this->function_name( 'wpaiclienttests/simple' ), array() ) )
				),
				$this->create_test_result( 'Final answer' ),
			),
			$captured,
			'wpaiclienttests/simple'
		);

		$result = $builder->using_ability_resolution()->generate_text();

		$this->assertSame( 'Final answer', $result );
	}

	/**
	 * Test that the loop stops without executing anything when unknown functions are requested.
	 *
	 * @ticket 64865
	 */
	public function test_stops_when_response_contains_unknown_function_calls() {
		$invoked_abilities = array();
		add_action(
			'wp_ability_invoked',
			static function ( $ability_name ) use ( &$invoked_abilities ) {
				$invoked_abilities[] = $ability_name;
			}
		);

		$captured = array();
		$builder  = $this->create_resolution_builder(
			array(
				$this->create_function_call_result(
					array(
						array( 'call-1', $this->function_name( 'wpaiclienttests/simple' ), array() ),
						array( 'call-2', 'custom_function', array() ),
					)
				),
				$this->create_test_result( 'Never returned' ),
			),
			$captured,
			'wpaiclienttests/simple'
		);

		$result = $builder->using_ability_resolution()->generate_text_result();

		$this->assertInstanceOf( GenerativeAiResult::class, $result );
		$this->assertCount( 1, $captured, 'The loop should not request a follow-up response.' );
		$this->assertSame( array(), $invoked_abilities, 'No ability should be executed when unknown functions are requested.' );

		$resolution = $result->getAdditionalData()['ability_resolution'];
		$this->assertSame( 'unresolved_function_calls', $resolution['stop_reason'] );
		$this->assertSame( 0, $resolution['rounds'] );
	}

	/**
	 * Test that an ability error is sent back to the model and the loop continues.
	 *
	 * @ticket 64865
	 */
	public function test_error_from_ability_is_sent_back_to_model() {
		$captured = array();
		$builder  = $this->create_resolution_builder(
			array(
				$this->create_function_call_result(
					array( array( 'call-1', $this->function_name( 'wpaiclienttests/returns-error' ), array() ) )
				),
				$this->create_test_result( 'Recovered' ),
			),
			$captured,
			'wpaiclienttests/returns-error'
		);

		$result = $builder->using_ability_resolution()->generate_text_result();

		$this->assertSame( 'Recovered', $result->toText() );

		$response = $captured[1][2]->getParts()[0]->getFunctionResponse()->getResponse();
		$this->assertSame( 'test_error', $response['code'] );
	}

	/**
	 * Test that a call to an ability outside the allowed list is answered with an error.
	 *
	 * @ticket 64865
	 */
	public function test_not_allowed_ability_error_is_sent_back_to_model() {
		$captured = array();
		$builder  = $this->create_resolution_builder(
			array(
				$this->create_function_call_result(
					array( array( 'call-1', $this->function_name( 'wpaiclienttests/with-params' ), array( 'title' => 'Hello' ) ) )
				),
				$this->create_test_result( 'Done' ),
			),
			$captured,
			'wpaiclienttests/simple'
		);

		$result = $builder->using_ability_resolution()->generate_text_result();

		$this->assertSame( 'Done', $result->toText() );

		$response = $captured[1][2]->getParts()[0]->getFunctionResponse()->getResponse();
		$this->assertSame( 'ability_not_allowed', $response['code'] );
	}

	/**
	 * Test that the prevent filter also stops the loop between rounds.
	 *
	 * @ticket 64865
	 */
	public function test_prevent_filter_stops_the_loop_between_rounds() {
		$evaluations       = 0;
		$invoked_abilities = array();
		add_filter(
			'wp_ai_client_prevent_prompt',
			static function ( $prevent ) use ( &$evaluations ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				++$evaluations;
				return $evaluations > 1;
			}
		);
		add_action(
			'wp_ability_invoked',
			static function ( $ability_name ) use ( &$invoked_abilities ) {
				$invoked_abilities[] = $ability_name;
			}
		);

		$captured = array();
		$builder  = $this->create_resolution_builder(
			array(
				$this->create_function_call_result(
					array( array( 'call-1', $this->function_name( 'wpaiclienttests/simple' ), array() ) )
				),
				$this->create_test_result( 'Never returned' ),
			),
			$captured,
			'wpaiclienttests/simple'
		);

		$result = $builder->using_ability_resolution()->generate_text_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_prevented', $result->get_error_code() );
		$this->assertCount( 1, $captured, 'The follow-up request should be prevented.' );
		$this->assertSame( array(), $invoked_abilities, 'No ability should be executed after prompt execution is prevented.' );
	}

	/**
	 * Test that token usage is aggregated across all rounds.
	 *
	 * @ticket 64865
	 */
	public function test_token_usage_is_aggregated_across_rounds() {
		$captured = array();
		$builder  = $this->create_resolution_builder(
			array(
				// Uses 5 prompt, 7 completion, and 12 total tokens.
				$this->create_function_call_result(
					array( array( 'call-1', $this->function_name( 'wpaiclienttests/simple' ), array() ) )
				),
				// Uses 10 prompt, 20 completion, and 30 total tokens.
				$this->create_test_result( 'Final answer' ),
			),
			$captured,
			'wpaiclienttests/simple'
		);

		$result = $builder->using_ability_resolution()->generate_text_result();
		$usage  = $result->getTokenUsage();

		$this->assertSame( 15, $usage->getPromptTokens() );
		$this->assertSame( 27, $usage->getCompletionTokens() );
		$this->assertSame( 42, $usage->getTotalTokens() );
	}

	/**
	 * Test that lifecycle events fire for every round.
	 *
	 * @ticket 64865
	 */
	public function test_lifecycle_events_fire_for_each_round() {
		$before_events = array();
		$after_events  = array();
		add_action(
			'wp_ai_client_before_generate_result',
			static function ( $event ) use ( &$before_events ) {
				$before_events[] = $event;
			}
		);
		add_action(
			'wp_ai_client_after_generate_result',
			static function ( $event ) use ( &$after_events ) {
				$after_events[] = $event;
			}
		);

		$captured = array();
		$builder  = $this->create_resolution_builder(
			array(
				$this->create_function_call_result(
					array( array( 'call-1', $this->function_name( 'wpaiclienttests/simple' ), array() ) )
				),
				$this->create_test_result( 'Final answer' ),
			),
			$captured,
			'wpaiclienttests/simple'
		);

		$result = $builder->using_ability_resolution()->generate_text_result();

		$this->assertSame( 'Final answer', $result->toText() );
		$this->assertCount( 2, $before_events, 'The before event should fire for the initial request and each round.' );
		$this->assertCount( 2, $after_events, 'The after event should fire after the initial request and each successful round.' );

		$this->assertInstanceOf( BeforeGenerateResultEvent::class, $before_events[0] );
		$this->assertInstanceOf( BeforeGenerateResultEvent::class, $before_events[1] );
		$this->assertCount( 1, $before_events[0]->getMessages() );
		$this->assertCount( 3, $before_events[1]->getMessages() );
		$this->assertEquals( $captured[0], $before_events[0]->getMessages() );
		$this->assertEquals( $captured[1], $before_events[1]->getMessages() );

		$this->assertInstanceOf( AfterGenerateResultEvent::class, $after_events[0] );
		$this->assertInstanceOf( AfterGenerateResultEvent::class, $after_events[1] );
		$this->assertCount( 1, $after_events[0]->getMessages() );
		$this->assertCount( 3, $after_events[1]->getMessages() );
		$this->assertCount( 1, $after_events[0]->getResult()->toMessage()->getParts() );
		$this->assertSame( 'call-1', $after_events[0]->getResult()->toMessage()->getParts()[0]->getFunctionCall()->getId() );
		$this->assertSame( 'Final answer', $after_events[1]->getResult()->toText() );
	}

	/**
	 * Test that a failed follow-up request does not dispatch an after event.
	 *
	 * @ticket 64865
	 */
	public function test_failed_follow_up_request_does_not_fire_after_event() {
		$before_events = array();
		$after_events  = array();
		add_action(
			'wp_ai_client_before_generate_result',
			static function ( $event ) use ( &$before_events ) {
				$before_events[] = $event;
			}
		);
		add_action(
			'wp_ai_client_after_generate_result',
			static function ( $event ) use ( &$after_events ) {
				$after_events[] = $event;
			}
		);

		$captured = array();
		$builder  = $this->create_resolution_builder(
			array(
				$this->create_function_call_result(
					array( array( 'call-1', $this->function_name( 'wpaiclienttests/simple' ), array() ) )
				),
				new RuntimeException( 'Follow-up failed.' ),
			),
			$captured,
			'wpaiclienttests/simple'
		);

		$result = $builder->using_ability_resolution()->generate_text_result();

		$this->assertWPError( $result );
		$this->assertSame( 'prompt_builder_error', $result->get_error_code() );
		$this->assertSame( 'Follow-up failed.', $result->get_error_message() );
		$this->assertCount( 2, $captured );
		$this->assertCount( 2, $before_events, 'The before event should fire before the failed follow-up request.' );
		$this->assertCount( 1, $after_events, 'The after event should only fire for the successful initial request.' );
		$this->assertInstanceOf( AfterGenerateResultEvent::class, $after_events[0] );
	}

	/**
	 * Test that ability declarations added directly are resolvable too.
	 *
	 * The allow-list is derived from the function declarations exposed to the
	 * model, so declarations built without using_abilities() participate.
	 *
	 * @ticket 64865
	 */
	public function test_directly_declared_abilities_are_resolvable() {
		$captured = array();
		$builder  = $this->create_resolution_builder(
			array(
				$this->create_function_call_result(
					array( array( 'call-1', $this->function_name( 'wpaiclienttests/simple' ), array() ) )
				),
				$this->create_test_result( 'Final answer' ),
			),
			$captured
		);

		$builder->using_function_declarations(
			new FunctionDeclaration( $this->function_name( 'wpaiclienttests/simple' ), 'A simple test ability.' )
		);

		$result = $builder->using_ability_resolution()->generate_text_result();

		$this->assertSame( 'Final answer', $result->toText() );
		$this->assertSame( 1, $result->getAdditionalData()['ability_resolution']['rounds'] );
	}

	/**
	 * Test that replacing the declarations also replaces the allow-list.
	 *
	 * @ticket 64865
	 */
	public function test_replaced_declarations_limit_the_allow_list() {
		$captured = array();
		$builder  = $this->create_resolution_builder(
			array(
				$this->create_function_call_result(
					array( array( 'call-1', $this->function_name( 'wpaiclienttests/simple' ), array() ) )
				),
				$this->create_test_result( 'Done' ),
			),
			$captured,
			'wpaiclienttests/simple'
		);

		// Replaces the declarations from using_abilities() above.
		$builder->using_function_declarations(
			new FunctionDeclaration( $this->function_name( 'wpaiclienttests/with-params' ), 'Another test ability.' )
		);

		$result = $builder->using_ability_resolution()->generate_text_result();

		$this->assertSame( 'Done', $result->toText() );

		// The call to the no longer declared ability must not execute.
		$response = $captured[1][2]->getParts()[0]->getFunctionResponse()->getResponse();
		$this->assertSame( 'ability_not_allowed', $response['code'] );
	}

	/**
	 * Test that resolution without registered abilities falls back to plain generation.
	 *
	 * @ticket 64865
	 * @expectedIncorrectUsage WP_AI_Client_Prompt_Builder::generate_with_ability_resolution
	 */
	public function test_resolution_without_abilities_falls_back_to_plain_generation() {
		$captured    = array();
		$text_result = $this->create_test_result( 'Plain answer' );

		$builder = $this->create_resolution_builder( array( $text_result ), $captured );
		$result  = $builder->using_ability_resolution()->generate_text_result();

		$this->assertSame( $text_result, $result, 'The unmodified result should be returned.' );
		$this->assertCount( 1, $captured );
	}

	/**
	 * Test that the generate_text() fallback still returns a string.
	 *
	 * @ticket 64865
	 * @expectedIncorrectUsage WP_AI_Client_Prompt_Builder::generate_with_ability_resolution
	 */
	public function test_generate_text_without_abilities_falls_back_to_plain_text() {
		$captured = array();
		$builder  = $this->create_resolution_builder(
			array( $this->create_test_result( 'Plain answer' ) ),
			$captured
		);

		$result = $builder->using_ability_resolution()->generate_text();

		$this->assertSame( 'Plain answer', $result );
	}

	/**
	 * Test that unsupported generation methods warn and run without resolution.
	 *
	 * @ticket 64865
	 * @expectedIncorrectUsage WP_AI_Client_Prompt_Builder::__call
	 */
	public function test_resolution_warns_for_unsupported_generation_methods() {
		$captured = array();
		$builder  = $this->create_resolution_builder(
			array( $this->create_test_result( 'Plain answer' ) ),
			$captured,
			'wpaiclienttests/simple'
		);

		$result = $builder->using_ability_resolution()->generate_image_result();

		$this->assertWPError( $result );
	}
}
