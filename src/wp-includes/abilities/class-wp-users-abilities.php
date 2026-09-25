<?php
/**
 * Registers core user abilities.
 *
 * @package WordPress
 * @subpackage Abilities_API
 * @since 7.1.0
 *
 * @access private
 */

declare( strict_types = 1 );

/**
 * Registers the read-only `core/read-users` ability, which retrieves one or more
 * readable WordPress users. Supports fetching a single readable user by ID,
 * email, username, or slug, or querying a paginated collection optionally
 * filtered by roles, published-post authorship, or included IDs. Field-level
 * access is enforced per user by omitting fields the current user cannot view.
 *
 * @since 7.1.0
 * @access private
 */
final class WP_Users_Abilities {

	/**
	 * The ability category used for user abilities.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	private const CATEGORY = 'user';

	/**
	 * Default number of users returned per page in collection mode.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	private const DEFAULT_PER_PAGE = 10;

	/**
	 * Maximum number of users returned per page in collection mode.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Lookup type returned for collection requests.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	private const LOOKUP_COLLECTION = 'collection';

	/**
	 * Default fields returned when the caller does not request a field subset.
	 *
	 * @since 7.1.0
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
	 * @since 7.1.0
	 */
	public function register(): void {
		$this->register_get_users();
	}

	/**
	 * Registers the read-only `core/read-users` ability.
	 *
	 * @since 7.1.0
	 */
	private function register_get_users(): void {
		wp_register_ability(
			'core/read-users',
			array(
				'label'               => __( 'Read Users' ),
				'description'         => __( 'Retrieves one or more readable WordPress users. Fetch a single readable user by ID, email, username, or slug, or query a paginated collection optionally filtered by roles, published-post authorship, or included IDs.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_users_input_schema(),
				'output_schema'       => $this->get_users_output_schema(),
				'execute_callback'    => array( $this, 'execute_get_users' ),
				'permission_callback' => array( $this, 'check_permission' ),
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
	 * Permission callback for the `core/read-users` ability.
	 *
	 * Performs request-level checks. Single-user requests are checked against
	 * the target user, while collection requests rely on query arguments in
	 * {@see self::execute_get_users()} for row-level access.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	public function check_permission( $input = array() ): bool {
		$input = $this->to_input_array( $input );

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
	 * Executes the `core/read-users` ability.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return array<string, mixed>|\stdClass|\WP_Error User data, paginated collection data, or a WP_Error on failure.
	 */
	public function execute_get_users( $input = array() ) {
		$input  = $this->to_input_array( $input );
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

		$per_page = $this->normalize_per_page( $input );
		$page     = isset( $input['page'] ) ? max( 1, $this->input_int( $input['page'] ) ) : 1;

		$query_args = array(
			'number'      => $per_page,
			'offset'      => ( $page - 1 ) * $per_page,
			'count_total' => true,
		);

		$include = $this->normalize_include( $input );
		if ( array() !== $include ) {
			// The include order is not applied as `orderby`. Keeping the default
			// ordering lets WP_User_Query share cached results with other queries.
			$query_args['include'] = $include;
		}

		if ( ! empty( $input['roles'] ) && current_user_can( 'list_users' ) ) {
			$query_args['role__in'] = $this->normalize_string_list( $input['roles'] );
		}

		$has_published_posts = $this->normalize_has_published_posts( $input );

		// Callers who cannot list users only see public authors in a collection,
		// matching core, so the filter is always applied for them. This intentionally
		// excludes the caller's own account when they have no published posts. Self is
		// read through a single-user lookup (like the REST `/users/me` endpoint) instead.
		$requires_published_posts = ! current_user_can( 'list_users' );

		if ( null !== $has_published_posts || $requires_published_posts ) {
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

		// `users` and `total`/`total_pages` all derive from the same WP_User_Query,
		// so the row count and the reported totals stay in agreement. Collections
		// are not post-filtered by site membership, matching the REST users
		// controller, whose collection endpoint applies no per-row membership check
		// and reports `get_total()` directly. On multisite the collection is still
		// scoped to the current site: WP_User_Query adds a capabilities meta clause
		// restricting results to members of the queried blog whenever `blog_id`
		// (defaulted to the current blog) is set, even for a bare query with no
		// roles/has_published_posts. Callers who cannot list users are additionally
		// narrowed by the forced `has_published_posts`, which joins the current
		// blog's posts table. Single-user lookups remain site-scoped via
		// {@see self::is_user_member_of_site()}, matching the controller's
		// single-user membership check.
		$total_users = (int) $query->get_total();

		return array(
			'users'       => $users,
			'total'       => $total_users,
			'total_pages' => (int) ceil( $total_users / $per_page ),
		);
	}

	/**
	 * Casts raw ability input to an array.
	 *
	 * Schema validation accepts object input (`rest_is_object()` allows a
	 * `stdClass`, and the input schema's own `default` is one), so it must be
	 * treated as equivalent to its array form rather than discarded. Any other
	 * non-array input is replaced with an empty array.
	 *
	 * The Abilities API validates input against the schema but does not coerce
	 * it, and REST `GET` requests (the only method for read-only abilities)
	 * deliver every scalar as a string. Each normalizer below therefore accepts
	 * the string forms validation accepted (`'true'` for `true`, CSV strings
	 * for arrays, numeric strings for integers).
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $input The raw ability input.
	 * @return array<mixed> The input as an array.
	 */
	private function to_input_array( $input ): array {
		if ( $input instanceof stdClass ) {
			$input = (array) $input;
		}

		return is_array( $input ) ? $input : array();
	}

	/**
	 * Casts a raw input value to a non-negative integer.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $value The raw input value.
	 * @return int The value as a non-negative integer, or 0 when not scalar.
	 */
	private function input_int( $value ): int {
		return is_scalar( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Determines the single-user lookup type represented by the input.
	 *
	 * @since 7.1.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return string The lookup type, or {@see self::LOOKUP_COLLECTION}.
	 */
	private function get_lookup_type( array $input ): string {
		foreach ( array( 'id', 'email', 'username', 'slug' ) as $key ) {
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
	 * @since 7.1.0
	 *
	 * @param array<mixed> $input       The ability input.
	 * @param string       $lookup_type The single-user lookup type.
	 * @return \WP_User|null The readable user, or null when not found or not readable.
	 */
	private function resolve_readable_user( array $input, string $lookup_type ): ?WP_User {
		$user = $this->find_user( $input );
		if ( ! $user instanceof WP_User || ! $this->is_user_member_of_site( $user ) ) {
			return null;
		}

		return $this->can_read_user_for_lookup( $user, $lookup_type ) ? $user : null;
	}

	/**
	 * Finds a user by one of the supported unique input identifiers.
	 *
	 * @since 7.1.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return \WP_User|null User object, or null when not found.
	 */
	private function find_user( array $input ): ?WP_User {
		if ( array_key_exists( 'id', $input ) ) {
			$user = get_userdata( $this->input_int( $input['id'] ) );
			return $user instanceof WP_User ? $user : null;
		}

		if ( array_key_exists( 'email', $input ) ) {
			if ( ! is_string( $input['email'] ) ) {
				return null;
			}

			$user = get_user_by( 'email', sanitize_email( $input['email'] ) );
			return $user instanceof WP_User ? $user : null;
		}

		if ( array_key_exists( 'username', $input ) ) {
			if ( ! is_string( $input['username'] ) ) {
				return null;
			}

			$user = get_user_by( 'login', sanitize_user( $input['username'] ) );
			return $user instanceof WP_User ? $user : null;
		}

		if ( array_key_exists( 'slug', $input ) ) {
			if ( ! is_string( $input['slug'] ) ) {
				return null;
			}

			// Query the raw nicename, matching the REST users controller. Applying
			// sanitize_title() here would miss users whose stored user_nicename is
			// not a sanitize_title() fixed point (e.g. set via the pre_user_nicename
			// filter or an import).
			$user = get_user_by( 'slug', $input['slug'] );
			return $user instanceof WP_User ? $user : null;
		}

		return null;
	}

	/**
	 * Checks whether a user belongs to the current site.
	 *
	 * @since 7.1.0
	 *
	 * @param \WP_User $user User object.
	 * @return bool Whether the user belongs to the current site.
	 */
	private function is_user_member_of_site( WP_User $user ): bool {
		return ! is_multisite() || is_user_member_of_blog( (int) $user->ID );
	}

	/**
	 * Checks whether a single-user lookup may return the target user.
	 *
	 * Email and username are identifier-sensitive lookup modes and do not use the
	 * public-author fallback.
	 *
	 * @since 7.1.0
	 *
	 * @param \WP_User $user        User object.
	 * @param string   $lookup_type Lookup type.
	 * @return bool Whether the user can be read for that lookup type.
	 */
	private function can_read_user_for_lookup( WP_User $user, string $lookup_type ): bool {
		if ( $this->is_current_user( $user ) ) {
			return true;
		}

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
	 * @since 7.1.0
	 *
	 * @param \WP_User $user User object.
	 * @return bool Whether the current user is the target user.
	 */
	private function is_current_user( WP_User $user ): bool {
		return get_current_user_id() === (int) $user->ID;
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
	 * @since 7.1.0
	 *
	 * @param \WP_User $user User object.
	 * @return bool Whether the user is visible as an author to the current user.
	 */
	private function is_public_author( WP_User $user ): bool {
		$post_types = $this->get_public_post_types();
		if ( array() === $post_types ) {
			return false;
		}

		return count_user_posts( (int) $user->ID, $post_types ) > 0;
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
	 * @since 7.1.0
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
	 * Otherwise the requested fields are returned; REST `GET` requests may
	 * deliver the list as a CSV string. The input schema has already validated
	 * the names against the supported set before the ability executes.
	 *
	 * The `id` field is always included, matching the REST users controller
	 * where `id` is present in every context. This also guarantees the result
	 * is never empty, so it always serializes as a JSON object.
	 *
	 * @since 7.1.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return string[] List of requested field names.
	 */
	private function normalize_fields( array $input ): array {
		$fields = isset( $input['fields'] ) ? $this->normalize_string_list( $input['fields'] ) : array();
		if ( array() === $fields ) {
			$fields = $this->get_default_fields();
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
	 * option could change between registration and use. Resolved on every call
	 * rather than cached: it is the single source of truth for the field set and
	 * inexpensive to rebuild.
	 *
	 * @since 7.1.0
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
			'avatar_urls'     => array(
				'type'                 => 'object',
				'description'          => __( 'Avatar URLs for the user, keyed by image size in pixels. A size is null when no avatar URL can be resolved for it. Present when the show_avatars option is enabled.' ),
				'additionalProperties' => array(
					'type' => array( 'string', 'null' ),
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
				'type'        => 'string',
				'format'      => 'date-time',
				'description' => __( 'Registration date for the user. Present when the current user can view it.' ),
			),
			'roles'           => array(
				'type'        => 'array',
				'description' => __( 'Roles assigned to the user. Present when the current user can view them.' ),
				// Output roles are not pinned to an enum. The schema is a
				// registration-time snapshot, but a role can be registered after
				// registration and still be held by a returned user; a snapshot enum
				// would reject that legitimate value during output validation and
				// fail the whole call. This also matches the REST users controller,
				// whose `roles` output items are plain strings.
				'items'       => array(
					'type' => 'string',
				),
			),
		);
	}

	/**
	 * Returns the default field list in output order.
	 *
	 * @since 7.1.0
	 *
	 * @return string[] Default field names.
	 */
	private function get_default_fields(): array {
		return array_values( array_intersect( array_keys( $this->get_user_properties() ), self::DEFAULT_FIELDS ) );
	}

	/**
	 * Returns registered role names.
	 *
	 * Deliberately resolved on every call rather than cached, since roles can be
	 * registered or unregistered at runtime.
	 *
	 * @since 7.1.0
	 *
	 * @return string[] Role names.
	 */
	private function get_role_names(): array {
		return array_keys( wp_roles()->roles );
	}

	/**
	 * Normalizes the requested per-page value to the supported bounds.
	 *
	 * @since 7.1.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return int The clamped per-page value.
	 */
	private function normalize_per_page( array $input ): int {
		$per_page = isset( $input['per_page'] ) ? $this->input_int( $input['per_page'] ) : self::DEFAULT_PER_PAGE;

		return max( 1, min( self::MAX_PER_PAGE, $per_page ) );
	}

	/**
	 * Normalizes a mixed value into a list of non-empty strings.
	 *
	 * Accepts arrays and CSV strings, since REST `GET` requests deliver list
	 * input as strings that schema validation coerces only for the check.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $value Raw value.
	 * @return string[] Normalized strings.
	 */
	private function normalize_string_list( $value ): array {
		if ( is_string( $value ) ) {
			$value = wp_parse_list( $value );
		}

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
	 * Normalizes collection-mode included user IDs.
	 *
	 * Accepts arrays and CSV strings via {@see wp_parse_id_list()}, which also
	 * deduplicates IDs that only differ as strings (e.g. `'1'` and `'01'`).
	 *
	 * @since 7.1.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return int[] User IDs.
	 */
	private function normalize_include( array $input ): array {
		if ( empty( $input['include'] ) ) {
			return array();
		}

		$include = $input['include'];
		if ( is_scalar( $include ) ) {
			$include = (string) $include;
		} elseif ( ! is_array( $include ) ) {
			return array();
		}

		return array_values( array_filter( wp_parse_id_list( $include ) ) );
	}

	/**
	 * Normalizes the `has_published_posts` collection input.
	 *
	 * Accepts the string and integer forms of `true` that schema validation
	 * accepts for REST `GET` input, alongside the native boolean.
	 *
	 * @since 7.1.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return bool|string[]|null Normalized query value, or null when omitted.
	 */
	private function normalize_has_published_posts( array $input ) {
		if ( ! array_key_exists( 'has_published_posts', $input ) ) {
			return null;
		}

		$value = $input['has_published_posts'];

		if ( true === $value || 1 === $value
			|| ( is_string( $value ) && in_array( strtolower( $value ), array( 'true', '1' ), true ) )
		) {
			return true;
		}

		$post_types = $this->normalize_string_list( $value );

		return array() === $post_types ? null : $post_types;
	}

	/**
	 * Builds the input schema for the `core/read-users` ability.
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
	 * @since 7.1.0
	 *
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_users_input_schema(): array {
		/*
		 * Input enums intentionally reflect roles and post types available at
		 * ability registration time. This makes the schema a stable contract that
		 * developers can filter when registering the ability.
		 */
		$role_names        = $this->get_role_names();
		$public_post_types = $this->get_public_post_types();
		$fields            = array(
			'type'        => 'array',
			'uniqueItems' => true,
			'minItems'    => 1,
			'items'       => array(
				'type' => 'string',
				'enum' => array_keys( $this->get_user_properties() ),
			),
			'description' => __( 'Limit each returned user to these fields. If omitted, a lean set of common read fields is returned.' ),
		);
		$include           = array(
			'type'        => 'array',
			'uniqueItems' => true,
			'minItems'    => 1,
			'items'       => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
			'description' => __( 'Limit the query to these user IDs. Collection results are limited to users the caller can read, which for callers without permission to list users means only public authors. To read your own account, use a single-user lookup by ID.' ),
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
	 * Builds the output schema for the `core/read-users` ability.
	 *
	 * No user field is marked required because the `fields` input lets the caller
	 * request any subset, and restricted fields are omitted when unavailable.
	 * Single-user mode returns the user object directly, while collection mode returns
	 * a paginated wrapper.
	 *
	 * @since 7.1.0
	 *
	 * @return array<string, mixed> The output JSON Schema.
	 */
	private function get_users_output_schema(): array {
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
	 * @since 7.1.0
	 *
	 * @param \WP_User $user   The user object.
	 * @param string[] $fields The requested field names.
	 * @return array<string, mixed>|\stdClass The formatted user data. An empty
	 *                                        result is returned as an object so
	 *                                        it serializes as `{}` rather than
	 *                                        `[]`; unreachable while `id` is
	 *                                        ungated, since REST post-processing
	 *                                        (`_fields`) cannot handle a
	 *                                        top-level object response.
	 */
	private function format_user( WP_User $user, array $fields ) {
		$fields_requested = static function ( string $field ) use ( $fields ): bool {
			return in_array( $field, $fields, true );
		};

		$user_id            = (int) $user->ID;
		$can_view_sensitive = $this->is_current_user( $user ) || current_user_can( 'edit_user', $user_id );

		$data = array();

		if ( $fields_requested( 'id' ) ) {
			$data['id'] = $user_id;
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
			$data['link'] = (string) get_author_posts_url( $user_id, $user->user_nicename );
		}
		if ( $fields_requested( 'slug' ) ) {
			$data['slug'] = (string) $user->user_nicename;
		}
		// The schemas always declare avatar_urls; availability is enforced here,
		// since the option can change after the schemas are registered.
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
				$registered_timestamp = strtotime( $user->user_registered );
				if ( false !== $registered_timestamp ) {
					$data['registered_date'] = gmdate( 'c', $registered_timestamp );
				}
			}
		}

		// Roles reveal a user's privilege level, so they are gated like the other
		// sensitive fields: visible only for the current user or a user the caller
		// can edit. `list_users` alone (which grants no edit rights) is not enough,
		// matching the REST users controller, where `roles` is an edit-context
		// field and rows the caller cannot edit are dropped from collections.
		if ( $fields_requested( 'roles' ) && $can_view_sensitive ) {
			$data['roles'] = $this->normalize_string_list( $user->roles );
		}

		// An empty result must serialize as a JSON object, not an empty array.
		return array() === $data ? (object) $data : $data;
	}
}
