<?php
/**
 * REST API: WP_REST_Networks_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since Unknown
 */

/**
 * Core controller used to access networks via the REST API.
 *
 * @since Unknown
 *
 * @see WP_REST_Controller
 */
class WP_REST_Networks_Controller extends WP_REST_Controller {

	/**
	 * Instance of a network meta fields object.
	 *
	 * @since Unknown
	 * @var WP_REST_Network_Meta_Fields
	 */
	protected $meta;

	/**
	 * Constructor.
	 *
	 * @since Unknown
	 */
	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'networks';

		$this->meta = new WP_REST_Network_Meta_Fields();
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since Unknown
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the object.' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Checks if a given request has access to read networks.
	 *
	 * @since Unknown
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|bool True if the request has read access, error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Retrieves a list of network items.
	 *
	 * @since Unknown
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response Response object on success, or error object on failure.
	 */
	public function get_items( $request ) {

		// Retrieve the list of registered collection query parameters.
		$registered = $this->get_collection_params();

		/*
		 * This array defines mappings between public API query parameters whose
		 * values are accepted as-passed, and their internal WP_Network_Query parameter
		 * name equivalents (some are the same). Only values which are also
		 * present in $registered will be set.
		 */
		$parameter_mappings = array(
			'domain'         => 'domain__in',
			'domain_exclude' => 'domain__not_in',
			'exclude'        => 'network__not_in',
			'include'        => 'network__in',
			'offset'         => 'offset',
			'order'          => 'order',
			'path'           => 'path__in',
			'path_exclude'   => 'path__not_in',
			'per_page'       => 'number',
			'search'         => 'search',
		);

		$prepared_args = array();

		/*
		 * For each known parameter which is both registered and present in the request,
		 * set the parameter's value on the query $prepared_args.
		 */
		foreach ( $parameter_mappings as $api_param => $wp_param ) {
			if ( isset( $registered[ $api_param ], $request[ $api_param ] ) ) {
				$prepared_args[ $wp_param ] = $request[ $api_param ];
			}
		}

		// Ensure certain parameter values default to empty strings.
		foreach ( array( 'search' ) as $param ) {
			if ( ! isset( $prepared_args[ $param ] ) ) {
				$prepared_args[ $param ] = '';
			}
		}

		$prepared_args['no_found_rows'] = false;

		/**
		 * Filters arguments, before passing to WP_Network_Query, when querying networks via the REST API.
		 *
		 * @since Unknown
		 *
		 * @link https://developer.wordpress.org/reference/classes/wp_network_query/
		 *
		 * @param array $prepared_args Array of arguments for WP_Network_Query.
		 * @param WP_REST_Request $request The current request.
		 */
		$prepared_args = apply_filters( 'rest_network_query', $prepared_args, $request );

		$query        = new WP_Network_Query;
		$query_result = $query->query( $prepared_args );

		$networks = array();

		foreach ( $query_result as $network ) {
			$data       = $this->prepare_item_for_response( $network, $request );
			$networks[] = $this->prepare_response_for_collection( $data );
		}

		$total_networks = (int) $query->found_networks;
		$max_pages      = (int) $query->max_num_pages;

		if ( $total_networks < 1 ) {
			// Out-of-bounds, run the query again without LIMIT for total count.
			unset( $prepared_args['number'], $prepared_args['offset'] );

			$query                  = new WP_Network_Query;
			$prepared_args['count'] = true;

			$total_networks = $query->query( $prepared_args );
			$max_pages      = ceil( $total_networks / $request['per_page'] );
		}

		$response = rest_ensure_response( $networks );
		$response->header( 'X-WP-Total', $total_networks );
		$response->header( 'X-WP-TotalPages', $max_pages );

		$base = add_query_arg( $request->get_query_params(), rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) );

