<?php
/**
 * Core Abilities registration.
 *
 * @package WordPress
 * @subpackage Abilities_API
 * @since 6.9.0
 */

declare( strict_types = 1 );

/**
 * Registers the core ability categories.
 *
 * @since 6.9.0
 */
function wp_register_core_ability_categories(): void {
	wp_register_ability_category(
		'site',
		array(
			'label'       => __( 'Site' ),
			'description' => __( 'Abilities that retrieve or modify site information and settings.' ),
		)
	);

	wp_register_ability_category(
		'user',
		array(
			'label'       => __( 'User' ),
			'description' => __( 'Abilities that retrieve or modify user information and settings.' ),
		)
	);

	wp_register_ability_category(
		'content',
		array(
			'label'       => __( 'Content' ),
			'description' => __( 'Abilities that retrieve or modify content items of any post type.' ),
		)
	);
}

/**
 * Registers the default core abilities.
 *
 * @since 6.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function wp_register_core_abilities(): void {
	$category_site = 'site';
	$category_user = 'user';

	$site_info_properties = array(
		'name'        => array(
			'type'        => 'string',
			'title'       => __( 'Site Title' ),
			'description' => __( 'The site title.' ),
		),
		'description' => array(
			'type'        => 'string',
			'title'       => __( 'Tagline' ),
			'description' => __( 'The site tagline.' ),
		),
		'url'         => array(
			'type'        => 'string',
			'title'       => __( 'Site Address (URL)' ),
			'description' => __( 'The public URL where visitors access the site. May differ from the WordPress installation URL.' ),
		),
		'wpurl'       => array(
			'type'        => 'string',
			'title'       => __( 'WordPress Address (URL)' ),
			'description' => __( 'The URL where WordPress core files are served. May differ from the public site URL.' ),
		),
		'admin_email' => array(
			'type'        => 'string',
			'title'       => __( 'Administration Email Address' ),
			'description' => __( 'The site administrator email address.' ),
		),
		'charset'     => array(
			'type'        => 'string',
			'title'       => __( 'Site Charset' ),
			'description' => __( 'The site character encoding.' ),
		),
		'language'    => array(
			'type'        => 'string',
			'title'       => __( 'Site Language' ),
			'description' => __( 'The site locale in dash form (e.g. en-US).' ),
		),
		'version'     => array(
			'type'        => 'string',
			'title'       => __( 'WordPress Version' ),
			'description' => __( 'The WordPress core version running on this site.' ),
		),
	);
	$site_info_fields     = array_keys( $site_info_properties );

	wp_register_ability(
		'core/get-site-info',
		array(
			'label'               => __( 'Get Site Information' ),
			'description'         => __( 'Returns site information configured in WordPress. By default returns all fields, or optionally a filtered subset.' ),
			'category'            => $category_site,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'fields' => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => $site_info_fields,
						),
						'description' => __( 'Optional: Limit response to specific fields. If omitted, all fields are returned.' ),
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => $site_info_properties,
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = array() ) use ( $site_info_fields ): array {
				$input = is_array( $input ) ? $input : array();
				$requested_fields = ! empty( $input['fields'] ) ? $input['fields'] : $site_info_fields;

				$result = array();
				foreach ( $requested_fields as $field ) {
					if ( 'language' === $field ) {
						$result[ $field ] = str_replace( '_', '-', get_locale() );
					} else {
						$result[ $field ] = get_bloginfo( $field );
					}
				}

				return $result;
			},
			'permission_callback' => static function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'public'      => true,
			),
		)
	);

	$user_info_properties = array(
		'id'            => array(
			'type'        => 'integer',
			'title'       => __( 'User ID' ),
			'description' => __( 'Unique identifier for the user.' ),
		),
		'display_name'  => array(
			'type'        => 'string',
			'title'       => __( 'Display Name' ),
			'description' => __( 'Public-facing name selected by the user.' ),
		),
		'user_nicename' => array(
			'type'        => 'string',
			'title'       => __( 'User Nicename' ),
			'description' => __( 'URL-friendly slug for the user. Defaults to the username.' ),
		),
		'user_login'    => array(
			'type'        => 'string',
			'title'       => __( 'Username' ),
			'description' => __( 'Login identifier for the user. Cannot be changed once set.' ),
		),
		'roles'         => array(
			'type'        => 'array',
			'title'       => __( 'Roles' ),
			'description' => __( 'Roles assigned to the user, such as administrator, editor, author, contributor, or subscriber.' ),
			'items'       => array(
				'type' => 'string',
			),
		),
		'locale'        => array(
			'type'        => 'string',
			'title'       => __( 'Language' ),
			'description' => __( 'Locale code for the user, such as en_US.' ),
		),
		'first_name'    => array(
			'type'        => 'string',
			'title'       => __( 'First Name' ),
			'description' => __( 'Given name.' ),
		),
		'last_name'     => array(
			'type'        => 'string',
			'title'       => __( 'Last Name' ),
			'description' => __( 'Family name.' ),
		),
		'nickname'      => array(
			'type'        => 'string',
			'title'       => __( 'Nickname' ),
			'description' => __( 'Informal name. Defaults to the username.' ),
		),
		'description'   => array(
			'type'        => 'string',
			'title'       => __( 'Biographical Info' ),
			'description' => __( 'User-authored biography. May be empty.' ),
		),
		'user_url'      => array(
			'type'        => 'string',
			'title'       => __( 'Website' ),
			'description' => __( 'Personal website URL.' ),
		),
	);
	$user_info_fields     = array_keys( $user_info_properties );

	wp_register_ability(
		'core/get-user-info',
		array(
			'label'               => __( 'Get User Information' ),
			'description'         => __( 'Returns profile details for the current authenticated user to support personalization, auditing, and access-aware behavior. By default returns all fields, or optionally a filtered subset.' ),
			'category'            => $category_user,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'fields' => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => $user_info_fields,
						),
						'description' => __( 'Optional: Limit response to specific fields. If omitted, all fields are returned.' ),
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => $user_info_properties,
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = array() ) use ( $user_info_fields ): array {
				$input            = is_array( $input ) ? $input : array();
				$requested_fields = ! empty( $input['fields'] ) ? $input['fields'] : $user_info_fields;
				$current_user     = wp_get_current_user();

				$all = array(
					'id'            => $current_user->ID,
					'display_name'  => $current_user->display_name,
					'user_nicename' => $current_user->user_nicename,
					'user_login'    => $current_user->user_login,
					// Ensure roles are encoded as a JSON array, regardless of their array keys.
					'roles'         => array_values( $current_user->roles ),
					'locale'        => get_user_locale( $current_user ),
					'first_name'    => $current_user->first_name,
					'last_name'     => $current_user->last_name,
					'nickname'      => $current_user->nickname,
					'description'   => $current_user->description,
					'user_url'      => $current_user->user_url,
				);

				return array_intersect_key( $all, array_flip( $requested_fields ) );
			},
			'permission_callback' => static function (): bool {
				return is_user_logged_in();
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'public'      => true,
			),
		)
	);

	$environment_info_properties = array(
		'environment'    => array(
			'type'        => 'string',
			'title'       => __( 'Environment Type' ),
			'description' => __( 'The site\'s runtime environment classification.' ),
			'enum'        => array( 'production', 'staging', 'development', 'local' ),
		),
		'php_version'    => array(
			'type'        => 'string',
			'title'       => __( 'PHP Version' ),
			'description' => __( 'The PHP runtime version executing WordPress.' ),
		),
		'db_server_info' => array(
			'type'        => 'string',
			'title'       => __( 'Database Server Info' ),
			'description' => __( 'The database server vendor and version string reported by the driver.' ),
		),
		'wp_version'     => array(
			'type'        => 'string',
			'title'       => __( 'WordPress Version' ),
			'description' => __( 'The WordPress core version running on this site.' ),
		),
	);
	$environment_info_fields     = array_keys( $environment_info_properties );

	wp_register_ability(
		'core/get-environment-info',
		array(
			'label'               => __( 'Get Environment Info' ),
			'description'         => __( 'Returns core details about the site\'s runtime context for diagnostics and compatibility (environment, PHP runtime, database server info, WordPress version). By default returns all fields, or optionally a filtered subset.' ),
			'category'            => $category_site,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'fields' => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => $environment_info_fields,
						),
						'description' => __( 'Optional: Limit response to specific fields. If omitted, all fields are returned.' ),
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => $environment_info_properties,
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = array() ) use ( $environment_info_fields ): array {
				global $wpdb;

				/** @var array{ fields?: string[] } $input */
				$input            = is_array( $input ) ? $input : array();
				$requested_fields = ! empty( $input['fields'] ) ? $input['fields'] : $environment_info_fields;

				$db_server_info = '';
				if ( method_exists( $wpdb, 'db_server_info' ) ) {
					$db_server_info = $wpdb->db_server_info() ?? '';
				}

				$all = array(
					'environment'    => wp_get_environment_type(),
					'php_version'    => phpversion(),
					'db_server_info' => $db_server_info,
					'wp_version'     => get_bloginfo( 'version' ),
				);

				return array_intersect_key( $all, array_flip( $requested_fields ) );
			},
			'permission_callback' => static function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'public'      => true,
			),
		)
	);
	wp_register_ability(
		'core/get-settings',
		array(
			'label'               => __( 'Get Site Settings' ),
			'description'         => __( 'Returns the site settings registered for exposure, cast to the types they were registered with.' ),
			'category'            => $category_site,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'description'          => __( 'The registered settings, keyed by setting name. Properties are determined at runtime by the registered settings.' ),
				'additionalProperties' => true,
			),
			'execute_callback'    => static function ( $input = array() ) {
				return wp_get_settings_values();
			},
			'permission_callback' => 'wp_current_user_can_manage_settings',
			'meta'                => array(
				'annotations' => array(
					'readonly' => true,
				),
			),
		)
	);

	wp_register_ability(
		'core/update-settings',
		array(
			'label'               => __( 'Update Site Settings' ),
			'description'         => __( 'Updates one or more registered site settings. A setting given an explicit null value is reset to its default.' ),
			'category'            => $category_site,
			'input_schema'        => array(
				'type'                 => 'object',
				'description'          => __( 'Setting names mapped to their new values. Accepted properties are determined at runtime by the registered settings.' ),
				'additionalProperties' => true,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'description'          => __( 'The full set of registered settings after the update.' ),
				'additionalProperties' => true,
			),
			'execute_callback'    => static function ( $input = array() ) {
				return wp_update_settings_values( is_array( $input ) ? $input : array() );
			},
			'permission_callback' => 'wp_current_user_can_manage_settings',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'core/get-item',
		array(
			'label'               => __( 'Get Content Item' ),
			'description'         => __( 'Returns a single content item of any exposed post type, addressed by ID. The post type is an argument, so one ability covers posts, pages, and custom post types.' ),
			'category'            => 'content',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'id'        => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the item to return.' ),
					),
					'post_type' => array(
						'type'        => 'string',
						'description' => __( 'Optional. The expected post type. When provided, the item must be of this type.' ),
					),
				),
				'required'             => array( 'id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'description'          => __( 'The content item. Which fields are present depends on what the post type supports.' ),
				'additionalProperties' => true,
			),
			'execute_callback'    => static function ( $input = array() ) {
				$post = get_post( (int) $input['id'] );

				if ( ! $post instanceof WP_Post || ! wp_is_post_type_exposed( $post->post_type ) ) {
					return new WP_Error(
						'invalid_item',
						__( 'Invalid item ID.' ),
						array( 'status' => 404 )
					);
				}

				if ( ! empty( $input['post_type'] ) && $post->post_type !== $input['post_type'] ) {
					return new WP_Error(
						'invalid_item',
						__( 'Invalid item ID.' ),
						array( 'status' => 404 )
					);
				}

				return wp_get_post_item_data( $post );
			},
			'permission_callback' => static function ( $input = array() ) {
				$post = get_post( (int) $input['id'] );

				if ( ! $post instanceof WP_Post ) {
					return false;
				}

				return wp_check_read_post_permission( $post );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly' => true,
				),
			),
		)
	);

	wp_register_ability(
		'core/create-item',
		array(
			'label'               => __( 'Create Content Item' ),
			'description'         => __( 'Creates a content item of any exposed post type. The post type is an argument, so one ability covers posts, pages, and custom post types. Media uploads are not handled here.' ),
			'category'            => 'content',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'post_type'      => array(
						'type'        => 'string',
						'default'     => 'post',
						'description' => __( 'The post type to create. Defaults to "post".' ),
					),
					'title'          => array( 'type' => 'string' ),
					'content'        => array( 'type' => 'string' ),
					'excerpt'        => array( 'type' => 'string' ),
					'slug'           => array( 'type' => 'string' ),
					'status'         => array( 'type' => 'string' ),
					'author'         => array( 'type' => 'integer' ),
					'parent'         => array( 'type' => 'integer' ),
					'menu_order'     => array( 'type' => 'integer' ),
					'comment_status' => array(
						'type' => 'string',
						'enum' => array( 'open', 'closed' ),
					),
					'ping_status'    => array(
						'type' => 'string',
						'enum' => array( 'open', 'closed' ),
					),
					'password'       => array( 'type' => 'string' ),
					'date'           => array( 'type' => 'string' ),
					'date_gmt'       => array( 'type' => 'string' ),
					'sticky'         => array( 'type' => 'boolean' ),
					'format'         => array( 'type' => 'string' ),
					'template'       => array( 'type' => 'string' ),
					'featured_media' => array( 'type' => 'integer' ),
					'terms'          => array(
						'type'                 => 'object',
						'description'          => __( 'Term IDs to assign, keyed by taxonomy REST base.' ),
						'additionalProperties' => true,
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'description'          => __( 'The created content item.' ),
				'additionalProperties' => true,
			),
			'execute_callback'    => static function ( $input = array() ) {
				$post_type = ! empty( $input['post_type'] ) ? $input['post_type'] : 'post';

				return wp_create_post_item( $post_type, $input );
			},
			'permission_callback' => static function ( $input = array() ) {
				$post_type = ! empty( $input['post_type'] ) ? $input['post_type'] : 'post';

				if ( ! wp_is_post_type_exposed( $post_type ) ) {
					return false;
				}

				/*
				* Abilities nest term assignments under `terms`; the shared permission
				* helper reads them keyed by taxonomy REST base, as REST sends them.
				*/
				$params = $input;
				if ( isset( $input['terms'] ) && is_array( $input['terms'] ) ) {
					$params = array_merge( $input, $input['terms'] );
				}

				return true === wp_check_create_post_permission( $post_type, $params );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		)
	);

	wp_register_ability(
		'core/update-item',
		array(
			'label'               => __( 'Update Content Item' ),
			'description'         => __( 'Updates a content item of any exposed post type, addressed by ID. Only the fields supplied are changed.' ),
			'category'            => 'content',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'id'             => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the item to update.' ),
					),
					'title'          => array( 'type' => 'string' ),
					'content'        => array( 'type' => 'string' ),
					'excerpt'        => array( 'type' => 'string' ),
					'slug'           => array( 'type' => 'string' ),
					'status'         => array( 'type' => 'string' ),
					'author'         => array( 'type' => 'integer' ),
					'parent'         => array( 'type' => 'integer' ),
					'menu_order'     => array( 'type' => 'integer' ),
					'comment_status' => array(
						'type' => 'string',
						'enum' => array( 'open', 'closed' ),
					),
					'ping_status'    => array(
						'type' => 'string',
						'enum' => array( 'open', 'closed' ),
					),
					'password'       => array( 'type' => 'string' ),
					'date'           => array( 'type' => 'string' ),
					'date_gmt'       => array( 'type' => 'string' ),
					'sticky'         => array( 'type' => 'boolean' ),
					'format'         => array( 'type' => 'string' ),
					'template'       => array( 'type' => 'string' ),
					'featured_media' => array( 'type' => 'integer' ),
					'terms'          => array(
						'type'                 => 'object',
						'description'          => __( 'Term IDs to assign, keyed by taxonomy REST base.' ),
						'additionalProperties' => true,
					),
				),
				'required'             => array( 'id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'description'          => __( 'The updated content item.' ),
				'additionalProperties' => true,
			),
			'execute_callback'    => static function ( $input = array() ) {
				return wp_update_post_item( (int) $input['id'], $input );
			},
			'permission_callback' => static function ( $input = array() ) {
				$post = get_post( (int) $input['id'] );

				if ( ! $post instanceof WP_Post || ! wp_is_post_type_exposed( $post->post_type ) ) {
					return false;
				}

				/*
				* Abilities nest term assignments under `terms`; the shared permission
				* helper reads them keyed by taxonomy REST base, as REST sends them.
				*/
				$params = $input;
				if ( isset( $input['terms'] ) && is_array( $input['terms'] ) ) {
					$params = array_merge( $input, $input['terms'] );
				}

				return true === wp_check_update_post_permission( $post, $params );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		)
	);

	wp_register_ability(
		'core/delete-item',
		array(
			'label'               => __( 'Delete Content Item' ),
			'description'         => __( 'Moves a content item of any exposed post type to the Trash, or deletes it permanently when force is true.' ),
			'category'            => 'content',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'id'    => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the item to delete.' ),
					),
					'force' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Whether to bypass the Trash and delete the item permanently.' ),
					),
				),
				'required'             => array( 'id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'deleted'  => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the item was deleted.' ),
					),
					'previous' => array(
						'type'                 => 'object',
						'description'          => __( 'The item as it was before deletion.' ),
						'additionalProperties' => true,
					),
				),
			),
			'execute_callback'    => static function ( $input = array() ) {
				return wp_delete_post_item( (int) $input['id'], ! empty( $input['force'] ) );
			},
			'permission_callback' => static function ( $input = array() ) {
				$post = get_post( (int) $input['id'] );

				if ( ! $post instanceof WP_Post ) {
					return false;
				}

				return wp_check_delete_post_permission( $post );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
			),
		)
	);
}
