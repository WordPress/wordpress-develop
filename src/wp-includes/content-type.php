<?php
/**
 * WordPress Content Type API
 *
 * Provides a declarative API for registering content types that combines
 * post type registration with field/meta definitions in a single call.
 *
 * @package WordPress
 * @subpackage Post
 * @since 7.0.0
 */

/**
 * Global content types registry.
 *
 * @since 7.0.0
 * @global array $wp_content_types
 */
global $wp_content_types;

/**
 * Registers a content type.
 *
 * A content type is a higher-level abstraction that combines a post type
 * with its associated meta field definitions. This function:
 *
 * 1. Calls register_post_type() with the provided arguments.
 * 2. Registers each declared field as post meta via register_post_meta().
 * 3. Stores UI hints for editor/admin integrations.
 *
 * @since 7.0.0
 *
 * @global array $wp_content_types Global content types registry.
 *
 * @param string $content_type Content type key. Must not exceed 20 characters and may only
 *                             contain lowercase alphanumeric characters, dashes, and underscores.
 * @param array  $args {
 *     Array of arguments for registering a content type.
 *
 *     @type array $labels      An array of labels for the post type.
 *     @type bool  $public      Whether the post type is public. Default false.
 *     @type bool  $show_in_rest Whether to expose this post type in the REST API. Default true.
 *     @type array $fields {
 *         Array of field definitions, keyed by field name.
 *
 *         @type string   $type              The data type. Accepts 'string', 'integer', 'number',
 *                                           'boolean', 'array', 'object'. Default 'string'.
 *         @type bool     $single            Whether the meta key has one value per object. Default true.
 *         @type bool     $show_in_rest      Whether to include in REST API. Default true.
 *         @type bool     $required          Whether the field is required. Default false.
 *         @type string   $label             Human-readable label for the field.
 *         @type string   $description       Description of the field.
 *         @type mixed    $default           Default value for the field.
 *         @type string   $control           UI control type hint. Accepts 'text', 'textarea',
 *                                           'number', 'checkbox', 'select', 'radio', 'date',
 *                                           'datetime', 'email', 'url', 'color', 'range'.
 *         @type array    $enum              Array of allowed values for validation.
 *         @type callable $sanitize_callback Custom sanitization callback.
 *         @type callable $auth_callback     Custom authorization callback.
 *         @type bool     $revisions_enabled Whether to enable revisions for this meta. Default false.
 *     }
 *     @type array $ui {
 *         UI hints for editor/admin integrations.
 *
 *         @type array $editor_panel {
 *             Editor panel configuration.
 *
 *             @type string $title  Panel title.
 *             @type array  $fields Array of field keys to include in the panel.
 *         }
 *         @type array $list_table_columns Array of field keys to show as list table columns.
 *     }
 *     @type string $label       Name of the post type shown in the menu.
 *     @type string $description A short descriptive summary of what the post type is.
 *     @type bool   $hierarchical Whether the post type is hierarchical. Default false.
 *     @type array  $supports    Core feature(s) the post type supports.
 *     @type array  $taxonomies  An array of taxonomy identifiers to register for the post type.
 *     @type bool   $has_archive Whether there should be post type archives. Default false.
 *     @type array  $rewrite     Triggers the handling of rewrites for this post type.
 *     @type string $capability_type The string to use to build the read, edit, and delete capabilities.
 *     @type bool   $map_meta_cap Whether to use the internal default meta capability handling.
 *     @type string $menu_icon   The URL to the icon to be used for this menu.
 *     @type int    $menu_position The position in the menu order the post type should appear.
 *     @type bool   $show_ui     Whether to generate a default UI for managing this post type.
 *     @type bool   $show_in_menu Whether to show the post type in the admin menu.
 *     @type bool   $show_in_nav_menus Whether to make the post type available for selection in navigation menus.
 *     @type bool   $show_in_admin_bar Whether to make the post type available in the admin bar.
 *     @type bool   $can_export  Whether to allow this post type to be exported.
 *     @type bool   $delete_with_user Whether to delete posts of this type when deleting a user.
 *     @type string $template    Array of blocks to use as the default initial state for an editor session.
 *     @type string $template_lock Whether the template should be locked.
 * }
 * @return WP_Content_Type|WP_Error The registered content type object on success, WP_Error on failure.
 *
 * @example
 * register_content_type( 'book', array(
 *     'labels' => array(
 *         'name'          => 'Books',
 *         'singular_name' => 'Book',
 *     ),
 *     'public'       => true,
 *     'show_in_rest' => true,
 *     'supports'     => array( 'title', 'editor', 'thumbnail' ),
 *     'fields'       => array(
 *         'isbn' => array(
 *             'type'     => 'string',
 *             'single'   => true,
 *             'required' => true,
 *             'label'    => 'ISBN',
 *             'control'  => 'text',
 *         ),
 *         'published_year' => array(
 *             'type'    => 'integer',
 *             'single'  => true,
 *             'label'   => 'Published Year',
 *             'control' => 'number',
 *         ),
 *         'genre' => array(
 *             'type'    => 'string',
 *             'single'  => true,
 *             'label'   => 'Genre',
 *             'control' => 'select',
 *             'enum'    => array( 'fiction', 'non-fiction', 'mystery', 'sci-fi' ),
 *         ),
 *     ),
 *     'ui' => array(
 *         'editor_panel' => array(
 *             'title'  => 'Book Details',
 *             'fields' => array( 'isbn', 'published_year', 'genre' ),
 *         ),
 *     ),
 * ) );
 */
