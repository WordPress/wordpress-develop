<?php
/**
 * Abilities API: WP_Abilities_Users class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.2.0
 */

declare( strict_types = 1 );

/**
 * Core class used to register user-related abilities.
 *
 * Registers the read-only `core/users-query` ability, which retrieves one or more
 * readable WordPress users. Supports fetching a single readable user by ID,
 * email, username, or slug, or querying a paginated collection optionally
 * filtered by roles, published-post authorship, or included IDs. Field-level
 * access is enforced per user by omitting fields the current user cannot view.
 *
 * Unlike the other core abilities, which are self-contained closures registered
 * directly in wp_register_core_abilities(), the users ability lives in a dedicated
 * class because its callbacks and schemas share helpers: the permission and execute
 * callbacks resolve and authorize the requested user through the same code, and the
 * input schema, output schema, and field normalization are built from the same
 * field definitions. Future write-oriented user abilities can reuse them as well.
 *
 * This class is part of WordPress' internal implementation of the core abilities and is
 * not part of the public API. It may be changed or removed at any time without notice.
 * Do not use it directly or rely on its existence.
 *
 * @since 7.2.0
 *
 * @access private
 */
final class WP_Abilities_Users {

	/**
	 * The ability category used for user abilities.
	 *
	 * @since 7.2.0
	 * @var string
	 */
	private const CATEGORY = 'user';

	/**
	 * Default number of users returned per page in collection mode.
	 *
	 * @since 7.2.0
	 * @var int
	 */
	private const DEFAULT_PER_PAGE = 10;

	/**
	 * Maximum number of users returned per page in collection mode.
	 *
	 * @since 7.2.0
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Lookup type returned for collection requests.
	 *
	 * @since 7.2.0
	 * @var string
	 */
	private const LOOKUP_COLLECTION = 'collection';

	/**
	 * The get_user_by() field for each single-user lookup type, in the order they are checked.
	 *
	 * @since 7.2.0
	 * @var array<string, string>
	 */
	private const LOOKUP_FIELDS = array(
		'id'       => 'id',
		'email'    => 'email',
		'username' => 'login',
		'slug'     => 'slug',
	);

	/**
	 * Default fields returned when the caller does not request a field subset.
	 *
	 * @since 7.2.0
	 * @var string[]
	 */
	private const DEFAULT_FIELDS = array(
		'id',
		'name',
		'link',
		'slug',
		'avatar_urls',
	);

	/**
	 * Registers all user abilities.
	 *
	 * Must run on the `wp_abilities_api_init` hook.
	 *
	 * @since 7.2.0
	 */
	public function register(): void {
		$this->register_users_query();
	}

