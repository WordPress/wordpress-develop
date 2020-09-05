<?php
/**
 * WP_REST_Batch_Controller class.
 *
 * @since      5.5.0
 * @package    WordPress
 * @subpackage REST API
 */

/**
 * Class WP_REST_Batch_Controller
 *
 * @since 5.5.0
 */
class WP_REST_Batch_Controller extends WP_REST_Controller {

	/**
	 * WP_REST_Bulk_Controller constructor.
	 *
	 * @since 5.5.0
	 */
	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'batch';
	}

	/**
	 * Registers the REST routes for the controller.
	 *
	 * @since 5.5.0
	 *
	 * @see   register_rest_route()
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->rest_base,
			array(
				// Don't support GET for now.
				'methods'  => array( 'POST', 'PUT', 'PATCH', 'DELETE' ),
				'callback' => array( $this, 'do_batch' ),
				'args'     => $this->get_endpoint_args_for_item_schema(),
				'schema'   => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Performs a batch request.
	 *
	 * @since 5.5.0
	 *
	 * @param WP_REST_Request $batch The batch request.
	 * @return WP_REST_Response The response object.
	 */
	public function do_batch( WP_REST_Request $batch ) {
		$server   = rest_get_server();
		$requests = array();

		foreach ( $batch['requests'] as $args ) {
			$parsed_url = wp_parse_url( $args['path'] );

			if ( false === $parsed_url ) {
				$requests[] = new WP_Error( 'parse_path_failed', __( 'Could not parse the path.', 'gutenberg' ), array( 'status' => 400 ) );

				continue;
			}

			$request_object = new WP_REST_Request( $batch->get_method(), $parsed_url['path'] );

			if ( ! empty( $parsed_url['query'] ) ) {
				wp_parse_str( $parsed_url['query'], $query_args );
				$request_object->set_query_params( $query_args );
			}

			if ( ! empty( $args['body'] ) ) {
				$request_object->set_body_params( $args['body'] );
			}

			$requests[] = $request_object;
		}

		if ( 'pre' === $batch['validation'] ) {
			$validation = array();
			$has_error  = false;

			foreach ( $requests as $request ) {
				$match = $server->match_request_to_handler( $request );
				$error = null;

				if ( is_wp_error( $match ) ) {
					$error = $match;
				}

				if ( ! $error ) {
					$check_required = $request->has_valid_params();
					if ( is_wp_error( $check_required ) ) {
						$error = $check_required;
					}
				}

				if ( ! $error ) {
					$check_sanitized = $request->sanitize_params();
					if ( is_wp_error( $check_sanitized ) ) {
						$error = $check_sanitized;
					}
				}

				if ( $error ) {
					$has_error = true;
					$response  = $server->error_to_response( $error );
				} else {
					$response = rest_ensure_response( true );
				}

				$validation[] = $server->envelope_response( $response, false )->get_data();
			}

			if ( $has_error ) {
				return new WP_REST_Response( array( 'pre-validation' => $validation ), WP_Http::MULTI_STATUS );
			}
		}

		$responses = array();

		foreach ( $requests as $request ) {
			$result = $server->dispatch( $request );
			$result = apply_filters( 'rest_post_dispatch', rest_ensure_response( $result ), $server, $request );

			$responses[] = $server->envelope_response( $result, false )->get_data();
		}

		return new WP_REST_Response( array( 'responses' => $responses ), WP_Http::MULTI_STATUS );
	}

	/**
	 * Gets the schema for the batch controller.
	 *
	 * @since 5.5.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( ! $this->schema ) {
			$this->schema = array(
				'$schema'    => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'batch',
				'type'       => 'object',
				'properties' => array(
					'validation' => array(
						'type'    => 'string',
						'enum'    => array( 'pre', 'normal' ),
						'default' => 'normal',
					),
					'requests'   => array(
						'required' => true,
						'type'     => 'array',
						'maxItems' => 25,
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'path'    => array(
									'type'     => 'string',
									'required' => true,
								),
								'body'    => array(
									'type'                 => 'object',
									'properties'           => array(),
									'additionalProperties' => true,
								),
								'headers' => array(
									'type'                 => 'object',
									'properties'           => array(),
									'additionalProperties' => array(
										'type'  => array( 'string', 'array' ),
										'items' => array(
											'type' => 'string',
										),
									),
								),
							),
						),
					),
				),
			);
		}

		return $this->schema;
	}
}