function register_content_type( $content_type, $args = array() ) {
	global $wp_content_types;

	if ( ! is_array( $wp_content_types ) ) {
		$wp_content_types = array();
	}

	// Sanitize content type name.
	$content_type = sanitize_key( $content_type );

	if ( empty( $content_type ) || strlen( $content_type ) > 20 ) {
		_doing_it_wrong(
			__FUNCTION__,
			__( 'Content type names must be between 1 and 20 characters in length.' ),
			'7.0.0'
		);
		return new WP_Error(
			'content_type_length_invalid',
			__( 'Content type names must be between 1 and 20 characters in length.' )
		);
	}

	// Check if content type already exists.
	if ( isset( $wp_content_types[ $content_type ] ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				/* translators: %s: Content type key. */
				__( 'Content type "%s" is already registered.' ),
				$content_type
			),
			'7.0.0'
		);
		return new WP_Error(
			'content_type_exists',
			sprintf(
				/* translators: %s: Content type key. */
				__( 'Content type "%s" is already registered.' ),
				$content_type
			)
		);
	}

	/**
	 * Filters the arguments for registering a content type.
	 *
	 * @since 7.0.0
	 *
	 * @param array  $args         Array of arguments for registering a content type.
	 * @param string $content_type Content type key.
	 */
	$args = apply_filters( 'register_content_type_args', $args, $content_type );

	// Set default for show_in_rest if not specified.
	if ( ! isset( $args['show_in_rest'] ) ) {
		$args['show_in_rest'] = true;
	}

	// Create the content type object.
	$content_type_object = new WP_Content_Type( $content_type, $args );

	// Validate fields.
	$validation = $content_type_object->validate_fields();
	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	// Extract fields and UI from args before passing to register_post_type.
	$post_type_args = $args;
	unset( $post_type_args['fields'] );
	unset( $post_type_args['ui'] );

	// Register the post type.
	$post_type_result = $content_type_object->register_post_type( $post_type_args );

	if ( is_wp_error( $post_type_result ) ) {
		return $post_type_result;
	}

	// Register all meta fields.
	$content_type_object->register_meta_fields();

	// Store in global registry.
	$wp_content_types[ $content_type ] = $content_type_object;

	/**
	 * Fires after a content type is registered.
	 *
	 * @since 7.0.0
	 *
	 * @param string          $content_type        Content type key.
	 * @param WP_Content_Type $content_type_object Registered content type object.
	 * @param array           $args                Original arguments passed to register_content_type().
	 */
	do_action( 'registered_content_type', $content_type, $content_type_object, $args );

	/**
	 * Fires after a specific content type is registered.
	 *
	 * The dynamic portion of the hook name, `$content_type`, refers to the content type key.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_Content_Type $content_type_object Registered content type object.
	 * @param array           $args                Original arguments passed to register_content_type().
	 */
	do_action( "registered_content_type_{$content_type}", $content_type_object, $args );

	return $content_type_object;
}

