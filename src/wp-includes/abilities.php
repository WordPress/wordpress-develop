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
 * @since 7.2.0 Added the `uncategorized` category.
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
		'uncategorized',
		array(
			'label'       => __( 'Uncategorized' ),
			'description' => __( 'Abilities that have not been assigned to a specific category.' ),
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
		'core/get-user',
		array(
			'label'               => __( 'Get User' ),
			'description'         => __( 'Returns profile data for a specific user looked up by ID, username, or email. Users can view their own record; viewing others requires the list_users capability.' ),
			'category'            => $category_user,
			'input_schema'        => array(
				'type'                 => 'object',
				'oneOf'                => array(
					array(
						'required'   => array( 'id' ),
						'properties' => array(
							'id' => array(
								'type'        => 'integer',
								'description' => __( 'The user ID.' ),
								'minimum'     => 1,
							),
						),
					),
					array(
						'required'   => array( 'username' ),
						'properties' => array(
							'username' => array(
								'type'        => 'string',
								'description' => __( 'The user login name.' ),
							),
						),
					),
					array(
						'required'   => array( 'email' ),
						'properties' => array(
							'email' => array(
								'type'        => 'string',
								'description' => __( 'The user email address.' ),
							),
						),
					),
				),
				'properties'           => array(
					'id'                   => array(
						'type'        => 'integer',
						'description' => __( 'The user ID.' ),
						'minimum'     => 1,
					),
					'username'             => array(
						'type'        => 'string',
						'description' => __( 'The user login name.' ),
					),
					'email'                => array(
						'type'        => 'string',
						'description' => __( 'The user email address.' ),
					),
					'include_capabilities' => array(
						'type'        => 'boolean',
						'description' => __( 'Whether to include the user\'s capabilities in the response.' ),
						'default'     => false,
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'required'             => array( 'id', 'username', 'email', 'display_name', 'first_name', 'last_name', 'nickname', 'description', 'url', 'link', 'slug', 'registered_date', 'roles', 'locale', 'avatar_urls' ),
				'properties'           => array(
					'id'              => array(
						'type'        => 'integer',
						'description' => __( 'The user ID.' ),
					),
					'username'        => array(
						'type'        => 'string',
						'description' => __( 'The login username for the user.' ),
					),
					'email'           => array(
						'type'        => 'string',
						'description' => __( 'The email address for the user.' ),
					),
					'display_name'    => array(
						'type'        => 'string',
						'description' => __( 'The display name for the user.' ),
					),
					'first_name'      => array(
						'type'        => 'string',
						'description' => __( 'The first name for the user.' ),
					),
					'last_name'       => array(
						'type'        => 'string',
						'description' => __( 'The last name for the user.' ),
					),
					'nickname'        => array(
						'type'        => 'string',
						'description' => __( 'The nickname for the user.' ),
					),
					'description'     => array(
						'type'        => 'string',
						'description' => __( 'The biographical description for the user.' ),
					),
					'url'             => array(
						'type'        => 'string',
						'description' => __( 'The URL of the user\'s website.' ),
					),
					'link'            => array(
						'type'        => 'string',
						'description' => __( 'The URL to the user\'s author archive page.' ),
					),
					'slug'            => array(
						'type'        => 'string',
						'description' => __( 'The URL-friendly slug for the user.' ),
					),
					'registered_date' => array(
						'type'        => 'string',
						'description' => __( 'The date the user was registered, as a UTC ISO 8601 date string.' ),
					),
					'roles'           => array(
						'type'        => 'array',
						'description' => __( 'The roles assigned to the user.' ),
						'items'       => array(
							'type' => 'string',
						),
					),
					'locale'          => array(
						'type'        => 'string',
						'description' => __( 'The locale for the user.' ),
					),
					'avatar_urls'     => array(
						'type'                 => 'object',
						'description'          => __( 'Avatar URLs for the user, keyed by size in pixels.' ),
						'additionalProperties' => array(
							'type' => 'string',
						),
					),
					'capabilities'    => array(
						'type'                 => 'object',
						'description'          => __( 'All capabilities assigned to the user. Only present when include_capabilities is true.' ),
						'additionalProperties' => array(
							'type' => 'boolean',
						),
					),
				),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = array() ) {
				$input = is_array( $input ) ? $input : array();
				$user  = null;

				if ( isset( $input['id'] ) ) {
					$user = get_user_by( 'id', $input['id'] );
				} elseif ( isset( $input['username'] ) ) {
					$user = get_user_by( 'login', $input['username'] );
				} elseif ( isset( $input['email'] ) ) {
					$user = get_user_by( 'email', $input['email'] );
				}

				if ( ! $user instanceof WP_User ) {
					return new WP_Error( 'user_not_found', __( 'No user was found matching the provided identifier.' ), array( 'status' => 404 ) );
				}

				$data = array(
					'id'              => $user->ID,
					'username'        => $user->user_login,
					'email'           => $user->user_email,
					'display_name'    => $user->display_name,
					'first_name'      => $user->first_name,
					'last_name'       => $user->last_name,
					'nickname'        => $user->nickname,
					'description'     => $user->description,
					'url'             => $user->user_url,
					'link'            => get_author_posts_url( $user->ID, $user->user_nicename ),
					'slug'            => $user->user_nicename,
					'registered_date' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $user->user_registered ) ),
					'roles'           => array_values( $user->roles ),
					'locale'          => get_user_locale( $user ),
					'avatar_urls'     => rest_get_avatar_urls( $user ),
				);

				if ( ! empty( $input['include_capabilities'] ) ) {
					$data['capabilities'] = $user->allcaps;
				}

				return $data;
			},
			'permission_callback' => static function ( $input = array() ): bool {
				if ( ! is_user_logged_in() ) {
					return false;
				}

				$input = is_array( $input ) ? $input : array();

				// Determine the target user from the input identifiers.
				$requested_user = null;
				if ( isset( $input['id'] ) ) {
					$requested_user = get_user_by( 'id', $input['id'] );
				} elseif ( isset( $input['username'] ) ) {
					$requested_user = get_user_by( 'login', $input['username'] );
				} elseif ( isset( $input['email'] ) ) {
					$requested_user = get_user_by( 'email', $input['email'] );
				}

				// Users may always view their own record.
				if ( $requested_user instanceof WP_User && get_current_user_id() === $requested_user->ID ) {
					return true;
				}

				// Viewing any other user requires list_users.
				return current_user_can( 'list_users' );
			},
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
			),
		)
	);

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
}
