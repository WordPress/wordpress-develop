<?php
/**
 * REST API: WP_REST_AI_V1_Generate_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 7.0.0
 */

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;

/**
 * Core class used to manage AI generation via the REST API.
 *
 * @since 7.0.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_AI_V1_Generate_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 */
	public function __construct() {
		$this->namespace = 'wp-ai/v1';
		$this->rest_base = 'generate';
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since 7.0.0
	 */
	public function register_routes() {
		$generation_request_schema = $this->get_generation_request_schema();

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'process_generate_request' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $generation_request_schema['properties'],
				),
				'schema' => array( $this, 'get_generation_result_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/is-supported',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'process_is_supported_request' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $generation_request_schema['properties'],
				),
				'schema' => array( $this, 'get_is_supported_schema' ),
			)
		);
	}

	/**
	 * Checks if the user has permission to prompt AI models.
	 *
	 * @since 7.0.0
	 *
	 * @return true|WP_Error True if authorized, WP_Error otherwise.
	 */
	public function permissions_check() {
		if ( current_user_can( WP_AI_Client_Capabilities::PROMPT_AI ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to prompt AI models directly.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Generates content using an AI model.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function process_generate_request( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		try {
			$builder = $this->create_builder_from_params( $params );

			$capability = null;
			if ( ! empty( $params['capability'] ) ) {
				$capability = CapabilityEnum::tryFrom( (string) $params['capability'] );
			}

			$result = $builder->generate_result( $capability );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return new WP_REST_Response( $result, 200 );
		} catch ( Exception $e ) {
			return new WP_Error( 'ai_generate_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Checks if the prompt and its configuration is supported by any available AI models.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function process_is_supported_request( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		try {
			$builder = $this->create_builder_from_params( $params );

			// Check specific capability if provided.
			if ( ! empty( $params['capability'] ) ) {
				$capability = CapabilityEnum::tryFrom( (string) $params['capability'] );
				if ( ! $capability ) {
					return new WP_Error(
						'ai_invalid_capability',
						__( 'Invalid capability.' ),
						array( 'status' => 400 )
					);
				}

				$supported = $builder->is_supported( $capability );
				return new WP_REST_Response( array( 'supported' => $supported ), 200 );
			}

			$supported = $builder->is_supported();
			return new WP_REST_Response( array( 'supported' => $supported ), 200 );
		} catch ( Exception $e ) {
			return new WP_Error( 'ai_is_supported_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Retrieves the generation request schema.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string, mixed> The request schema.
	 */
	public function get_generation_request_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ai_generation_request',
			'type'       => 'object',
			'properties' => array(
				'messages'         => array(
					'description' => __( 'The messages to generate content from.' ),
					'type'        => 'array',
					'items'       => WP_AI_Client_JSON_Schema_Converter::convert( Message::getJsonSchema() ),
					'required'    => true,
					'minItems'    => 1,
				),
				'modelConfig'      => WP_AI_Client_JSON_Schema_Converter::convert( ModelConfig::getJsonSchema() ),
				'providerId'       => array(
					'description' => __( 'The provider ID, to enforce using a model from that provider.' ),
					'type'        => 'string',
				),
				'modelId'          => array(
					'description' => __( 'The model ID, to enforce using that model. If given, a providerId must also be present.' ),
					'type'        => 'string',
				),
				'modelPreferences' => array(
					'description' => __( 'List of preferred models.' ),
					'type'        => 'array',
					'items'       => array(
						'oneOf' => array(
							array(
								'type' => 'string',
							),
							array(
								'type'     => 'array',
								'items'    => array(
									'type' => 'string',
								),
								'minItems' => 2,
								'maxItems' => 2,
							),
						),
					),
				),
				'capability'       => array(
					'description' => __( 'The capability to use.' ),
					'type'        => 'string',
					'enum'        => CapabilityEnum::getValues(),
				),
				'requestOptions'   => WP_AI_Client_JSON_Schema_Converter::convert( RequestOptions::getJsonSchema() ),
			),
		);
	}

	/**
	 * Retrieves the generation result schema.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string, mixed> The result schema.
	 */
	public function get_generation_result_schema(): array {
		$schema            = GenerativeAiResult::getJsonSchema();
		$schema['$schema'] = 'http://json-schema.org/draft-04/schema#';
		$schema['title']   = 'ai_generation_result';

		return WP_AI_Client_JSON_Schema_Converter::convert( $schema );
	}

	/**
	 * Retrieves the supported check schema.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string, mixed> The supported check schema.
	 */
	public function get_is_supported_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ai_is_supported_response',
			'type'       => 'object',
			'properties' => array(
				'supported' => array(
					'description' => __( 'Whether the capability is supported.' ),
					'type'        => 'boolean',
					'required'    => true,
				),
			),
		);
	}

	/**
	 * Creates a prompt builder from request parameters.
	 *
	 * @since 7.0.0
	 *
	 * @param array<string, mixed> $params The request parameters.
	 * @return WP_AI_Client_Prompt_Builder The prompt builder instance.
	 */
	private function create_builder_from_params( array $params ): WP_AI_Client_Prompt_Builder {
		// Messages are required by schema.
		$messages_data = $params['messages'];

		$messages = array_map(
			function ( $message ) {
				return Message::fromArray( $message );
			},
			$messages_data
		);

		$builder = wp_ai_client_prompt( array_values( $messages ) );

		if ( ! empty( $params['modelConfig'] ) && is_array( $params['modelConfig'] ) ) {
			$model_config_data = $params['modelConfig'];
			$config            = ModelConfig::fromArray( $model_config_data );
			$builder->using_model_config( $config );
		}

		// If both providerId and modelId are provided, this model must be used.
		if ( ! empty( $params['providerId'] ) && ! empty( $params['modelId'] ) ) {
			$provider_id = (string) $params['providerId'];
			$model_id    = (string) $params['modelId'];

			$provider_class_name = AiClient::defaultRegistry()->getProviderClassName( $provider_id );

			$model = $provider_class_name::model( $model_id );

			return $builder->using_model( $model );
		}

		if ( ! empty( $params['providerId'] ) ) {
			$builder->using_provider( (string) $params['providerId'] );
		}

		if ( ! empty( $params['modelPreferences'] ) && is_array( $params['modelPreferences'] ) ) {
			$builder->using_model_preference( ...$params['modelPreferences'] );
		}

		if ( ! empty( $params['requestOptions'] ) && is_array( $params['requestOptions'] ) ) {
			$request_options = RequestOptions::fromArray( $params['requestOptions'] );
			$builder->using_request_options( $request_options );
		}

		return $builder;
	}
}