/**
 * Unregisters a content type.
 *
 * Can not be used to unregister built-in post types.
 *
 * @since 7.0.0
 *
 * @global array $wp_content_types Global content types registry.
 *
 * @param string $content_type Content type key to unregister.
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function unregister_content_type( $content_type ) {
	global $wp_content_types;

	if ( ! content_type_exists( $content_type ) ) {
		return new WP_Error(
			'content_type_not_exists',
			__( 'Content type does not exist.' )
		);
	}

	$content_type_object = $wp_content_types[ $content_type ];

	// Unregister all meta fields.
	foreach ( $content_type_object->get_fields() as $field_key => $field ) {
		unregister_post_meta( $content_type, $field_key );
	}

	// Unregister the post type.
	$result = unregister_post_type( $content_type );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	// Remove from content types registry.
	unset( $wp_content_types[ $content_type ] );

	/**
	 * Fires after a content type is unregistered.
	 *
	 * @since 7.0.0
	 *
	 * @param string $content_type Content type key.
	 */
	do_action( 'unregistered_content_type', $content_type );

	return true;
}

/**
 * Checks if a content type is registered.
 *
 * @since 7.0.0
 *
 * @global array $wp_content_types Global content types registry.
 *
 * @param string $content_type Content type key.
 * @return bool True if the content type is registered, false otherwise.
 */
function content_type_exists( $content_type ) {
	return (bool) get_content_type_object( $content_type );
}

/**
 * Retrieves a registered content type object.
 *
 * @since 7.0.0
 *
 * @global array $wp_content_types Global content types registry.
 *
 * @param string $content_type Content type key.
 * @return WP_Content_Type|null The registered content type object, or null if not found.
 */
function get_content_type_object( $content_type ) {
	global $wp_content_types;

	if ( ! is_scalar( $content_type ) || empty( $wp_content_types[ $content_type ] ) ) {
		return null;
	}

	return $wp_content_types[ $content_type ];
}

/**
 * Retrieves a list of registered content types.
 *
 * @since 7.0.0
 *
 * @global array $wp_content_types Global content types registry.
 *
 * @param array  $args     Optional. An array of key => value arguments to match against
 *                         the content type objects. Default empty array.
 * @param string $output   Optional. The type of output to return. Accepts content type 'names'
 *                         or 'objects'. Default 'names'.
 * @param string $operator Optional. The logical operation to perform. Accepts 'and' or 'or'.
 *                         'or' means only one element from the array needs to match; 'and'
 *                         means all elements must match. Default 'and'.
 * @return string[]|WP_Content_Type[] An array of content type names or objects.
 */
function get_content_types( $args = array(), $output = 'names', $operator = 'and' ) {
	global $wp_content_types;

	if ( ! is_array( $wp_content_types ) ) {
		return array();
	}

	$field = ( 'names' === $output ) ? 'name' : false;

	return wp_filter_object_list( $wp_content_types, $args, $operator, $field );
}

/**
 * Retrieves the fields defined for a content type.
 *
 * @since 7.0.0
 *
 * @param string $content_type Content type key.
 * @return array|null Array of field definitions, or null if content type not found.
 */
function get_content_type_fields( $content_type ) {
	$content_type_object = get_content_type_object( $content_type );

	if ( ! $content_type_object ) {
		return null;
	}

	return $content_type_object->get_fields();
}

