<?php
/**
 * REST API: WP_REST_Menus_Controller class
 *
 * @package    WordPress
 * @subpackage REST_API
 * @since      5.9.0
 */

/**
 * Core class used to managed menu terms associated via the REST API.
 *
 * @since 5.9.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Menus_Controller extends WP_REST_Terms_Controller {

	/**
	 * Overrides the route registration to support "allow_batch".
	 *
	 * @since 5.9.0
	 *
	 * @see register_rest_route()
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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'        => array(
					'id' => array(
						'description' => __( 'Unique identifier for the term.', 'default' ),
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
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'force' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Required to be true, as terms do not support trashing.', 'default' ),
						),
					),
				),
				'allow_batch' => array( 'v1' => true ),
				'schema'      => array( $this, 'get_public_item_schema' ),
			)
		);
	}


	/**
	 * Checks if a request has access to read terms in the specified taxonomy.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool|WP_Error True if the request has read access, otherwise false or WP_Error object.
	 */
	public function get_items_permissions_check( $request ) {
		$tax_obj = get_taxonomy( $this->taxonomy );
		if ( ! $tax_obj || ! $this->check_is_taxonomy_allowed( $this->taxonomy ) ) {
			return false;
		}
		if ( ! current_user_can( $tax_obj->cap->edit_terms ) ) {
			if ( 'edit' === $request['context'] ) {
				return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit terms in this taxonomy.' ), array( 'status' => rest_authorization_required_code() ) );
			}

			return new WP_Error( 'rest_cannot_view', __( 'Sorry, you cannot view these menus, unless you have access to permission edit them.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Checks if a request has access to read or edit the specified menu.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool|WP_Error True if the request has read access for the item, otherwise false or WP_Error object.
	 */
	public function get_item_permissions_check( $request ) {
		$term = $this->get_term( $request['id'] );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		if ( ! current_user_can( 'edit_term', $term->term_id ) ) {
			if ( 'edit' === $request['context'] ) {
				return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit this term.' ), array( 'status' => rest_authorization_required_code() ) );
			}

			return new WP_Error( 'rest_cannot_view', __( 'Sorry, you cannot view this menu, unless you have access to permission edit it. ' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Get the term, if the ID is valid.
	 *
	 * @since 5.9.0
	 *
	 * @param int $id Supplied ID.
	 *
	 * @return WP_Term|WP_Error Term object if ID is valid, WP_Error otherwise.
	 */
	protected function get_term( $id ) {
		$term = parent::get_term( $id );

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$nav_term           = wp_get_nav_menu_object( $term );
		$nav_term->auto_add = $this->get_menu_auto_add( $nav_term->term_id );

		return $nav_term;
	}

	/**
	 * Checks if a request has access to create a term.
	 * Also check if request can assign menu locations.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool|WP_Error True if the request has access to create items, false or WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		$check = $this->check_assign_locations_permission( $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$check = $this->check_set_auto_add_permission( $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		return parent::create_item_permissions_check( $request );
	}

	/**
	 * Checks if a request has access to update the specified term.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool|WP_Error True if the request has access to update the item, false or WP_Error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		$check = $this->check_assign_locations_permission( $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$check = $this->check_set_auto_add_permission( $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		return parent::update_item_permissions_check( $request );
	}

	/**
	 * Checks whether current user can assign all locations sent with the current request.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request The request object with post and locations data.
	 *
	 * @return bool|WP_Error Whether the current user can assign the provided terms.
	 */
	protected function check_assign_locations_permission( $request ) {
		if ( ! isset( $request['locations'] ) ) {
			return true;
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'rest_cannot_assign_location', __( 'Sorry, you are not allowed to assign the provided locations.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		foreach ( $request['locations'] as $location ) {
			if ( ! array_key_exists( $location, get_registered_nav_menus() ) ) {
				return new WP_Error(
					'rest_menu_location_invalid',
					__( 'Invalid menu location.' ),
					array(
						'status'   => 400,
						'location' => $location,
					)
				);
			}
		}

		return true;
	}

	/**
	 * Checks whether current user can set auto add pages.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request The request object with post and locations data.
	 *
	 * @return true|WP_Error Whether the current user can assign the provided terms.
	 */
	protected function check_set_auto_add_permission( $request ) {
		if ( ! isset( $request['auto_add'] ) ) {
			return true;
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'rest_cannot_set_auto_add', __( 'Sorry, you are not allowed to set auto add pages.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Prepares a single term output for response.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_Term $term Term object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response $response Response object.
	 */
	public function prepare_item_for_response( $term, $request ) {
		$nav_menu = wp_get_nav_menu_object( $term );
		$response = parent::prepare_item_for_response( $nav_menu, $request );

		$fields = $this->get_fields_for_response( $request );
		$data   = $response->get_data();

		if ( rest_is_field_included( 'locations', $fields ) ) {
			$data['locations'] = $this->get_menu_locations( $nav_menu->term_id );
		}

		if ( rest_is_field_included( 'auto_add', $fields ) ) {
			$auto_add         = $this->get_menu_auto_add( $nav_menu->term_id );
			$data['auto_add'] = $auto_add;
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $term ) );

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-terms-controller.php */
		return apply_filters( "rest_prepare_{$this->taxonomy}", $response, $term, $request );
	}

	/**
	 * Prepares links for the request.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_Term $term Term object.
	 *
	 * @return array Links for the given term.
	 */
	protected function prepare_links( $term ) {
		$links = parent::prepare_links( $term );

		$locations = $this->get_menu_locations( $term->term_id );
		foreach ( $locations as $location ) {
			$url = rest_url( sprintf( 'wp/v2/menu-locations/%s', $location ) );

			$links['https://api.w.org/menu-location'][] = array(
				'href'       => $url,
				'embeddable' => true,
			);
		}

		return $links;
	}

	/**
	 * Prepares a single term for create or update.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return array $prepared_term Term object.
	 */
	public function prepare_item_for_database( $request ) {
		$prepared_term = parent::prepare_item_for_database( $request );

		$prepared_term = (array) $prepared_term;
		$schema        = $this->get_item_schema();
		if ( isset( $request['name'] ) && ! empty( $schema['properties']['name'] ) ) {
			$prepared_term['menu-name'] = $request['name'];
		}

		return $prepared_term;
	}

	/**
	 * Creates a single term in a taxonomy.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		if ( isset( $request['parent'] ) ) {
			if ( ! is_taxonomy_hierarchical( $this->taxonomy ) ) {
				return new WP_Error( 'rest_taxonomy_not_hierarchical', __( 'Cannot set parent term, taxonomy is not hierarchical.' ), array( 'status' => 400 ) );
			}

			$parent = wp_get_nav_menu_object( (int) $request['parent'] );

			if ( ! $parent ) {
				return new WP_Error( 'rest_term_invalid', __( 'Parent term does not exist.' ), array( 'status' => 400 ) );
			}
		}

		$prepared_term = $this->prepare_item_for_database( $request );

		$term = wp_update_nav_menu_object( 0, wp_slash( (array) $prepared_term ) );

		if ( is_wp_error( $term ) ) {
			/*
			 * If we're going to inform the client that the term already exists,
			 * give them the identifier for future use.
			 */

			if ( in_array( 'menu_exists', $term->get_error_codes(), true ) ) {
				$existing_term = get_term_by( 'name', $prepared_term['menu-name'], $this->taxonomy );
				$term->add_data( $existing_term->term_id, 'menu_exists' );
				$term->add_data(
					array(
						'status'  => 400,
						'term_id' => $existing_term->term_id,
					)
				);
			} else {
				$term->add_data( array( 'status' => 400 ) );
			}

			return $term;
		}

		$term = $this->get_term( $term );

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-terms-controller.php */
		do_action( "rest_insert_{$this->taxonomy}", $term, $request, true );

		$schema = $this->get_item_schema();
		if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {
			$meta_update = $this->meta->update_value( $request['meta'], $term->term_id );

			if ( is_wp_error( $meta_update ) ) {
				return $meta_update;
			}
		}

		$locations_update = $this->handle_locations( $term->term_id, $request );

		if ( is_wp_error( $locations_update ) ) {
			return $locations_update;
		}

		$this->handle_auto_add( $term->term_id, $request );

		$fields_update = $this->update_additional_fields_for_object( $term, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'view' );

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-terms-controller.php */
		do_action( "rest_after_insert_{$this->taxonomy}", $term, $request, true );

		$response = $this->prepare_item_for_response( $term, $request );
		$response = rest_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $term->term_id ) );

		return $response;
	}

	/**
	 * Updates a single term from a taxonomy.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$term = $this->get_term( $request['id'] );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		if ( isset( $request['parent'] ) ) {
			if ( ! is_taxonomy_hierarchical( $this->taxonomy ) ) {
				return new WP_Error( 'rest_taxonomy_not_hierarchical', __( 'Cannot set parent term, taxonomy is not hierarchical.' ), array( 'status' => 400 ) );
			}

			$parent = get_term( (int) $request['parent'], $this->taxonomy );

			if ( ! $parent ) {
				return new WP_Error( 'rest_term_invalid', __( 'Parent term does not exist.' ), array( 'status' => 400 ) );
			}
		}

		$prepared_term = $this->prepare_item_for_database( $request );

		// Only update the term if we haz something to update.
		if ( ! empty( $prepared_term ) ) {
			$update = wp_update_nav_menu_object( $term->term_id, wp_slash( (array) $prepared_term ) );

			if ( is_wp_error( $update ) ) {
				return $update;
			}
		}

		$term = get_term( $term->term_id, $this->taxonomy );

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-terms-controller.php */
		do_action( "rest_insert_{$this->taxonomy}", $term, $request, false );

		$schema = $this->get_item_schema();
		if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {
			$meta_update = $this->meta->update_value( $request['meta'], $term->term_id );

			if ( is_wp_error( $meta_update ) ) {
				return $meta_update;
			}
		}

		$locations_update = $this->handle_locations( $term->term_id, $request );

		if ( is_wp_error( $locations_update ) ) {
			return $locations_update;
		}

		$this->handle_auto_add( $term->term_id, $request );

		$fields_update = $this->update_additional_fields_for_object( $term, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'view' );

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-terms-controller.php */
		do_action( "rest_after_insert_{$this->taxonomy}", $term, $request, false );

		$response = $this->prepare_item_for_response( $term, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Deletes a single term from a taxonomy.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$term = $this->get_term( $request['id'] );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$force = isset( $request['force'] ) ? (bool) $request['force'] : false;

		// We don't support trashing for terms.
		if ( ! $force ) {
			/* translators: %s: force=true */
			return new WP_Error( 'rest_trash_not_supported', sprintf( __( "Terms do not support trashing. Set '%s' to delete." ), 'force=true' ), array( 'status' => 501 ) );
		}

		$request->set_param( 'context', 'view' );

		$previous = $this->prepare_item_for_response( $term, $request );

		$retval = wp_delete_nav_menu( $term );

		if ( ! $retval || is_wp_error( $retval ) ) {
			return new WP_Error( 'rest_cannot_delete', __( 'The term cannot be deleted.' ), array( 'status' => 500 ) );
		}

		$response = new WP_REST_Response();
		$response->set_data(
			array(
				'deleted'  => true,
				'previous' => $previous->get_data(),
			)
		);

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-terms-controller.php */
		do_action( "rest_delete_{$this->taxonomy}", $term, $response, $request );

		return $response;
	}

	/**
	 * Returns the value of a menu's auto_add
	 *
	 * @since 5.9.0
	 *
	 * @param int $menu_id The menu id to update the location form.
	 *
	 * @return bool The value of auto_add.
	 */
	function get_menu_auto_add( $menu_id ) {
		$nav_menu_option = (array) get_option( 'nav_menu_options', array( 'auto_add' => array() ) );

		return in_array( $menu_id, $nav_menu_option['auto_add'], true );
	}

	/**
	 * Updates the menu's auto add from a REST request.
	 *
	 * @since 5.9.0
	 *
	 * @param int $menu_id The menu id to update the location form.
	 * @param WP_REST_Request $request The request object with menu and locations data.
	 *
	 * @return bool True if the auto update was successfully updated.
	 */
	function handle_auto_add( $menu_id, $request ) {
		if ( ! isset( $request['auto_add'] ) ) {
			return true;
		}

		$nav_menu_option = (array) get_option( 'nav_menu_options', array( 'auto_add' => array() ) );

		if ( ! isset( $nav_menu_option['auto_add'] ) ) {
			$nav_menu_option['auto_add'] = array();
		}

		$auto_add = $request['auto_add'];

		$i = array_search( $menu_id, $nav_menu_option['auto_add'], true );

		if ( $auto_add && false === $i ) {
			$nav_menu_option['auto_add'][] = $menu_id;
		} elseif ( ! $auto_add && false !== $i ) {
			array_splice( $nav_menu_option['auto_add'], $i, 1 );
		}

		$update = update_option( 'nav_menu_options', $nav_menu_option );

		/** This action is documented in wp-includes/nav-menu.php */
		do_action( 'wp_update_nav_menu', $menu_id );

		return $update;
	}

	/**
	 * Returns names of the locations assigned to the menu.
	 *
	 * @since 5.9.0
	 *
	 * @param int $menu_id The menu id.
	 *
	 * @return string[] $menu_locations The locations assigned to the menu.
	 */
	protected function get_menu_locations( $menu_id ) {
		$locations      = get_nav_menu_locations();
		$menu_locations = array();

		foreach ( $locations as $location => $assigned_menu_id ) {
			if ( $menu_id === $assigned_menu_id ) {
				$menu_locations[] = $location;
			}
		}

		return $menu_locations;
	}

	/**
	 * Updates the menu's locations from a REST request.
	 *
	 * @since 5.9.0
	 *
	 * @param int $menu_id The menu id to update the location form.
	 * @param WP_REST_Request $request The request object with menu and locations data.
	 *
	 * @return true|WP_Error WP_Error on an error assigning any of the locations, otherwise null.
	 */
	protected function handle_locations( $menu_id, $request ) {
		if ( ! isset( $request['locations'] ) ) {
			return true;
		}

		$menu_locations = get_registered_nav_menus();
		$menu_locations = array_keys( $menu_locations );
		$new_locations  = array();
		foreach ( $request['locations'] as $location ) {
			if ( ! in_array( $location, $menu_locations, true ) ) {
				return new WP_Error( 'invalid_menu_location', __( 'Menu location does not exist.' ), array( 'status' => 400 ) );
			}
			$new_locations[ $location ] = $menu_id;
		}
		$assigned_menu = get_nav_menu_locations();
		foreach ( $assigned_menu as $location => $term_id ) {
			if ( $term_id === $menu_id ) {
				unset( $assigned_menu[ $location ] );
			}
		}
		$new_assignments = array_merge( $assigned_menu, $new_locations );
		set_theme_mod( 'nav_menu_locations', $new_assignments );

		return true;
	}

	/**
	 * Retrieves the term's schema, conforming to JSON Schema.
	 *
	 * @since 5.9.0
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		$schema = parent::get_item_schema();
		unset( $schema['properties']['count'] );
		unset( $schema['properties']['link'] );
		unset( $schema['properties']['taxonomy'] );

		$schema['properties']['locations'] = array(
			'description' => __( 'The locations assigned to the menu.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
			'context'     => array( 'view', 'edit' ),
		);

		$schema['properties']['auto_add'] = array(
			'description' => __( 'Whether to automatically add top level pages to this menu.' ),
			'context'     => array( 'view', 'edit' ),
			'type'        => 'boolean',
		);

		return $schema;
	}
}
