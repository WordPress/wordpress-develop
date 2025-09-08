<?php
/**
 * REST API run controller for Abilities API.
 *
 * @package WordPress
 * @subpackage Abilities_API
 * @since 0.1.0
 */

declare( strict_types = 1 );

/**
 * Core controller used to execute abilities via the REST API.
 *
 * @since 0.1.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Abilities_Run_Controller extends WP_REST_Controller {

	/**
	 * REST API namespace.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $namespace = 'wp/v2';

	/**
	 * REST API base route.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $rest_base = 'abilities';

	/**
	 * Registers the routes for ability execution.
	 *
	 * @since 0.1.0
	 *
	 * @see register_rest_route()
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<name>[a-zA-Z0-9\-\/]+?)/run',
			array(
				'args'   => array(
					'name' => array(
						'description' => __( 'Unique identifier for the ability.' ),
						'type'        => 'string',
						'pattern'     => '^[a-zA-Z0-9\-\/]+$',
					),
				),

				// TODO: We register ALLMETHODS because at route registration time, we don't know
				// which abilities exist or their types (resource vs tool). This is due to WordPress
				// load order - routes are registered early, before plugins have registered their abilities.
				// This approach works but could be improved with lazy route registration or a different
				// architecture that allows type-specific routes after abilities are registered.
				// This was the same issue that we ended up seeing with the Feature API.
				array(
					'methods'             => WP_REST_Server::ALLMETHODS,
					'callback'            => array( $this, 'run_ability_with_method_check' ),
					'permission_callback' => array( $this, 'run_ability_permissions_check' ),
					'args'                => $this->get_run_args(),
				),
				'schema' => array( $this, 'get_run_schema' ),
			)
		);
	}

	/**
	 * Executes an ability with HTTP method validation.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function run_ability_with_method_check( $request ) {
		$ability = wp_get_ability( $request->get_param( 'name' ) );

		if ( ! $ability ) {
			return new \WP_Error(
				'rest_ability_not_found',
				__( 'Ability not found.' ),
				array( 'status' => 404 )
			);
		}

		// Check if the HTTP method matches the ability type.
		$meta   = $ability->get_meta();
		$type   = isset( $meta['type'] ) ? $meta['type'] : 'tool';
		$method = $request->get_method();

		if ( 'resource' === $type && 'GET' !== $method ) {
			return new \WP_Error(
				'rest_ability_invalid_method',
				__( 'Resource abilities require GET method.' ),
				array( 'status' => 405 )
			);
		}

		if ( 'tool' === $type && 'POST' !== $method ) {
			return new \WP_Error(
				'rest_ability_invalid_method',
				__( 'Tool abilities require POST method.' ),
				array( 'status' => 405 )
			);
		}

		return $this->run_ability( $request );
	}

	/**
	 * Executes an ability.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function run_ability( $request ) {
		$ability = wp_get_ability( $request->get_param( 'name' ) );
		if ( ! $ability ) {
			return new \WP_Error(
				'rest_ability_not_found',
				__( 'Ability not found.' ),
				array( 'status' => 404 )
			);
		}

		$input  = $this->get_input_from_request( $request );
		$result = $ability->execute( $input );
		if ( is_wp_error( $result ) ) {
			if ( 'ability_invalid_input' === $result->get_error_code() ) {
				$result->add_data( array( 'status' => 400 ) );
			}
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Checks if a given request has permission to execute a specific ability.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Full details about the request.
	 * @return true|\WP_Error True if the request has execution permission, WP_Error object otherwise.
	 */
	public function run_ability_permissions_check( $request ) {
		$ability = wp_get_ability( $request->get_param( 'name' ) );
		if ( ! $ability ) {
			return new \WP_Error(
				'rest_ability_not_found',
				__( 'Ability not found.' ),
				array( 'status' => 404 )
			);
		}

		$input = $this->get_input_from_request( $request );
		if ( ! $ability->has_permission( $input ) ) {
			return new \WP_Error(
				'rest_ability_cannot_execute',
				__( 'Sorry, you are not allowed to execute this ability.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Extracts input parameters from the request.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request The request object.
	 * @return array<string, mixed> The input parameters.
	 */
	private function get_input_from_request( $request ) {
		if ( 'GET' === $request->get_method() ) {
			// For GET requests, look for 'input' query parameter.
			$query_params = $request->get_query_params();
			return isset( $query_params['input'] ) && is_array( $query_params['input'] )
				? $query_params['input']
				: array();
		}

		// For POST requests, look for 'input' in JSON body.
		$json_params = $request->get_json_params();
		return isset( $json_params['input'] ) && is_array( $json_params['input'] )
			? $json_params['input']
			: array();
	}

	/**
	 * Retrieves the arguments for ability execution endpoint.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> Arguments for the run endpoint.
	 */
	public function get_run_args(): array {
		return array(
			'input' => array(
				'description' => __( 'Input parameters for the ability execution.' ),
				'type'        => 'object',
				'default'     => array(),
			),
		);
	}

	/**
	 * Retrieves the schema for ability execution endpoint.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> Schema for the run endpoint.
	 */
	public function get_run_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ability-execution',
			'type'       => 'object',
			'properties' => array(
				'result' => array(
					'description' => __( 'The result of the ability execution.' ),
					'type'        => 'mixed',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
			),
		);
	}
}