/**
 * Retrieves a specific field from a content type.
 *
 * @since 7.0.0
 *
 * @param string $content_type Content type key.
 * @param string $field_key    Field key.
 * @return array|null Field definition, or null if not found.
 */
function get_content_type_field( $content_type, $field_key ) {
	$content_type_object = get_content_type_object( $content_type );

	if ( ! $content_type_object ) {
		return null;
	}

	return $content_type_object->get_field( $field_key );
}

/**
 * Retrieves the UI hints for a content type.
 *
 * @since 7.0.0
 *
 * @param string $content_type Content type key.
 * @return array|null UI hints array, or null if content type not found.
 */
function get_content_type_ui( $content_type ) {
	$content_type_object = get_content_type_object( $content_type );

	if ( ! $content_type_object ) {
		return null;
	}

	return $content_type_object->get_ui();
}

/**
 * Retrieves the REST API schema for a content type's fields.
 *
 * @since 7.0.0
 *
 * @param string $content_type Content type key.
 * @return array|null REST schema array, or null if content type not found.
 */
function get_content_type_rest_schema( $content_type ) {
	$content_type_object = get_content_type_object( $content_type );

	if ( ! $content_type_object ) {
		return null;
	}

	return $content_type_object->get_rest_schema();
}

/**
 * Validates field values for a content type.
 *
 * @since 7.0.0
 *
 * @param string $content_type Content type key.
 * @param array  $values       Array of field key => value pairs to validate.
 * @return true|WP_Error True if valid, WP_Error on failure.
 */
function validate_content_type_values( $content_type, $values ) {
	$content_type_object = get_content_type_object( $content_type );

	if ( ! $content_type_object ) {
		return new WP_Error(
			'content_type_not_exists',
			__( 'Content type does not exist.' )
		);
	}

	return $content_type_object->validate_values( $values );
}

/**
 * Adds REST API endpoint for content type schema.
 *
 * Registers a REST route that exposes the content type's field schema
 * for use by client applications.
 *
 * @since 7.0.0
 */
function _register_content_type_rest_routes() {
	register_rest_route(
		'wp/v2',
		'/content-types',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => '_rest_get_content_types',
			'permission_callback' => '__return_true',
			'schema'              => array(
				'description' => __( 'List of registered content types with their field schemas.' ),
				'type'        => 'array',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'name'   => array(
							'type'        => 'string',
							'description' => __( 'Content type identifier.' ),
						),
						'fields' => array(
							'type'        => 'object',
							'description' => __( 'Field definitions.' ),
						),
						'ui'     => array(
							'type'        => 'object',
							'description' => __( 'UI hints.' ),
						),
					),
				),
			),
		)
	);

	register_rest_route(
		'wp/v2',
		'/content-types/(?P<content_type>[a-z0-9_-]+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => '_rest_get_content_type',
			'permission_callback' => '__return_true',
			'args'                => array(
				'content_type' => array(
					'description'       => __( 'Content type identifier.' ),
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_key',
				),
			),
		)
	);
}
add_action( 'rest_api_init', '_register_content_type_rest_routes' );

/**
 * REST API callback to get all content types.
 *
 * @since 7.0.0
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object.
 */
function _rest_get_content_types( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	$content_types = get_content_types( array(), 'objects' );
	$response      = array();

	foreach ( $content_types as $content_type ) {
		$response[] = $content_type->to_array();
	}

	return rest_ensure_response( $response );
}

/**
 * REST API callback to get a single content type.
 *
 * @since 7.0.0
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error Response object or error.
 */
function _rest_get_content_type( $request ) {
	$content_type        = $request->get_param( 'content_type' );
	$content_type_object = get_content_type_object( $content_type );

	if ( ! $content_type_object ) {
		return new WP_Error(
			'rest_content_type_invalid',
			__( 'Invalid content type.' ),
			array( 'status' => 404 )
		);
	}

	return rest_ensure_response( $content_type_object->to_array() );
}
