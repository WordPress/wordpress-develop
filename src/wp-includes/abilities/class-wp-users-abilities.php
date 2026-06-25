<?php
/**
 * Registers core user abilities.
 *
 * @package WordPress
 * @subpackage Abilities_API
 * @since 6.9.0
 *
 * @access private
 */

declare( strict_types = 1 );

/**
 * Registers core user abilities.
 *
 * @since 6.9.0
 * @access private
 */
class WP_Users_Abilities {

	/**
	 * The ability category used for user abilities.
	 *
	 * @since 6.9.0
	 * @var string
	 */
	private const CATEGORY = 'user';

	/**
	 * Default number of users returned per page in collection mode.
	 *
	 * @since 6.9.0
	 * @var int
	 */
	private const DEFAULT_PER_PAGE = 10;

	/**
	 * Maximum number of users returned per page in collection mode.
	 *
	 * @since 6.9.0
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Public/read-context user fields.
	 *
	 * @since 6.9.0
	 * @var string[]
	 */
	private static $read_fields = array(
		'id',
		'display_name',
		'description',
		'url',
		'link',
		'slug',
	);

	/**
	 * Fields that expose edit-context user data.
	 *
	 * @since 6.9.0
	 * @var string[]
	 */
	private static $sensitive_fields = array(
		'username',
		'email',
		'first_name',
		'last_name',
		'nickname',
		'locale',
		'registered_date',
	);

	/**
	 * Registers all user abilities.
	 *
	 * @since 6.9.0
	 */
	public static function register(): void {
		self::register_get_users();
	}

	/**
	 * Registers the read-only `core/users` ability.
	 *
	 * @since 6.9.0
	 */
	private static function register_get_users(): void {
		wp_register_ability(
			'core/users',
			array(
				'label'               => __( 'Get Users' ),
				'description'         => __( 'Retrieves one or more readable WordPress users. Fetch a single readable user by ID, email, username, or slug, or query a paginated collection optionally filtered by roles or published-post authorship.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::get_users_input_schema(),
				'output_schema'       => self::get_users_output_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_get_users' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
					'pagination'   => true,
				),
			)
		);
	}

	/**
	 * Permission callback for the `core/users` ability.
	 *
	 * @since 6.9.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	public static function check_permission( $input = array() ): bool {
		$input = is_array( $input ) ? $input : array();

		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( ! empty( $input['roles'] ) && ! current_user_can( 'list_users' ) ) {
			return false;
		}

		$lookup_type = self::get_lookup_type( $input );
		if ( '' === $lookup_type ) {
			return true;
		}

		$user = self::find_user( $input );
		if ( ! $user instanceof WP_User || ! self::is_user_member_of_site( $user ) ) {
			return false;
		}

		return self::can_read_user_for_lookup( $user, $lookup_type );
	}

	/**
	 * Executes the `core/users` ability.
	 *
	 * @since 6.9.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return array<string, mixed>|WP_Error A map with a `users` list, or a WP_Error on failure.
	 */
	public static function execute_get_users( $input = array() ) {
		$input  = is_array( $input ) ? $input : array();
		$fields = self::normalize_fields( $input );

		$lookup_type = self::get_lookup_type( $input );
		if ( '' !== $lookup_type ) {
			$user = self::find_user( $input );
			if ( ! $user instanceof WP_User
				|| ! self::is_user_member_of_site( $user )
				|| ! self::can_read_user_for_lookup( $user, $lookup_type )
			) {
				return self::not_found_error();
			}

			return array(
				'users'       => array( self::format_user( $user, $fields ) ),
				'total'       => 1,
				'total_pages' => 1,
			);
		}

		$per_page = self::normalize_per_page( $input );
		$page     = isset( $input['page'] ) ? max( 1, self::input_int( $input['page'] ) ) : 1;

		$query_args = array(
			'number'      => $per_page,
			'offset'      => ( $page - 1 ) * $per_page,
			'count_total' => true,
		);

		if ( ! empty( $input['roles'] ) && current_user_can( 'list_users' ) ) {
			$query_args['role__in'] = self::normalize_string_list( $input['roles'] );
		}

		if ( current_user_can( 'list_users' ) ) {
			$has_published_posts = self::normalize_has_published_posts( $input );
			if ( null !== $has_published_posts ) {
				$query_args['has_published_posts'] = $has_published_posts;
			}
		} else {
			$query_args['has_published_posts'] = self::get_public_author_post_types();
		}

		$query = new WP_User_Query( $query_args );

		$users = array();
		foreach ( $query->get_results() as $user ) {
			if ( ! $user instanceof WP_User || ! self::is_user_member_of_site( $user ) || ! self::can_read_user( $user ) ) {
				continue;
			}

			$users[] = self::format_user( $user, $fields );
		}

		$total_users = (int) $query->get_total();

		return array(
			'users'       => $users,
			'total'       => $total_users,
			'total_pages' => $per_page > 0 ? (int) ceil( $total_users / $per_page ) : 0,
		);
	}

