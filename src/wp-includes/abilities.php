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
			'description' => __( 'Abilities that retrieve or modify posts, pages, and other content types.' ),
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
	$category_site    = 'site';
	$category_user    = 'user';
	$category_content = 'content';

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

	// Register get abilities for all post types that opt in via show_in_abilities.
	$post_types = get_post_types( array( 'show_in_abilities' => true ), 'objects' );

	foreach ( $post_types as $post_type ) {
		wp_register_post_type_get_ability( $post_type, $category_content );
	}
}

/**
 * Registers a `core/post-type/{slug}/get` ability for a given post type.
 *
 * @since 6.9.0
 *
 * @param WP_Post_Type $post_type      The post type object.
 * @param string       $category_slug  The ability category slug.
 */
function wp_register_post_type_get_ability( WP_Post_Type $post_type, string $category_slug ): void {
	$post_type_slug  = $post_type->name;
	$post_type_label = $post_type->labels->singular_name;

	$post_item_schema = array(
		'type'                 => 'object',
		'properties'           => array(
			'id'             => array(
				'type'        => 'integer',
				'description' => __( 'Unique identifier for the post.' ),
			),
			'title'          => array(
				'type'        => 'string',
				'description' => __( 'The post title.' ),
			),
			'content'        => array(
				'type'        => 'string',
				'description' => __( 'The post content.' ),
			),
			'excerpt'        => array(
				'type'        => 'string',
				'description' => __( 'The post excerpt.' ),
			),
			'status'         => array(
				'type'        => 'string',
				'description' => __( 'The post status.' ),
			),
			'author'         => array(
				'type'        => 'integer',
				'description' => __( 'ID of the post author.' ),
			),
			'date'           => array(
				'type'        => 'string',
				'description' => __( 'The publication date in ISO 8601 format.' ),
			),
			'modified'       => array(
				'type'        => 'string',
				'description' => __( 'The last modified date in ISO 8601 format.' ),
			),
			'slug'           => array(
				'type'        => 'string',
				'description' => __( 'The post slug.' ),
			),
			'link'           => array(
				'type'        => 'string',
				'description' => __( 'The post permalink URL.' ),
			),
			'comment_status' => array(
				'type'        => 'string',
				'description' => __( 'Whether comments are open or closed for this post.' ),
				'enum'        => array( 'open', 'closed' ),
			),
			'ping_status'    => array(
				'type'        => 'string',
				'description' => __( 'Whether pings are open or closed for this post.' ),
				'enum'        => array( 'open', 'closed' ),
			),
			'parent'         => array(
				'type'        => 'integer',
				'description' => __( 'The ID of the parent post. 0 if no parent.' ),
			),
			'type'           => array(
				'type'        => 'string',
				'description' => __( 'The post type.' ),
			),
		),
		'additionalProperties' => false,
	);

	wp_register_ability(
		"core/post-type/{$post_type_slug}/get",
		array(
			/* translators: %s: Post type singular name. */
			'label'               => sprintf( __( 'Get %s' ), $post_type_label ),
			/* translators: %s: Post type singular name (lowercase). */
			'description'         => sprintf( __( 'Retrieves one or more %s entries. Supports single-post lookup by ID and multi-post querying with filters for status, author, search, taxonomy, meta, date, comment status, and ping status.' ), strtolower( $post_type_label ) ),
			'category'            => $category_slug,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'id'             => array(
						'type'        => 'integer',
						'description' => __( 'Retrieve a single post by its ID. When provided, all other filters are ignored.' ),
					),
					'per_page'       => array(
						'type'        => 'integer',
						'description' => __( 'Maximum number of posts to return per page. Minimum 1, maximum 100.' ),
						'default'     => 10,
						'minimum'     => 1,
						'maximum'     => 100,
					),
					'page'           => array(
						'type'        => 'integer',
						'description' => __( 'Page of results to return.' ),
						'default'     => 1,
						'minimum'     => 1,
					),
					'status'         => array(
						'type'        => 'string',
						'description' => __( 'Filter by post status. Defaults to "publish".' ),
						'enum'        => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'any' ),
						'default'     => 'publish',
					),
					'author'         => array(
						'type'        => 'integer',
						'description' => __( 'Filter by author ID.' ),
					),
					'search'         => array(
						'type'        => 'string',
						'description' => __( 'Limit results to those matching a keyword search.' ),
					),
					'order'          => array(
						'type'        => 'string',
						'description' => __( 'Result sort direction: ASC or DESC. Default DESC.' ),
						'enum'        => array( 'ASC', 'DESC' ),
						'default'     => 'DESC',
					),
					'orderby'        => array(
						'type'        => 'string',
						'description' => __( 'Sort results by this field. Default "date".' ),
						'enum'        => array( 'date', 'title', 'ID', 'author', 'modified', 'menu_order', 'rand', 'comment_count' ),
						'default'     => 'date',
					),
					'comment_status' => array(
						'type'        => 'string',
						'description' => __( 'Filter by comment status.' ),
						'enum'        => array( 'open', 'closed' ),
					),
					'ping_status'    => array(
						'type'        => 'string',
						'description' => __( 'Filter by ping status.' ),
						'enum'        => array( 'open', 'closed' ),
					),
					'tax_query'      => array(
						'type'        => 'array',
						'description' => __( 'Filter by taxonomy terms. Each item requires a "taxonomy" slug and a "terms" array, with optional "field" (default: slug) and "operator" (default: IN).' ),
						'items'       => array(
							'type'                 => 'object',
							'properties'           => array(
								'taxonomy' => array(
									'type'        => 'string',
									'description' => __( 'Taxonomy slug.' ),
								),
								'terms'    => array(
									'type'        => 'array',
									'description' => __( 'Array of term values to filter by.' ),
									'items'       => array(
										'type' => 'string',
									),
								),
								'field'    => array(
									'type'        => 'string',
									'description' => __( 'Term field to match against: slug, name, term_id, or term_taxonomy_id. Default slug.' ),
									'enum'        => array( 'slug', 'name', 'term_id', 'term_taxonomy_id' ),
									'default'     => 'slug',
								),
								'operator' => array(
									'type'        => 'string',
									'description' => __( 'Logical operator: IN, NOT IN, AND, EXISTS, or NOT EXISTS. Default IN.' ),
									'enum'        => array( 'IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS' ),
									'default'     => 'IN',
								),
							),
							'additionalProperties' => false,
						),
					),
					'meta_query'     => array(
						'type'        => 'array',
						'description' => __( 'Filter by post meta. Each item requires a "key", with optional "value", "compare" (default: =), and "type" (default: CHAR).' ),
						'items'       => array(
							'type'                 => 'object',
							'properties'           => array(
								'key'     => array(
									'type'        => 'string',
									'description' => __( 'Meta key.' ),
								),
								'value'   => array(
									'type'        => 'string',
									'description' => __( 'Meta value.' ),
								),
								'compare' => array(
									'type'        => 'string',
									'description' => __( 'Comparison operator. Default =.' ),
									'enum'        => array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS' ),
									'default'     => '=',
								),
								'type'    => array(
									'type'        => 'string',
									'description' => __( 'Data type for comparison: NUMERIC, BINARY, CHAR, DATE, DATETIME, DECIMAL, SIGNED, TIME, or UNSIGNED. Default CHAR.' ),
									'enum'        => array( 'NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED' ),
									'default'     => 'CHAR',
								),
							),
							'additionalProperties' => false,
						),
					),
					'date_query'     => array(
						'type'                 => 'object',
						'description'          => __( 'Filter posts by a date range.' ),
						'properties'           => array(
							'after'     => array(
								'type'        => 'string',
								'description' => __( 'Return posts published after this date. Accepts any strtotime()-compatible string.' ),
							),
							'before'    => array(
								'type'        => 'string',
								'description' => __( 'Return posts published before this date. Accepts any strtotime()-compatible string.' ),
							),
							'inclusive' => array(
								'type'        => 'boolean',
								'description' => __( 'Whether to include posts that fall exactly on the after/before dates. Default false.' ),
								'default'     => false,
							),
						),
						'additionalProperties' => false,
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'required'             => array( 'posts', 'total', 'pages' ),
				'properties'           => array(
					'posts' => array(
						'type'        => 'array',
						'description' => __( 'Array of posts matching the query.' ),
						'items'       => $post_item_schema,
					),
					'total' => array(
						'type'        => 'integer',
						'description' => __( 'Total number of posts matching the query.' ),
					),
					'pages' => array(
						'type'        => 'integer',
						'description' => __( 'Total number of pages available.' ),
					),
				),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = array() ) use ( $post_type_slug ) {
				$input = is_array( $input ) ? $input : array();

				// Single-post retrieval by ID.
				if ( ! empty( $input['id'] ) ) {
					$post = get_post( (int) $input['id'] );

					if ( ! $post instanceof WP_Post || $post->post_type !== $post_type_slug ) {
						return new WP_Error(
							'ability_post_not_found',
							/* translators: 1: Post type slug, 2: Post ID. */
							sprintf( __( 'No %1$s found with ID %2$d.' ), $post_type_slug, (int) $input['id'] ),
							array( 'status' => 404 )
						);
					}

					if ( ! current_user_can( 'read_post', $post->ID ) ) {
						return new WP_Error(
							'ability_cannot_read_post',
							__( 'Sorry, you are not allowed to read this post.' ),
							array( 'status' => 403 )
						);
					}

					return array(
						'posts' => array( _wp_ability_format_post( $post ) ),
						'total' => 1,
						'pages' => 1,
					);
				}

				// Multi-post query.
				$per_page = isset( $input['per_page'] ) ? min( max( (int) $input['per_page'], 1 ), 100 ) : 10;
				$page     = isset( $input['page'] ) ? max( (int) $input['page'], 1 ) : 1;

				$query_args = array(
					'post_type'      => $post_type_slug,
					'post_status'    => isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'publish',
					'posts_per_page' => $per_page,
					'paged'          => $page,
					'no_found_rows'  => false,
				);

				if ( isset( $input['author'] ) && $input['author'] > 0 ) {
					$query_args['author'] = (int) $input['author'];
				}

				if ( ! empty( $input['search'] ) ) {
					$query_args['s'] = sanitize_text_field( $input['search'] );
				}

				if ( ! empty( $input['order'] ) ) {
					$query_args['order'] = sanitize_text_field( $input['order'] );
				}

				if ( ! empty( $input['orderby'] ) ) {
					$query_args['orderby'] = sanitize_text_field( $input['orderby'] );
				}

				if ( ! empty( $input['comment_status'] ) ) {
					$query_args['comment_status'] = sanitize_text_field( $input['comment_status'] );
				}

				if ( ! empty( $input['ping_status'] ) ) {
					$query_args['ping_status'] = sanitize_text_field( $input['ping_status'] );
				}

				if ( ! empty( $input['tax_query'] ) && is_array( $input['tax_query'] ) ) {
					$query_args['tax_query'] = $input['tax_query'];
				}

				if ( ! empty( $input['meta_query'] ) && is_array( $input['meta_query'] ) ) {
					$query_args['meta_query'] = $input['meta_query'];
				}

				if ( ! empty( $input['date_query'] ) && is_array( $input['date_query'] ) ) {
					$query_args['date_query'] = array( $input['date_query'] );
				}

				$query = new WP_Query( $query_args );

				$posts = array();
				foreach ( $query->posts as $post ) {
					$posts[] = _wp_ability_format_post( $post );
				}

				return array(
					'posts' => $posts,
					'total' => (int) $query->found_posts,
					'pages' => (int) $query->max_num_pages,
				);
			},
			'permission_callback' => static function (): bool {
				return current_user_can( 'read' );
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
}

/**
 * Formats a WP_Post object into the shape returned by post-type get abilities.
 *
 * @since 6.9.0
 * @access private
 *
 * @param WP_Post $post The post object to format.
 * @return array Formatted post data.
 */
function _wp_ability_format_post( WP_Post $post ): array {
	return array(
		'id'             => $post->ID,
		'title'          => $post->post_title,
		'content'        => $post->post_content,
		'excerpt'        => $post->post_excerpt,
		'status'         => $post->post_status,
		'author'         => (int) $post->post_author,
		'date'           => mysql_to_rfc3339( $post->post_date ),
		'modified'       => mysql_to_rfc3339( $post->post_modified ),
		'slug'           => $post->post_name,
		'link'           => get_permalink( $post->ID ),
		'comment_status' => $post->comment_status,
		'ping_status'    => $post->ping_status,
		'parent'         => (int) $post->post_parent,
		'type'           => $post->post_type,
	);
}
