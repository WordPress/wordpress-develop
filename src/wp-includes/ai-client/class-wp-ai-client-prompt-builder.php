<?php
/**
 * WP AI Client: WP_AI_Client_Prompt_Builder class
 *
 * @package WordPress
 * @subpackage AI
 * @since 7.0.0
 */

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Builders\PromptBuilder;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Common\Exception\TokenLimitReachedException;
use WordPress\AiClient\Events\AfterGenerateResultEvent;
use WordPress\AiClient\Events\BeforeGenerateResultEvent;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\Http\Exception\NetworkException;
use WordPress\AiClient\Providers\Http\Exception\ServerException;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;
use WordPress\AiClient\Tools\DTO\WebSearch;

/**
 * Fluent builder for constructing AI prompts, returning WP_Error on failure.
 *
 * This class provides a fluent interface for building prompts with various
 * content types and model configurations. It wraps the PHP AI Client SDK's
 * PromptBuilder and adds WordPress-specific behavior including WP_Error
 * handling instead of exceptions, snake_case method naming, and integration
 * with the Abilities API.
 *
 * Only the generating methods will return a WP_Error, to not break the fluent
 * interface. As soon as any exception is caught in a chain of method calls,
 * the returned instance will be in an error state, and all subsequent method
 * calls will be no-ops that just return the same error state instance. Only
 * when a generating method is called, the WP_Error will be returned.
 *
 * @since 7.0.0
 *
 * @phpstan-import-type Prompt from PromptBuilder
 *
 * @method self with_text(string $text) Adds text to the current message.
 * @method self with_file($file, ?string $mimeType = null) Adds a file to the current message.
 * @method self with_function_response(FunctionResponse $functionResponse) Adds a function response to the current message.
 * @method self with_message_parts(MessagePart ...$parts) Adds message parts to the current message.
 * @method self with_history(Message ...$messages) Adds conversation history messages.
 * @method self using_model(ModelInterface $model) Sets the model to use for generation.
 * @method self using_model_preference(...$preferredModels) Sets preferred models to evaluate in order.
 * @method self using_model_config(ModelConfig $config) Sets the model configuration.
 * @method self using_provider(string $providerIdOrClassName) Sets the provider to use for generation.
 * @method self using_system_instruction(string $systemInstruction) Sets the system instruction.
 * @method self using_max_tokens(int $maxTokens) Sets the maximum number of tokens to generate.
 * @method self using_temperature(float $temperature) Sets the temperature for generation.
 * @method self using_top_p(float $topP) Sets the top-p value for generation.
 * @method self using_top_k(int $topK) Sets the top-k value for generation.
 * @method self using_stop_sequences(string ...$stopSequences) Sets stop sequences for generation.
 * @method self using_candidate_count(int $candidateCount) Sets the number of candidates to generate.
 * @method self using_function_declarations(FunctionDeclaration ...$functionDeclarations) Sets the function declarations available to the model.
 * @method self using_presence_penalty(float $presencePenalty) Sets the presence penalty for generation.
 * @method self using_frequency_penalty(float $frequencyPenalty) Sets the frequency penalty for generation.
 * @method self using_web_search(WebSearch $webSearch) Sets the web search configuration.
 * @method self using_request_options(RequestOptions $options) Sets the request options for HTTP transport.
 * @method self using_top_logprobs(?int $topLogprobs = null) Sets the top log probabilities configuration.
 * @method self as_output_mime_type(string $mimeType) Sets the output MIME type.
 * @method self as_output_schema(array<string, mixed> $schema) Sets the output schema.
 * @method self as_output_modalities(ModalityEnum ...$modalities) Sets the output modalities.
 * @method self as_output_file_type(FileTypeEnum $fileType) Sets the output file type.
 * @method self as_output_media_orientation(MediaOrientationEnum $orientation) Sets the output media orientation.
 * @method self as_output_media_aspect_ratio(string $aspectRatio) Sets the output media aspect ratio.
 * @method self as_output_speech_voice(string $voice) Sets the output speech voice.
 * @method self as_json_response(?array<string, mixed> $schema = null) Configures the prompt for JSON response output.
 * @method bool|WP_Error is_supported(?CapabilityEnum $capability = null) Checks if the prompt is supported for the given capability.
 * @method bool is_supported_for_text_generation() Checks if the prompt is supported for text generation.
 * @method bool is_supported_for_image_generation() Checks if the prompt is supported for image generation.
 * @method bool is_supported_for_text_to_speech_conversion() Checks if the prompt is supported for text to speech conversion.
 * @method bool is_supported_for_video_generation() Checks if the prompt is supported for video generation.
 * @method bool is_supported_for_speech_generation() Checks if the prompt is supported for speech generation.
 * @method bool is_supported_for_music_generation() Checks if the prompt is supported for music generation.
 * @method bool is_supported_for_embedding_generation() Checks if the prompt is supported for embedding generation.
 * @method GenerativeAiResult|WP_Error generate_result(?CapabilityEnum $capability = null) Generates a result from the prompt.
 * @method GenerativeAiResult|WP_Error generate_text_result() Generates a text result from the prompt.
 * @method GenerativeAiResult|WP_Error generate_image_result() Generates an image result from the prompt.
 * @method GenerativeAiResult|WP_Error generate_speech_result() Generates a speech result from the prompt.
 * @method GenerativeAiResult|WP_Error convert_text_to_speech_result() Converts text to speech and returns the result.
 * @method GenerativeAiResult|WP_Error generate_video_result() Generates a video result from the prompt.
 * @method string|WP_Error generate_text() Generates text from the prompt.
 * @method list<string>|WP_Error generate_texts(?int $candidateCount = null) Generates multiple text candidates from the prompt.
 * @method File|WP_Error generate_image() Generates an image from the prompt.
 * @method list<File>|WP_Error generate_images(?int $candidateCount = null) Generates multiple images from the prompt.
 * @method File|WP_Error convert_text_to_speech() Converts text to speech.
 * @method list<File>|WP_Error convert_text_to_speeches(?int $candidateCount = null) Converts text to multiple speech outputs.
 * @method File|WP_Error generate_speech() Generates speech from the prompt.
 * @method list<File>|WP_Error generate_speeches(?int $candidateCount = null) Generates multiple speech outputs from the prompt.
 * @method File|WP_Error generate_video() Generates a video from the prompt.
 * @method list<File>|WP_Error generate_videos(?int $candidateCount = null) Generates multiple videos from the prompt.
 */
