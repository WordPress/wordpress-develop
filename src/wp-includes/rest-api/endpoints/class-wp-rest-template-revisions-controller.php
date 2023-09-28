<?php
/**
 * REST API: WP_REST_Revisions_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 6.4.0
 */

/**
 * Core class used to access revisions via the REST API.
 *
 * @since 6.4.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Template_Revisions_Controller extends WP_REST_Revisions_Controller {
	/**
	 * Parent post type.
	 *
	 * @since 6.4.0
	 * @var string
	 */
	private $parent_post_type;

	/**
	 * Parent controller.
	 *
	 * @since 6.4.0
	 * @var WP_REST_Controller
	 */
	private $parent_controller;

	/**
	 * The base of the parent controller's route.
	 *
	 * @since 6.4.0
	 * @var string
	 */
	private $parent_base;

	/**
	 * Constructor.
	 *
	 * @since 6.4.0
	 *
	 * @param string $parent_post_type Post type of the parent.
	 */
	public function __construct( $parent_post_type ) {
		parent::__construct( $parent_post_type );
		$this->parent_post_type = $parent_post_type;
		$post_type_object       = get_post_type_object( $parent_post_type );
		$parent_controller      = $post_type_object->get_rest_controller();

		if ( ! $parent_controller ) {
			$parent_controller = new WP_REST_Posts_Controller( $parent_post_type );
		}

		$this->parent_controller = $parent_controller;
		$this->rest_base         = 'revisions';
		$this->parent_base       = ! empty( $post_type_object->rest_base ) ? $post_type_object->rest_base : $post_type_object->name;
		$this->namespace         = ! empty( $post_type_object->rest_namespace ) ? $post_type_object->rest_namespace : 'wp/v2';
	}

	/**
	 * Registers the routes for revisions based on post types supporting revisions.
	 *
	 * @since 6.4.0
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			sprintf(
				'/%s/(?P<parent>%s%s)/%s',
				$this->parent_base,
				/*
				 * Matches theme's directory: `/themes/<subdirectory>/<theme>/` or `/themes/<theme>/`.
				 * Excludes invalid directory name characters: `/:<>*?"|`.
				 */
				'([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)',
				// Matches the template name.
				'[\/\w%-]+',
				$this->rest_base
			),
			array(
				'args'   => array(
					'parent' => array(
						'description'       => __( 'The id of a template' ),
						'type'              => 'string',
						'sanitize_callback' => array( $this->parent_controller, '_sanitize_template_id' ),
					),
				),
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
			sprintf(
				'/%s/(?P<parent>%s%s)/%s/%s',
				$this->parent_base,
				/*
				 * Matches theme's directory: `/themes/<subdirectory>/<theme>/` or `/themes/<theme>/`.
				 * Excludes invalid directory name characters: `/:<>*?"|`.
				 */
				'([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)',
				// Matches the template name.
				'[\/\w%-]+',
				$this->rest_base,
				'(?P<id>[\d]+)'
			),
			array(
				'args'   => array(
					'parent' => array(
						'description'       => __( 'The id of a template' ),
						'type'              => 'string',
						'sanitize_callback' => array( $this->parent_controller, '_sanitize_template_id' ),
					),
					'id'     => array(
						'description' => __( 'Unique identifier for the revision.' ),
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
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'force' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Required to be true, as revisions do not support trashing.' ),
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Get the parent post, if the ID is valid.
	 *
	 * @since 4.7.2
	 *
	 * @param int $parent_post_id Supplied ID.
	 * @return WP_Post|WP_Error Post object if ID is valid, WP_Error otherwise.
	 */
	protected function get_parent( $parent_post_id ) {
		$error = new WP_Error(
			'rest_post_invalid_parent',
			__( 'Invalid post parent ID!' ),
			array( 'status' => 404 )
		);

		$template = get_block_template( $parent_post_id, $this->parent_post_type );

		if ( ! $template ) {
			return $error;
		}

		return get_post( $template->wp_id );
	}

	/**
	 * Prepares the item for the REST response.
	 *
	 * @since 6.4.0
	 *
	 * @param mixed           $item    WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$template = _build_block_template_result_from_post( $item );
		return $this->parent_controller->prepare_item_for_response( $template, $request );
	}

	/**
	 * Retrieves the item's schema, conforming to JSON Schema.
	 *
	 * @since 6.4.0
	 *
	 * @return array Item schema data.
	 */

	public function get_item_schema(){
		return $this->parent_controller->get_item_schema();
	}
}
