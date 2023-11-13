<?php
/**
 * REST API: WP_REST_Block_Pattern_Categories_Controller class
 *
 * @package    WordPress
 * @subpackage REST_API
 * @since      6.0.0
 */

/**
 * Core class used to access block pattern categories via the REST API.
 *
 * @since 6.0.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Block_Pattern_Categories_Controller extends WP_REST_Controller {

	/**
	 * Constructs the controller.
	 *
	 * @since 6.0.0
	 */
	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'block-patterns/categories';
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since 6.0.0
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
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Checks whether a given request has permission to read block patterns.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
			if ( current_user_can( $post_type->cap->edit_posts ) ) {
				return true;
			}
		}

		return new WP_Error(
			'rest_cannot_view',
			__( 'Sorry, you are not allowed to view the registered block pattern categories.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Retrieves all block pattern categories.
	 *
	 * @since 6.0.0
	 * @since 6.5.0 Includes user categories from the wp_pattern_category taxonomy in the response if `user` sepecified in the request `source` param.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$valid_query_args = array(
			'source' => true,
		);
		$query_args        = array_intersect_key( $request->get_params(), $valid_query_args );
		$response          = array();
		$unique_categories = array();

		if ( is_array( $query_args['source'] ) && in_array( 'user', $query_args['source'], true ) ) {
			$user_categories   = get_terms(
				array(
					'taxonomy'   => 'wp_pattern_category',
					'hide_empty' => false,
				)
			);
			foreach ( $user_categories as $user_category ) {
				$prepared_category   = $this->prepare_item_for_response(
					array(
						'name'        => $user_category->slug,
						'label'       => $user_category->name,
						'description' => $user_category->description,
						'id'          => $user_category->term_id,
					),
					$request
				);
				$response[]          = $this->prepare_response_for_collection( $prepared_category );
				$unique_categories[] = $user_category->name;
			}
		}

		if ( ! isset( $query_args['source'] ) || in_array( 'core', $query_args['source'], true ) ) {
			$categories        = WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered();
			foreach ( $categories as $category ) {
				if ( in_array( $category['label'], $unique_categories, true ) ) {
					continue;
				}
				$prepared_category = $this->prepare_item_for_response( $category, $request );
				$response[]        = $this->prepare_response_for_collection( $prepared_category );
			}
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Prepare a raw block pattern category before it gets output in a REST API response.
	 *
	 * @since 6.0.0
	 * @since 6.5 Added `id` field for identifying user categories
	 *
	 * @param array           $item    Raw category as registered, before any changes.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$fields = $this->get_fields_for_response( $request );
		$keys   = array( 'name', 'label', 'description' );
		$data   = array();
		foreach ( $keys as $key ) {
			if ( isset( $item[ $key ] ) && rest_is_field_included( $key, $fields ) ) {
				$data[ $key ] = $item[ $key ];
			}
		}

		// For backwards compatibility we only want to include the id if the field is explicitly requested.
		if ( rest_is_field_included( 'id', $fields ) && isset( $item['id'] ) ) {
			$data['id'] = $item['id'];
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		return rest_ensure_response( $data );
	}

	/**
	 * Retrieves the block pattern category schema, conforming to JSON Schema.
	 *
	 * @since 6.0.0
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'block-pattern-category',
			'type'       => 'object',
			'properties' => array(
				'name'        => array(
					'description' => __( 'The category name.' ),
					'type'        => 'string',
					'readonly'    => true,
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'label'       => array(
					'description' => __( 'The category label, in human readable format.' ),
					'type'        => 'string',
					'readonly'    => true,
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'description' => array(
					'description' => __( 'The category description, in human readable format.' ),
					'type'        => 'string',
					'readonly'    => true,
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'id'          => array(
					'description' => __( 'An optional category id, currently used to provide id for user wp_pattern_category terms' ),
					'type'        => 'number',
					'readonly'    => true,
					'context'     => array( 'view', 'edit', 'embed' ),
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Retrieves the search parameters for the block pattern categories.
	 *
	 * @since 6.5.0 Added source param to request.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		$query_params['source'] = array(
			'description' => __( 'Limit result set to specific sources, `core` and/or `user`' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		/**
		 * Filter collection parameters for the block pattern categories controller.
		 *
		 * @since 5.8.0
		 *
		 * @param array $query_params JSON Schema-formatted collection parameters.
		 */
		return apply_filters( 'rest_pattern_directory_collection_params', $query_params );
	}
}
