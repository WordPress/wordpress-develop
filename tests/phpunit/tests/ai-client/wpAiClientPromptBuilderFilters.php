<?php
/**
 * Tests for WP_AI_Client_Prompt_Builder customization filters and hooks.
 *
 * @group ai-client
 * @covers WP_AI_Client_Prompt_Builder
 */

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

require_once dirname( __DIR__, 2 ) . '/includes/wp-ai-client-mock-model-creation-trait.php';

/**
 * Test class for prompt builder customization filters (Trac #65115).
 *
 * @ticket 65115
 */
class Tests_AI_Client_Prompt_Builder_Filters extends WP_UnitTestCase {
	use WP_AI_Client_Mock_Model_Creation_Trait;

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
	 * Creates a text model metadata supporting any input modalities.
	 *
	 * @param string $id The model identifier.
	 * @return ModelMetadata
	 */
	private function create_text_model_metadata( string $id ): ModelMetadata {
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
	 * Tests that wp_ai_client_prompt_builder_init fires on construction.
	 */
	public function test_prompt_builder_init_action_fires_on_construction(): void {
		$captured_builder = null;
		$captured_prompt  = null;

		add_action(
			'wp_ai_client_prompt_builder_init',
			static function ( $builder, $prompt ) use ( &$captured_builder, &$captured_prompt ) {
				$captured_builder = $builder;
				$captured_prompt  = $prompt;
			},
			10,
			2
		);

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Test prompt' );

		$this->assertSame( $builder, $captured_builder );
		$this->assertSame( 'Test prompt', $captured_prompt );
	}

	/**
	 * Tests that wp_ai_client_prompt_builder_init allows configuring defaults that callers can override.
	 */
	public function test_prompt_builder_init_action_allows_setting_defaults(): void {
		add_action(
			'wp_ai_client_prompt_builder_init',
			static function ( $builder ) {
				$builder->using_temperature( 0.4 );
				$builder->using_max_tokens( 250 );
				$builder->using_model_preference( 'default-model' );
			}
		);

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Prompt' );

		$params = $builder->get_generation_parameters();
		$this->assertSame( 0.4, $params['temperature'] );
		$this->assertSame( 250, $params['max_tokens'] );
		$this->assertSame( array( 'default-model' ), $builder->get_model_preferences() );

		// Caller can subsequently override.
		$builder->using_temperature( 0.9 );
		$builder->using_model_preference( 'custom-model' );

		$updated_params = $builder->get_generation_parameters();
		$this->assertSame( 0.9, $updated_params['temperature'] );
		$this->assertSame( array( 'custom-model' ), $builder->get_model_preferences() );
	}

	/**
	 * Tests that get_generation_parameters() tracks parameters configured via fluent calls.
	 */
	public function test_get_generation_parameters_tracks_fluent_calls(): void {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Prompt' );

		$builder->using_temperature( 0.7 )
			->using_max_tokens( 500 )
			->using_top_p( 0.9 )
			->using_top_k( 40 )
			->using_presence_penalty( 0.5 )
			->using_frequency_penalty( 0.6 )
			->using_stop_sequences( 'STOP', 'END' )
			->using_candidate_count( 2 )
			->using_system_instruction( 'Be precise' )
			->using_top_logprobs( 5 );

		$params = $builder->get_generation_parameters();

		$this->assertSame( 0.7, $params['temperature'] );
		$this->assertSame( 500, $params['max_tokens'] );
		$this->assertSame( 0.9, $params['top_p'] );
		$this->assertSame( 40, $params['top_k'] );
		$this->assertSame( 0.5, $params['presence_penalty'] );
		$this->assertSame( 0.6, $params['frequency_penalty'] );
		$this->assertSame( array( 'STOP', 'END' ), $params['stop_sequences'] );
		$this->assertSame( 2, $params['candidate_count'] );
		$this->assertSame( 'Be precise', $params['system_instruction'] );
		$this->assertSame( 5, $params['top_logprobs'] );
		$this->assertSame( 30.0, $params['timeout'] );
	}

	/**
	 * Tests that get_model_preferences() and get_model() track model selection.
	 */
	public function test_model_selection_getters(): void {
		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Prompt' );

		$this->assertSame( array(), $builder->get_model_preferences() );
		$this->assertNull( $builder->get_model() );

		$builder->using_model_preference( 'model-1', 'model-2' );
		$this->assertSame( array( 'model-1', 'model-2' ), $builder->get_model_preferences() );

		$result   = $this->create_test_result( 'Test' );
		$metadata = $this->create_text_model_metadata( 'explicit-model' );
		$model    = $this->create_mock_text_generation_model( $result, $metadata );

		$builder->using_model( $model );
		$this->assertSame( $model, $builder->get_model() );
	}

	/**
	 * Tests that wp_ai_client_model filter allows customizing the model instance used for generation.
	 */
	public function test_wp_ai_client_model_filter_customizes_model(): void {
		$result1   = $this->create_test_result( 'Original model result' );
		$metadata1 = $this->create_text_model_metadata( 'orig-model' );
		$model1    = $this->create_mock_text_generation_model( $result1, $metadata1 );

		$result2   = $this->create_test_result( 'Filtered model result' );
		$metadata2 = $this->create_text_model_metadata( 'filtered-model' );
		$model2    = $this->create_mock_text_generation_model( $result2, $metadata2 );

		$captured_capability = null;

		add_filter(
			'wp_ai_client_model',
			static function ( $model, $capability, $builder ) use ( $model2, &$captured_capability ) {
				$captured_capability = $capability;
				return $model2;
			},
			10,
			3
		);

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Hello' );
		$builder->using_model( $model1 );

		$output = $builder->generate_text();

		$this->assertSame( 'Filtered model result', $output );
		$this->assertInstanceOf( CapabilityEnum::class, $captured_capability );
		$this->assertTrue( $captured_capability->isTextGeneration() );
	}

	/**
	 * Tests that wp_ai_client_model_preference filter passes preferences and capability context.
	 */
	public function test_wp_ai_client_model_preference_filter_passes_context(): void {
		$captured_prefs      = null;
		$captured_capability = null;
		$captured_builder    = null;

		add_filter(
			'wp_ai_client_model_preference',
			static function ( $prefs, $capability, $builder ) use ( &$captured_prefs, &$captured_capability, &$captured_builder ) {
				$captured_prefs      = $prefs;
				$captured_capability = $capability;
				$captured_builder    = $builder;
				return $prefs;
			},
			10,
			3
		);

		$result   = $this->create_test_result( 'Response' );
		$metadata = $this->create_text_model_metadata( 'pref-model' );
		$model    = $this->create_mock_text_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Hello' );
		$builder->using_model_preference( 'custom-pref-1', 'custom-pref-2' );
		$builder->using_model( $model );

		$builder->generate_text();

		$this->assertSame( array( 'custom-pref-1', 'custom-pref-2' ), $captured_prefs );
		$this->assertInstanceOf( CapabilityEnum::class, $captured_capability );
		$this->assertTrue( $captured_capability->isTextGeneration() );
		$this->assertSame( $builder, $captured_builder );
	}

	/**
	 * Tests that wp_ai_client_generation_parameters filter can customize generation parameters before execution.
	 */
	public function test_wp_ai_client_generation_parameters_filter_customizes_parameters(): void {
		$captured_params = null;
		$applied_config  = null;

		add_filter(
			'wp_ai_client_generation_parameters',
			static function ( $params, $capability, $builder ) use ( &$captured_params ) {
				$captured_params              = $params;
				$params['temperature']        = 0.1;
				$params['max_tokens']         = 120;
				$params['top_p']              = 0.85;
				$params['top_k']              = 25;
				$params['presence_penalty']   = 0.3;
				$params['frequency_penalty']  = 0.4;
				$params['system_instruction'] = 'Filtered system instruction';
				$params['candidate_count']    = 1;
				$params['top_logprobs']       = 2;
				return $params;
			},
			10,
			3
		);

		$result   = $this->create_test_result( 'Response' );
		$metadata = $this->create_text_model_metadata( 'param-model' );
		$model    = $this->create_mock_text_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Hello' );
		$builder->using_temperature( 0.8 );
		$builder->using_model( $model );

		$builder->generate_text();

		// Filter received the caller's temperature.
		$this->assertSame( 0.8, $captured_params['temperature'] );

		// Filtered parameters were applied to the ModelConfig.
		$applied_config = $model->getConfig();
		$this->assertInstanceOf( ModelConfig::class, $applied_config );
		$this->assertSame( 0.1, $applied_config->getTemperature() );
		$this->assertSame( 120, $applied_config->getMaxTokens() );
		$this->assertSame( 0.85, $applied_config->getTopP() );
		$this->assertSame( 25, $applied_config->getTopK() );
		$this->assertSame( 0.3, $applied_config->getPresencePenalty() );
		$this->assertSame( 0.4, $applied_config->getFrequencyPenalty() );
		$this->assertSame( 'Filtered system instruction', $applied_config->getSystemInstruction() );
		$this->assertSame( 2, $applied_config->getTopLogprobs() );
	}

	/**
	 * Tests that wp_ai_client_generation_parameters filter can customize timeout per capability.
	 */
	public function test_wp_ai_client_generation_parameters_customizes_timeout(): void {
		add_filter(
			'wp_ai_client_generation_parameters',
			static function ( $params, $capability ) {
				$params['timeout'] = 45.0;
				return $params;
			},
			10,
			2
		);

		$result   = $this->create_test_result( 'Response' );
		$metadata = $this->create_text_model_metadata( 'timeout-model' );
		$model    = $this->create_mock_text_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Hello' );
		$builder->using_model( $model );

		$output = $builder->generate_text();
		$this->assertSame( 'Response', $output );
	}

	/**
	 * Tests that invalid parameters trigger _doing_it_wrong().
	 *
	 * @dataProvider data_invalid_generation_parameters
	 *
	 * @param array $invalid_params Array with an invalid parameter value.
	 */
	public function test_invalid_generation_parameters_trigger_doing_it_wrong( array $invalid_params ): void {
		$this->setExpectedIncorrectUsage( 'WP_AI_Client_Prompt_Builder::apply_filtered_generation_parameters' );

		add_filter(
			'wp_ai_client_generation_parameters',
			static function () use ( $invalid_params ) {
				return $invalid_params;
			}
		);

		$result   = $this->create_test_result( 'Response' );
		$metadata = $this->create_text_model_metadata( 'test-model' );
		$model    = $this->create_mock_text_generation_model( $result, $metadata );

		$builder = new WP_AI_Client_Prompt_Builder( $this->registry, 'Hello' );
		$builder->using_model( $model );

		$builder->generate_text();
	}

	/**
	 * Data provider for invalid generation parameters.
	 *
	 * @return array[]
	 */
	public function data_invalid_generation_parameters(): array {
		return array(
			'non_numeric_temperature'       => array( array( 'temperature' => 'high' ) ),
			'non_positive_max_tokens'       => array( array( 'max_tokens' => 0 ) ),
			'non_numeric_top_p'             => array( array( 'top_p' => 'narrow' ) ),
			'non_positive_top_k'            => array( array( 'top_k' => -1 ) ),
			'non_numeric_presence_penalty'  => array( array( 'presence_penalty' => 'none' ) ),
			'non_numeric_frequency_penalty' => array( array( 'frequency_penalty' => 'none' ) ),
			'empty_stop_sequences'          => array( array( 'stop_sequences' => array() ) ),
			'non_positive_candidate_count'  => array( array( 'candidate_count' => 0 ) ),
			'non_string_system_instruction' => array( array( 'system_instruction' => array( 'invalid' ) ) ),
			'negative_top_logprobs'         => array( array( 'top_logprobs' => -1 ) ),
			'negative_timeout'              => array( array( 'timeout' => -5.0 ) ),
		);
	}

	/**
	 * Tests that cloning a prompt builder preserves independent generation parameters.
	 */
	public function test_cloned_builder_preserves_independent_parameters(): void {
		$builder1 = new WP_AI_Client_Prompt_Builder( $this->registry, 'Original' );
		$builder1->using_temperature( 0.3 );
		$builder1->using_model_preference( 'model-1' );

		$builder2 = clone $builder1;
		$builder2->using_temperature( 0.85 );
		$builder2->using_model_preference( 'model-2' );

		$this->assertSame( 0.3, $builder1->get_generation_parameters()['temperature'] );
		$this->assertSame( array( 'model-1' ), $builder1->get_model_preferences() );

		$this->assertSame( 0.85, $builder2->get_generation_parameters()['temperature'] );
		$this->assertSame( array( 'model-2' ), $builder2->get_model_preferences() );
	}
}
