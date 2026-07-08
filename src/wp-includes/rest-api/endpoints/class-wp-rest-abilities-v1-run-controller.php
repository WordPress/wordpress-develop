<?php
/**
 * REST API run controller for Abilities API.
 *
 * @package WordPress
 * @subpackage Abilities_API
 * @since 6.9.0
 */

declare( strict_types = 1 );

/**
 * Core controller used to execute abilities via the REST API.
 *
 * @since 6.9.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Abilities_V1_Run_Controller extends WP_REST_Controller {

	/**
	 * REST API namespace.
	 *
	 * @since 6.9.0
	 * @var string
	 */
	protected $namespace = 'wp-abilities/v1';

	/**
	 * REST API base route.
	 *
	 * @since 6.9.0
	 * @var string
	 */
	protected $rest_base = 'abilities';

	/**
	 * The request the coerced input in `$coerced_input` was computed for.
	 *
	 * Written by `check_ability_permissions()` on every call, so it always describes the
	 * dispatch in progress rather than an earlier one that reused the same request object.
	 *
	 * @since 7.1.0
	 * @var WP_REST_Request|null
	 */
	private $coerced_input_request = null;

	/**
	 * Coerced input computed for `$coerced_input_request`.
	 *
	 * @since 7.1.0
	 * @var mixed
	 */
	private $coerced_input = null;

	/**
	 * Registers the routes for ability execution.
	 *
	 * @since 6.9.0
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

				// TODO: We register ALLMETHODS because at route registration time, we don't know which abilities
				// exist or their annotations (`destructive`, `idempotent`, `readonly`). This is due to WordPress
				// load order - routes are registered early, before plugins have registered their abilities.
				// This approach works but could be improved with lazy route registration or a different
				// architecture that allows type-specific routes after abilities are registered.
				// This was the same issue that we ended up seeing with the Feature API.
				array(
					'methods'             => WP_REST_Server::ALLMETHODS,
					'callback'            => array( $this, 'execute_ability' ),
					'permission_callback' => array( $this, 'check_ability_permissions' ),
					'args'                => $this->get_run_args(),
				),
				'schema' => array( $this, 'get_run_schema' ),
			)
		);
	}

	/**
	 * Executes an ability.
	 *
	 * @since 6.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function execute_ability( $request ) {
		$ability = wp_get_ability( $request['name'] );
		if ( ! $ability ) {
			return new WP_Error(
				'rest_ability_not_found',
				__( 'Ability not found.' ),
				array( 'status' => 404 )
			);
		}

		/*
		 * check_ability_permissions() always runs first for this request and has already coerced
		 * the input against the ability's schema. Reuse that value instead of validating and
		 * sanitizing a second time, falling back for callers that reach this method directly.
		 */
		$input = $this->coerced_input_request === $request
			? $this->coerced_input
			: $this->get_input_from_request( $request, $ability );

		$result = $ability->execute( $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Validates if the HTTP method matches the expected method for the ability based on its annotations.
	 *
	 * @since 6.9.0
	 *
	 * @param string                     $request_method The HTTP method of the request.
	 * @param array<string, (null|bool)> $annotations    The ability annotations.
	 * @return true|WP_Error True on success, or WP_Error object on failure.
	 */
	public function validate_request_method( string $request_method, array $annotations ) {
		$expected_method = 'POST';
		if ( ! empty( $annotations['readonly'] ) ) {
			$expected_method = 'GET';
		} elseif ( ! empty( $annotations['destructive'] ) && ! empty( $annotations['idempotent'] ) ) {
			$expected_method = 'DELETE';
		}

		if ( $expected_method === $request_method ) {
			return true;
		}

		$error_message = __( 'Abilities that perform updates require POST method.' );
		if ( 'GET' === $expected_method ) {
			$error_message = __( 'Read-only abilities require GET method.' );
		} elseif ( 'DELETE' === $expected_method ) {
			$error_message = __( 'Abilities that perform destructive actions require DELETE method.' );
		}
		return new WP_Error(
			'rest_ability_invalid_method',
			$error_message,
			array( 'status' => 405 )
		);
	}

	/**
	 * Checks if a given request has permission to execute a specific ability.
	 *
	 * @since 6.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has execution permission, WP_Error object otherwise.
	 */
	public function check_ability_permissions( $request ) {
		$ability = wp_get_ability( $request['name'] );
		if ( ! $ability || ! $ability->get_meta_item( 'show_in_rest' ) ) {
			return new WP_Error(
				'rest_ability_not_found',
				__( 'Ability not found.' ),
				array( 'status' => 404 )
			);
		}

		$is_valid = $this->validate_request_method(
			$request->get_method(),
			$ability->get_meta_item( 'annotations' )
		);
		if ( is_wp_error( $is_valid ) ) {
			return $is_valid;
		}

		/*
		 * Coercing validates the input against the ability's schema, so hand the result to
		 * execute_ability() rather than let it repeat the work. Assigned unconditionally: this
		 * method runs at the start of every dispatch, so the stored value can never describe an
		 * earlier dispatch that happened to reuse the same request object.
		 */
		$input                       = $this->get_input_from_request( $request, $ability );
		$this->coerced_input_request = $request;
		$this->coerced_input         = $input;

		$input = $ability->normalize_input( $input );
		if ( is_wp_error( $input ) ) {
			return $this->ensure_error_status( $input, 400 );
		}

		$is_valid = $ability->validate_input( $input );
		if ( is_wp_error( $is_valid ) ) {
			return $this->ensure_error_status( $is_valid, 400 );
		}

		$result = $ability->check_permissions( $input );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => rest_authorization_required_code() ) );
			return $result;
		}
		if ( ! $result ) {
			return new WP_Error(
				'rest_ability_cannot_execute',
				__( 'Sorry, you are not allowed to execute this ability.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Ensures a WP_Error object carries an HTTP status, adding a default when none is set.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Error $error  Error object to update.
	 * @param int      $status HTTP status code to add if not already present.
	 * @return WP_Error The error object, with a default status when needed.
	 */
	private function ensure_error_status( WP_Error $error, int $status ): WP_Error {
		$error_data = $error->get_error_data();
		if ( ! is_array( $error_data ) || ! isset( $error_data['status'] ) ) {
			$error->add_data( array( 'status' => $status ) );
		}

		return $error;
	}

	/**
	 * Extracts input parameters from the request.
	 *
	 * When an ability is provided, the extracted input is coerced to the types declared
	 * in the ability's input schema before it is returned, so a `permission_callback` or
	 * `execute_callback` receives natively typed input regardless of transport.
	 *
	 * @since 6.9.0
	 * @since 7.1.0 Added the `$ability` parameter to coerce input to the ability schema.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @param WP_Ability|null $ability Optional. The ability whose input schema the input is
	 *                                 coerced against. Default `null` (no coercion).
	 * @return mixed|null The input parameters.
	 */
	private function get_input_from_request( $request, $ability = null ) {
		if ( in_array( $request->get_method(), array( 'GET', 'DELETE' ), true ) ) {
			// For GET and DELETE requests, look for 'input' query parameter.
			$query_params = $request->get_query_params();
			$input        = $query_params['input'] ?? null;
		} else {
			// For POST requests, look for 'input' in JSON body.
			$json_params = $request->get_json_params();
			$input       = $json_params['input'] ?? null;
		}

		if ( $ability instanceof WP_Ability ) {
			$input = $this->coerce_input_to_schema( $input, $ability );
		}

		return $input;
	}

	/**
	 * Coerces raw request input to the types declared in the ability input schema.
	 *
	 * REST GET and DELETE requests deliver every scalar as a string ("10", "true") and
	 * comma-separated values as a single string, so without coercion an ability receives
	 * raw strings where its schema declares integers, booleans, or arrays. This sanitizes
	 * the extracted input against the ability's registered input schema — the same snapshot
	 * {@see WP_Ability::validate_input()} runs against — so coercion and validation always
	 * agree and every ability receives natively typed input regardless of transport.
	 *
	 * Coercion is non-destructive with respect to validation. Input is only coerced when
	 * {@see WP_Ability::validate_input()} accepts it, and any error produced while sanitizing
	 * (including one nested inside the returned value) causes the raw input to be returned
	 * unchanged. `validate_input()` therefore remains the single authority on whether input is
	 * accepted and continues to emit the user-facing error for invalid input. `null` input and
	 * abilities without an input schema are passed through untouched.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed      $input   Raw input extracted from the request.
	 * @param WP_Ability $ability The ability being executed.
	 * @return mixed Coerced input, or the raw input when it cannot be safely coerced.
	 */
	private function coerce_input_to_schema( $input, WP_Ability $ability ) {
		if ( null === $input ) {
			return $input;
		}

		$schema = $ability->get_input_schema();
		if ( empty( $schema ) ) {
			return $input;
		}

		/*
		 * Only coerce input that already validates. Sanitizing invalid input can silently
		 * change which values are accepted -- `additionalProperties: false` strips unknown
		 * keys, and a non-numeric string casts to 0 -- so leaving invalid input untouched
		 * lets validate_input() reject it exactly as it does without coercion.
		 *
		 * validate_input() is asked rather than rest_validate_value_from_schema() so that the
		 * `wp_ability_validate_input` filter decides what counts as valid here as well. A filter
		 * that overrides a schema failure accepts the input, so the input is coerced; a filter
		 * that rejects otherwise valid input leaves it untouched for validate_input() to report.
		 */
		if ( is_wp_error( $ability->validate_input( $input ) ) ) {
			return $input;
		}

		$sanitized = rest_sanitize_value_from_schema( $input, $schema, 'input' );

		/*
		 * Sanitizing can still surface an error the lenient validation above did not, such as
		 * items that are unique as strings but collide once cast to integers (`uniqueItems`).
		 * The error may be returned at the top level or nested inside the returned array, so
		 * scan recursively and fall back to the raw input on any error.
		 */
		if ( $this->input_contains_error( $sanitized ) ) {
			return $input;
		}

		return $sanitized;
	}

	/**
	 * Determines whether a sanitized value is, or contains, a WP_Error.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $value The value to inspect.
	 * @return bool True if the value is, or contains, a WP_Error.
	 */
	private function input_contains_error( $value ): bool {
		if ( is_wp_error( $value ) ) {
			return true;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( $this->input_contains_error( $item ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Retrieves the arguments for ability execution endpoint.
	 *
	 * @since 6.9.0
	 *
	 * @return array<string, mixed> Arguments for the run endpoint.
	 */
	public function get_run_args(): array {
		return array(
			'input' => array(
				'description' => __( 'Input parameters for the ability execution.' ),
				'type'        => array( 'integer', 'number', 'boolean', 'string', 'array', 'object', 'null' ),
				'default'     => null,
			),
		);
	}

	/**
	 * Retrieves the schema for ability execution endpoint.
	 *
	 * @since 6.9.0
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
					'type'        => array( 'integer', 'number', 'boolean', 'string', 'array', 'object', 'null' ),
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			),
		);
	}
}