	/**
	 * Registers the read-only `core/users-query` ability.
	 *
	 * @since 7.2.0
	 */
	private function register_users_query(): void {
		wp_register_ability(
			'core/users-query',
			array(
				'label'               => __( 'Query Users' ),
				'description'         => __( 'Retrieves one or more readable WordPress users. Fetch a single readable user by ID, email, username, or slug, or query a paginated collection optionally filtered by roles, published-post authorship, or included IDs.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_users_query_input_schema(),
				'output_schema'       => $this->get_users_query_output_schema(),
				'execute_callback'    => array( $this, 'execute_users_query' ),
				'permission_callback' => array( $this, 'check_permission' ),
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

	/**
	 * Permission callback for the `core/users-query` ability.
	 *
	 * Performs request-level checks. Single-user requests are checked against
	 * the target user, while collection requests rely on query arguments in
	 * {@see self::execute_users_query()} for row-level access.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	public function check_permission( $input = array() ): bool {
		$input = rest_sanitize_object( $input );

		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( ! empty( $input['roles'] ) && ! current_user_can( 'list_users' ) ) {
			return false;
		}

		$lookup_type = $this->get_lookup_type( $input );
		if ( self::LOOKUP_COLLECTION === $lookup_type ) {
			return true;
		}

		return $this->resolve_readable_user( $input, $lookup_type ) instanceof WP_User;
	}

	/**
	 * Executes the `core/users-query` ability.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return array<string, mixed>|WP_Error User data, paginated collection data, or a WP_Error on failure.
	 */
	public function execute_users_query( $input = array() ) {
		$input  = rest_sanitize_object( $input );
		$fields = $this->normalize_fields( $input );

		$lookup_type = $this->get_lookup_type( $input );
		if ( self::LOOKUP_COLLECTION !== $lookup_type ) {
			$user = $this->resolve_readable_user( $input, $lookup_type );
			if ( ! $user instanceof WP_User ) {
				return new WP_Error(
					'ability_invalid_permissions',
					__( 'The requested user cannot be read.' )
				);
			}

			return $this->format_user( $user, $fields );
		}

		$include        = ! empty( $input['include'] ) ? wp_parse_id_list( $input['include'] ) : array();
		$per_page       = $this->normalize_per_page( $input, $include );
		$can_list_users = current_user_can( 'list_users' );

		$query_args = array(
			'number' => $per_page,
			'paged'  => isset( $input['page'] ) ? max( 1, absint( $input['page'] ) ) : 1,
		);

		if ( array() !== $include ) {
			/*
			 * The include list filters the results but does not order them, as in the REST
			 * users controller. Results keep WP_User_Query's default order, by login.
			 */
			$query_args['include'] = $include;
		}

		if ( ! empty( $input['roles'] ) && $can_list_users ) {
			$query_args['role__in'] = $this->normalize_string_list( $input['roles'] );
		}

		$has_published_posts = $this->normalize_has_published_posts( $input );

		/*
		 * Callers who cannot list users only see public authors in a collection,
		 * matching core, so the filter is always applied for them. This intentionally
		 * excludes the caller's own account when they have no published posts. Self is
		 * read through a single-user lookup (like the REST `/users/me` endpoint) instead.
		 */
		if ( null !== $has_published_posts || ! $can_list_users ) {
			/*
			 * The post types are always resolved here rather than passed as `true`.
			 * WP_User_Query reads `true` as `get_post_types( array( 'public' => true ) )`,
			 * which is not the same as the publicly viewable set the rest of the ability
			 * uses. Resolving here also picks up post types registered or unregistered
			 * after the input schema was built.
			 */
			$public_post_types = $this->get_public_post_types();

			$has_published_posts = is_array( $has_published_posts )
				? array_values( array_intersect( $public_post_types, $has_published_posts ) )
				: $public_post_types;

			if ( array() === $has_published_posts ) {
				return array(
					'users'       => array(),
					'total'       => 0,
					'total_pages' => 0,
				);
			}

			$query_args['has_published_posts'] = $has_published_posts;
		}

		$query = new WP_User_Query( $query_args );

		$users = array();
		foreach ( $query->get_results() as $user ) {
			if ( ! $user instanceof WP_User ) {
				continue;
			}

			$users[] = $this->format_user( $user, $fields );
		}

		/*
		 * The rows and the totals come from the same query, so they agree. As in the REST
		 * users controller, rows are not filtered by site membership afterwards: on
		 * multisite, WP_User_Query already limits the query to members of the current site.
		 */
		$total_users = $query->get_total();

		return array(
			'users'       => $users,
			'total'       => $total_users,
			'total_pages' => (int) ceil( $total_users / $per_page ),
		);
	}

	/**
	 * Determines the single-user lookup type represented by the input.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return string The lookup type, or {@see self::LOOKUP_COLLECTION}.
	 */
	private function get_lookup_type( array $input ): string {
		foreach ( array_keys( self::LOOKUP_FIELDS ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				return $key;
			}
		}

		return self::LOOKUP_COLLECTION;
	}

	/**
	 * Resolves the target of a single-user lookup when the current user may read it.
	 *
	 * Shared by the permission and execute callbacks so the single-user
	 * authorization decision has exactly one implementation.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input       The ability input.
	 * @param string       $lookup_type The single-user lookup type.
	 * @return WP_User|null The readable user, or null when not found or not readable.
	 */
	private function resolve_readable_user( array $input, string $lookup_type ): ?WP_User {
		$value = $input[ $lookup_type ];

		// WP_Ability::check_permissions() does not validate the input, so the value may not be a scalar.
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		/*
		 * get_user_by() sanitizes a login itself, and matches a slug as given, like the REST
		 * users controller, so a stored nicename that sanitize_title() would change is found.
		 * The value is passed as a string because validation also accepts a float ID, like 5.0.
		 */
		$user = get_user_by( self::LOOKUP_FIELDS[ $lookup_type ], (string) $value );
		if ( ! $user instanceof WP_User ) {
			return null;
		}

		/*
		 * The current user can always read their own account, like the REST `/users/me`
		 * endpoint, even on a site they are not a member of.
		 */
		if ( $this->is_current_user( $user ) ) {
			return $user;
		}

		if ( is_multisite() && ! is_user_member_of_blog( $user->ID ) ) {
			return null;
		}

		return $this->can_read_user_for_lookup( $user, $lookup_type ) ? $user : null;
	}

	/**
	 * Checks whether a single-user lookup may return another user.
	 *
	 * Email and username are identifier-sensitive lookup modes and do not use the
	 * public-author fallback.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_User $user        User object.
	 * @param string  $lookup_type Lookup type.
	 * @return bool Whether the user can be read for that lookup type.
	 */
	private function can_read_user_for_lookup( WP_User $user, string $lookup_type ): bool {
		if ( current_user_can( 'edit_user', $user->ID ) || current_user_can( 'list_users' ) ) {
			return true;
		}

		if ( 'email' === $lookup_type || 'username' === $lookup_type ) {
			return false;
		}

		return $this->is_public_author( $user );
	}

	/**
	 * Checks whether the current user is the target user.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_User $user User object.
	 * @return bool Whether the current user is the target user.
	 */
	private function is_current_user( WP_User $user ): bool {
		return get_current_user_id() === $user->ID;
	}

	/**
	 * Checks whether the current user can see a post by this user in a publicly
	 * viewable post type.
	 *
	 * Published posts always count. Private posts also count for a caller who holds
	 * `read_private_posts` for the post type, because `count_user_posts()` defaults to
	 * `$public_only = false`. This matches how the REST users controller resolves a
	 * single user. Collection mode is filtered by `WP_User_Query`'s
	 * `has_published_posts`, which matches published posts only, so the two modes
	 * disagree about an author whose posts are all private.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_User $user User object.
	 * @return bool Whether the user is visible as an author to the current user.
	 */
	private function is_public_author( WP_User $user ): bool {
		$post_types = $this->get_public_post_types();
		if ( array() === $post_types ) {
			return false;
		}

		return count_user_posts( $user->ID, $post_types ) > 0;
	}

	/**
	 * Returns publicly viewable post types.
	 *
	 * Uses {@see is_post_type_viewable()} rather than the `public` registration
	 * argument, since `public` alone does not guarantee a post type is viewable
	 * on the front end. Deliberately resolved on every call rather than cached:
	 * post types can be unregistered or re-registered with different arguments
	 * between the ability being registered and the ability being used.
	 *
	 * @since 7.2.0
	 *
	 * @return string[] Publicly viewable post type names.
	 */
	private function get_public_post_types(): array {
		return array_values( array_filter( get_post_types(), 'is_post_type_viewable' ) );
	}

	/**
	 * Returns the requested fields, or a lean default set when none are given.
	 *
	 * An empty or absent `fields` value selects a lean set of common read fields.
	 * Otherwise the requested fields are returned. The input schema has already
	 * validated the names against the supported set before the ability executes.
	 *
	 * The `id` field is always included, matching the REST users controller
	 * where `id` is present in every context. This also guarantees the result
	 * is never empty, so it always serializes as a JSON object.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return string[] List of requested field names.
	 */
	private function normalize_fields( array $input ): array {
		$fields = isset( $input['fields'] ) ? $this->normalize_string_list( $input['fields'] ) : array();
		if ( array() === $fields ) {
			$fields = self::DEFAULT_FIELDS;
		}

		if ( ! in_array( 'id', $fields, true ) ) {
			array_unshift( $fields, 'id' );
		}

		return $fields;
	}

	/**
	 * Returns the user field definitions, keyed by field name in output order.
	 *
	 * This is the single source of truth for the ability's user fields: the output
	 * schema uses the definitions directly, while the input schema and field
	 * normalization use the keys. The field set is deliberately unconditional:
	 * the registered schemas are a registration-time snapshot, so conditional
	 * availability (such as `avatar_urls` honoring the `show_avatars` option) is
	 * enforced per call in {@see self::format_user()} instead of here, where the
	 * option could change between registration and use.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> User field definitions.
	 */
	private function get_user_properties(): array {
		return array(
			'id'              => array(
				'type'        => 'integer',
				'description' => __( 'The user ID.' ),
			),
			'name'            => array(
				'type'        => 'string',
				'description' => __( 'The display name for the user.' ),
			),
			'description'     => array(
				'type'        => 'string',
				'description' => __( 'Description of the user.' ),
			),
			'url'             => array(
				/*
				 * Unlike the REST users controller, `url` declares no `uri` format. It is
				 * empty for users without a website, and clients that check formats,
				 * such as the abilities JS client when it re-validates the output, would
				 * reject the empty string and fail the whole call.
				 */
				'type'        => 'string',
				'description' => __( 'URL of the user.' ),
			),
			'link'            => array(
				'type'        => 'string',
				'format'      => 'uri',
				'description' => __( 'Author archive URL for the user.' ),
			),
			'slug'            => array(
				'type'        => 'string',
				'description' => __( 'An alphanumeric identifier for the user.' ),
			),
			'avatar_urls'     => array(
				'type'                 => 'object',
				'description'          => __( 'Avatar URLs for the user, keyed by image size in pixels. A size is null when no avatar URL can be resolved for it. Present when the show_avatars option is enabled.' ),
				'additionalProperties' => array(
					'type'   => array( 'string', 'null' ),
					'format' => 'uri',
				),
			),
			'username'        => array(
				'type'        => 'string',
				'description' => __( 'Login name for the user. Present when the current user can view it.' ),
			),
			'email'           => array(
				'type'        => array( 'string', 'null' ),
				'format'      => 'email',
				'description' => __( 'The email address for the user. Null when the user has no stored address, or when the stored address is not a valid email. Present when the current user can view it.' ),
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
				'type'        => array( 'string', 'null' ),
				'format'      => 'date-time',
				'description' => __( 'Registration date for the user. Null when the stored date is not a valid date. Present when the current user can view it.' ),
			),
			'roles'           => array(
				'type'        => 'array',
				'description' => __( 'Roles assigned to the user. Present when the current user can view them.' ),
				/*
				 * Output roles are not pinned to an enum. The schema is a
				 * registration-time snapshot, but a role can be registered after
				 * registration and still be held by a returned user; a snapshot enum
				 * would reject that legitimate value during output validation and
				 * fail the whole call. This also matches the REST users controller,
				 * whose `roles` output items are plain strings.
				 */
				'items'       => array(
					'type' => 'string',
				),
			),
		);
	}

	/**
	 * Normalizes the requested per-page value to the supported bounds.
	 *
	 * An explicit `per_page` always wins. Otherwise an `include` request pages to the
	 * number of requested IDs, so a caller loading a known set of users receives all of
	 * them in one call rather than silently losing the ones past the default page size.
	 * The input schema caps `include` at {@see self::MAX_PER_PAGE} so it always fits.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input       The ability input.
	 * @param int[]        $include_ids Parsed included user IDs; empty when not requested.
	 * @return int The clamped per-page value.
	 */
	private function normalize_per_page( array $input, array $include_ids ): int {
		$default  = array() === $include_ids ? self::DEFAULT_PER_PAGE : count( $include_ids );
		$per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : $default;

		return max( 1, min( self::MAX_PER_PAGE, $per_page ) );
	}

	/**
	 * Normalizes a mixed value into a list of strings.
	 *
	 * Accepts arrays and CSV strings, which it parses with wp_parse_list(), as schema
	 * validation does. Schema validation accepts a CSV string for an array, and only the
	 * REST run controller converts input to the schema types, so callers that bypass it,
	 * such as a direct WP_Ability::execute() call, can pass one. Empty and duplicate
	 * items need no handling here, because validation has already rejected them.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $value Raw value.
	 * @return string[] Normalized strings.
	 */
	private function normalize_string_list( $value ): array {
		if ( ! is_array( $value ) && ! is_string( $value ) ) {
			return array();
		}

		return array_values( array_filter( wp_parse_list( $value ), 'is_string' ) );
	}

	/**
	 * Normalizes the `has_published_posts` collection input.
	 *
	 * Accepts the string and integer forms of `true` alongside the native boolean.
	 * Schema validation accepts them, and only the REST run controller converts input
	 * to the schema types, so callers that bypass it, such as a direct
	 * WP_Ability::execute() call, can pass one.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return bool|string[]|null Normalized query value, or null when omitted.
	 */
	private function normalize_has_published_posts( array $input ) {
		if ( ! array_key_exists( 'has_published_posts', $input ) ) {
			return null;
		}

		$value = $input['has_published_posts'];

		if ( rest_is_boolean( $value ) && rest_sanitize_boolean( $value ) ) {
			return true;
		}

		$post_types = $this->normalize_string_list( $value );

		return array() === $post_types ? null : $post_types;
	}

	/**
	 * Builds the input schema for the `core/users-query` ability.
	 *
	 * The ability has five mutually exclusive modes, modeled as a `oneOf` so invalid
	 * combinations are rejected rather than silently ignored:
	 *
	 *   - Get a single readable user by `id`.
	 *   - Get a single readable user by `email`.
	 *   - Get a single readable user by `username`.
	 *   - Get a single readable user by `slug`.
	 *   - Query a collection of readable users.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_users_query_input_schema(): array {
		/*
		 * Input enums intentionally reflect roles and post types available at
		 * ability registration time. This makes the schema a stable contract that
		 * developers can filter when registering the ability.
		 */
		$role_names        = array_keys( wp_roles()->roles );
		$public_post_types = $this->get_public_post_types();
		$fields            = array(
			'type'        => 'array',
			'uniqueItems' => true,
			'items'       => array(
				'type' => 'string',
				'enum' => array_keys( $this->get_user_properties() ),
			),
			'description' => __( 'Limit each returned user to these fields. If omitted or empty, a lean set of common read fields is returned. The `id` field is always included, and fields the current user cannot view are omitted rather than causing an error.' ),
		);
		$include           = array(
			'type'        => 'array',
			'uniqueItems' => true,
			'minItems'    => 1,
			'maxItems'    => self::MAX_PER_PAGE,
			'items'       => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
			'description' => __( 'Limit the query to these user IDs. The order of the IDs does not affect the order of the results. If `per_page` is omitted, the page size defaults to the number of included IDs, capped at the maximum. Collection results are limited to users the caller can read, which for callers without permission to list users means only public authors. To read your own account, use a single-user lookup by ID.' ),
		);

		return array(
			'type'    => 'object',
			'default' => (object) array(),
			'oneOf'   => array(
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
								'enum' => $role_names,
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
										'enum' => $public_post_types,
									),
								),
							),
							'description' => __( 'Limit results to users with published posts. Use true for all publicly viewable post types, or provide post type names.' ),
						),
						'include'             => $include,
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
	 * Builds the output schema for the `core/users-query` ability.
	 *
	 * No user field is marked required because the `fields` input lets the caller
	 * request any subset, and restricted fields are omitted when unavailable.
	 * Single-user mode returns the user object directly, while collection mode returns
	 * a paginated wrapper.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> The output JSON Schema.
	 */
	private function get_users_query_output_schema(): array {
		$user_schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => $this->get_user_properties(),
		);

		$collection_schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'users', 'total', 'total_pages' ),
			'properties'           => array(
				'users'       => array(
					'type'        => 'array',
					'description' => __( 'The readable users matching the collection request.' ),
					'items'       => $user_schema,
				),
				'total'       => array(
					'type'        => 'integer',
					'description' => __( 'Total number of users matching the query, across all pages.' ),
				),
				'total_pages' => array(
					'type'        => 'integer',
					'description' => __( 'Total number of result pages available for the query.' ),
				),
			),
		);