class WP_AI_Client_Prompt_Builder {

	/**
	 * Wrapped prompt builder instance from the PHP AI Client SDK.
	 *
	 * @since 7.0.0
	 * @var PromptBuilder
	 */
	private PromptBuilder $builder;

	/**
	 * WordPress error instance, if any error occurred during method calls.
	 *
	 * @since 7.0.0
	 * @var WP_Error|null
	 */
	private ?WP_Error $error = null;

	/**
	 * Options for automatic ability resolution, or null when disabled.
	 *
	 * @since 7.2.0
	 * @var array{max_iterations: int}|null
	 */
	private ?array $ability_resolution_options = null;

	/**
	 * List of methods that generate a result from the prompt.
	 *
	 * Structured as a map for faster lookups.
	 *
	 * @since 7.0.0
	 * @var array<string, bool>
	 */
	private static array $generating_methods = array(
		'generate_result'               => true,
		'generate_text_result'          => true,
		'generate_image_result'         => true,
		'generate_speech_result'        => true,
		'convert_text_to_speech_result' => true,
		'generate_video_result'         => true,
		'generate_text'                 => true,
		'generate_texts'                => true,
		'generate_image'                => true,
		'generate_images'               => true,
		'convert_text_to_speech'        => true,
		'convert_text_to_speeches'      => true,
		'generate_speech'               => true,
		'generate_speeches'             => true,
		'generate_video'                => true,
		'generate_videos'               => true,
	);

