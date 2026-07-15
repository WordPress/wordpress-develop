<?php
/**
 * WP AI Client: WP_AI_Client_Embedding_Builder class
 *
 * @package WordPress
 * @subpackage AI
 * @since 7.1.0
 */

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Builders\EmbeddingBuilder;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Common\Exception\TokenLimitReachedException;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\Http\Exception\NetworkException;
use WordPress\AiClient\Providers\Http\Exception\ServerException;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Results\DTO\Embedding;
use WordPress\AiClient\Results\DTO\EmbeddingResult;

/**
 * Fluent builder for generating embeddings, returning WP_Error on failure.
 *
 * This class provides a fluent interface for generating embedding vectors from
 * text or file inputs. It wraps the PHP AI Client SDK's EmbeddingBuilder and
 * adds WordPress-specific behavior including WP_Error handling instead of
 * exceptions and snake_case method naming.
 *
 * Only the generating methods will return a WP_Error, to not break the fluent
 * interface. As soon as any exception is caught in a chain of method calls,
 * the returned instance will be in an error state, and all subsequent method
 * calls will be no-ops that just return the same error state instance. Only
 * when a generating method is called, the WP_Error will be returned.
 *
 * @since 7.1.0
 *
 * @phpstan-import-type EmbeddingInput from EmbeddingBuilder
 *
 * @method self with_input(...$input) Adds one or more inputs to embed.
 * @method self using_dimensions(int $dimensions) Sets the embedding dimensions.
 * @method self using_model(ModelInterface $model) Sets the model to use for generation.
 * @method self using_model_preference(...$preferredModels) Sets preferred models to evaluate in order.
 * @method self using_model_config(ModelConfig $config) Sets the model configuration.
 * @method self using_provider(string $providerIdOrClassName) Sets the provider to use for generation.
 * @method self using_request_options(RequestOptions $options) Sets the request options for HTTP transport.
 * @method bool is_supported() Checks whether the current inputs and configuration are supported by an available model.
 * @method EmbeddingResult|WP_Error generate_embedding_result() Generates an embedding result from the configured inputs.
 * @method Embedding|WP_Error generate_embedding() Generates a single embedding from the configured input.
 * @method list<Embedding>|WP_Error generate_embeddings() Generates embeddings from the configured inputs.
 */
class WP_AI_Client_Embedding_Builder {

	/**
	 * Wrapped embedding builder instance from the PHP AI Client SDK.
	 *
	 * @since 7.1.0
	 * @var EmbeddingBuilder
	 */
	private EmbeddingBuilder $builder;

	/**
	 * WordPress error instance, if any error occurred during method calls.
	 *
	 * @since 7.1.0
	 * @var WP_Error|null
	 */
	private ?WP_Error $error = null;

	/**
	 * List of methods that generate embeddings from the inputs.
	 *
	 * Structured as a map for faster lookups.
	 *
	 * @since 7.1.0
	 * @var array<string, bool>
	 */
	private static array $generating_methods = array(
		'generate_embedding_result' => true,
		'generate_embedding'        => true,
		'generate_embeddings'       => true,
	);

	/**
	 * List of methods that check whether the embedding generation is supported.
	 *
	 * Structured as a map for faster lookups.
	 *
	 * @since 7.1.0
	 * @var array<string, bool>
	 */
	private static array $support_check_methods = array(
		'is_supported' => true,
	);