		return array(
			'type'  => 'object',
			'oneOf' => array(
				$user_schema,
				$collection_schema,
			),
		);
	}

	/**
	 * Formats a user into the ability output shape.
	 *
	 * Only the requested fields the current user can see are included, except
	 * `id`, which {@see self::normalize_fields()} always requests.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_User  $user   The user object.
	 * @param string[] $fields The requested field names.
	 * @return array<string, mixed> The formatted user data.
	 */
	private function format_user( WP_User $user, array $fields ): array {
		$fields_requested = static function ( string $field ) use ( $fields ): bool {
			return in_array( $field, $fields, true );
		};

		$can_view_sensitive = $this->is_current_user( $user ) || current_user_can( 'edit_user', $user->ID );

		$data = array();

		if ( $fields_requested( 'id' ) ) {
			$data['id'] = $user->ID;
		}
		if ( $fields_requested( 'name' ) ) {
			$data['name'] = (string) $user->display_name;
		}
		if ( $fields_requested( 'description' ) ) {
			$data['description'] = (string) $user->description;
		}
		if ( $fields_requested( 'url' ) ) {
			$data['url'] = (string) $user->user_url;
		}
		if ( $fields_requested( 'link' ) ) {
			$data['link'] = (string) get_author_posts_url( $user->ID, $user->user_nicename );
		}
		if ( $fields_requested( 'slug' ) ) {
			$data['slug'] = (string) $user->user_nicename;
		}
		/*
		 * The schemas always declare avatar_urls; availability is enforced here,
		 * since the option can change after the schemas are registered.
		 */
		if ( $fields_requested( 'avatar_urls' ) && get_option( 'show_avatars' ) ) {
			$data['avatar_urls'] = array_map(
				static function ( $url ) {
					return is_string( $url ) ? $url : null;
				},
				rest_get_avatar_urls( $user )
			);
		}

		if ( $can_view_sensitive ) {
			if ( $fields_requested( 'username' ) ) {
				$data['username'] = (string) $user->user_login;
			}
			if ( $fields_requested( 'email' ) ) {
				$data['email'] = is_email( $user->user_email ) ? (string) $user->user_email : null;
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
				/*
				 * The zero date, the column default, formats with a negative year that the
				 * `date-time` format rejects, so it is reported as null, like an unusable email.
				 */
				$registered_timestamp    = strtotime( $user->user_registered );
				$registered_date         = false !== $registered_timestamp ? gmdate( 'c', $registered_timestamp ) : '';
				$data['registered_date'] = rest_parse_date( $registered_date ) ? $registered_date : null;
			}
		}

		/*
		 * Roles reveal a user's privilege level, so they are gated like the other
		 * sensitive fields: visible only for the current user or a user the caller
		 * can edit. `list_users` alone (which grants no edit rights) is not enough,
		 * matching the REST users controller, where `roles` is an edit-context
		 * field and rows the caller cannot edit are dropped from collections.
		 */
		if ( $fields_requested( 'roles' ) && $can_view_sensitive ) {
			$data['roles'] = array_values( $user->roles );
		}

		return $data;
	}
}