		if ( $request['page'] > 1 ) {
			$prev_page = $request['page'] - 1;

			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}

			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}

		if ( $max_pages > $request['page'] ) {
			$next_page = $request['page'] + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );

			$response->link_header( 'next', $next_link );
		}

		return $response;
	}

	/**
	 * Get the network, if the ID is valid.
	 *
	 * @since Unknown
	 *
	 * @param int $id Supplied ID.
	 *
	 * @return WP_Network|WP_Error Network object if ID is valid, WP_Error otherwise.
	 */
	protected function get_network( $id ) {
		$error = new WP_Error( 'rest_network_invalid_id', __( 'Invalid network ID.' ), array( 'status' => 404 ) );
		if ( (int) $id <= 0 ) {
			return $error;
		}

		$id      = (int) $id;
		$network = get_network( $id );
		if ( empty( $network ) ) {
			return $error;
		}

		return $network;
	}

	/**
	 * Checks if a given request has access to read the network.
	 *
	 * @since Unknown
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|bool True if the request has read access for the item, error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_network' ) ) {
			return new WP_Error( 'rest_cannot_view', __( 'Sorry, you are not allowed to view netwrosk' ), array( 'status' => rest_authorization_required_code() ) );
		}

		$network = $this->get_network( $request['id'] );
		if ( is_wp_error( $network ) ) {
			return $network;
		}

		if ( ! empty( $request['context'] ) && 'edit' === $request['context'] ) {
			return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit networks.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Retrieves a network.
	 *
	 * @since Unknown
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response Response object on success, or error object on failure.
	 */
	public function get_item( $request ) {
		$network = $this->get_network( $request['id'] );
		if ( is_wp_error( $network ) ) {
			return $network;
		}

		$data     = $this->prepare_item_for_response( $network, $request );
		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Prepares a single network output for response.
	 *
	 * @since Unknown
	 *
	 * @param WP_Network $network Network object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $network, $request ) {
		$data = array(
			'id'            => (int) $network->id,
			'site_id'       => (int) $network->blog_id,
			'domain'        => $network->domain,
			'path'          => $network->path,
			'cookie_domain' => $network->cookie_domain,
			'site_name'     => $network->site_name,
		);

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['meta'] ) ) {
			$data['meta'] = $this->meta->get_value( $network->id, $request );
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filters a network returned from the API.
		 *
		 * Allows modification of the network right before it is returned.
		 *
		 * @since Unknown
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WP_Network $network The original network object.
		 * @param WP_REST_Request $request Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_network', $response, $network, $request );
	}

	/**
	 * Retrieves the network's schema, conforming to JSON Schema.
	 *
	 * @since Unknown
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => 'network',
			'type'       => 'object',
			'properties' => array(
				'id'            => array(
					'description' => __( 'Unique identifier for the object.' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'site_id'       => array(
					'description' => __( 'The ID of the network\'s main site.' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'domain'        => array(
					'description' => __( 'Domain of the network.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'path'          => array(
					'description' => __( 'Path of the network.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'cookie_domain' => array(
					'description' => __( 'Domain used to set cookies for the network.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'site_name'     => array(
					'description' => __( 'Name of the network.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);

		$schema['properties']['meta'] = $this->meta->get_field_schema();

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Retrieves the query params for collections.
	 *
	 * @since Unknown
	 *
	 * @return array Networks collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		$query_params['context']['default'] = 'view';

		$query_params['exclude'] = array(
			'description' => __( 'Ensure result set excludes specific IDs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['include'] = array(
			'description' => __( 'Limit result set to specific IDs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['offset'] = array(
			'description' => __( 'Offset the result set by a specific number of items.' ),
			'type'        => 'integer',
		);

		$query_params['order'] = array(
			'description' => __( 'Order sort attribute ascending or descending.' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array(
				'asc',
				'desc',
			),
		);

		$query_params['orderby'] = array(
			'description' => __( 'Sort collection by object attribute.' ),
			'type'        => 'string',
			'default'     => 'id',
			'enum'        => array(
				'id',
				'domain',
				'path',
				'domain_length',
				'path_length',
				'network__in',
			),
		);

		/**
		 * Filter collection parameters for the networks controller.
		 *
		 * This filter registers the collection parameter, but does not map the
		 * collection parameter to an internal WP_Network_Query parameter. Use the
		 * `rest_network_query` filter to set WP_Network_Query parameters.
		 *
		 * @since Unknown
		 *
		 * @param array $query_params JSON Schema-formatted collection parameters.
		 */
		return apply_filters( 'rest_network_collection_params', $query_params );
	}
}
