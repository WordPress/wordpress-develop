<?php
/**
 * REST API: WP_REST_Sites_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 7.2.0
 */

/**
 * Core controller used to access sites via the REST API.
 *
 * @since 7.2.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Sites_Controller extends WP_REST_Controller {

	/**
	 * Instance of a site meta fields object.
	 *
	 * @since 7.2.0
	 *
	 * @var WP_REST_Site_Meta_Fields
	 */
	protected $meta;

	/**
	 * Constructor.
	 *
	 * @since 7.2.0
	 */
	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'sites';

		$this->meta = new WP_REST_Site_Meta_Fields();
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since 7.2.0
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
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Title and administrator reach wp_initialize_site(), which only runs on creation.
		$update_args = $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE );
		unset( $update_args['title'], $update_args['user_id'] );

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
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $update_args,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'force' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Required to be true, as sites do not support trashing.' ),
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Checks if a given request has access to read sites.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool True if the request has read access, error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {

		if ( 0 === get_current_user_id() ) {
			return false;
		}

		if ( ! is_multisite() ) {
			return new WP_Error( 'rest_multisite_not_installed', __( 'Multisite is not installed' ), array( 'status' => 400 ) );
		}

		if ( current_user_can( 'manage_sites' ) ) {
			return true;
		}

		// Without that capability a user may still ask for their own sites.
		if ( $this->is_own_user_filter( $request ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit sites.' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Checks whether the request is limited to the sites of the current user.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool Whether the request asks for the current user's own sites.
	 */
	protected function is_own_user_filter( $request ) {
		$user = $request['user'];

		if ( empty( $user ) ) {
			return false;
		}

		if ( 'me' === $user ) {
			return true;
		}

		return get_current_user_id() === (int) $user;
	}

	/**
	 * Retrieves a list of site items.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response Response object on success, or error object on failure.
	 */
	public function get_items( $request ) {

		// Retrieve the list of registered collection query parameters.
		$registered = $this->get_collection_params();

		/*
		 * This array defines mappings between public API query parameters whose
		 * values are accepted as-passed, and their internal WP_Query parameter
		 * name equivalents (some are the same). Only values which are also
		 * present in $registered will be set.
		 */
		$parameter_mappings = array(
			'domain'          => 'domain__in',
			'domain_exclude'  => 'domain__not_in',
			'exclude'         => 'site__not_in',
			'include'         => 'site__in',
			'offset'          => 'offset',
			'order'           => 'order',
			'network'         => 'network__in',
			'network_exclude' => 'network__not_in',
			'per_page'        => 'number',
			'path'            => 'path__in',
			'path_exclude'    => 'path__not_in',
			'search'          => 'search',
			'public'          => 'public',
			'archived'        => 'archived',
			'mature'          => 'mature',
			'spam'            => 'spam',
			'deleted'         => 'deleted',
			'lang_id'         => 'lang__in',
			'lang_id_exclude' => 'lang__not_in',
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

		// WP_Site_Query tests the status columns with is_numeric(), and a boolean is not numeric.
		foreach ( array( 'public', 'archived', 'mature', 'spam', 'deleted' ) as $status_param ) {
			if ( isset( $prepared_args[ $status_param ] ) ) {
				$prepared_args[ $status_param ] = (int) rest_sanitize_boolean( $prepared_args[ $status_param ] );
			}
		}

		$user = $request['user'];

		if ( ! empty( $user ) ) {
			$user_id  = ( 'me' === $user ) ? get_current_user_id() : (int) $user;
			$site_ids = $this->get_user_site_ids( $user_id );

			if ( ! empty( $prepared_args['site__in'] ) ) {
				$site_ids = array_intersect( $prepared_args['site__in'], $site_ids );
			}

			// An empty site__in is no restriction at all, so ask for an impossible ID instead.
			$prepared_args['site__in'] = $site_ids ? array_values( $site_ids ) : array( 0 );
		}

		if ( isset( $registered['orderby'] ) ) {
			$orderby = $request['orderby'];

			// Ordering by an ID list needs a list to order by.
			if ( in_array( $orderby, array( 'site__in', 'network__in' ), true ) && empty( $prepared_args[ $orderby ] ) ) {
				$orderby = 'id';
			}

			$prepared_args['orderby'] = $orderby;
		}

		$prepared_args['no_found_rows'] = false;

		$prepared_args['date_query'] = array();

		/*
		 * WP_Date_Query reads the registered column as local time, but wp_blogs
		 * stores GMT, so the boundaries are converted before they are handed over.
		 */
		foreach ( array( 'before', 'after' ) as $date_param ) {
			if ( ! isset( $registered[ $date_param ], $request[ $date_param ] ) ) {
				continue;
			}

			$timestamp = rest_parse_date( $request[ $date_param ] );

			if ( false === $timestamp ) {
				continue;
			}

			$prepared_args['date_query'][0][ $date_param ] = gmdate( 'Y-m-d H:i:s', $timestamp );
		}

		if ( isset( $registered['page'] ) && empty( $request['offset'] ) ) {
			$prepared_args['offset'] = $prepared_args['number'] * ( absint( $request['page'] ) - 1 );
		}

		/**
		 * Filters arguments, before passing to WP_Site_Query, when querying sites via the REST API.
		 *
		 * @since 7.2.0
		 *
		 * @link https://developer.wordpress.org/reference/classes/wp_site_query/
		 * @param array           $prepared_args Array of arguments for WP_Site_Query.
		 * @param WP_REST_Request $request       The current request.
		 */
		$prepared_args = apply_filters( 'rest_site_query', $prepared_args, $request );

		$is_head_request = $request->is_method( 'HEAD' );

		if ( $is_head_request ) {
			// The body stays empty, so the rows are not needed.
			$prepared_args['fields'] = 'ids';
		}

		$query        = new WP_Site_Query();
		$query_result = $query->query( $prepared_args );

		$sites = array();

		if ( ! $is_head_request ) {
			foreach ( $query_result as $site ) {
				$data    = $this->prepare_item_for_response( $site, $request );
				$sites[] = $this->prepare_response_for_collection( $data );
			}
		}

		$total_sites = $query->found_sites;
		$max_pages   = $query->max_num_pages;

		if ( $total_sites < 1 ) {
			// Out-of-bounds, run the query again without LIMIT for total count.
			unset( $prepared_args['number'], $prepared_args['offset'] );

			$query                  = new WP_Site_Query();
			$prepared_args['count'] = true;

			$total_sites = $query->query( $prepared_args );
			$max_pages   = (int) ceil( $total_sites / $request['per_page'] );
		}

		$response = $is_head_request ? new WP_REST_Response( array() ) : rest_ensure_response( $sites );
		$response->header( 'X-WP-Total', (string) $total_sites );
		$response->header( 'X-WP-TotalPages', (string) $max_pages );

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
	 * Get the site, if the ID is valid.
	 *
	 * @since 7.2.0
	 *
	 * @param int $id Supplied ID.
	 * @return WP_Site|WP_Error Site object if ID is valid, WP_Error otherwise.
	 */
	protected function get_site( $id ) {
		if ( ! is_multisite() ) {
			return new WP_Error( 'rest_multisite_not_installed', __( 'Multisite is not installed' ), array( 'status' => 400 ) );
		}

		$error = new WP_Error( 'rest_site_invalid_id', __( 'Invalid site ID.' ), array( 'status' => 404 ) );
		if ( (int) $id <= 0 ) {
			return $error;
		}

		$id   = (int) $id;
		$site = get_site( $id );
		if ( empty( $site ) ) {
			return $error;
		}

		return $site;
	}

	/**
	 * Retrieves the IDs of the sites a user is a member of.
	 *
	 * @since 7.2.0
	 *
	 * @param int|string $user_id User ID.
	 * @return int[] Site IDs, empty when the user has none.
	 */
	public function get_user_site_ids( $user_id ) {
		if ( ! is_numeric( $user_id ) || (int) $user_id <= 0 ) {
			return array();
		}

		return array_map( 'intval', array_keys( get_blogs_of_user( (int) $user_id ) ) );
	}

	/**
	 * Checks if a given request has access to read the site.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool True if the request has read access for the item, error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		$site = $this->get_site( $request['id'] );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		if ( 0 === get_current_user_id() ) {
			return false;
		}

		if ( ! is_multisite() ) {
			return new WP_Error( 'rest_multisite_not_installed', __( 'Multisite is not installed' ), array( 'status' => 400 ) );
		}

		if ( ! empty( $request['context'] ) && 'edit' === $request['context'] && ! current_user_can( 'manage_sites' ) ) {
			return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit sites.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Retrieves a site.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response Response object on success, or error object on failure.
	 */
	public function get_item( $request ) {
		$site = $this->get_site( $request['id'] );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		$data     = $this->prepare_item_for_response( $site, $request );
		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Checks if a given request has access to create a site.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool True if the request has access to create items, error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		if ( 0 === get_current_user_id() ) {
			return false;
		}

		if ( ! is_multisite() ) {
			return new WP_Error( 'rest_multisite_not_installed', __( 'Multisite is not installed' ), array( 'status' => 400 ) );
		}

		return current_user_can( 'create_sites' );
	}

	/**
	 * Creates a site.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response Response object on success, or error object on failure.
	 */
	public function create_item( $request ) {

		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'rest_site_exists', __( 'Cannot create existing site.' ), array( 'status' => 400 ) );
		}

		$prepared_site = $this->prepare_item_for_database( $request );
		if ( is_wp_error( $prepared_site ) ) {
			return $prepared_site;
		}

		/**
		 * Filters a site before it is inserted via the REST API.
		 *
		 * Allows modification of the site right before it is inserted via wp_insert_site().
		 * Returning a WP_Error value from the filter will shortcircuit insertion and allow
		 * skipping further processing.
		 *
		 * @since 7.2.0
		 *
		 * @param array|WP_Error  $prepared_site The prepared site data for wp_insert_site().
		 * @param WP_REST_Request $request       Request used to insert the site.
		 */
		$prepared_site = apply_filters( 'rest_pre_insert_site', $prepared_site, $request );
		if ( is_wp_error( $prepared_site ) ) {
			return $prepared_site;
		}

		$site_id = wp_insert_site( $prepared_site );

		if ( is_wp_error( $site_id ) ) {
			$site_id->add_data( array( 'status' => 500 ) );

			return $site_id;
		}

		if ( ! $site_id ) {
			return new WP_Error( 'rest_site_failed_create', __( 'Creating site failed.' ), array( 'status' => 500 ) );
		}

		$site = get_site( $site_id );

		/**
		 * Fires after a site is created or updated via the REST API.
		 *
		 * @since 7.2.0
		 *
		 * @param WP_Site         $site     Inserted or updated site object.
		 * @param WP_REST_Request $request  Request object.
		 * @param bool            $creating True when creating a site, false
		 *                                  when updating.
		 */
		do_action( 'rest_insert_site', $site, $request, true );

		$schema = $this->get_item_schema();

		if ( isset( $request['meta'] ) ) {
			if ( empty( $schema['properties']['meta'] ) ) {
				return new WP_Error(
					'rest_site_meta_not_supported',
					/* translators: %s: database table name */
					sprintf( __( 'The %s table is not installed. Please run the network database upgrade.' ), $GLOBALS['wpdb']->blogmeta ),
					array( 'status' => 400 )
				);
			}

			$meta_update = $this->meta->update_value( $request['meta'], $site_id );

			if ( is_wp_error( $meta_update ) ) {
				return $meta_update;
			}
		}

		$fields_update = $this->update_additional_fields_for_object( $site, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$context = current_user_can( 'manage_sites' ) ? 'edit' : 'view';

		$request->set_param( 'context', $context );

		$response = $this->prepare_item_for_response( $site, $request );
		$response = rest_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $site_id ) ) );

		return $response;
	}

	/**
	 * Checks if a given REST request has access to update a site.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool True if the request has access to update the item, error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		$site = $this->get_site( $request['id'] );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		if ( 0 === get_current_user_id() ) {
			return false;
		}

		if ( ! is_multisite() ) {
			return new WP_Error( 'rest_multisite_not_installed', __( 'Multisite is not installed' ), array( 'status' => 400 ) );
		}

		if ( ! $this->check_edit_permission( $site ) ) {
			return new WP_Error( 'rest_cannot_edit', __( 'Sorry, you are not allowed to edit this site.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Updates a site.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response Response object on success, or error object on failure.
	 */
	public function update_item( $request ) {
		$site = $this->get_site( $request['id'] );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		$id = (int) $site->blog_id;

		$prepared_args = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $prepared_args ) ) {
			return $prepared_args;
		}

		if ( ! empty( $prepared_args ) ) {
			$result = wp_update_site( $id, $prepared_args );
			if ( is_wp_error( $result ) ) {
				$result->add_data( array( 'status' => 500 ) );

				return $result;
			}
		}

		$site = get_site( $id );

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-sites-controller.php */
		do_action( 'rest_insert_site', $site, $request, false );

		$schema = $this->get_item_schema();

		if ( isset( $request['meta'] ) ) {
			if ( empty( $schema['properties']['meta'] ) ) {
				return new WP_Error(
					'rest_site_meta_not_supported',
					/* translators: %s: database table name */
					sprintf( __( 'The %s table is not installed. Please run the network database upgrade.' ), $GLOBALS['wpdb']->blogmeta ),
					array( 'status' => 400 )
				);
			}

			$meta_update = $this->meta->update_value( $request['meta'], $id );

			if ( is_wp_error( $meta_update ) ) {
				return $meta_update;
			}
		}

		$fields_update = $this->update_additional_fields_for_object( $site, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'edit' );

		$response = $this->prepare_item_for_response( $site, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Checks if a given request has access to delete a site.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool True if the request has access to delete the item, error object otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		$site = $this->get_site( $request['id'] );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		if ( 0 === (int) get_current_user_id() ) {
			return false;
		}

		if ( ! is_multisite() ) {
			return new WP_Error( 'rest_multisite_not_installed', __( 'Multisite is not installed' ), array( 'status' => 400 ) );
		}

		if ( ! current_user_can( 'delete_sites' ) ) {
			return new WP_Error( 'rest_cannot_delete', __( 'Sorry, you are not allowed to delete this site.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		if ( get_main_site_id( (int) $site->site_id ) === (int) $site->blog_id ) {
			return new WP_Error( 'rest_cannot_delete_main_site', __( 'Sorry, the main site of a network cannot be deleted.' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Deletes a site.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response Response object on success, or error object on failure.
	 */
	public function delete_item( $request ) {
		$site = $this->get_site( $request['id'] );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		if ( ! (bool) $request['force'] ) {
			return new WP_Error(
				'rest_trash_not_supported',
				/* translators: %s: force=true */
				sprintf( __( "Sites do not support trashing. Set '%s' to delete." ), 'force=true' ),
				array( 'status' => 501 )
			);
		}

		$request->set_param( 'context', 'edit' );

		$previous = $this->prepare_item_for_response( $site, $request );
		$result   = wp_delete_site( $request['id'] );

		$response = new WP_REST_Response();
		$response->set_data(
			array(
				'deleted'  => true,
				'previous' => $previous->get_data(),
			)
		);

		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 500 ) );

			return $result;
		}

		/**
		 * Fires after a site is deleted via the REST API.
		 *
		 * @since 7.2.0
		 *
		 * @param WP_Site          $site     The deleted site data.
		 * @param WP_REST_Response $response The response returned from the API.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'rest_delete_site', $site, $response, $request );

		return $response;
	}

	/**
	 * Prepares a single site output for response.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Site         $site    Site object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $site, $request ) {
		// A HEAD response carries no body, so nothing needs to be prepared.
		if ( $request->is_method( 'HEAD' ) ) {
			/** This filter is documented in wp-includes/rest-api/endpoints/class-wp-rest-sites-controller.php */
			return apply_filters( 'rest_prepare_site', new WP_REST_Response( array() ), $site, $request );
		}

		$data   = array();
		$fields = $this->get_fields_for_response( $request );

		if ( rest_is_field_included( 'id', $fields ) ) {
			$data['id'] = (int) $site->blog_id;
		}

		if ( rest_is_field_included( 'network', $fields ) ) {
			$data['network'] = (int) $site->site_id;
		}

		if ( rest_is_field_included( 'domain', $fields ) ) {
			$data['domain'] = $site->domain;
		}

		if ( rest_is_field_included( 'path', $fields ) ) {
			$data['path'] = $site->path;
		}

		if ( rest_is_field_included( 'registered', $fields ) ) {
			$data['registered'] = $this->prepare_date_response( $site->registered );
		}

		if ( rest_is_field_included( 'registered_gmt', $fields ) ) {
			$data['registered_gmt'] = $this->prepare_date_response( $site->registered, true );
		}

		if ( rest_is_field_included( 'last_updated', $fields ) ) {
			$data['last_updated'] = $this->prepare_date_response( $site->last_updated );
		}

		if ( rest_is_field_included( 'last_updated_gmt', $fields ) ) {
			$data['last_updated_gmt'] = $this->prepare_date_response( $site->last_updated, true );
		}

		if ( rest_is_field_included( 'public', $fields ) ) {
			$data['public'] = (bool) $site->public;
		}

		if ( rest_is_field_included( 'archived', $fields ) ) {
			$data['archived'] = (bool) $site->archived;
		}

		if ( rest_is_field_included( 'mature', $fields ) ) {
			$data['mature'] = (bool) $site->mature;
		}

		if ( rest_is_field_included( 'spam', $fields ) ) {
			$data['spam'] = (bool) $site->spam;
		}

		if ( rest_is_field_included( 'deleted', $fields ) ) {
			$data['deleted'] = (bool) $site->deleted;
		}

		if ( rest_is_field_included( 'lang_id', $fields ) ) {
			$data['lang_id'] = (int) $site->lang_id;
		}

		// These four are not columns of the sites table. Reading one switches to the site.
		if ( rest_is_field_included( 'blogname', $fields ) ) {
			$data['blogname'] = $site->blogname;
		}

		if ( rest_is_field_included( 'siteurl', $fields ) ) {
			$data['siteurl'] = $site->siteurl;
		}

		if ( rest_is_field_included( 'home', $fields ) ) {
			$data['home'] = $site->home;
		}

		if ( rest_is_field_included( 'post_count', $fields ) ) {
			$data['post_count'] = (int) $site->post_count;
		}

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['meta'] ) && rest_is_field_included( 'meta', $fields ) ) {
			$data['meta'] = $this->meta->get_value( (int) $site->blog_id, $request );
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		if ( rest_is_field_included( '_links', $fields ) || rest_is_field_included( '_embedded', $fields ) ) {
			$response->add_links( $this->prepare_links( $site ) );
		}

		/**
		 * Filters a site returned from the API.
		 *
		 * Allows modification of the site right before it is returned.
		 *
		 * @since 7.2.0
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WP_Site          $site     The original site object.
		 * @param WP_REST_Request  $request  Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_site', $response, $site, $request );
	}

	/**
	 * Checks a date against the site's timezone, wp_blogs stores GMT.
	 *
	 * @since 7.2.0
	 *
	 * @param string $date_gmt The date as it is stored, in GMT.
	 * @param bool   $gmt      Optional. Whether to return the GMT date. Default false.
	 * @return string|null ISO8601/RFC3339 formatted date, null for an empty date.
	 */
	protected function prepare_date_response( $date_gmt, $gmt = false ) {
		if ( empty( $date_gmt ) || '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}

		if ( $gmt ) {
			return mysql_to_rfc3339( $date_gmt );
		}

		return mysql_to_rfc3339( get_date_from_gmt( $date_gmt ) );
	}

	/**
	 * Prepares the links for the request.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Site $site Site object.
	 * @return array Links for the given site.
	 */
	protected function prepare_links( $site ) {
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $site->blog_id ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ),
			),
		);

		/**
		 * Filters the links for a site returned from the API.
		 *
		 * @since 7.2.0
		 *
		 * @param array   $links Links for the given site.
		 * @param WP_Site $site  The site object.
		 */
		return apply_filters( 'rest_site_links', $links, $site );
	}

	/**
	 * Prepares a single site to be inserted into the database.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array|WP_Error Prepared site, otherwise WP_Error object.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_site = array();

		// Schema defaults apply to POST only, so on an update anything left out is null.
		foreach ( array( 'public', 'archived', 'mature', 'spam', 'deleted' ) as $status_field ) {
			if ( isset( $request[ $status_field ] ) ) {
				// The columns are TINYINT, the schema says boolean.
				$prepared_site[ $status_field ] = (int) rest_sanitize_boolean( $request[ $status_field ] );
			}
		}

		if ( isset( $request['lang_id'] ) ) {
			$prepared_site['lang_id'] = (int) $request['lang_id'];
		}

		/*
		 * Title, administrator and the initial options reach wp_initialize_site()
		 * through wp_insert_site(), so they only apply while the site is created.
		 */
		if ( WP_REST_Server::CREATABLE === $request->get_method() ) {
			if ( isset( $request['title'] ) ) {
				$prepared_site['title'] = $request['title'];
			}

			if ( isset( $request['user_id'] ) ) {
				if ( ! get_userdata( (int) $request['user_id'] ) ) {
					return new WP_Error( 'rest_site_invalid_user_id', __( 'Invalid user ID.' ), array( 'status' => 400 ) );
				}

				$prepared_site['user_id'] = (int) $request['user_id'];
			}
		}

		if ( isset( $request['network'] ) ) {
			if ( ! get_network( $request['network'] ) ) {
				return new WP_Error( 'rest_network_id_invalid', __( 'Invalid network ID.' ), array( 'status' => 400 ) );
			}
			$prepared_site['network_id'] = (int) $request['network'];
		}

		if ( isset( $request['path'] ) ) {
			$prepared_site['path'] = $request['path'];
		}

		if ( isset( $request['domain'] ) ) {
			$prepared_site['domain'] = $request['domain'];
		}

		/**
		 * Filters a site after it is prepared for the database.
		 *
		 * Allows modification of the site right after it is prepared for the database.
		 *
		 * @since 7.2.0
		 *
		 * @param array           $prepared_site The prepared site data for `wp_insert_site`.
		 * @param WP_REST_Request $request       The current request.
		 */
		return apply_filters( 'rest_preprocess_site', $prepared_site, $request );
	}

	/**
	 * Retrieves the site's schema, conforming to JSON Schema.
	 *
	 * @since 7.2.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => 'site',
			'type'       => 'object',
			'properties' => array(
				'id'               => array(
					'description' => __( 'Unique identifier for the object.' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'network'          => array(
					'description' => __( 'The site\'s network ID. Default is the current network ID.' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'domain'           => array(
					'description' => __( 'Site domain.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'default'     => '',
				),
				'path'             => array(
					'description' => __( 'Site path.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'default'     => '/',
				),
				'registered'       => array(
					'description' => __( 'When the site was registered, in the site\'s timezone.' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'registered_gmt'   => array(
					'description' => __( 'When the site was registered, as GMT.' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'last_updated'     => array(
					'description' => __( 'When the site was last updated, in the site\'s timezone.' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'last_updated_gmt' => array(
					'description' => __( 'When the site was last updated, as GMT.' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'public'           => array(
					'context'     => array( 'view', 'edit', 'embed' ),
					'description' => __( 'Whether the site is public. Default true.' ),
					'type'        => 'boolean',
					'default'     => true,
				),
				'archived'         => array(
					'context'     => array( 'view', 'edit', 'embed' ),
					'description' => __( 'Whether the site is archived. Default false.' ),
					'type'        => 'boolean',
					'default'     => false,
				),
				'mature'           => array(
					'context'     => array( 'view', 'edit', 'embed' ),
					'description' => __( 'Whether the site is mature. Default false.' ),
					'type'        => 'boolean',
					'default'     => false,
				),
				'spam'             => array(
					'context'     => array( 'view', 'edit', 'embed' ),
					'description' => __( 'Whether the site is spam. Default false.' ),
					'type'        => 'boolean',
					'default'     => false,
				),
				'deleted'          => array(
					'context'     => array( 'view', 'edit', 'embed' ),
					'description' => __( 'Whether the site is deleted. Default false.' ),
					'type'        => 'boolean',
					'default'     => false,
				),
				'lang_id'          => array(
					'context'     => array( 'view', 'edit', 'embed' ),
					'description' => __( 'The site\'s language ID. Currently unused. Default 0.' ),
					'type'        => 'integer',
					'default'     => 0,
				),
				'blogname'         => array(
					'description' => __( 'Site name, stored in the blogname option.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'siteurl'          => array(
					'description' => __( 'Site address, stored in the siteurl option.' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'home'             => array(
					'description' => __( 'Home address, stored in the home option.' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'post_count'       => array(
					'description' => __( 'Number of posts on the site.' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'title'            => array(
					'description' => __( 'Site title, set when the site is created. Default is the word "Site" followed by the site ID.' ),
					'type'        => 'string',
					'context'     => array(),
				),
				'user_id'          => array(
					'description' => __( 'User ID of the site administrator, set when the site is created.' ),
					'type'        => 'integer',
					'context'     => array(),
				),
			),
		);

		// Not all sites support site meta, do not register if the site does not support it.
		if ( is_site_meta_supported() ) {
			$schema['properties']['meta'] = $this->meta->get_field_schema();
		}

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Retrieves the query params for collections.
	 *
	 * @since 7.2.0
	 *
	 * @return array Sites collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		$query_params['context']['default'] = 'view';

		$query_params['domain'] = array(
			'description' => __( 'Limit result set to sites assigned to specific domain. ' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		$query_params['domain_exclude'] = array(
			'description' => __( 'Ensure result set excludes sites assigned to specific domain. ' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		$query_params['path'] = array(
			'description' => __( 'Limit result set to sites assigned to specific path. ' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		$query_params['path_exclude'] = array(
			'description' => __( 'Ensure result set excludes sites assigned to specific path. ' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

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
			'default'     => 'asc',
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
				'network_id',
				'last_updated',
				'registered',
				'domain_length',
				'path_length',
				'site__in',
				'network__in',
			),
		);
		$query_params['user']    = array(
			'description' => __( 'Limit result set to the sites a user is a member of. Accepts a user ID or "me".' ),
			'type'        => 'string',
		);

		$status_descriptions = array(
			'public'   => __( 'Limit result set to sites with a specific public status.' ),
			'archived' => __( 'Limit result set to sites with a specific archived status.' ),
			'mature'   => __( 'Limit result set to sites with a specific mature status.' ),
			'spam'     => __( 'Limit result set to sites with a specific spam status.' ),
			'deleted'  => __( 'Limit result set to sites with a specific deleted status.' ),
		);

		foreach ( $status_descriptions as $status_param => $status_description ) {
			// No default, an absent parameter must not filter the collection.
			$query_params[ $status_param ] = array(
				'description' => $status_description,
				'type'        => 'boolean',
			);
		}

		$query_params['lang_id'] = array(
			'default'     => array(),
			'description' => __( 'Limit result set to sites of specific language IDs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
		);

		$query_params['lang_id_exclude'] = array(
			'default'     => array(),
			'description' => __( 'Ensure result set excludes specific language IDs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
		);

		$query_params['before'] = array(
			'description' => __( 'Limit response to sites registered before a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);

		$query_params['after'] = array(
			'description' => __( 'Limit response to sites registered after a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);

		$query_params['network'] = array(
			'default'     => array(),
			'description' => __( 'Limit result set to sites of specific network IDs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
		);

		$query_params['network_exclude'] = array(
			'default'     => array(),
			'description' => __( 'Ensure result set excludes specific network IDs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
		);

		/**
		 * Filter collection parameters for the sites controller.
		 *
		 * This filter registers the collection parameter, but does not map the
		 * collection parameter to an internal WP_Site_Query parameter. Use the
		 * `rest_site_query` filter to set WP_Site_Query parameters.
		 *
		 * @since 7.2.0
		 *
		 * @param array $query_params JSON Schema-formatted collection parameters.
		 */
		return apply_filters( 'rest_site_collection_params', $query_params );
	}

	/**
	 * Checks if a site can be edited or deleted.
	 *
	 * @since 7.2.0
	 *
	 * @param object $site Site object.
	 * @return bool Whether the site can be edited or deleted.
	 */
	protected function check_edit_permission( $site ) {
		if ( 0 === (int) get_current_user_id() ) {
			return false;
		}

		if ( ! is_multisite() ) {
			return false;
		}

		return current_user_can( 'manage_sites' );
	}
}