	/**
	 * Casts a raw input value to a non-negative integer.
	 *
	 * @since 6.9.0
	 *
	 * @param mixed $value The raw input value.
	 * @return int The value as a non-negative integer, or 0 when not scalar.
	 */
	private static function input_int( $value ): int {
		return is_scalar( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Determines the single-user lookup type represented by the input.
	 *
	 * @since 6.9.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return string The lookup type, or an empty string for collection mode.
	 */
	private static function get_lookup_type( array $input ): string {
		foreach ( array( 'id', 'email', 'username', 'slug' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				return $key;
			}
		}

		return '';
	}

	/**
	 * Finds a user by one of the supported unique input identifiers.
	 *
	 * @since 6.9.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return WP_User|null User object, or null when not found.
	 */
	private static function find_user( array $input ): ?WP_User {
		if ( isset( $input['id'] ) ) {
			$user = get_userdata( self::input_int( $input['id'] ) );
			return $user instanceof WP_User ? $user : null;
		}

		if ( isset( $input['email'] ) && is_string( $input['email'] ) ) {
			$user = get_user_by( 'email', sanitize_email( $input['email'] ) );
			return $user instanceof WP_User ? $user : null;
		}

		if ( isset( $input['username'] ) && is_string( $input['username'] ) ) {
			$user = get_user_by( 'login', $input['username'] );
			return $user instanceof WP_User ? $user : null;
		}

		if ( isset( $input['slug'] ) && is_string( $input['slug'] ) ) {
			$user = get_user_by( 'slug', sanitize_title( $input['slug'] ) );
			return $user instanceof WP_User ? $user : null;
		}

		return null;
	}

	/**
	 * Checks whether a user belongs to the current site.
	 *
	 * @since 6.9.0
	 *
	 * @param WP_User $user User object.
	 * @return bool Whether the user belongs to the current site.
	 */
	private static function is_user_member_of_site( WP_User $user ): bool {
		return ! is_multisite() || is_user_member_of_blog( (int) $user->ID );
	}

	/**
	 * Checks whether a single-user lookup may return the target user.
	 *
	 * Email and username are identifier-sensitive lookup modes and do not use the
	 * public-author fallback.
	 *
	 * @since 6.9.0
	 *
	 * @param WP_User $user        User object.
	 * @param string  $lookup_type Lookup type.
	 * @return bool Whether the user can be read for that lookup type.
	 */
	private static function can_read_user_for_lookup( WP_User $user, string $lookup_type ): bool {
		if ( self::is_current_user( $user ) ) {
			return true;
		}

		if ( current_user_can( 'edit_user', $user->ID ) || current_user_can( 'list_users' ) ) {
			return true;
		}

		if ( 'email' === $lookup_type || 'username' === $lookup_type ) {
			return false;
		}

		return self::is_public_author( $user );
	}

	/**
	 * Checks whether a user may be included in collection results.
	 *
	 * @since 6.9.0
	 *
	 * @param WP_User $user User object.
	 * @return bool Whether the user can be read.
	 */
	private static function can_read_user( WP_User $user ): bool {
		return self::is_current_user( $user )
			|| current_user_can( 'edit_user', $user->ID )
			|| current_user_can( 'list_users' )
			|| self::is_public_author( $user );
	}

	/**
	 * Checks whether the current user is the target user.
	 *
	 * @since 6.9.0
	 *
	 * @param WP_User $user User object.
	 * @return bool Whether the current user is the target user.
	 */
	private static function is_current_user( WP_User $user ): bool {
		return get_current_user_id() === (int) $user->ID;
	}

	/**
	 * Checks whether a user has published posts in REST-visible author post types.
	 *
	 * @since 6.9.0
	 *
	 * @param WP_User $user User object.
	 * @return bool Whether the user is publicly visible as an author.
	 */
	private static function is_public_author( WP_User $user ): bool {
		$post_types = self::get_public_author_post_types();
		if ( array() === $post_types ) {
			return false;
		}

		return count_user_posts( (int) $user->ID, $post_types ) > 0;
	}

	/**
	 * Returns REST-visible post types that support authors.
	 *
	 * @since 6.9.0
	 *
	 * @return string[] REST-visible author post type names.
	 */
	private static function get_public_author_post_types(): array {
		$post_types = array();

		foreach ( get_post_types( array( 'show_in_rest' => true ), 'names' ) as $post_type ) {
			if ( is_string( $post_type ) && post_type_supports( $post_type, 'author' ) ) {
				$post_types[] = $post_type;
			}
		}

		return $post_types;
	}

	/**
	 * Normalizes the requested fields to the supported set, defaulting to all fields.
	 *
	 * @since 6.9.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return string[] List of requested field names.
	 */
	private static function normalize_fields( array $input ): array {
		$available_fields = self::get_fields();

		if ( empty( $input['fields'] ) || ! is_array( $input['fields'] ) ) {
			return $available_fields;
		}

		$requested_fields = array_filter( $input['fields'], 'is_string' );
		$fields           = array_intersect( $available_fields, $requested_fields );

		return array() === $fields ? $available_fields : array_values( $fields );
	}

	/**
	 * Returns the supported field list in output order.
	 *
	 * @since 6.9.0
	 *
	 * @return string[] Supported field names.
	 */
	private static function get_fields(): array {
		$fields = self::$read_fields;

		if ( get_option( 'show_avatars' ) ) {
			$fields[] = 'avatar_urls';
		}

		return array_merge( $fields, self::$sensitive_fields, array( 'roles' ) );
	}

	/**
	 * Normalizes the requested per-page value to the supported bounds.
	 *
	 * @since 6.9.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return int The clamped per-page value.
	 */
	private static function normalize_per_page( array $input ): int {
		$per_page = isset( $input['per_page'] ) ? self::input_int( $input['per_page'] ) : self::DEFAULT_PER_PAGE;

		return max( 1, min( self::MAX_PER_PAGE, $per_page ) );
	}

	/**
	 * Normalizes a mixed value into a list of non-empty strings.
	 *
	 * @since 6.9.0
	 *
	 * @param mixed $value Raw value.
	 * @return string[] Normalized strings.
	 */
	private static function normalize_string_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$strings = array();
		foreach ( $value as $item ) {
			if ( ! is_string( $item ) || '' === $item ) {
				continue;
			}

			$strings[] = $item;
		}

		return array_values( array_unique( $strings ) );
	}

	/**
	 * Normalizes the `has_published_posts` collection input.
	 *
	 * @since 6.9.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return bool|string[]|null Normalized query value, or null when absent/invalid.
	 */
	private static function normalize_has_published_posts( array $input ) {
		if ( ! array_key_exists( 'has_published_posts', $input ) ) {
			return null;
		}

		if ( true === $input['has_published_posts'] ) {
			return true;
		}

		$post_types = self::normalize_string_list( $input['has_published_posts'] );

		return array() === $post_types ? null : $post_types;
	}

	/**
	 * Builds the input schema for the `core/users` ability.
	 *
	 * @since 6.9.0
	 *
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private static function get_users_input_schema(): array {
		$fields = array(
			'type'        => 'array',
			'uniqueItems' => true,
			'items'       => array(
				'type' => 'string',
				'enum' => self::get_fields(),
			),
			'description' => __( 'Limit each returned user to these fields. If omitted, all fields visible to the current user are returned.' ),
		);

		return array(
			'type'  => 'object',
			'oneOf' => array(
				array(
					'title'                => __( 'Get a single readable user by ID' ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
					'properties'           => array(
						'id'     => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Retrieve a single readable user by ID.' ),
						),
						'fields' => $fields,
					),
				),
				array(
					'title'                => __( 'Get a single readable user by email address' ),
					'required'             => array( 'email' ),
					'additionalProperties' => false,
					'properties'           => array(
						'email'  => array(
							'type'        => 'string',
							'format'      => 'email',
							'description' => __( 'Retrieve a single readable user by email address. Resolving another user by email requires permission to list or edit users.' ),
						),
						'fields' => $fields,
					),
				),
				array(
					'title'                => __( 'Get a single readable user by username' ),
					'required'             => array( 'username' ),
					'additionalProperties' => false,
					'properties'           => array(
						'username' => array(
							'type'        => 'string',
							'description' => __( 'Retrieve a single readable user by username. Resolving another user by username requires permission to list or edit users.' ),
						),
						'fields'   => $fields,
					),
				),
				array(
					'title'                => __( 'Get a single readable user by slug' ),
					'required'             => array( 'slug' ),
					'additionalProperties' => false,
					'properties'           => array(
						'slug'   => array(
							'type'        => 'string',
							'description' => __( 'Retrieve a single readable user by slug.' ),
						),
						'fields' => $fields,
					),
				),
				array(
					'title'                => __( 'Query readable users' ),
					'additionalProperties' => false,
					'properties'           => array(
						'roles'               => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'items'       => array(
								'type' => 'string',
							),
							'description' => __( 'Filter users by one or more roles. Requires permission to list users.' ),
						),
						'has_published_posts' => array(
							'oneOf'       => array(
								array(
									'type' => 'boolean',
									'enum' => array( true ),
								),
								array(
									'type'        => 'array',
									'uniqueItems' => true,
									'minItems'    => 1,
									'items'       => array(
										'type' => 'string',
									),
								),
							),
							'description' => __( 'Limit results to users with published posts. Use true for all post types, or provide post type names.' ),
						),
						'fields'              => $fields,
						'page'                => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Page of results to return.' ),
						),
						'per_page'            => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => self::MAX_PER_PAGE,
							'description' => __( 'Maximum number of users to return per page.' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Builds the output schema for the `core/users` ability.
	 *
	 * @since 6.9.0
	 *
	 * @return array<string, mixed> The output JSON Schema.
	 */
	private static function get_users_output_schema(): array {
		$user_properties = array(
			'id'              => array(
				'type'        => 'integer',
				'description' => __( 'The user ID.' ),
			),
			'display_name'    => array(
				'type'        => 'string',
				'description' => __( 'The display name for the user.' ),
			),
			'description'     => array(
				'type'        => 'string',
				'description' => __( 'Description of the user.' ),
			),
			'url'             => array(
				'type'        => 'string',
				'description' => __( 'URL of the user.' ),
			),
			'link'            => array(
				'type'        => 'string',
				'description' => __( 'Author archive URL for the user.' ),
			),
			'slug'            => array(
				'type'        => 'string',
				'description' => __( 'An alphanumeric identifier for the user.' ),
			),
			'username'        => array(
				'type'        => 'string',
				'description' => __( 'Login name for the user. Present when the current user can view it.' ),
			),
			'email'           => array(
				'type'        => 'string',
				'format'      => 'email',
				'description' => __( 'The email address for the user. Present when the current user can view it.' ),
			),
			'first_name'      => array(
				'type'        => 'string',
				'description' => __( 'First name for the user. Present when the current user can view it.' ),
			),
			'last_name'       => array(
				'type'        => 'string',
				'description' => __( 'Last name for the user. Present when the current user can view it.' ),
			),
			'nickname'        => array(
				'type'        => 'string',
				'description' => __( 'The nickname for the user. Present when the current user can view it.' ),
			),
			'locale'          => array(
				'type'        => 'string',
				'description' => __( 'Locale for the user. Present when the current user can view it.' ),
			),
			'registered_date' => array(
				'type'        => 'string',
				'format'      => 'date-time',
				'description' => __( 'Registration date for the user in ISO 8601 format. Present when the current user can view it.' ),
			),
			'roles'           => array(
				'type'        => 'array',
				'description' => __( 'Roles assigned to the user. Present when the current user can view them.' ),
				'items'       => array(
					'type' => 'string',
				),
			),
		);

		if ( get_option( 'show_avatars' ) ) {
			$user_properties['avatar_urls'] = array(
				'type'                 => 'object',
				'description'          => __( 'Avatar URLs for the user at various sizes.' ),
				'additionalProperties' => array(
					'type' => 'string',
				),
			);
		}

		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'users', 'total', 'total_pages' ),
			'properties'           => array(
				'users'       => array(
					'type'        => 'array',
					'description' => __( 'The readable users matching the request. A single-element list when requested by a unique identifier.' ),
					'items'       => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => $user_properties,
					),
				),
				'total'       => array(
					'type'        => 'integer',
					'description' => __( 'Total number of users matching the query, across all pages, after applying the permission filter to the query. Surfaced over REST as the X-WP-Total header.' ),
				),
				'total_pages' => array(
					'type'        => 'integer',
					'description' => __( 'Total number of query result pages available after applying the permission filter to the query. Surfaced over REST as the X-WP-TotalPages header.' ),
				),
			),
		);
	}

	/**
	 * Formats a user into the ability output shape.
	 *
	 * @since 6.9.0
	 *
	 * @param WP_User $user   The user object.
	 * @param string[] $fields The requested field names.
	 * @return array<string, mixed> The formatted user data.
	 */
	private static function format_user( WP_User $user, array $fields ): array {
		$fields_requested = static function ( string $field ) use ( $fields ): bool {
			return in_array( $field, $fields, true );
		};

		$user_id            = (int) $user->ID;
		$can_view_sensitive = self::is_current_user( $user ) || current_user_can( 'edit_user', $user_id );
		$can_view_roles     = current_user_can( 'list_users' ) || current_user_can( 'edit_user', $user_id );

		$data = array();

		if ( $fields_requested( 'id' ) ) {
			$data['id'] = $user_id;
		}
		if ( $fields_requested( 'display_name' ) ) {
			$data['display_name'] = (string) $user->display_name;
		}
		if ( $fields_requested( 'description' ) ) {
			$data['description'] = (string) $user->description;
		}
		if ( $fields_requested( 'url' ) ) {
			$data['url'] = (string) $user->user_url;
		}
		if ( $fields_requested( 'link' ) ) {
			$data['link'] = (string) get_author_posts_url( $user_id, $user->user_nicename );
		}
		if ( $fields_requested( 'slug' ) ) {
			$data['slug'] = (string) $user->user_nicename;
		}
		if ( $fields_requested( 'avatar_urls' ) && get_option( 'show_avatars' ) ) {
			$data['avatar_urls'] = rest_get_avatar_urls( $user );
		}

		if ( $can_view_sensitive ) {
			if ( $fields_requested( 'username' ) ) {
				$data['username'] = (string) $user->user_login;
			}
			if ( $fields_requested( 'email' ) ) {
				$data['email'] = (string) $user->user_email;
			}
			if ( $fields_requested( 'first_name' ) ) {
				$data['first_name'] = (string) $user->first_name;
			}
			if ( $fields_requested( 'last_name' ) ) {
				$data['last_name'] = (string) $user->last_name;
			}
			if ( $fields_requested( 'nickname' ) ) {
				$data['nickname'] = (string) $user->nickname;
			}
			if ( $fields_requested( 'locale' ) ) {
				$data['locale'] = (string) get_user_locale( $user );
			}
			if ( $fields_requested( 'registered_date' ) ) {
				$registered_timestamp = strtotime( (string) $user->user_registered );
				if ( false !== $registered_timestamp ) {
					$data['registered_date'] = gmdate( 'c', $registered_timestamp );
				}
			}
		}

		if ( $fields_requested( 'roles' ) && $can_view_roles ) {
			$data['roles'] = self::normalize_string_list( $user->roles );
		}

		return $data;
	}

	/**
	 * Returns a generic not-found error for missing or inaccessible user lookups.
	 *
	 * @since 6.9.0
	 *
	 * @return WP_Error Not found error.
	 */
	private static function not_found_error(): WP_Error {
		return new WP_Error(
			'user_not_found',
			__( 'The requested user was not found.' ),
			array( 'status' => 404 )
		);
	}
}
