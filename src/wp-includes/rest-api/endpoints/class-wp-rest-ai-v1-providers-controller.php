<?php
/**
 * REST API: WP_REST_AI_V1_Providers_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 7.0.0
 */

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Core class used to manage AI providers and models via the REST API.
 *
 * @since 7.0.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_AI_V1_Providers_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 */
	public function __construct() {
		$this->namespace = 'wp-ai/v1';
		$this->rest_base = 'providers';
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since 7.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'process_get_providers_request' ),
					'permission_callback' => array( $this, 'permissions_check_providers' ),
				),
				'schema' => array( $this, 'get_provider_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<providerId>[^/]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'process_get_provider_request' ),
					'permission_callback' => array( $this, 'permissions_check_providers' ),
				),
				'args'   => array(
					'providerId' => array(
						'description' => __( 'The provider ID.' ),
						'type'        => 'string',
					),
				),
				'schema' => array( $this, 'get_provider_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<providerId>[^/]+)/models',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'process_get_models_request' ),
					'permission_callback' => array( $this, 'permissions_check_models' ),
				),
				'schema' => array( $this, 'get_model_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<providerId>[^/]+)/models/(?P<modelId>[^/]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'process_get_model_request' ),
					'permission_callback' => array( $this, 'permissions_check_models' ),
				),
				'args'   => array(
					'providerId' => array(
						'description' => __( 'The provider ID.' ),
						'type'        => 'string',
					),
					'modelId'    => array(
						'description' => __( 'The model ID.' ),
						'type'        => 'string',
					),
				),
				'schema' => array( $this, 'get_model_schema' ),
			)
		);
	}

	/**
	 * Checks if the user has permission to list AI providers.
	 *
	 * @since 7.0.0
	 *
	 * @return true|WP_Error True if authorized, WP_Error otherwise.
	 */
	public function permissions_check_providers() {
		if ( current_user_can( WP_AI_Client_Capabilities::LIST_AI_PROVIDERS ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to list AI providers.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Checks if the user has permission to list AI models.
	 *
	 * @since 7.0.0
	 *
	 * @return true|WP_Error True if authorized, WP_Error otherwise.
	 */
	public function permissions_check_models() {
		if ( current_user_can( WP_AI_Client_Capabilities::LIST_AI_MODELS ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to list AI models.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Retrieves a list of AI providers.
	 *
	 * @since 7.0.0
	 *
	 * @return WP_REST_Response The response object.
	 */
	public function process_get_providers_request() {
		$registry = AiClient::defaultRegistry();

		$provider_ids              = $registry->getRegisteredProviderIds();
		$provider_metadata_objects = array_map(
			function ( $id ) use ( $registry ) {
				$classname = $registry->getProviderClassName( $id );
				return $classname::metadata();
			},
			$provider_ids
		);

		return new WP_REST_Response( $provider_metadata_objects, 200 );
	}

	/**
	 * Retrieves a specific AI provider.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function process_get_provider_request( WP_REST_Request $request ) {
		$provider_id = $request['providerId'];
		$registry    = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( $provider_id ) ) {
			return new WP_Error(
				'rest_not_found',
				__( 'AI provider not found.' ),
				array( 'status' => 404 )
			);
		}

		$provider_classname = $registry->getProviderClassName( $provider_id );
		$provider_metadata  = $provider_classname::metadata();

		return new WP_REST_Response( $provider_metadata, 200 );
	}

	/**
	 * Retrieves a list of models for a specific provider.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function process_get_models_request( WP_REST_Request $request ) {
		$provider_id = $request['providerId'];
		$registry    = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( $provider_id ) ) {
			return new WP_Error(
				'rest_not_found',
				__( 'AI provider not found.' ),
				array( 'status' => 404 )
			);
		}

		$provider_classname = $registry->getProviderClassName( $provider_id );

		try {
			/** @var ProviderAvailabilityInterface $provider_availability */
			$provider_availability = $provider_classname::availability();
			if ( ! $provider_availability->isConfigured() ) {
				return new WP_Error(
					'ai_provider_not_configured',
					__( 'AI provider not configured - missing API credentials.' ),
					array( 'status' => 400 )
				);
			}

			/** @var ModelMetadataDirectoryInterface $model_metadata_directory */
			$model_metadata_directory = $provider_classname::modelMetadataDirectory();
			$model_metadata_objects   = $model_metadata_directory->listModelMetadata();

			return new WP_REST_Response( $model_metadata_objects, 200 );
		} catch ( Exception $e ) {
			return new WP_Error(
				'ai_list_models_error',
				sprintf(
					/* translators: %s: Error message. */
					__( 'Could not list models for provider - are the API credentials invalid? Error: %s' ),
					$e->getMessage()
				),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Retrieves a specific model for a specific provider.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function process_get_model_request( WP_REST_Request $request ) {
		$provider_id = $request['providerId'];
		$model_id    = $request['modelId'];

		$sub_request = new WP_REST_Request( 'GET', '/wp-ai/v1/providers/' . $provider_id . '/models' );
		$sub_request->set_url_params( array( 'providerId' => $provider_id ) );

		$get_models_response = $this->process_get_models_request( $sub_request );
		if ( is_wp_error( $get_models_response ) ) {
			return $get_models_response;
		}

		/** @var list<ModelMetadata> $models_metadata_objects */
		$models_metadata_objects = $get_models_response->get_data();
		foreach ( $models_metadata_objects as $model_metadata ) {
			if ( $model_metadata->getId() === $model_id ) {
				return new WP_REST_Response( $model_metadata, 200 );
			}
		}

		return new WP_Error(
			'rest_not_found',
			__( 'AI model not found.' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Retrieves the provider schema.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string, mixed> The provider schema.
	 */
	public function get_provider_schema(): array {
		$schema            = ProviderMetadata::getJsonSchema();
		$schema['$schema'] = 'http://json-schema.org/draft-04/schema#';
		$schema['title']   = 'ai_provider';

		return WP_AI_Client_JSON_Schema_Converter::convert( $schema );
	}

	/**
	 * Retrieves the model schema.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string, mixed> The model schema.
	 */
	public function get_model_schema(): array {
		$schema            = ModelMetadata::getJsonSchema();
		$schema['$schema'] = 'http://json-schema.org/draft-04/schema#';
		$schema['title']   = 'ai_model';

		return WP_AI_Client_JSON_Schema_Converter::convert( $schema );
	}
}