	/**
	 * Constructor.
	 *
	 * @since 7.1.0
	 *
	 * @param ProviderRegistry                         $registry The provider registry for finding suitable models.
	 * @param EmbeddingInput|list<EmbeddingInput>|null $input    Optional. Initial input(s) to embed.
	 *                                                           A string for simple text inputs,
	 *                                                           a MessagePart or File object for
	 *                                                           structured content, an array for a
	 *                                                           message part array shape, or a list
	 *                                                           of any of these to embed multiple
	 *                                                           inputs. Default null.
	 */
	public function __construct( ProviderRegistry $registry, $input = null ) {
		try {
			$this->builder = new EmbeddingBuilder( $registry, $input, AiClient::getEventDispatcher() );
		} catch ( Exception $e ) {
			$this->builder = new EmbeddingBuilder( $registry, null, AiClient::getEventDispatcher() );
			$this->error   = $this->exception_to_wp_error( $e );
		}

		$default_timeout = 30.0;

		/** This filter is documented in wp-includes/ai-client/class-wp-ai-client-prompt-builder.php */
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
				'7.1.0'
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
	 * Magic method to proxy snake_case method calls to their PHP AI Client camelCase counterparts.
	 *
	 * This allows WordPress developers to use snake_case naming conventions. It catches
	 * any exceptions thrown, stores them, and returns a WP_Error when a terminate method
	 * is called.
	 *
	 * @since 7.1.0
	 *
	 * @param string            $name      The method name in snake_case.
	 * @param array<int, mixed> $arguments The method arguments.
	 * @return mixed The result of the method call.
	 */
	public function __call( string $name, array $arguments ) {
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

		// Check if the embedding generation should be prevented for is_supported and generate_* methods.
		if ( self::is_support_check_method( $name ) || self::is_generating_method( $name ) ) {
			// If AI is not supported, then there's no need to apply the filter as the embedding will be prevented anyway.
			$is_ai_disabled = ! wp_supports_ai();
			$prevent        = $is_ai_disabled;
			if ( ! $prevent ) {
				/**
				 * Filters whether to prevent the embedding generation from being executed.
				 *
				 * @since 7.1.0
				 *
				 * @param bool                           $prevent Whether to prevent the embedding generation. Default false.
				 * @param WP_AI_Client_Embedding_Builder $builder A clone of the embedding builder instance (read-only).
				 */
				$prevent = (bool) apply_filters( 'wp_ai_client_prevent_embedding', false, clone $this );
			}

			if ( $prevent ) {
				// For the is_supported method, return false.
				if ( self::is_support_check_method( $name ) ) {
					return false;
				}

				$error_message = $is_ai_disabled
					? __( 'AI features are not supported in this environment.' )
					: __( 'Embedding generation was prevented by a filter.' );

				// For generate_* methods, create a WP_Error.
				$this->error = new WP_Error(
					'embedding_prevented',
					$error_message,
					array(
						'status' => 503,
					)
				);

				if ( self::is_generating_method( $name ) ) {
					return $this->error;
				}
				return $this;
			}
		}

		try {
			$callable = $this->get_builder_callable( $name );
			$result   = $callable( ...$arguments );

			// If the result is an EmbeddingBuilder, return the current instance to allow method chaining.
			if ( $result instanceof EmbeddingBuilder ) {
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
	 * Converts an exception into a WP_Error with a structured error code and message.
	 *
	 * This method maps different exception types to specific WP_Error codes and HTTP status codes.
	 * The presence of the status codes means these WP_Error objects can be easily used in REST API responses
	 * or other contexts where HTTP semantics are relevant.
	 *
	 * @since 7.1.0
	 *
	 * @param Exception $e The exception to convert.
	 * @return WP_Error The resulting WP_Error object.
	 */
	private function exception_to_wp_error( Exception $e ): WP_Error {
		if ( $e instanceof NetworkException ) {
			$error_code  = 'embedding_network_error';
			$status_code = 503;
		} elseif ( $e instanceof ClientException ) {
			// `ClientException` uses HTTP status codes as exception codes, so we can rely on them.
			$error_code  = 'embedding_client_error';
			$status_code = $e->getCode() ? $e->getCode() : 400;
		} elseif ( $e instanceof ServerException ) {
			// `ServerException` uses HTTP status codes as exception codes, so we can rely on them.
			$error_code  = 'embedding_upstream_server_error';
			$status_code = $e->getCode() ? $e->getCode() : 500;
		} elseif ( $e instanceof TokenLimitReachedException ) {
			$error_code  = 'embedding_token_limit_reached';
			$status_code = 400;
		} elseif ( $e instanceof InvalidArgumentException ) {
			$error_code  = 'embedding_invalid_argument';
			$status_code = 400;
		} else {
			$error_code  = 'embedding_builder_error';
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
	 * Checks if a method name is a support check method (is_supported).
	 *
	 * @since 7.1.0
	 *
	 * @param string $name The method name.
	 * @return bool True if the method is a support check method, false otherwise.
	 */
	private static function is_support_check_method( string $name ): bool {
		return isset( self::$support_check_methods[ $name ] );
	}

	/**
	 * Checks if a method name is a generating method (generate_*).
	 *
	 * @since 7.1.0
	 *
	 * @param string $name The method name.
	 * @return bool True if the method is a generating method, false otherwise.
	 */
	private static function is_generating_method( string $name ): bool {
		return isset( self::$generating_methods[ $name ] );
	}

	/**
	 * Retrieves a callable for a given PHP AI Client SDK embedding builder method name.
	 *
	 * @since 7.1.0
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
	 * @since 7.1.0
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