	/**
	 * List of methods that check whether the prompt is supported.
	 *
	 * Structured as a map for faster lookups.
	 *
	 * @since 7.0.0
	 * @var array<string, bool>
	 */
	private static array $support_check_methods = array(
		'is_supported'                               => true,
		'is_supported_for_text_generation'           => true,
		'is_supported_for_image_generation'          => true,
		'is_supported_for_text_to_speech_conversion' => true,
		'is_supported_for_video_generation'          => true,
		'is_supported_for_speech_generation'         => true,
		'is_supported_for_music_generation'          => true,
		'is_supported_for_embedding_generation'      => true,
	);

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param ProviderRegistry $registry The provider registry for finding suitable models.
	 * @param Prompt           $prompt   Optional. Initial prompt content.
	 *                                   A string for simple text prompts,
	 *                                   a MessagePart or Message object for
	 *                                   structured content, an array for a
	 *                                   message array shape, or a list of
	 *                                   parts or messages for multi-turn
	 *                                   conversations. Default null.
	 */
	public function __construct( ProviderRegistry $registry, $prompt = null ) {
		try {
			$this->builder = new PromptBuilder( $registry, $prompt, AiClient::getEventDispatcher() );
		} catch ( Exception $e ) {
			$this->builder = new PromptBuilder( $registry, null, AiClient::getEventDispatcher() );
			$this->error   = $this->exception_to_wp_error( $e );
		}

		$default_timeout = 30.0;

		/**
		 * Filters the default request timeout in seconds for AI Client HTTP requests.
		 *
		 * @since 7.0.0
		 *
		 * @param float $default_timeout The default timeout in seconds.
		 */
		$filtered_default_timeout = apply_filters( 'wp_ai_client_default_request_timeout', $default_timeout );
		if ( is_numeric( $filtered_default_timeout ) && (float) $filtered_default_timeout >= 0.0 ) {
			$default_timeout = (float) $filtered_default_timeout;
		} else {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: wp_ai_client_default_request_timeout */
					__( 'The %s filter must return a non-negative number.' ),
					'<code>wp_ai_client_default_request_timeout</code>'
				),
				'7.0.0'
			);
		}

		$this->builder->usingRequestOptions(
			RequestOptions::fromArray(
				array(
					RequestOptions::KEY_TIMEOUT => $default_timeout,
				)
			)
		);
	}

	/**
	 * Registers WordPress abilities as function declarations for the AI model.
	 *
	 * Converts each WP_Ability to a FunctionDeclaration using the wpab__ prefix
	 * naming convention and passes them to the underlying prompt builder.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_Ability|string ...$abilities The abilities to register, either as WP_Ability objects or ability name strings.
	 * @return self The current instance for method chaining.
	 */
	public function using_abilities( ...$abilities ): self {
		$declarations = array();

		foreach ( $abilities as $ability ) {
			if ( is_string( $ability ) ) {
				$ability_name = $ability;
				$ability      = wp_get_ability( $ability );
				if ( ! $ability ) {
					_doing_it_wrong(
						__METHOD__,
						sprintf(
							/* translators: %s: string value of the ability name. */
							__( 'The ability %s was not found.' ),
							'<code>' . esc_html( $ability_name ) . '</code>'
						),
						'7.0.0'
					);
					continue;
				}
			}

			// This is only here as a sanity check, the method signature should ensure this already.
			if ( ! $ability instanceof WP_Ability ) {
				continue;
			}

			$function_name = WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name( $ability->get_name() );
			$input_schema  = wp_prepare_json_schema_for_client( $ability->get_input_schema() );

			$declarations[] = new FunctionDeclaration(
				$function_name,
				$ability->get_description(),
				! empty( $input_schema ) ? $input_schema : null
			);
		}

		if ( ! empty( $declarations ) ) {
			return $this->using_function_declarations( ...$declarations );
		}

		return $this;
	}

	/**
	 * Enables automatic resolution of ability function calls.
	 *
	 * When enabled, the text generation methods run a resolution loop instead of
	 * a single request. Each round executes the ability calls requested by the
	 * model, appends the results to the conversation, and requests a follow-up
	 * response. The loop ends when the model produces a response without ability
	 * calls, when it requests a function that is not a registered ability, or
	 * when the maximum number of rounds is reached.
	 *
	 * Only abilities that were exposed to the model as function declarations,
	 * typically with {@see self::using_abilities()}, can be executed.
	 * Resolution follows the first response candidate and supports the
	 * generate_text_result() and generate_text() methods. Details about the loop
	 * are exposed under the `ability_resolution` key of the additional data of
	 * the final result.
	 *
	 * @since 7.2.0
	 *
	 * @param array $options {
	 *     Optional. Options controlling the resolution loop.
	 *
	 *     @type int $max_iterations Maximum number of resolution rounds. Each round executes
	 *                               the ability calls from one model response and requests a
	 *                               follow-up response. Default 5.
	 * }
	 * @return self The current instance for method chaining.
	 */
	public function using_ability_resolution( array $options = array() ): self {
		$options = wp_parse_args(
			$options,
			array(
				'max_iterations' => 5,
			)
		);

		if ( ! is_int( $options['max_iterations'] ) || $options['max_iterations'] < 1 ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: max_iterations */
					__( 'The %s option must be a positive integer.' ),
					'<code>max_iterations</code>'
				),
				'7.2.0'
			);
			$options['max_iterations'] = 5;
		}

		$this->ability_resolution_options = array(
			'max_iterations' => $options['max_iterations'],
		);

		return $this;
	}

	/**
	 * Magic method to proxy snake_case method calls to their PHP AI Client camelCase counterparts.
	 *
	 * This allows WordPress developers to use snake_case naming conventions. It catches
	 * any exceptions thrown, stores them, and returns a WP_Error when a terminate method
	 * is called. When automatic ability resolution is enabled, the supported text
	 * generation methods run the resolution loop instead of a single request.
	 *
	 * @since 7.0.0
	 *
	 * @param string            $name      The method name in snake_case.
	 * @param array<int, mixed> $arguments The method arguments.
	 * @return mixed The result of the method call.
	 */
	public function __call( string $name, array $arguments ) {
		if ( null !== $this->ability_resolution_options && self::is_generating_method( $name ) ) {
			if ( 'generate_text_result' === $name || 'generate_text' === $name ) {
				return $this->generate_with_ability_resolution( $name );
			}

			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: 1: generate_text_result, 2: generate_text, 3: the method that was called. */
					__( 'Automatic ability resolution supports only the %1$s and %2$s methods. The %3$s method runs without it.' ),
					'<code>generate_text_result()</code>',
					'<code>generate_text()</code>',
					'<code>' . esc_html( $name ) . '()</code>'
				),
				'7.2.0'
			);
		}

		return $this->call_builder( $name, $arguments );
	}

	/**
	 * Proxies a method call to the wrapped prompt builder with WordPress-specific guards.
	 *
	 * @since 7.2.0
	 *
	 * @param string            $name      The method name in snake_case.
	 * @param array<int, mixed> $arguments The method arguments.
	 * @return mixed The result of the method call.
	 */
	private function call_builder( string $name, array $arguments ) {
		/*
		 * If an error occurred in a previous method call, either return the error for terminate methods,
		 * or return the same instance for other methods to maintain the fluent interface.
		 */
		if ( null !== $this->error ) {
			if ( self::is_generating_method( $name ) ) {
				return $this->error;
			}
			if ( self::is_support_check_method( $name ) ) {
				return false;
			}
			return $this;
		}

		// Check if the prompt should be prevented for is_supported* and generate_*/convert_text_to_speech* methods.
		if ( self::is_support_check_method( $name ) || self::is_generating_method( $name ) ) {
			$prevented = $this->get_prompt_prevented_error();

			if ( null !== $prevented ) {
				// For is_supported* methods, return false.
				if ( self::is_support_check_method( $name ) ) {
					return false;
				}

				// For generate_* and convert_text_to_speech* methods, store the WP_Error.
				$this->error = $prevented;

				if ( self::is_generating_method( $name ) ) {
					return $this->error;
				}
				return $this;
			}
		}

		try {
			$callable = $this->get_builder_callable( $name );
			$result   = $callable( ...$arguments );

			// If the result is a PromptBuilder, return the current instance to allow method chaining.
			if ( $result instanceof PromptBuilder ) {
				return $this;
			}

			return $result;
		} catch ( Exception $e ) {
			$this->error = $this->exception_to_wp_error( $e );

			if ( self::is_generating_method( $name ) ) {
				return $this->error;
			}
			return $this;
		}
	}

	/**
	 * Checks whether the prompt is prevented from being executed.
	 *
	 * @since 7.2.0
	 *
	 * @return WP_Error|null A WP_Error when the prompt is prevented, null otherwise.
	 */
	private function get_prompt_prevented_error(): ?WP_Error {
		// If AI is not supported, then there's no need to apply the filter as the prompt will be prevented anyway.
		$is_ai_disabled = ! wp_supports_ai();
		$prevent        = $is_ai_disabled;
		if ( ! $prevent ) {
			/**
			 * Filters whether to prevent the prompt from being executed.
			 *
			 * @since 7.0.0
			 *
			 * @param bool                        $prevent Whether to prevent the prompt. Default false.
			 * @param WP_AI_Client_Prompt_Builder $builder A clone of the prompt builder instance (read-only).
			 */
			$prevent = (bool) apply_filters( 'wp_ai_client_prevent_prompt', false, clone $this );
		}

		if ( ! $prevent ) {
			return null;
		}

		$error_message = $is_ai_disabled
			? __( 'AI features are not supported in this environment.' )
			: __( 'Prompt execution was prevented by a filter.' );

		return new WP_Error(
			'prompt_prevented',
			$error_message,
			array(
				'status' => 503,
			)
		);
	}

	/**
	 * Generates a text result while automatically resolving ability function calls.
	 *
	 * Runs the resolution loop: each round executes the ability calls requested
	 * by the model, appends the results to the conversation, and requests a
	 * follow-up response. See {@see self::using_ability_resolution()} for the
	 * termination conditions.
	 *
	 * @since 7.2.0
	 *
	 * @param string $method Either 'generate_text_result' or 'generate_text'.
	 * @return GenerativeAiResult|string|WP_Error The final result, the final text, or a WP_Error on failure.
	 */
	private function generate_with_ability_resolution( string $method ) {
		$options = $this->ability_resolution_options;

		/*
		 * The PHP AI Client prompt builder does not expose its message list, nor
		 * a way to append messages to it. The first request therefore captures
		 * the sent messages and the resolved model from the lifecycle event that
		 * the builder dispatches. Later rounds call the captured model directly
		 * with an extended copy of that transcript. A message append method in
		 * the PHP AI Client would simplify this.
		 */
		$captured = null;
		$capture  = static function ( $event ) use ( &$captured ) {
			if ( null === $captured && $event instanceof BeforeGenerateResultEvent ) {
				$captured = $event;
			}
		};

		add_action( 'wp_ai_client_before_generate_result', $capture );
		$result = $this->call_builder( 'generate_text_result', array() );
		remove_action( 'wp_ai_client_before_generate_result', $capture );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( null === $captured || ! $captured->getModel() instanceof TextGenerationModelInterface ) {
			// Without the captured context the conversation cannot be continued.
			return $this->to_generation_return_value( $result, $method );
		}

		$model      = $captured->getModel();
		$capability = $captured->getCapability();
		$transcript = $captured->getMessages();
		$dispatcher = AiClient::getEventDispatcher();

		/*
		 * The allow-list for execution is derived from the function declarations
		 * that were sent to the model. A response may name any function, so the
		 * resolver enforces that only explicitly exposed abilities can run.
		 */
		$ability_names = array();
		$declarations  = $model->getConfig()->getFunctionDeclarations() ?? array();
		foreach ( $declarations as $declaration ) {
			$function_name = $declaration->getName();
			if ( WP_AI_Client_Ability_Function_Resolver::is_ability_function_name( $function_name ) ) {
				$ability_names[] = WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name( $function_name );
			}
		}

		if ( empty( $ability_names ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: 1: using_ability_resolution, 2: using_abilities */
					__( '%1$s requires abilities registered with %2$s.' ),
					'<code>using_ability_resolution()</code>',
					'<code>using_abilities()</code>'
				),
				'7.2.0'
			);
			return $this->to_generation_return_value( $result, $method );
		}

		$resolver = new WP_AI_Client_Ability_Function_Resolver( ...$ability_names );

		$rounds         = 0;
		$usage          = $result->getTokenUsage();
		$resolved_calls = array();

		while ( true ) {
			$message = $result->toMessage();
			$calls   = $this->get_function_calls( $message );

			if ( empty( $calls ) ) {
				$stop_reason = 'completed';
				break;
			}

			$ability_calls = array_filter( $calls, array( $resolver, 'is_ability_call' ) );

			if ( count( $ability_calls ) < count( $calls ) ) {
				// The response requests functions that are not registered abilities.
				// Hand the round back to the caller to resolve them.
				$stop_reason = 'unresolved_function_calls';
				break;
			}

			if ( $rounds >= $options['max_iterations'] ) {
				$stop_reason = 'max_iterations';
				break;
			}

			$prevented = $this->get_prompt_prevented_error();
			if ( null !== $prevented ) {
				$this->error = $prevented;
				return $this->error;
			}

			$responses = $resolver->execute_abilities( $message );

			foreach ( $ability_calls as $call ) {
				$resolved_calls[] = array(
					'id'      => $call->getId(),
					'ability' => WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name( (string) $call->getName() ),
				);
			}

			$transcript[] = $message;
			$transcript[] = $responses;
			++$rounds;

			if ( null !== $dispatcher ) {
				$dispatcher->dispatch( new BeforeGenerateResultEvent( $transcript, $model, $capability ) );
			}

			try {
				$result = $model->generateTextResult( $transcript );
			} catch ( Exception $e ) {
				$this->error = $this->exception_to_wp_error( $e );
				return $this->error;
			}

			if ( null !== $dispatcher ) {
				$dispatcher->dispatch( new AfterGenerateResultEvent( $transcript, $model, $capability, $result ) );
			}

			$usage = $this->aggregate_token_usage( $usage, $result->getTokenUsage() );
		}

		return $this->finish_ability_resolution( $result, $method, $stop_reason, $rounds, $usage, $resolved_calls, $transcript );
	}

	/**
	 * Converts a result into the return value of the called generation method.
	 *
	 * Used when the resolution loop exits early with a plain result, so that
	 * generate_text() still returns a string or a WP_Error.
	 *
	 * @since 7.2.0
	 *
	 * @param GenerativeAiResult $result The result to convert.
	 * @param string             $method Either 'generate_text_result' or 'generate_text'.
	 * @return GenerativeAiResult|string|WP_Error The result, its text, or a WP_Error on failure.
	 */
	private function to_generation_return_value( GenerativeAiResult $result, string $method ) {
		if ( 'generate_text' !== $method ) {
			return $result;
		}

		try {
			return $result->toText();
		} catch ( Exception $e ) {
			$this->error = $this->exception_to_wp_error( $e );
			return $this->error;
		}
	}

	/**
	 * Retrieves the function calls contained in a message.
	 *
	 * @since 7.2.0
	 *
	 * @param Message $message The message to inspect.
	 * @return FunctionCall[] The function calls in the message.
	 */
	private function get_function_calls( Message $message ): array {
		$calls = array();

		foreach ( $message->getParts() as $part ) {
			if ( $part->getType()->isFunctionCall() ) {
				$call = $part->getFunctionCall();
				if ( $call instanceof FunctionCall ) {
					$calls[] = $call;
				}
			}
		}

		return $calls;
	}

	/**
	 * Adds up two token usage objects.
	 *
	 * @since 7.2.0
	 *
	 * @param TokenUsage $total    The running total.
	 * @param TokenUsage $addition The usage to add.
	 * @return TokenUsage The combined token usage.
	 */
	private function aggregate_token_usage( TokenUsage $total, TokenUsage $addition ): TokenUsage {
		$thought_tokens = null;
		if ( null !== $total->getThoughtTokens() || null !== $addition->getThoughtTokens() ) {
			$thought_tokens = (int) $total->getThoughtTokens() + (int) $addition->getThoughtTokens();
		}

		return new TokenUsage(
			$total->getPromptTokens() + $addition->getPromptTokens(),
			$total->getCompletionTokens() + $addition->getCompletionTokens(),
			$total->getTotalTokens() + $addition->getTotalTokens(),
			$thought_tokens
		);
	}

	/**
	 * Builds the final value of an ability resolution loop.
	 *
	 * Rebuilds the result with the aggregated token usage and details about the
	 * loop under the `ability_resolution` key of the additional data.
	 *
	 * @since 7.2.0
	 *
	 * @param GenerativeAiResult                          $result         The result of the last round.
	 * @param string                                      $method         Either 'generate_text_result' or 'generate_text'.
	 * @param string                                      $stop_reason    Why the loop ended. One of 'completed',
	 *                                                                    'unresolved_function_calls', or 'max_iterations'.
	 * @param int                                         $rounds         Number of resolution rounds that ran.
	 * @param TokenUsage                                  $usage          Aggregated token usage across all rounds.
	 * @param array<int, array{id: ?string, ability: string}> $resolved_calls The ability calls that were resolved.
	 * @param Message[]                                   $transcript     The conversation before the final response.
	 * @return GenerativeAiResult|string|WP_Error The final result or text, or a WP_Error on failure.
	 */
	private function finish_ability_resolution( GenerativeAiResult $result, string $method, string $stop_reason, int $rounds, TokenUsage $usage, array $resolved_calls, array $transcript ) {
		$messages   = $transcript;
		$messages[] = $result->toMessage();

		$additional_data                       = $result->getAdditionalData();
		$additional_data['ability_resolution'] = array(
			'rounds'         => $rounds,
			'stop_reason'    => $stop_reason,
			'resolved_calls' => $resolved_calls,
			'messages'       => array_map(
				static function ( Message $message ) {
					return $message->toArray();
				},
				$messages
			),
		);

		$final = new GenerativeAiResult(
			$result->getId(),
			$result->getCandidates(),
			$usage,
			$result->getProviderMetadata(),
			$result->getModelMetadata(),
			$additional_data
		);

		if ( 'generate_text_result' === $method ) {
			return $final;
		}

		// generate_text() returns the plain text of the final answer.
		if ( 'completed' !== $stop_reason ) {
			$this->error = new WP_Error(
				'ability_resolution_incomplete',
				__( 'The model did not produce a final answer within the ability resolution limits.' ),
				array(
					'status'      => 500,
					'stop_reason' => $stop_reason,
					'rounds'      => $rounds,
				)
			);
			return $this->error;
		}

		return $this->to_generation_return_value( $final, $method );
	}

	/**
	 * Converts an exception into a WP_Error with a structured error code and message.
	 *
	 * This method maps different exception types to specific WP_Error codes and HTTP status codes.
	 * The presence of the status codes means these WP_Error objects can be easily used in REST API responses
	 * or other contexts where HTTP semantics are relevant.
	 *
	 * @since 7.0.0
	 *
	 * @param Exception $e The exception to convert.
	 * @return WP_Error The resulting WP_Error object.
	 */
	private function exception_to_wp_error( Exception $e ): WP_Error {
		if ( $e instanceof NetworkException ) {
			$error_code  = 'prompt_network_error';
			$status_code = 503;
		} elseif ( $e instanceof ClientException ) {
			// `ClientException` uses HTTP status codes as exception codes, so we can rely on them.
			$error_code  = 'prompt_client_error';
			$status_code = $e->getCode() ? $e->getCode() : 400;
		} elseif ( $e instanceof ServerException ) {
			// `ServerException` uses HTTP status codes as exception codes, so we can rely on them.
			$error_code  = 'prompt_upstream_server_error';
			$status_code = $e->getCode() ? $e->getCode() : 500;
		} elseif ( $e instanceof TokenLimitReachedException ) {
			$error_code  = 'prompt_token_limit_reached';
			$status_code = 400;
		} elseif ( $e instanceof InvalidArgumentException ) {
			$error_code  = 'prompt_invalid_argument';
			$status_code = 400;
		} else {
			$error_code  = 'prompt_builder_error';
			$status_code = 500;
		}

		return new WP_Error(
			$error_code,
			$e->getMessage(),
			array(
				'status'          => $status_code,
				'exception_class' => get_class( $e ),
			)
		);
	}

	/**
	 * Checks if a method name is a support check method (is_supported*).
	 *
	 * @since 7.0.0
	 *
	 * @param string $name The method name.
	 * @return bool True if the method is a support check method, false otherwise.
	 */
	private static function is_support_check_method( string $name ): bool {
		return isset( self::$support_check_methods[ $name ] );
	}

	/**
	 * Checks if a method name is a generating method (generate_*, convert_text_to_speech*).
	 *
	 * @since 7.0.0
	 *
	 * @param string $name The method name.
	 * @return bool True if the method is a generating method, false otherwise.
	 */
	private static function is_generating_method( string $name ): bool {
		return isset( self::$generating_methods[ $name ] );
	}

	/**
	 * Retrieves a callable for a given PHP AI Client SDK prompt builder method name.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name The method name in snake_case.
	 * @return callable The callable for the specified method.
	 *
	 * @throws BadMethodCallException If the method does not exist.
	 */
	protected function get_builder_callable( string $name ): callable {
		$camel_case_name = $this->snake_to_camel_case( $name );

		$method = array( $this->builder, $camel_case_name );
		if ( ! is_callable( $method ) ) {
			throw new BadMethodCallException(
				sprintf(
					/* translators: 1: Method name. 2: Class name. */
					__( 'Method %1$s does not exist on %2$s.' ),
					$name, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					get_class( $this->builder ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		return $method;
	}

	/**
	 * Converts snake_case to camelCase.
	 *
	 * @since 7.0.0
	 *
	 * @param string $snake_case The snake_case string.
	 * @return string The camelCase string.
	 */
	private function snake_to_camel_case( string $snake_case ): string {
		$parts = explode( '_', $snake_case );

		$camel_case  = $parts[0];
		$parts_count = count( $parts );
		for ( $i = 1; $i < $parts_count; $i++ ) {
			$camel_case .= ucfirst( $parts[ $i ] );
		}

		return $camel_case;
	}
}
