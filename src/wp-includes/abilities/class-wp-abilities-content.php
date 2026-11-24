<?php
/**
 * Abilities API: WP_Abilities_Content class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.2.0
 */

declare( strict_types = 1 );

/**
 * Core class used to register content-related abilities.
 *
 * Registers the read-only `core/content-query` ability, which queries the readable posts of a
 * post type exposed to abilities via `show_in_abilities`, filtered by any of post IDs, slugs,
 * status, author, and parent. Raw fields are only returned for posts the current user can
 * edit, and the author's name and slug only for authors the current user may see.
 *
 * Also registers `core/content-create`, `core/content-update`, and `core/content-delete`,
 * which create, update, and trash or delete one post of such a post type, and return the
 * written post in the same shape as a `core/content-query` item.
 *
 * Unlike the core abilities registered directly in wp_register_core_abilities() as
 * self-contained closures, the four content abilities live in one dedicated class because
 * their callbacks and schemas share helpers as private methods, such as the read check,
 * the field projection, and the input parsing: the permission and execute callbacks read
 * the post type and the filters through the same code, and the input schema, output schema,
 * and field projection are built from the same field definitions.
 *
 * Only register() is public. The ability callbacks are closures that call private
 * methods, so callers go through the Abilities API, such as
 * `wp_get_ability( 'core/content-query' )->execute()`, which validates the input and
 * checks permissions before running them.
 *
 * @since 7.2.0
 *
 * @access private
 */
final class WP_Abilities_Content {

	/**
	 * The ability category used for content abilities.
	 *
	 * @since 7.2.0
	 * @var string
	 */
	private const CATEGORY = 'content';

	/**
	 * Default number of posts returned per page, unless `where` lists posts by ID or slug.
	 *
	 * @since 7.2.0
	 * @var int
	 */
	private const DEFAULT_PER_PAGE = 10;

	/**
	 * Maximum number of posts returned per page.
	 *
	 * The `ids` and `slugs` lists in `where` each hold at most this many values, so the posts
	 * that `ids` lists fit on one page.
	 *
	 * @since 7.2.0
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Field paths whose output may read post meta or terms.
	 *
	 * Requests that include any of these, directly or through their whole object, prime
	 * the post meta and term caches for the page. Rendered excerpts and content may read
	 * either, and permalinks read terms when the permalink structure contains `%category%`.
	 * Other fields, such as the rendered title, do not need that cache priming.
	 *
	 * @since 7.2.0
	 * @var list<string>
	 */
	private const CACHE_PRIMING_FIELDS = array( 'link', 'excerpt.rendered', 'content.rendered' );

	/**
	 * Default field paths returned when the caller does not request a field subset.
	 *
	 * @since 7.2.0
	 * @var list<string>
	 */
	private const DEFAULT_FIELDS = array( 'id', 'type', 'status', 'date', 'slug', 'title.rendered' );

	/**
	 * Globals that rendering a post changes: the global post and the globals that
	 * setup_postdata() populates.
	 *
	 * @since 7.2.0
	 * @var list<string>
	 */
	private const LOOP_GLOBALS = array( 'post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages' );

	/**
	 * Whether each author seen in the current call has posts the current user may read.
	 *
	 * @since 7.2.0
	 * @var array<int, bool>
	 */
	private $visible_authors = array();

	/**
	 * Registers all content abilities.
	 *
	 * Must run on the `wp_abilities_api_init` hook.
	 *
	 * @since 7.2.0
	 * @since 7.3.0 Added the `core/content-create`, `core/content-update`, and `core/content-delete` abilities.
	 */
	public function register(): void {
		/*
		 * Post types must be registered with `show_in_abilities` before the abilities are
		 * registered so they are included in the input schemas. Without one, no content
		 * ability is registered.
		 */
		$post_types = array_values( get_post_types( array( 'show_in_abilities' => true ) ) );
		if ( empty( $post_types ) ) {
			return;
		}

		// The statuses that can be set: the ones that are not internal.
		$statuses = array_values( get_post_stati( array( 'internal' => false ) ) );

		$this->register_content_query( $post_types, $statuses );
		$this->register_content_write_abilities( $post_types, $statuses );
	}

	/**
	 * Registers the read-only `core/content-query` ability.
	 *
	 * @since 7.2.0
	 * @since 7.3.0 Added the `$post_types` and `$statuses` parameters, and support for `trash`
	 *              in the `where.status` filter.
	 *
	 * @param list<string> $post_types Exposed post type names.
	 * @param list<string> $statuses   Post status slugs that are not internal.
	 */
	private function register_content_query( array $post_types, array $statuses ): void {
		/*
		 * Of the internal statuses, only `trash` can be requested, and only by name: it is never
		 * one of the statuses a query covers by default. The others, such as `inherit`, can never
		 * be queried, so the posts of post types that rely on them (attachments) cannot be
		 * queried, not even by ID. Revisit if such a post type is ever exposed via
		 * `show_in_abilities`.
		 */
		$statuses[] = 'trash';

		wp_register_ability(
			'core/content-query',
			array(
				'label'               => __( 'Query Content' ),
				'description'         => __( 'Retrieves a paginated list of the readable posts of a post type exposed to abilities, filtered by any of post IDs, slugs, status, author, and parent. Requires an authenticated user. Filters are exact-match only; the ability does not perform full-text search.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_content_query_input_schema( $post_types, $statuses ),
				'output_schema'       => $this->get_content_query_output_schema(),
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_content_query( $input );
				},
				'permission_callback' => function ( $input = array() ): bool {
					return $this->check_query_permission( $input );
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

	/**
	 * Registers the `core/content-create`, `core/content-update`, and `core/content-delete` abilities.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $post_types Exposed post type names.
	 * @param list<string> $statuses   Post status slugs that can be set.
	 */
	private function register_content_write_abilities( array $post_types, array $statuses ): void {
		wp_register_ability(
			'core/content-create',
			array(
				'label'               => __( 'Create Content' ),
				'description'         => __( 'Creates a post of a post type exposed to abilities and returns it. Requires an authenticated user who can create posts of that type. The post is saved as a draft unless another status is given.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_content_create_input_schema( $post_types, $statuses ),
				'output_schema'       => $this->get_content_output_schema(),
				'execute_callback'    => function ( $input ) {
					return $this->execute_content_create( $input );
				},
				'permission_callback' => function ( $input ): bool {
					return $this->check_create_permission( $input );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'public'      => true,
				),
			)
		);

		wp_register_ability(
			'core/content-update',
			array(
				'label'               => __( 'Update Content' ),
				'description'         => __( 'Updates the given attributes of a post of a post type exposed to abilities and returns it. Attributes that are left out keep their stored values. Requires an authenticated user who can edit the post.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_content_update_input_schema( $post_types, $statuses ),
				'output_schema'       => $this->get_content_output_schema(),
				'execute_callback'    => function ( $input ) {
					return $this->execute_content_update( $input );
				},
				'permission_callback' => function ( $input ): bool {
					return $this->check_post_permission( $input, 'edit_post' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'public'      => true,
				),
			)
		);

		wp_register_ability(
			'core/content-delete',
			array(
				'label'               => __( 'Delete Content' ),
				'description'         => __( 'Moves a post of a post type exposed to abilities to the Trash, or deletes it permanently when `permanent` is true. Requires an authenticated user who can delete the post.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_content_delete_input_schema( $post_types ),
				'output_schema'       => $this->get_content_delete_output_schema(),
				'execute_callback'    => function ( $input ) {
					return $this->execute_content_delete( $input );
				},
				'permission_callback' => function ( $input ): bool {
					return $this->check_post_permission( $input, 'delete_post' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
					'public'      => true,
				),
			)
		);
	}

	/**
	 * Permission callback for the `core/content-query` ability.
	 *
	 * The request is only gated as a whole here: it needs a logged-in user, a post type
	 * exposed to abilities, and the capabilities to query the explicitly requested statuses.
	 * It reads no post. {@see self::execute_content_query()} enforces row-level read
	 * permissions, since individual rows are unknown until the query runs.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	private function check_query_permission( $input = array() ): bool {
		$input = rest_sanitize_object( $input );

		if ( ! is_user_logged_in() ) {
			return false;
		}

		/*
		 * A `where` that is not an object is let through, so that the execute callback can
		 * report it as an invalid filter.
		 */
		$where = $this->get_input_object( $input, 'where' );
		if ( null === $where ) {
			return true;
		}

		// A query requires an exposed post type.
		$post_type_object = $this->get_exposed_post_type( $where['type'] ?? null );
		if ( ! $post_type_object ) {
			return false;
		}

		return $this->can_query_statuses( $where, $post_type_object );
	}

	/**
	 * Permission callback for the `core/content-create` ability.
	 *
	 * A coarse gate: a logged-in user, a `data.type` that is exposed to abilities, and the
	 * capability to create posts of that type. {@see self::execute_content_create()} checks
	 * the capability again and makes every other decision.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $input The unprepared ability input.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	private function check_create_permission( $input ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$data             = $this->get_input_object( rest_sanitize_object( $input ), 'data' ) ?? array();
		$post_type_object = $this->get_exposed_post_type( $data['type'] ?? null );

		return null !== $post_type_object && current_user_can( $post_type_object->cap->create_posts );
	}

	/**
	 * Permission callback for the `core/content-update` and `core/content-delete` abilities.
	 *
	 * Requires a logged-in user, a `where.type` that is exposed to abilities, and the given
	 * capability on the post that `where` names. When `where` names no post of that type,
	 * such as a missing ID or the ID of a post of another type, the request is let through
	 * so that the execute callback can report it as not found. The execute callbacks check
	 * the capability again and make every other decision.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $input      The unprepared ability input.
	 * @param string $capability The capability required on the post: `edit_post` or `delete_post`.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	private function check_post_permission( $input, string $capability ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$where = $this->get_input_object( rest_sanitize_object( $input ), 'where' ) ?? array();
		if ( ! $this->get_exposed_post_type( $where['type'] ?? null ) ) {
			return false;
		}

		$post = $this->get_requested_post( $where );

		return ! $post || current_user_can( $capability, $post->ID );
	}

	/**
	 * Parses a raw input value into an integer of at least a minimum, or null when invalid.
	 *
	 * Accepts the whole numbers that the integer schema accepts: native integers, floats such
	 * as the 2.0 that JSON encoders produce, and integer strings such as "2", "2.0", or "+2".
	 * WP_Ability::execute() converts input to the schema types, but
	 * WP_Ability::check_permissions() does not, so a permission callback can receive any of them.
	 * Anything else, such as a fraction, is rejected rather than coerced: a malformed ID would
	 * otherwise resolve to another post, and a malformed `parent` filter would be read as 0,
	 * which asks for top-level posts.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $value The raw input value.
	 * @param int   $min   The smallest acceptable value.
	 * @return int|null The parsed integer, or null when the value is not an integer >= $min.
	 */
	private function parse_filter_int( $value, int $min ): ?int {
		if ( is_int( $value ) ) {
			return $value >= $min ? $value : null;
		}

		if ( is_string( $value ) && '' !== $value && ctype_digit( $value ) ) {
			$int = (int) $value;

			return $int >= $min ? $int : null;
		}

		if ( ! rest_is_integer( $value ) ) {
			return null;
		}

		/*
		 * Other whole numbers are read as floats, which hold every integer only up to 2 ** 53.
		 * Past that, the value may not be the one that was sent, and casting a float beyond the
		 * integer range wraps it to an arbitrary integer, such as the ID of another post.
		 */
		$number = (float) $value;

		return $number >= $min && $number <= 2 ** 53 ? (int) $number : null;
	}

	/**
	 * Parses a raw list input into a list of strings.
	 *
	 * Like schema validation, it also accepts a scalar or a comma-separated string, and
	 * parses it the same way, with wp_parse_list(). A number, such as a slug sent as 2024,
	 * is read as its string form.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input The ability input, or one of its objects, such as `where`.
	 * @param string       $key   The input key holding the list.
	 * @return list<string> The parsed string values; empty when absent or unparseable.
	 */
	private function parse_list_input( array $input, string $key ): array {
		$value = $input[ $key ] ?? null;
		if ( is_scalar( $value ) ) {
			$value = (string) $value;
		} elseif ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_filter( wp_parse_list( $value ), 'is_string' ) );
	}

	/**
	 * Checks whether the current user may query the explicitly requested statuses.
	 *
	 * The statuses a query covers by default, see {@see self::normalize_statuses()}, only
	 * hold statuses the current user may query, so they need no check.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $where            The `where` filters.
	 * @param WP_Post_Type $post_type_object The post type object.
	 * @return bool True if the requested statuses may be queried.
	 */
	private function can_query_statuses( array $where, WP_Post_Type $post_type_object ): bool {
		return array_all(
			$this->get_requested_statuses( $where ),
			fn( $status ) => $this->can_query_status( $status, $post_type_object )
		);
	}

	/**
	 * Checks whether the current user may query posts in a status.
	 *
	 * Like the REST posts controller, any user may query `publish`, while other statuses, such
	 * as `draft`, require edit access. Any user may also query the other publicly viewable
	 * statuses, and `private`, since the query only covers the private posts the user can
	 * read, see {@see self::execute_content_query()}.
	 *
	 * @since 7.2.0
	 *
	 * @param string       $status           The post status slug.
	 * @param WP_Post_Type $post_type_object The post type object.
	 * @return bool True if the status may be queried.
	 */
	private function can_query_status( string $status, WP_Post_Type $post_type_object ): bool {
		return in_array( $status, array( 'publish', 'private' ), true ) || is_post_status_viewable( $status ) || current_user_can( $post_type_object->cap->edit_posts );
	}

	/**
	 * Checks if a post can be read by the current user.
	 *
	 * Mirrors the REST posts controller's read permission, while keeping this ability
	 * authenticated-only via {@see self::check_query_permission()}.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Post          $post             Post object.
	 * @param array<int, true> $checked_post_ids Post IDs already checked while walking inherited parents.
	 * @return bool Whether the post can be read.
	 */
	private function check_read_permission( WP_Post $post, array $checked_post_ids = array() ): bool {
		if ( isset( $checked_post_ids[ $post->ID ] ) ) {
			return false;
		}

		$checked_post_ids[ $post->ID ] = true;

		if ( ! $this->get_exposed_post_type( $post->post_type ) ) {
			return false;
		}

		/*
		 * Treat publicly viewable posts as readable. This checks both the post type
		 * and post status using Core's viewability helpers, which is stricter than
		 * checking the status object's `public` flag alone.
		 */
		if ( is_post_publicly_viewable( $post ) ) {
			return true;
		}

		/*
		 * Use the normalized status for the status object lookup. For attachments,
		 * get_post_status() resolves `inherit` through the parent before returning.
		 */
		$post_status = get_post_status( $post );
		if ( ! is_string( $post_status ) ) {
			return false;
		}

		$post_status_object = get_post_status_object( $post_status );
		if ( ! $post_status_object instanceof stdClass ) {
			return false;
		}

		/*
		 * Core maps `read_post` for public statuses to the post type's plain `read`
		 * capability. Publicly viewable posts already returned above, so a remaining
		 * public status is public but not viewable and should require edit access.
		 */
		if ( $post_status_object->public ) {
			return current_user_can( 'edit_post', $post->ID );
		}

		/*
		 * For non-public statuses, defer to Core's meta-capability mapping. This
		 * handles own drafts, private posts, and statuses that require edit access.
		 */
		if ( current_user_can( 'read_post', $post->ID ) ) {
			return true;
		}

		/*
		 * Mirror the REST posts controller's inherited-parent behavior, but keep the
		 * ability fail-closed for missing parents or parent loops. No input can request
		 * `inherit`: the input schema leaves out every internal status other than `trash`,
		 * and the statuses a query covers by default hold no internal status. A query
		 * filter such as a `pre_get_posts` callback can still add it, though, so such posts
		 * keep their parent's read check.
		 */
		if ( 'inherit' === $post->post_status && $post->post_parent > 0 ) {
			$parent = get_post( $post->post_parent );
			if ( $parent instanceof WP_Post ) {
				return $this->check_read_permission( $parent, $checked_post_ids );
			}
		}

		return false;
	}

	/**
	 * Executes the `core/content-query` ability.
	 *
	 * {@see WP_Ability::execute()} always runs {@see self::check_query_permission()} first, which
	 * gates the request as a whole. The query still filters every row by read permission,
	 * because the gate cannot know the rows before the query runs.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return array<string, mixed>|WP_Error A page of posts, or a WP_Error on failure.
	 */
	private function execute_content_query( $input = array() ) {
		$input  = rest_sanitize_object( $input );
		$fields = $this->normalize_fields( $input );

		// The author visibility memo is per query; see get_visible_author().
		$this->visible_authors = array();

		/*
		 * A `where` that is not an object cannot be honored. Reject it rather than reading it
		 * as no filter at all.
		 */
		$where = $this->get_input_object( $input, 'where' );
		if ( null === $where ) {
			return $this->invalid_filter_error( 'where', __( 'The where filter must be an object.' ) );
		}

		// Pagination that is not an object falls back to the defaults, like page values that cannot be parsed.
		$pagination = $this->get_input_object( $input, 'pagination' ) ?? array();
		$page       = $this->parse_filter_int( $pagination['page'] ?? 1, 1 ) ?? 1;

		/*
		 * Unreachable through gated transports, which run check_query_permission() first and deny
		 * a request without a post type exposed to abilities. A direct call to the execute
		 * callback still fails closed here, but it bypasses the checks of the request as a
		 * whole that the permission callback performed, such as the status capabilities, so
		 * only invoke the callback through WP_Ability::execute().
		 */
		$post_type_object = $this->get_exposed_post_type( $where['type'] ?? null );
		if ( ! $post_type_object ) {
			return $this->not_found_error();
		}

		$post_type = $post_type_object->name;

		/*
		 * REST only registers the equivalent collection filters for post types that
		 * support them; a shared input schema cannot express that per post type. On
		 * transports that skip schema validation a malformed value would otherwise
		 * coerce to a benign default and silently *widen* the query (`author => 0`
		 * drops the author filter, an empty `post__in` or `post_name__in` is ignored,
		 * `post_parent => 0` becomes a top-level query). Reject unsupported filters and
		 * invalid filter values loudly so a filter that cannot be honored fails closed
		 * instead.
		 */
		$parent = null;
		if ( isset( $where['parent'] ) ) {
			if ( ! is_post_type_hierarchical( $post_type ) ) {
				return $this->invalid_filter_error( 'where[parent]', __( 'The parent filter is only supported for hierarchical post types.' ) );
			}

			$parent = $this->parse_filter_int( $where['parent'], 0 );
			if ( null === $parent ) {
				return $this->invalid_filter_error( 'where[parent]', __( 'The parent filter must be a non-negative integer.' ) );
			}
		}

		/*
		 * The author filter is an object with an `id` or a `slug`, the keys the `author`
		 * field returns. Null, int, or false: no filter, the user ID to query, or a filter
		 * that names no user, which matches no post (see below).
		 */
		$author_id = null;
		if ( array_key_exists( 'author', $where ) ) {
			if ( ! post_type_supports( $post_type, 'author' ) ) {
				return $this->invalid_filter_error(
					'where[author]',
					/* translators: %s: Parameter. */
					sprintf( __( 'The %s filter is only supported for post types that support authors.' ), 'author' )
				);
			}

			/*
			 * An object without `id` or `slug`, such as `{}` or an empty string, and one with
			 * an unknown key, such as a list or a misspelled property, name no user and must not
			 * fall through to an unrestricted query, like an empty `ids` filter.
			 */
			$author_filter = $this->get_input_object( $where, 'author' );
			if ( empty( $author_filter ) || ! empty( array_diff_key( $author_filter, array_flip( array( 'id', 'slug' ) ) ) ) ) {
				return $this->invalid_filter_error(
					'where[author]',
					/* translators: %s: Parameter. */
					sprintf( __( 'The %s filter must be an object with an id or a slug.' ), 'author' )
				);
			}

			/*
			 * An `id` of 0 must not reach the query: WP_Query reads `author => 0` as no author
			 * filter, which would return every post of the type.
			 */
			if ( array_key_exists( 'id', $author_filter ) ) {
				$author_id = $this->parse_filter_int( $author_filter['id'], 1 );
				if ( null === $author_id ) {
					return $this->invalid_filter_error(
						'where[author][id]',
						/* translators: %s: Parameter. */
						sprintf( __( 'The %s ID must be a positive integer.' ), 'author' )
					);
				}
			}

			if ( array_key_exists( 'slug', $author_filter ) ) {
				if ( ! is_string( $author_filter['slug'] ) || '' === $author_filter['slug'] ) {
					return $this->invalid_filter_error(
						'where[author][slug]',
						/* translators: %s: Parameter. */
						sprintf( __( 'The %s slug must be a non-empty string.' ), 'author' )
					);
				}

				/*
				 * The slug is looked up as given, like the `slug` of the users endpoint: the
				 * collation of the users table decides whether the case matters. A slug that
				 * names no user, or a user the current user may not see, or names another user
				 * than `id`, matches no post, as a post must match every given filter. The
				 * visibility rule is the one the `author` field follows, so a slug the field
				 * withholds cannot be confirmed through the filter.
				 */
				$user = get_user_by( 'slug', $author_filter['slug'] );
				$user = $user ? $this->get_visible_author( $user->ID ) : null;
				if ( ! $user || ( null !== $author_id && $user->ID !== $author_id ) ) {
					$author_id = false;
				} else {
					$author_id = $user->ID;
				}
			}
		}

		/*
		 * An `ids` or `slugs` filter that was given but kept no valid entry must not fall
		 * through to an unrestricted query: WP_Query ignores an empty `post__in` or
		 * `post_name__in`, which would return every post of the type, the opposite of the
		 * caller's intent.
		 */
		$ids = null;
		if ( isset( $where['ids'] ) ) {
			$ids = $this->normalize_ids( $where );
			if ( empty( $ids ) ) {
				return $this->invalid_filter_error(
					'where[ids]',
					/* translators: %s: Parameter. */
					sprintf( __( 'The %s filter must list one or more valid post IDs.' ), 'ids' )
				);
			}
		}

		$slugs = null;
		if ( isset( $where['slugs'] ) ) {
			$slugs = $this->normalize_slugs( $where );
			if ( empty( $slugs ) ) {
				return $this->invalid_filter_error(
					'where[slugs]',
					/* translators: %s: Parameter. */
					sprintf( __( 'The %s filter must list one or more valid post slugs.' ), 'slugs' )
				);
			}
		}

		/*
		 * A `status` filter that was given but kept no status must not fall through to the
		 * default statuses, which with `ids` or `slugs` cover every status the current user may
		 * query, wider than the caller asked for.
		 */
		$statuses = $this->get_requested_statuses( $where );
		if ( isset( $where['status'] ) && empty( $statuses ) ) {
			return $this->invalid_filter_error(
				'where[status]',
				/* translators: %s: Parameter. */
				sprintf( __( 'The %s filter must list one or more post statuses.' ), 'status' )
			);
		}

		/*
		 * The input schema lists the statuses registered when the ability was, so check them
		 * again: WP_Query ignores a status that is not registered, and drops the status clause
		 * when none is left, which would cover every status, including internal ones such as
		 * `trash`. Of the internal statuses, only `trash` can be requested, by name.
		 */
		if ( ! empty( array_diff( $statuses, get_post_stati( array( 'internal' => false ) ), array( 'trash' ) ) ) ) {
			return $this->invalid_filter_error(
				'where[status]',
				/* translators: %s: Parameter. */
				sprintf( __( 'The %s filter must list valid post statuses.' ), 'status' )
			);
		}

		$lists_posts = null !== $ids || null !== $slugs;
		$per_page    = $this->normalize_per_page( $pagination, $lists_posts );

		$prime_post_caches = $this->should_prime_post_caches( $fields );

		/*
		 * `orderby` is left unset, which orders by `post_date` descending, matching the
		 * default of the REST posts controller.
		 *
		 * `perm` leaves out the other users' private posts for a user who cannot read them,
		 * so the totals do not count them. It is not set for a user who can: when a query
		 * filter adds post types, WP_Query checks `read_private_multiple_post_types`
		 * instead, which no role has, and would leave the posts out too.
		 */
		$query_args = array(
			'post_type'              => $post_type,
			'post_status'            => $this->normalize_statuses( $statuses, $post_type_object, $lists_posts ),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'perm'                   => current_user_can( $post_type_object->cap->read_private_posts ) ? '' : 'readable',
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => $prime_post_caches,
			'update_post_term_cache' => $prime_post_caches,
		);

		if ( null !== $ids ) {
			$query_args['post__in'] = $ids;
		}

		if ( null !== $slugs ) {
			$query_args['post_name__in'] = $slugs;
		}

		if ( is_int( $author_id ) ) {
			$query_args['author'] = $author_id;
		}

		if ( null !== $parent ) {
			$query_args['post_parent'] = $parent;
		}

		/*
		 * A slug that names no user or a user the current user may not see, or an ID and a slug
		 * that name different users, match no post, like IDs and slugs that name no post, rather
		 * than failing the query. An `id` is not looked up: the query matches the stored author
		 * ID, so the posts of a deleted user still match it.
		 */
		if ( false === $author_id ) {
			return $this->prepare_output( array(), 0, 0 );
		}

		$query = new WP_Query( $query_args );
		$total = $this->get_query_total( $query, $query_args, $page );

		/*
		 * Count the pages with the page size the query ran with, as the REST posts controller
		 * does, since query filters such as `pre_get_posts` callbacks can change it. Without
		 * paging, the query returns every post on one page.
		 */
		$query_per_page = (int) $query->get( 'posts_per_page' );
		if ( $query->get( 'nopaging' ) || $query_per_page < 1 ) {
			$total_pages = $total > 0 ? 1 : 0;
		} else {
			$total_pages = (int) ceil( $total / $query_per_page );
		}

		/*
		 * A page past the last one does not exist, so report it as not found instead of
		 * returning a bare empty list. A genuinely empty result set still returns zero
		 * totals and no error.
		 */
		if ( $total > 0 && $page > $total_pages ) {
			return $this->invalid_page_number_error();
		}

		/*
		 * Prime the parent and author caches with a single query each instead of one
		 * lookup per post, as the REST posts controller does. Hierarchical permalinks and
		 * inherited read permissions read the parent, and format_post() sets up every post
		 * with setup_postdata(), which reads the author.
		 */
		update_post_parent_caches( $query->posts );
		update_post_author_caches( $query->posts );

		$posts = array();
		foreach ( $query->posts as $post ) {
			/*
			 * Skip posts of other post types, which query filters can add, such as a
			 * `pre_get_posts` callback that adds post types to the blog home without an
			 * is_main_query() check.
			 */
			if ( ! $post instanceof WP_Post || $post_type !== $post->post_type ) {
				continue;
			}
			if ( ! $this->check_read_permission( $post ) ) {
				continue;
			}
			$posts[] = $this->format_post( $post, $fields );
		}

		/*
		 * Mirror the REST posts controller: totals come from the underlying WP_Query,
		 * while the row-level checks above may withhold individual returned rows. The
		 * totals can therefore count posts the current user cannot read, such as another
		 * author's draft listed in `ids`. This is deliberate: it only reveals that such a
		 * post exists, as a REST collection request with `include` and `status` does.
		 */
		return $this->prepare_output( $posts, $total, $total_pages );
	}

	/**
	 * Executes the `core/content-create` ability.
	 *
	 * The post type and the capability to create posts of it are checked first, then every
	 * attribute rule, and only then is the post saved, so a rejected call writes nothing.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input, as prepared by WP_Ability::execute().
	 * @return array<string, mixed>|WP_Error The created post, or a WP_Error on failure.
	 */
	private function execute_content_create( array $input ) {
		// The author visibility memo is per call; see get_visible_author().
		$this->visible_authors = array();

		$data             = $input['data'] ?? array();
		$post_type_object = $this->get_exposed_post_type( $data['type'] ?? null );
		if ( ! $post_type_object ) {
			return $this->not_found_error();
		}

		// Checked again after the permission callback, whose result a filter can change.
		if ( ! current_user_can( $post_type_object->cap->create_posts ) ) {
			return new WP_Error(
				'content_cannot_create',
				__( 'Sorry, you are not allowed to create posts as this user.' ),
				array( 'status' => 403 )
			);
		}

		$postarr = $this->prepare_post_data( $data, $post_type_object, null );
		if ( is_wp_error( $postarr ) ) {
			return $postarr;
		}

		return $this->persist_post( $postarr, $this->normalize_fields( $input ) );
	}

	/**
	 * Executes the `core/content-update` ability.
	 *
	 * The post is looked up first, then the capability to edit it is checked, then every
	 * attribute rule, and only then is the post saved, so a rejected call writes nothing.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input, as prepared by WP_Ability::execute().
	 * @return array<string, mixed>|WP_Error The updated post, or a WP_Error on failure.
	 */
	private function execute_content_update( array $input ) {
		// The author visibility memo is per call; see get_visible_author().
		$this->visible_authors = array();

		$post = $this->get_requested_post( $input['where'] ?? array() );
		if ( ! $post ) {
			return $this->not_found_error();
		}

		// Checked again after the permission callback, whose result a filter can change.
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'content_cannot_edit',
				__( 'Sorry, you are not allowed to edit this post.' ),
				array( 'status' => 403 )
			);
		}

		/*
		 * A `data` that sends no attribute that can be written has nothing to apply, so it is
		 * rejected instead of saving the post unchanged.
		 */
		$data = $input['data'] ?? array();
		if ( empty( $data ) ) {
			return $this->invalid_attribute_error( 'data', __( 'The data must be an object with at least one attribute to write.' ) );
		}

		/*
		 * Known issue: a `data.status` sent for a post in the Trash moves it out of the Trash
		 * through a regular save, not through wp_untrash_post(). The `_wp_trash_meta_status`
		 * and `_wp_trash_meta_time` meta and the comments trashed with the post are left
		 * behind. The REST API posts endpoint has the same issue when an update sends a
		 * status for a trashed post. The cause is in the save path, so the fix belongs there,
		 * where it covers every caller of wp_update_post().
		 * See https://core.trac.wordpress.org/ticket/66809.
		 */

		// get_requested_post() only returns posts of an exposed post type.
		$postarr = $this->prepare_post_data( $data, get_post_type_object( $post->post_type ), $post );
		if ( is_wp_error( $postarr ) ) {
			return $postarr;
		}

		return $this->persist_post( $postarr, $this->normalize_fields( $input ) );
	}

	/**
	 * Executes the `core/content-delete` ability.
	 *
	 * The post is looked up first, then the capability to delete it is checked. The post is
	 * deleted permanently when `permanent` is true; otherwise it is moved to the Trash. The
	 * outcome is confirmed against the stored post afterwards.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input, as prepared by WP_Ability::execute().
	 * @return array<string, string>|WP_Error The outcome, or a WP_Error on failure.
	 */
	private function execute_content_delete( array $input ) {
		$post = $this->get_requested_post( $input['where'] ?? array() );
		if ( ! $post ) {
			return $this->not_found_error();
		}

		// Checked again after the permission callback, whose result a filter can change.
		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			return new WP_Error(
				'content_cannot_delete',
				__( 'Sorry, you are not allowed to delete this post.' ),
				array( 'status' => 403 )
			);
		}

		if ( ! empty( $input['permanent'] ) ) {
			$deleted = wp_delete_post( $post->ID, true );

			return $deleted && null === get_post( $post->ID ) ? array( 'outcome' => 'deleted' ) : $this->delete_failed_error();
		}

		/*
		 * Checked before wp_trash_post() is called: without a Trash, it deletes the post
		 * permanently instead.
		 */
		if ( ! $this->can_trash( $post ) ) {
			return new WP_Error(
				'content_trash_not_supported',
				__( 'The post does not support trashing. Set `permanent` to true to delete it.' ),
				array( 'status' => 501 )
			);
		}

		if ( 'trash' === $post->post_status ) {
			return new WP_Error(
				'content_already_trashed',
				__( 'The post has already been trashed.' ),
				array( 'status' => 410 )
			);
		}

		$trashed = wp_trash_post( $post->ID );

		return $trashed && 'trash' === get_post_status( $post->ID ) ? array( 'outcome' => 'trashed' ) : $this->delete_failed_error();
	}

	/**
	 * Returns the post that `where` names, when it exists and has the named exposed type.
	 *
	 * The ID is read once, with {@see self::parse_filter_int()}, and that same integer is used
	 * for the lookup, the capability checks, and the write. An ID that is not a whole number
	 * of at least 1 names no post.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $where The `where` input.
	 * @return WP_Post|null The post, or null when `where` names no post of an exposed type.
	 */
	private function get_requested_post( array $where ): ?WP_Post {
		$post_type_object = $this->get_exposed_post_type( $where['type'] ?? null );
		$post_id          = $this->parse_filter_int( $where['id'] ?? null, 1 );
		if ( ! $post_type_object || null === $post_id ) {
			return null;
		}

		$post = get_post( $post_id );

		return $post instanceof WP_Post && $post_type_object->name === $post->post_type ? $post : null;
	}

	/**
	 * Checks the `data` attributes and builds the array passed to the save.
	 *
	 * Shared by the create and update abilities. Only the attributes of
	 * {@see self::get_content_write_properties()} that were sent reach the array, each mapped
	 * to one post field, next to the values the server sets: the post type on create, the
	 * post ID on update. The first rule that fails is returned and nothing is written.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $data             The `data` input.
	 * @param WP_Post_Type $post_type_object The post type of the post being written.
	 * @param WP_Post|null $post             The stored post on update, or null on create.
	 * @return array<string, mixed>|WP_Error The unslashed post array, or a WP_Error.
	 */
	private function prepare_post_data( array $data, WP_Post_Type $post_type_object, ?WP_Post $post ) {
		$post_type = $post_type_object->name;

		$error = $this->check_supported_attributes( $data, $post_type );
		if ( $error ) {
			return $error;
		}

		$postarr     = array();
		$text_fields = array(
			'title'   => 'post_title',
			'content' => 'post_content',
			'excerpt' => 'post_excerpt',
		);

		foreach ( $text_fields as $attribute => $field ) {
			if ( array_key_exists( $attribute, $data ) ) {
				$postarr[ $field ] = $data[ $attribute ];
			}
		}

		$status = $this->prepare_status( $data, $post_type_object, $post );
		if ( is_wp_error( $status ) ) {
			return $status;
		}
		if ( null !== $status ) {
			$postarr['post_status'] = $status;
		}

		$author = $this->prepare_author( $data, $post_type_object, $post );
		if ( is_wp_error( $author ) ) {
			return $author;
		}
		if ( null !== $author ) {
			$postarr['post_author'] = $author;
		}

		$parent = $this->prepare_parent( $data, $post_type, $post );
		if ( is_wp_error( $parent ) ) {
			return $parent;
		}
		if ( null !== $parent ) {
			$postarr['post_parent'] = $parent;
		}

		$dates = $this->prepare_date( $data, $post, $status );
		if ( is_wp_error( $dates ) ) {
			return $dates;
		}
		$postarr = array_merge( $postarr, $dates );

		// Without a sent status, the save defaults to `draft`.
		$final_status = $postarr['post_status'] ?? $post->post_status ?? 'draft';
		$final_parent = $postarr['post_parent'] ?? $post->post_parent ?? 0;

		$slug = $this->prepare_slug( $data, $post_type_object, $post, $final_status, $final_parent );
		if ( is_wp_error( $slug ) ) {
			return $slug;
		}
		if ( null !== $slug ) {
			$postarr['post_name'] = $slug;
		}

		if ( $post ) {
			$postarr['ID'] = $post->ID;

			// A stored page template that no longer exists must not fail an unrelated update.
			$postarr['page_template'] = null;
		} else {
			$postarr['post_type'] = $post_type;
		}

		return $postarr;
	}

	/**
	 * Rejects the sent attributes that the post type does not support.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $data      The `data` input.
	 * @param string       $post_type The post type name.
	 * @return WP_Error|null The error for the first unsupported attribute, or null.
	 */
	private function check_supported_attributes( array $data, string $post_type ): ?WP_Error {
		/*
		 * Intentionally strict: an attribute that cannot be stored is an error and is never
		 * ignored, even when its value would change nothing.
		 */
		$features = array(
			'title'   => 'title',
			'content' => 'editor',
			'excerpt' => 'excerpt',
			'author'  => 'author',
		);
		foreach ( $features as $attribute => $feature ) {
			if ( array_key_exists( $attribute, $data ) && ! post_type_supports( $post_type, $feature ) ) {
				return $this->invalid_attribute_error(
					"data[{$attribute}]",
					/* translators: 1: Attribute name, such as "content", 2: Post type feature, such as "editor". */
					sprintf( __( 'The %1$s attribute is only supported for post types that support the %2$s feature.' ), $attribute, $feature )
				);
			}
		}

		if ( array_key_exists( 'parent', $data ) && ! is_post_type_hierarchical( $post_type ) ) {
			return $this->invalid_attribute_error( 'data[parent]', __( 'The parent attribute is only supported for hierarchical post types.' ) );
		}

		return null;
	}

	/**
	 * Checks the sent `status`.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $data             The `data` input.
	 * @param WP_Post_Type $post_type_object The post type of the post being written.
	 * @param WP_Post|null $post             The stored post on update, or null on create.
	 * @return string|WP_Error|null The status, null when none is sent or it is unchanged, or a WP_Error.
	 */
	private function prepare_status( array $data, WP_Post_Type $post_type_object, ?WP_Post $post ) {
		if ( ! array_key_exists( 'status', $data ) ) {
			return null;
		}

		$status        = $data['status'];
		$status_object = get_post_status_object( $status );

		/*
		 * Intentionally strict: a status that is not registered is an error and is never
		 * replaced by another status. Internal statuses, such as `trash`, can never be set,
		 * not even when the post already has them.
		 */
		if ( ! $status_object instanceof stdClass || ! empty( $status_object->internal ) ) {
			return $this->invalid_attribute_error( 'data[status]', __( 'The status must be a registered post status that is not internal.' ) );
		}

		// Keeping the stored status changes nothing.
		if ( $post && $post->post_status === $status ) {
			return null;
		}

		/*
		 * Intentionally strict: every status that makes a post visible needs the capability
		 * to publish posts of the type, including custom ones.
		 */
		$makes_visible = 'future' === $status || ! empty( $status_object->public ) || ! empty( $status_object->private ) || is_post_status_viewable( $status_object );
		if ( $makes_visible && ! current_user_can( $post_type_object->cap->publish_posts ) ) {
			return new WP_Error(
				'content_cannot_publish',
				'private' === $status
					? __( 'Sorry, you are not allowed to create private posts in this post type.' )
					: __( 'Sorry, you are not allowed to publish posts in this post type.' ),
				array( 'status' => 403 )
			);
		}

		return $status;
	}

	/**
	 * Checks the sent `author`.
	 *
	 * The permission to name another user is checked before the user is looked up, so the
	 * error never tells whether a user ID exists.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $data             The `data` input.
	 * @param WP_Post_Type $post_type_object The post type of the post being written.
	 * @param WP_Post|null $post             The stored post on update, or null on create.
	 * @return int|WP_Error|null The author ID, null when none is sent or it is unchanged, or a WP_Error.
	 */
	private function prepare_author( array $data, WP_Post_Type $post_type_object, ?WP_Post $post ) {
		if ( ! array_key_exists( 'author', $data ) ) {
			return null;
		}

		$author_id = $data['author'];

		// Keeping the stored author changes nothing.
		if ( $post && (int) $post->post_author === $author_id ) {
			return null;
		}

		if ( get_current_user_id() === $author_id ) {
			return $author_id;
		}

		if ( ! current_user_can( $post_type_object->cap->edit_others_posts ) ) {
			return new WP_Error(
				'content_cannot_edit_others',
				$post
					? __( 'Sorry, you are not allowed to update posts as this user.' )
					: __( 'Sorry, you are not allowed to create posts as this user.' ),
				array( 'status' => 403 )
			);
		}

		// Intentionally strict: the author must be an existing user who belongs to this site.
		if ( ! get_userdata( $author_id ) || ( is_multisite() && ! is_user_member_of_blog( $author_id ) ) ) {
			return $this->invalid_attribute_error( 'data[author]', __( 'The author must be an existing user of this site.' ) );
		}

		return $author_id;
	}

	/**
	 * Checks the sent `parent`.
	 *
	 * Every failure returns the same error, so it never tells whether a post ID exists.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $data      The `data` input.
	 * @param string       $post_type The post type name.
	 * @param WP_Post|null $post      The stored post on update, or null on create.
	 * @return int|WP_Error|null The parent ID, null when none is sent or it is unchanged, or a WP_Error.
	 */
	private function prepare_parent( array $data, string $post_type, ?WP_Post $post ) {
		if ( ! array_key_exists( 'parent', $data ) ) {
			return null;
		}

		$reason    = __( 'The parent must be 0, or a readable post of the same post type that is neither the post itself nor one of its descendants.' );
		$parent_id = $data['parent'];

		// Keeping the stored parent changes nothing.
		if ( $post && (int) $post->post_parent === $parent_id ) {
			return null;
		}

		if ( 0 === $parent_id ) {
			return 0;
		}

		/*
		 * Intentionally strict: a parent must be readable, of the same type and free of
		 * loops; it is never silently reset.
		 */
		$parent = get_post( $parent_id );
		if ( ! $parent instanceof WP_Post || $post_type !== $parent->post_type || ! $this->check_read_permission( $parent ) ) {
			return $this->invalid_attribute_error( 'data[parent]', $reason );
		}

		if ( $post && ( $post->ID === $parent->ID || in_array( $post->ID, get_post_ancestors( $parent ), true ) ) ) {
			return $this->invalid_attribute_error( 'data[parent]', $reason );
		}

		return $parent_id;
	}

	/**
	 * Checks the sent `date` and returns the date fields to save.
	 *
	 * `site` wins when both `site` and `gmt` are sent. On update, a sent date that equals the
	 * stored one counts as not sent, so a draft with a floating date keeps floating, unless
	 * the call moves it to a status whose dates do not float with a date that is still ahead.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $data   The `data` input.
	 * @param WP_Post|null $post   The stored post on update, or null on create.
	 * @param string|null  $status The status to set, or null when none is sent or it is unchanged.
	 * @return array<string, mixed>|WP_Error The date fields to save, empty for none, or a WP_Error.
	 */
	private function prepare_date( array $data, ?WP_Post $post, ?string $status ) {
		if ( ! array_key_exists( 'date', $data ) ) {
			return array();
		}

		$date  = $data['date'];
		$dates = isset( $date['site'] ) ? rest_get_date_with_gmt( $date['site'] ) : rest_get_date_with_gmt( $date['gmt'] ?? '', true );
		if ( null === $dates ) {
			return $this->invalid_attribute_error( 'data[date]', __( 'The date must be an object with a valid `site` or `gmt` date.' ) );
		}

		$fields = array(
			'post_date'     => $dates[0],
			'post_date_gmt' => $dates[1],
		);

		if ( ! $post ) {
			return $fields;
		}

		$fields['edit_date'] = true;

		$is_floating = '0000-00-00 00:00:00' === $post->post_date_gmt;
		if ( isset( $date['site'] ) ) {
			$is_unchanged = $post->post_date === $dates[0];
		} else {
			$stored_gmt   = $is_floating ? get_gmt_from_date( $post->post_date ) : $post->post_date_gmt;
			$is_unchanged = $stored_gmt === $dates[1];
		}

		if ( ! $is_unchanged ) {
			return $fields;
		}

		/*
		 * Intentionally strict: sending back the date a floating draft shows, in either form,
		 * never pins it. The one exception is a call that also moves the draft to a status
		 * whose dates do not float, with a date still ahead: the date is stored, so the post
		 * is scheduled for the date it shows instead of being published at once.
		 */
		$schedules = $is_floating
			&& null !== $status
			&& ! in_array( $status, get_post_stati( array( 'date_floating' => true ) ), true )
			&& $dates[1] > gmdate( 'Y-m-d H:i:s' );

		return $schedules ? $fields : array();
	}

	/**
	 * Checks the sent `slug` and returns the slug to save.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $data             The `data` input.
	 * @param WP_Post_Type $post_type_object The post type of the post being written.
	 * @param WP_Post|null $post             The stored post on update, or null on create.
	 * @param string       $status           The status the post will have.
	 * @param int          $parent_id        The parent ID the post will have.
	 * @return string|WP_Error|null The slug, null when none is sent, or a WP_Error.
	 */
	private function prepare_slug( array $data, WP_Post_Type $post_type_object, ?WP_Post $post, string $status, int $parent_id ) {
		if ( ! array_key_exists( 'slug', $data ) ) {
			return null;
		}

		/*
		 * Intentionally strict: the save drops the slug of a pending post when the user cannot
		 * publish it, so the slug is an error here instead of being dropped without notice.
		 */
		if ( 'pending' === $status ) {
			$can_publish = $post
				? current_user_can( 'publish_post', $post->ID )
				: current_user_can( $post_type_object->cap->publish_posts );
			if ( ! $can_publish ) {
				return $this->invalid_attribute_error( 'data[slug]', __( 'The slug of a pending post can only be set by a user who can publish the post.' ) );
			}
		}

		$slug = sanitize_title( $data['slug'] );

		// Intentionally strict: a slug that sanitizes to nothing is an error.
		if ( '' === $slug ) {
			return $this->invalid_attribute_error( 'data[slug]', __( 'The slug must contain characters that are valid in a slug.' ) );
		}

		/*
		 * Intentionally strict: a draft never takes the slug of another post that shares its
		 * parent. The save keeps the slugs of drafts and pending posts as given, so they are
		 * made unique here as if the post were published.
		 */
		if ( in_array( $status, array( 'draft', 'pending' ), true ) ) {
			$slug = wp_unique_post_slug( $slug, $post ? $post->ID : 0, 'publish', $post_type_object->name, $parent_id );
		}

		return $slug;
	}

	/**
	 * Saves a prepared post and returns it in the requested fields.
	 *
	 * The post is saved through the regular save functions, as the current user, so the
	 * default filters, such as the markup filtering for users without `unfiltered_html`, and
	 * the default hooks apply as in any other save.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $postarr The unslashed post array from prepare_post_data(). It
	 *                                      holds an `ID` when the post is updated.
	 * @param list<string>         $fields  The requested field paths.
	 * @return array<string, mixed>|WP_Error The saved post, only its ID when the current user
	 *                                       may not read it, or a WP_Error on failure.
	 */
	private function persist_post( array $postarr, array $fields ) {
		$postarr = wp_slash( $postarr );
		$post_id = isset( $postarr['ID'] ) ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) ) {
			$is_database_error = in_array( $post_id->get_error_code(), array( 'db_insert_error', 'db_update_error' ), true );
			$post_id->add_data( array( 'status' => $is_database_error ? 500 : 400 ) );

			return $post_id;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return $this->not_found_error();
		}

		/*
		 * Intentionally strict: the saved post passes the same read check as every query
		 * row. When the current user may no longer read it, for example after moving it to
		 * a status they cannot read, the write stands but only its ID is returned.
		 */
		if ( ! $this->check_read_permission( $post ) ) {
			return array( 'id' => $post->ID );
		}

		return $this->format_post( $post, $fields );
	}

	/**
	 * Checks whether the site can move a post to the Trash.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_Post $post The post.
	 * @return bool Whether the post can be trashed.
	 */
	private function can_trash( WP_Post $post ): bool {
		return EMPTY_TRASH_DAYS > 0 && ( 'attachment' !== $post->post_type || MEDIA_TRASH );
	}

	/**
	 * Reads an object member of the input, such as `where` or `pagination`.
	 *
	 * Accepts what schema validation reads as an object: an array, a `stdClass`, or an
	 * empty string. WP_Ability::execute() converts input to the schema types, but
	 * WP_Ability::check_permissions() does not, so a permission callback can receive any of them.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @param string       $key   The member name.
	 * @return array<mixed>|null The member as an array, an empty array when it is omitted,
	 *                           or null when it is not an object.
	 */
	private function get_input_object( array $input, string $key ): ?array {
		if ( ! array_key_exists( $key, $input ) ) {
			return array();
		}

		return rest_is_object( $input[ $key ] ) ? rest_sanitize_object( $input[ $key ] ) : null;
	}

	/**
	 * Normalizes the requested per-page value to the supported bounds.
	 *
	 * An explicit `per_page` always wins. Otherwise a request that lists posts by ID or slug
	 * pages to the maximum, as `core/users-query` does for its lists, so a caller loading a
	 * known set of posts receives them in one call rather than silently losing the ones past
	 * the default page size. The input schema caps each list at {@see self::MAX_PER_PAGE},
	 * so every post that `ids` lists fits on the first page. A slug can match several posts,
	 * though, so the matches of `slugs` only continue past the first page when more than
	 * that many posts match.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $pagination  The `pagination` input.
	 * @param bool         $lists_posts Whether the query lists `ids` or `slugs`.
	 * @return int The clamped per-page value.
	 */
	private function normalize_per_page( array $pagination, bool $lists_posts ): int {
		$default_per_page = $lists_posts ? self::MAX_PER_PAGE : self::DEFAULT_PER_PAGE;

		return min( self::MAX_PER_PAGE, $this->parse_filter_int( $pagination['per_page'] ?? null, 1 ) ?? $default_per_page );
	}

	/**
	 * Returns the query total, recovering it when WP_Query skipped the count.
	 *
	 * WP_Query leaves `found_posts` at 0 when a requested page has no rows. Re-run a
	 * minimal unpaged query so the caller can distinguish an out-of-range page from
	 * an empty result set, matching the REST posts controller behavior.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Query     $query      The executed query.
	 * @param array<mixed> $query_args The arguments used for the executed query.
	 * @param int          $page       The requested page.
	 * @return int Total matching rows across all pages.
	 */
	private function get_query_total( WP_Query $query, array $query_args, int $page ): int {
		$total = (int) $query->found_posts;

		if ( $total > 0 || $page <= 1 ) {
			return $total;
		}

		$count_args                           = $query_args;
		$count_args['fields']                 = 'ids';
		$count_args['posts_per_page']         = 1;
		$count_args['update_post_meta_cache'] = false;
		$count_args['update_post_term_cache'] = false;
		unset( $count_args['paged'] );

		$count_query = new WP_Query( $count_args );

		return (int) $count_query->found_posts;
	}

	/**
	 * Checks whether requested fields benefit from page-level cache priming.
	 *
	 * A whole object counts as a request for its sub-fields, so `content` primes the
	 * caches as `content.rendered` does.
	 *
	 * @since 7.2.0
	 *
	 * @param list<string> $fields The requested field paths.
	 * @return bool True when post meta and term caches should be primed.
	 */
	private function should_prime_post_caches( array $fields ): bool {
		return array_any(
			self::CACHE_PRIMING_FIELDS,
			fn( $field ) => $this->is_field_requested( $field, $fields )
		);
	}

	/**
	 * Checks whether a field path is requested.
	 *
	 * Matches paths as the REST API's `_fields` parameter does, with rest_is_field_included():
	 * a field such as `title` is requested when it or one of its paths, such as
	 * `title.rendered`, is requested, and a path such as `title.rendered` is requested when it
	 * or its whole field is.
	 *
	 * @since 7.2.0
	 *
	 * @param string       $path   The field path, such as `title` or `title.rendered`.
	 * @param list<string> $fields The requested field paths.
	 * @return bool Whether the field path is requested.
	 */
	private function is_field_requested( string $path, array $fields ): bool {
		return rest_is_field_included( $path, $fields );
	}

	/**
	 * Returns a post type exposed through the Abilities API.
	 *
	 * Deliberately resolved on every call rather than cached: post types can be
	 * unregistered or re-registered with different arguments between the ability
	 * being registered and the ability being used.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $post_type The post type name.
	 * @return WP_Post_Type|null The post type object, or null when the post type is not exposed.
	 */
	private function get_exposed_post_type( $post_type ): ?WP_Post_Type {
		$post_type_object = is_string( $post_type ) ? get_post_type_object( $post_type ) : null;

		return empty( $post_type_object->show_in_abilities ) ? null : $post_type_object;
	}

	/**
	 * Returns the statuses the `status` filter explicitly requests.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $where The `where` filters.
	 * @return list<string> Sanitized post status slugs; empty when none is requested.
	 */
	private function get_requested_statuses( array $where ): array {
		return array_map( 'sanitize_key', $this->parse_list_input( $where, 'status' ) );
	}

	/**
	 * Normalizes the statuses a query covers.
	 *
	 * Explicitly requested statuses are queried as given, once the permission callback has
	 * allowed them. Otherwise a query covers `publish`, as in the REST posts controller,
	 * unless it lists `ids` or `slugs`. Such a query names the posts it wants, which may be
	 * drafts or private posts, so it covers every non-internal status that
	 * {@see self::can_query_status()} allows instead. The row-level read checks still
	 * withhold the posts the current user cannot read, such as other users' drafts.
	 *
	 * @since 7.2.0
	 *
	 * @param list<string> $statuses         The explicitly requested statuses; empty when none is requested.
	 * @param WP_Post_Type $post_type_object The post type object.
	 * @param bool         $lists_posts      Whether the query lists `ids` or `slugs`.
	 * @return list<string> Post status slugs.
	 */
	private function normalize_statuses( array $statuses, WP_Post_Type $post_type_object, bool $lists_posts ): array {
		if ( ! empty( $statuses ) ) {
			return $statuses;
		}

		if ( ! $lists_posts ) {
			return array( 'publish' );
		}

		foreach ( get_post_stati( array( 'internal' => false ) ) as $status ) {
			if ( $this->can_query_status( $status, $post_type_object ) ) {
				$statuses[] = $status;
			}
		}

		return $statuses;
	}

	/**
	 * Normalizes the post IDs the `ids` filter lists.
	 *
	 * Like schema validation, it also accepts a single ID or a comma-separated string. Each
	 * ID is read with {@see self::parse_filter_int()}, and one that is not a whole number of
	 * at least 1, such as a fraction, a negative number, or a float too large to hold its
	 * integer exactly, is dropped rather than coerced, so it can never select another post.
	 * A digit string beyond the integer range saturates to PHP_INT_MAX, as REST sanitization
	 * does, which selects no post either.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $where The `where` filters.
	 * @return list<int> Unique post IDs; empty when none is valid.
	 */
	private function normalize_ids( array $where ): array {
		$ids = $where['ids'] ?? null;
		if ( is_string( $ids ) ) {
			$ids = wp_parse_list( $ids );
		} elseif ( is_int( $ids ) || is_float( $ids ) ) {
			$ids = array( $ids );
		} elseif ( ! is_array( $ids ) ) {
			return array();
		}

		$post_ids = array();
		foreach ( $ids as $id ) {
			$post_id = $this->parse_filter_int( $id, 1 );
			if ( null !== $post_id ) {
				$post_ids[] = $post_id;
			}
		}

		return array_values( array_unique( $post_ids ) );
	}

	/**
	 * Normalizes the post slugs the `slugs` filter lists.
	 *
	 * Like schema validation, it also accepts a single slug, even one sent as a number, or a
	 * comma-separated string. Each slug is sanitized with sanitize_title(), as WordPress
	 * sanitizes a post slug when it saves the post, so `Hello-World` matches `hello-world`.
	 * A slug that sanitizes to an empty string, such as `!!!`, is dropped: it names no post,
	 * and querying it would match the posts that have no slug yet, such as drafts.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $where The `where` filters.
	 * @return list<string> Unique post slugs; empty when none is valid.
	 */
	private function normalize_slugs( array $where ): array {
		$slugs = array();
		foreach ( $this->parse_list_input( $where, 'slugs' ) as $slug ) {
			$slug = sanitize_title( $slug );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Returns the requested field paths, or a lean default set when none are given.
	 *
	 * An empty or absent `fields` value selects a lean set of common read fields.
	 * Otherwise the requested paths are returned. The input schema has already
	 * validated them against the supported set before the ability executes, and
	 * {@see self::build_post_fields()} ignores any other path.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return list<string> List of requested field paths.
	 */
	private function normalize_fields( array $input ): array {
		$fields = $this->parse_list_input( $input, 'fields' );

		return empty( $fields ) ? self::DEFAULT_FIELDS : $fields;
	}

	/**
	 * Returns the post field definitions, keyed by field name in output order.
	 *
	 * This is the single source of truth for the ability's post fields: the output
	 * schema uses the definitions directly, while the input schema `fields` enum lists
	 * each field, followed by the path of each of its sub-fields, such as `title.rendered`.
	 * Read-context fields are returned for readable posts; the `raw` sub-fields only for
	 * posts the current user can edit.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> Post field definitions.
	 */
	private function get_post_properties(): array {
		return array(
			'id'       => array(
				'type'        => 'integer',
				'description' => __( 'The post ID.' ),
			),
			'type'     => array(
				'type'        => 'string',
				'description' => __( 'The post type.' ),
			),
			'status'   => array(
				'type'        => 'string',
				'description' => __( 'The post status.' ),
			),
			'date'     => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'site' => array(
						'type'        => array( 'string', 'null' ),
						'format'      => 'date-time',
						'description' => __( "The publication date, in ISO 8601 format using the site's timezone. Null when the date cannot be resolved." ),
					),
					'gmt'  => array(
						'type'        => array( 'string', 'null' ),
						'format'      => 'date-time',
						'description' => __( 'The publication date, in ISO 8601 format as GMT. Null when the date cannot be resolved.' ),
					),
				),
				'description'          => __( 'The publication date.' ),
			),
			'modified' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'site' => array(
						'type'        => array( 'string', 'null' ),
						'format'      => 'date-time',
						'description' => __( "The last modified date, in ISO 8601 format using the site's timezone. Null when the date cannot be resolved." ),
					),
					'gmt'  => array(
						'type'        => array( 'string', 'null' ),
						'format'      => 'date-time',
						'description' => __( 'The last modified date, in ISO 8601 format as GMT. Null when the date cannot be resolved.' ),
					),
				),
				'description'          => __( 'The last modified date.' ),
			),
			'slug'     => array(
				'type'        => 'string',
				'description' => __( 'The post slug.' ),
			),
			'link'     => array(
				'type'        => 'string',
				'format'      => 'uri',
				'description' => __( 'The permalink URL.' ),
			),
			'title'    => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'raw'      => array(
						'type'        => 'string',
						'description' => __( 'The raw post title. Present when the current user can edit the post.' ),
					),
					'rendered' => array(
						'type'        => 'string',
						'description' => __( 'The rendered post title.' ),
					),
				),
				'description'          => __( 'The post title. Present when the post type supports titles.' ),
			),
			'excerpt'  => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'raw'       => array(
						'type'        => 'string',
						'description' => __( 'The raw post excerpt. Present when the current user can edit the post.' ),
					),
					'rendered'  => array(
						'type'        => 'string',
						'description' => __( 'The rendered post excerpt (HTML). Empty when withheld for a password-protected post.' ),
					),
					'protected' => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the excerpt is protected with a password.' ),
					),
				),
				'description'          => __( 'The post excerpt. Present when the post type supports excerpts.' ),
			),
			'content'  => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'raw'       => array(
						'type'        => 'string',
						'description' => __( 'The raw, unfiltered post content (block markup). Present when the current user can edit the post.' ),
					),
					'rendered'  => array(
						'type'        => 'string',
						'description' => __( 'The rendered post content. Empty when withheld for a password-protected post.' ),
					),
					'protected' => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the content is protected with a password.' ),
					),
				),
				'description'          => __( 'The post content. Present when the post type supports the editor.' ),
			),
			'author'   => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'id'   => array(
						'type'        => 'integer',
						'description' => __( 'The user ID of the author, as the `author.id` filter takes it.' ),
					),
					'name' => array(
						'type'        => array( 'string', 'null' ),
						'description' => __( "The author's display name, as the REST API users endpoint returns it in `name`. Null when the author no longer exists or the current user may not see them." ),
					),
					'slug' => array(
						'type'        => array( 'string', 'null' ),
						'description' => __( "The author's user slug (nicename), as the REST API users endpoint returns it in `slug`, and as the `author.slug` filter takes it. Null when the author no longer exists or the current user may not see them." ),
					),
				),
				'description'          => __( 'The post author. Present when the post type supports authors.' ),
			),
			'parent'   => array(
				'type'        => 'integer',
				'description' => __( 'The parent post ID. Present for hierarchical post types.' ),
			),
		);
	}

	/**
	 * Returns the field paths the `fields` input accepts, in output order.
	 *
	 * Each post field is followed by the path of each of its sub-fields, such as `title`,
	 * `title.raw` and `title.rendered`. Requesting a field returns all of its sub-fields
	 * that apply, while a sub-field path returns only that sub-field, as the REST API's
	 * `_fields` parameter does.
	 *
	 * @since 7.2.0
	 *
	 * @return list<string> The field paths.
	 */
	private function get_field_paths(): array {
		$paths = array();
		foreach ( $this->get_post_properties() as $name => $schema ) {
			$paths[] = $name;

			foreach ( array_keys( $schema['properties'] ?? array() ) as $sub_field ) {
				$paths[] = "{$name}.{$sub_field}";
			}
		}

		return $paths;
	}

	/**
	 * Builds the schema of the `fields` input, shared by all content abilities.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> The `fields` JSON Schema.
	 */
	private function get_fields_input_schema(): array {
		return array(
			'type'        => 'array',
			'uniqueItems' => true,
			'items'       => array(
				'type' => 'string',
				'enum' => $this->get_field_paths(),
			),
			'description' => sprintf(
				/* translators: %s: Comma-separated list of field paths. */
				__( 'Limit each returned post to these fields. A field such as `title` returns all of its sub-fields the current user can see, while a path such as `title.rendered` returns only that sub-field. The `id` is always included. If omitted or empty, the default fields are returned: %s. Requested fields that do not apply to a post are left out of it: raw sub-fields, such as `content.raw`, when the current user cannot edit the post, and fields its post type does not support, such as `parent` for a post type that is not hierarchical. A returned post may therefore hold only its `id`. Password-protected posts differ: when the current user cannot edit one, its `excerpt.rendered` and `content.rendered` are returned as empty strings rather than left out, with `protected` set to true.' ),
				'`' . implode( '`, `', self::DEFAULT_FIELDS ) . '`'
			),
		);
	}

	/**
	 * Builds the input schema for the `core/content-query` ability.
	 *
	 * The input has three members: `where` selects the posts, `fields` selects the fields
	 * returned for each post, and `pagination` selects the page of results. `where` holds
	 * the post type to query in `type`, and any of the filters `ids`, `slugs`, `status`,
	 * `author`, and `parent`, which all combine. Every object sets
	 * `additionalProperties: false`, so an unknown key, such as a misspelled filter, fails
	 * validation instead of being silently ignored.
	 *
	 * @since 7.2.0
	 *
	 * @param list<string> $post_types Exposed post type names.
	 * @param list<string> $statuses   Requestable post status slugs.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_content_query_input_schema( array $post_types, array $statuses ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where' ),
			'properties'           => array(
				'where'      => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'type' ),
					'properties'           => array(
						'type'   => array(
							'type'        => 'string',
							'enum'        => $post_types,
							'description' => __( 'Post type to query for readable posts.' ),
						),
						'ids'    => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'maxItems'    => self::MAX_PER_PAGE,
							'items'       => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'description' => __( 'Limit the query to these post IDs. The order of the IDs does not affect the order of the results. If `pagination.per_page` is omitted, every matching post is returned on the first page.' ),
						),
						'slugs'  => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'maxItems'    => self::MAX_PER_PAGE,
							'items'       => array(
								'type'      => 'string',
								'minLength' => 1,
							),
							'description' => __( 'Limit the query to posts with these slugs. Each slug is sanitized as a post slug is, so `Hello-World` matches `hello-world`. A slug can match several posts, such as pages under different parents, and every match is returned. The order of the slugs does not affect the order of the results. If `pagination.per_page` is omitted, every matching post is returned on the first page, up to the `per_page` maximum.' ),
						),
						'status' => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'items'       => array(
								'type' => 'string',
								'enum' => $statuses,
							),
							'description' => __( 'Filter readable posts by one or more post statuses. Defaults to publish, or, when `ids` or `slugs` is given, to every status the current user may query. Statuses that are not publicly viewable, other than private, require edit access. Trashed posts are only covered when `trash` is requested.' ),
						),
						'author' => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'minProperties'        => 1,
							'properties'           => array(
								'id'   => array(
									'type'        => 'integer',
									'minimum'     => 1,
									'description' => __( 'The user ID of the author, as `author.id` is returned.' ),
								),
								'slug' => array(
									'type'        => 'string',
									'minLength'   => 1,
									'description' => __( "The author's user slug (nicename), as `author.slug` is returned and as the REST API users endpoint returns it in `slug`." ),
								),
							),
							'description'          => __( 'Filter by the post author, given as the `id` or the `slug` the `author` field of a post returns. An `id` matches the author ID stored on the post, even when that user no longer exists. A `slug` that names no user or a user the current user may not see, as the `author` field reports them, or an `id` and a `slug` that name different users, match no post. Only supported for post types that support authors.' ),
						),
						'parent' => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => __( 'Filter by parent post ID. Only supported for hierarchical post types. Use 0 for top-level posts.' ),
						),
					),
					'description'          => __( 'Selects the posts to return: the readable posts of a `type`, filtered by any of `ids`, `slugs`, `status`, `author`, and `parent`. A post must match every given filter.' ),
				),
				'fields'     => $this->get_fields_input_schema(),
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'page'     => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Page of results to return. Defaults to 1. Requesting a page beyond the last one is an error, unless no post matches the query: then any page returns empty `items` and zero totals. Check `pagination.total_pages` in the result before requesting later pages.' ),
						),
						'per_page' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => self::MAX_PER_PAGE,
							'description' => sprintf(
								/* translators: 1: Default number of posts per page, 2: Maximum number of posts per page. */
								__( 'Maximum number of posts to return per page. Defaults to %1$d, or to %2$d when `where.ids` or `where.slugs` is given.' ),
								self::DEFAULT_PER_PAGE,
								self::MAX_PER_PAGE
							),
						),
					),
					'description'          => __( 'Selects the page of results to return.' ),
				),
			),
		);
	}

	/**
	 * Returns the `data` attributes shared by the create and update input schemas.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $statuses Post status slugs that can be set.
	 * @return array<string, mixed> The attribute schemas, keyed by attribute name.
	 */
	private function get_content_write_properties( array $statuses ): array {
		return array(
			'title'   => array(
				'type'        => 'string',
				'description' => __( 'The raw post title. Only supported for post types that support titles.' ),
			),
			'content' => array(
				'type'        => 'string',
				'description' => __( 'The raw post content, such as block markup. Only supported for post types that support the editor.' ),
			),
			'excerpt' => array(
				'type'        => 'string',
				'description' => __( 'The raw post excerpt. Only supported for post types that support excerpts.' ),
			),
			'status'  => array(
				'type'        => 'string',
				'enum'        => $statuses,
				'description' => __( 'The post status. A status that makes the post visible, such as publish, future, or private, requires the capability to publish posts of the type. A published post with a future date is scheduled, and a scheduled post with a past date is published.' ),
			),
			'slug'    => array(
				'type'        => 'string',
				'minLength'   => 1,
				'description' => __( 'The post slug. It is sanitized as a post slug is, and made unique among the posts of the type that share its parent.' ),
			),
			'date'    => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'minProperties'        => 1,
				'properties'           => array(
					'site' => array(
						'type'        => 'string',
						'format'      => 'date-time',
						'description' => __( "The publication date, in ISO 8601 format. Read in the site's timezone unless it has a UTC offset." ),
					),
					'gmt'  => array(
						'type'        => 'string',
						'format'      => 'date-time',
						'description' => __( 'The publication date, in ISO 8601 format. Read as GMT unless it has a UTC offset.' ),
					),
				),
				'description'          => __( 'The publication date. When both `site` and `gmt` are given, `site` is used.' ),
			),
			'author'  => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'The user ID of the author. Naming a user other than the current one requires the capability to edit the posts of others. Only supported for post types that support authors.' ),
			),
			'parent'  => array(
				'type'        => 'integer',
				'minimum'     => 0,
				'description' => __( 'The parent post ID: a readable post of the same type, or 0 for a top-level post. Only supported for hierarchical post types.' ),
			),
		);
	}

	/**
	 * Builds the `where` schema that names one post, shared by the update and delete abilities.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $post_types  Exposed post type names.
	 * @param string       $description The description of `where`.
	 * @return array<string, mixed> The `where` JSON Schema.
	 */
	private function get_content_where_post_schema( array $post_types, string $description ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'type', 'id' ),
			'properties'           => array(
				'type' => array(
					'type'        => 'string',
					'enum'        => $post_types,
					'description' => __( 'The post type of the post.' ),
				),
				'id'   => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'The post ID.' ),
				),
			),
			'description'          => $description,
		);
	}

	/**
	 * Builds the input schema for the `core/content-create` ability.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $post_types Exposed post type names.
	 * @param list<string> $statuses   Post status slugs that can be set.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_content_create_input_schema( array $post_types, array $statuses ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'data' ),
			'properties'           => array(
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'type' ),
					'properties'           => array_merge(
						array(
							'type' => array(
								'type'        => 'string',
								'enum'        => $post_types,
								'description' => __( 'The post type of the post to create.' ),
							),
						),
						$this->get_content_write_properties( $statuses )
					),
					'description'          => __( 'The post to create. The status defaults to draft.' ),
				),
				'fields' => $this->get_fields_input_schema(),
			),
		);
	}

	/**
	 * Builds the input schema for the `core/content-update` ability.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $post_types Exposed post type names.
	 * @param list<string> $statuses   Post status slugs that can be set.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_content_update_input_schema( array $post_types, array $statuses ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where', 'data' ),
			'properties'           => array(
				'where'  => $this->get_content_where_post_schema( $post_types, __( 'Selects the post to update, by its type and ID.' ) ),
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'minProperties'        => 1,
					'properties'           => $this->get_content_write_properties( $statuses ),
					'description'          => __( 'The attributes to change. Attributes that are left out keep their stored values.' ),
				),
				'fields' => $this->get_fields_input_schema(),
			),
		);
	}

	/**
	 * Builds the input schema for the `core/content-delete` ability.
	 *
	 * @since 7.3.0
	 *
	 * @param list<string> $post_types Exposed post type names.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_content_delete_input_schema( array $post_types ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where' ),
			'properties'           => array(
				'where'     => $this->get_content_where_post_schema( $post_types, __( 'Selects the post to delete, by its type and ID.' ) ),
				'permanent' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether to delete the post permanently instead of moving it to the Trash. Defaults to false.' ),
				),
			),
		);
	}

	/**
	 * Builds the output schema for the `core/content-delete` ability.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The output JSON Schema.
	 */
	private function get_content_delete_output_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'outcome' ),
			'properties'           => array(
				'outcome' => array(
					'type'        => 'string',
					'enum'        => array( 'trashed', 'deleted' ),
					'description' => __( 'What happened to the post: `trashed` when it was moved to the Trash, `deleted` when it was deleted permanently.' ),
				),
			),
		);
	}

	/**
	 * Builds the output schema of a single post, shared by all content abilities.
	 *
	 * Only `id` is required, because it is always returned. The other fields are optional
	 * because the `fields` input lets the caller request any subset, and a field is only
	 * present when its post type supports it.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> The post JSON Schema.
	 */
	private function get_content_output_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id' ),
			'properties'           => $this->get_post_properties(),
		);
	}

	/**
	 * Builds the output schema for the `core/content-query` ability.
	 *
	 * Every request returns the same shape: a page of posts in `items`, and the totals
	 * across all pages in `pagination`.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> The output JSON Schema.
	 */
	private function get_content_query_output_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'items', 'pagination' ),
			'properties'           => array(
				'items'      => array(
					'type'        => 'array',
					'description' => __( 'The readable posts on the requested page, ordered by post date, newest first. Can be empty even when `pagination.total` is not zero, because the totals can count matching posts the current user cannot read.' ),
					'items'       => $this->get_content_output_schema(),
				),
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'total', 'total_pages' ),
					'properties'           => array(
						'total'       => array(
							'type'        => 'integer',
							'description' => __( 'Total number of posts matching the underlying query, across all pages. May exceed the number of returned posts when row-level permission checks withhold some of them.' ),
						),
						'total_pages' => array(
							'type'        => 'integer',
							'description' => __( 'Total number of query result pages available for the underlying query. May include pages whose rows are withheld by row-level permission checks.' ),
						),
					),
					'description'          => __( 'The totals of the request, across all pages.' ),
				),
			),
		);
	}

	/**
	 * Returns a user when the current user may see them as an author.
	 *
	 * The current user may see themselves, the users they can edit, every user when they can
	 * list users, and otherwise the authors of posts they can read in a publicly viewable post
	 * type exposed to abilities: this ability returns those posts with their author, so the
	 * author is already in view. Which post types the REST API exposes plays no part. A user
	 * the current user may not see is reported like a missing one.
	 *
	 * The `author` field and the `author` slug filter share this rule, so the filter never
	 * confirms a slug the field withholds. The post count is kept per author for the call,
	 * since count_user_posts() is not cached.
	 *
	 * @since 7.2.0
	 *
	 * @param int $user_id The user ID.
	 * @return WP_User|null The user, or null when they no longer exist or may not be seen.
	 */
	private function get_visible_author( int $user_id ): ?WP_User {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		if ( get_current_user_id() === $user_id || current_user_can( 'list_users' ) || current_user_can( 'edit_user', $user_id ) ) {
			return $user;
		}

		if ( ! isset( $this->visible_authors[ $user_id ] ) ) {
			$post_types                        = array_values( array_filter( get_post_types( array( 'show_in_abilities' => true ), 'names' ), 'is_post_type_viewable' ) );
			$this->visible_authors[ $user_id ] = (int) count_user_posts( $user_id, $post_types ) > 0;
		}

		return $this->visible_authors[ $user_id ] ? $user : null;
	}

	/**
	 * Formats a post into the ability output shape.
	 *
	 * As the REST posts controller does, the post is set up as the global post while its
	 * fields are built, so filters that rely on loop globals, including title filters, see
	 * the requested post. For an editor of a password-protected post, the cookie-based
	 * password gate is also suspended, so rendered fields resolve to real values instead of
	 * protected-post placeholders. The field projection itself is delegated to
	 * {@see self::build_post_fields()}.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Post      $post   The post object.
	 * @param list<string> $fields The requested field paths.
	 * @return array<string, mixed> The formatted post data.
	 */
	private function format_post( WP_Post $post, array $fields ): array {
		$can_edit          = current_user_can( 'edit_post', $post->ID );
		$password_required = post_password_required( $post );
		$unlock_password   = $password_required && $can_edit;
		$previous_context  = $this->get_post_context();
		$the_post_fired    = did_action( 'the_post' ) > 0;

		/*
		 * The filter unlocks only posts the current user can edit, mirroring the REST posts
		 * controller's check_password_required(): an unconditional bypass (e.g. __return_false)
		 * would also expose other protected posts that the content filter may render, such as
		 * posts pulled in by a Query Loop block. The closure is kept in a variable, so the same
		 * instance can be removed again.
		 */
		$allow_password_content = function ( $required, $checked_post ): bool {
			return $this->allow_password_content( $required, $checked_post );
		};

		/*
		 * Set up both inside the try block and undo them in the finally block, so a throw from
		 * a `the_post` callback or mid-render cannot leave the password gate disabled or the
		 * global post pointing at this post for the rest of the request.
		 */
		try {
			$this->set_up_post_context( $post );
			if ( $unlock_password ) {
				add_filter( 'post_password_required', $allow_password_content, 10, 2 );
			}

			return $this->build_post_fields( $post, $fields, $can_edit, $password_required && ! $can_edit );
		} finally {
			if ( $unlock_password ) {
				remove_filter( 'post_password_required', $allow_password_content, 10 );
			}

			$this->restore_post_context( $previous_context, $the_post_fired );
		}
	}

	/**
	 * Builds the requested field projection for a post.
	 *
	 * Fields are selected as the REST API's `_fields` parameter selects them, with
	 * {@see self::is_field_requested()}. The `id` is always included, whether or not it
	 * was requested, so every returned post can be identified. This also means a post is
	 * never empty, so it always encodes as a JSON object.
	 *
	 * Only the fields that the post type supports and the current user can see are included,
	 * and a field none of whose requested sub-fields remain is left out, so it never encodes
	 * as an empty JSON array. Raw sub-fields are edit-context fields, included only when the
	 * current user can edit the post, whether they were requested on their own or through
	 * their field. Rendered sub-fields are read-context fields and are withheld for
	 * password-protected posts unless the current user can edit the post, mirroring the REST
	 * API behavior.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Post      $post         The post object.
	 * @param list<string> $fields       The requested field paths.
	 * @param bool         $can_edit     Whether the current user can edit the post.
	 * @param bool         $is_protected Whether rendered fields must be withheld as password-protected.
	 * @return array<string, mixed> The formatted post data.
	 */
	private function build_post_fields( WP_Post $post, array $fields, bool $can_edit, bool $is_protected ): array {
		$post_type = $post->post_type;
		$data      = array( 'id' => (int) $post->ID );

		if ( $this->is_field_requested( 'type', $fields ) ) {
			$data['type'] = $post_type;
		}
		if ( $this->is_field_requested( 'status', $fields ) ) {
			$data['status'] = $post->post_status;
		}

		foreach ( array( 'date', 'modified' ) as $field ) {
			$dates = array();
			if ( $this->is_field_requested( "{$field}.site", $fields ) ) {
				$dates['site'] = $this->format_date( $post, $field, false );
			}
			if ( $this->is_field_requested( "{$field}.gmt", $fields ) ) {
				$dates['gmt'] = $this->format_date( $post, $field, true );
			}
			if ( ! empty( $dates ) ) {
				$data[ $field ] = $dates;
			}
		}

		if ( $this->is_field_requested( 'slug', $fields ) ) {
			$data['slug'] = $post->post_name;
		}
		if ( $this->is_field_requested( 'link', $fields ) ) {
			$data['link'] = (string) get_permalink( $post );
		}

		/*
		 * The rendered fields share a shape: `raw` for a user who can edit the post,
		 * `rendered`, and `protected` for the excerpt and content, whose rendered values are
		 * withheld for a password-protected post. The title is never withheld, so it has no
		 * `protected` sub-field. Each field lists the feature the post type must support, the
		 * post property holding the raw value, the method rendering it, and whether it can be
		 * protected.
		 */
		$rendered_fields = array(
			'title'   => array( 'title', 'post_title', 'get_title', false ),
			'excerpt' => array( 'excerpt', 'post_excerpt', 'get_rendered_excerpt', true ),
			'content' => array( 'editor', 'post_content', 'get_rendered_content', true ),
		);
		foreach ( $rendered_fields as $field => $spec ) {
			list( $feature, $property, $render, $protectable ) = $spec;
			if ( ! $this->is_field_requested( $field, $fields ) || ! post_type_supports( $post_type, $feature ) ) {
				continue;
			}

			$value = array();
			if ( $can_edit && $this->is_field_requested( "{$field}.raw", $fields ) ) {
				$value['raw'] = $post->$property;
			}
			if ( $this->is_field_requested( "{$field}.rendered", $fields ) ) {
				$value['rendered'] = ( $protectable && $is_protected ) ? '' : $this->$render( $post );
			}
			if ( $protectable && $this->is_field_requested( "{$field}.protected", $fields ) ) {
				$value['protected'] = (bool) $post->post_password;
			}
			if ( ! empty( $value ) ) {
				$data[ $field ] = $value;
			}
		}

		if ( $this->is_field_requested( 'author', $fields ) && post_type_supports( $post_type, 'author' ) ) {
			$user = null;
			if ( $this->is_field_requested( 'author.name', $fields ) || $this->is_field_requested( 'author.slug', $fields ) ) {
				$user = $this->get_visible_author( (int) $post->post_author );
			}
			$author = array();
			if ( $this->is_field_requested( 'author.id', $fields ) ) {
				$author['id'] = (int) $post->post_author;
			}
			if ( $this->is_field_requested( 'author.name', $fields ) ) {
				$author['name'] = $user ? $user->display_name : null;
			}
			if ( $this->is_field_requested( 'author.slug', $fields ) ) {
				$author['slug'] = $user ? $user->user_nicename : null;
			}
			if ( ! empty( $author ) ) {
				$data['author'] = $author;
			}
		}

		if ( $this->is_field_requested( 'parent', $fields ) && is_post_type_hierarchical( $post_type ) ) {
			$data['parent'] = (int) $post->post_parent;
		}

		return $data;
	}

	/**
	 * Filters {@see post_password_required()} to unlock only posts the current user can edit.
	 *
	 * Added by {@see self::format_post()} while formatting a password-protected post the
	 * current user can edit, so rendered fields resolve to real values without also unlocking
	 * other protected posts that the content filter may render. Mirrors the REST posts
	 * controller's check_password_required().
	 *
	 * @since 7.2.0
	 *
	 * @param bool         $required Whether the post currently requires a password.
	 * @param WP_Post|null $post     The post being checked, or null if no post is found.
	 * @return bool Whether the post still requires a password.
	 */
	private function allow_password_content( $required, $post ): bool {
		if ( ! $required || ! $post instanceof WP_Post ) {
			return (bool) $required;
		}

		return ! current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Returns the post title with the protected/private prefixes stripped.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Post $post The post object.
	 * @return string The post title.
	 */
	private function get_title( WP_Post $post ): string {
		$strip = static function (): string {
			return '%s';
		};
		add_filter( 'protected_title_format', $strip );
		add_filter( 'private_title_format', $strip );

		/*
		 * The format filters are removed in a finally block so a throw from a title
		 * filter cannot leave them attached for the rest of the request.
		 */
		try {
			$title = get_the_title( $post );

			/*
			 * A title filter that returns a non-string would fail the return type, so guard
			 * it as the excerpt and content are.
			 */
			return is_string( $title ) ? $title : '';
		} finally {
			remove_filter( 'protected_title_format', $strip );
			remove_filter( 'private_title_format', $strip );
		}
	}

	/**
	 * Returns the post excerpt transformed for display.
	 *
	 * Applies the `get_the_excerpt` and `the_excerpt` filter chains, as the REST posts
	 * controller does. {@see self::format_post()} has set the post up as the global post.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Post $post The post object.
	 * @return string Rendered post excerpt.
	 */
	private function get_rendered_excerpt( WP_Post $post ): string {
		/** This filter is documented in wp-includes/post-template.php */
		$excerpt = apply_filters( 'get_the_excerpt', $post->post_excerpt, $post );

		/** This filter is documented in wp-includes/post-template.php */
		$excerpt = apply_filters( 'the_excerpt', $excerpt );

		return is_string( $excerpt ) ? $excerpt : '';
	}

	/**
	 * Returns post content transformed for display.
	 *
	 * Applies `the_content`, as the REST posts controller does. {@see self::format_post()}
	 * has set the post up as the global post.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Post $post The post object.
	 * @return string Rendered post content.
	 */
	private function get_rendered_content( WP_Post $post ): string {
		/** This filter is documented in wp-includes/post-template.php */
		$content = apply_filters( 'the_content', $post->post_content );

		return is_string( $content ) ? $content : '';
	}

	/**
	 * Returns the global post context, so it can be restored after a post is rendered.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> The loop globals, keyed by name, leaving out those that are not set.
	 */
	private function get_post_context(): array {
		/*
		 * Copy each global by value. A calling function that binds a global with `global`,
		 * as load_template() and WP_Block::render() do, makes it a reference, which
		 * array_intersect_key( $GLOBALS, ... ) would keep. The saved copy would then follow
		 * the global to the rendered post, and the restore would put that post back.
		 */
		$context = array();
		foreach ( self::LOOP_GLOBALS as $name ) {
			if ( ! array_key_exists( $name, $GLOBALS ) ) {
				continue;
			}

			$context[ $name ] = $GLOBALS[ $name ];
		}

		return $context;
	}

	/**
	 * Sets up the global post context for rendering a post.
	 *
	 * Sets the global post and calls setup_postdata(), as the REST posts controller does
	 * before it renders a post, so filters that rely on loop globals render against the
	 * requested post.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Post $post The post to render.
	 */
	private function set_up_post_context( WP_Post $post ): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporarily mirrors REST post context for rendering.
		$GLOBALS['post'] = $post;
		setup_postdata( $post );
	}

	/**
	 * Restores the global post context saved by {@see self::get_post_context()}.
	 *
	 * Each loop global gets its previous value back, or is unset again when it was not set.
	 * wp_reset_postdata() alone is not enough: it does nothing when the main query has no
	 * post, which would leave the rendered post's data in the globals. When a post was set
	 * up before, setup_postdata() first runs for it again, as wp_reset_postdata() would, so
	 * callbacks on the `the_post` action can restore their own globals too. The saved values
	 * are put back even when such a callback throws. A global post that was never set up
	 * keeps the loop globals that setup_postdata() gives it. The `the_post` count is reset
	 * only when the ability was the first thing to fire that action and no global post was
	 * set, so rendering a post before the main query does not count as starting the loop.
	 *
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $previous_context The loop globals that get_post_context() returned.
	 * @param bool                 $the_post_fired   Whether the `the_post` action had fired before the ability set up the post.
	 */
	private function restore_post_context( array $previous_context, bool $the_post_fired ): void {
		$previous_post = $previous_context['post'] ?? null;

		/*
		 * A global post that was never set up, such as the main post before the loop
		 * starts, keeps what setup_postdata() gives it below. Do not put back the values
		 * saved before: `the_post` has fired then, so get_the_content() called without a
		 * post, as the Post Content block and the_content() outside the loop do, reads the
		 * loop globals instead of the post, and an unset `$pages` makes it throw a
		 * TypeError that breaks the page being rendered.
		 */
		$restore_saved_values = ! $previous_post instanceof WP_Post || is_array( $previous_context['pages'] ?? null );

		try {
			if ( $previous_post instanceof WP_Post ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the previous global post context.
				$GLOBALS['post'] = $previous_post;
				setup_postdata( $previous_post );
			}
		} finally {
			if ( $restore_saved_values ) {
				foreach ( self::LOOP_GLOBALS as $name ) {
					if ( array_key_exists( $name, $previous_context ) ) {
						$GLOBALS[ $name ] = $previous_context[ $name ];
					} else {
						unset( $GLOBALS[ $name ] );
					}
				}
			}

			/*
			 * Only reset the `the_post` count when the ability was the first thing to fire it
			 * and no global post was set. Setting up the rendered post counted the action, and
			 * once it has fired, get_the_content() called without a post reads the loop globals
			 * unset above instead of the post. A global post set later without being set up,
			 * as WP::register_globals() sets the main post after the ability runs on `init`,
			 * would then make it throw a TypeError. When a loop had already fired the action,
			 * or a post was set, the count is left alone.
			 */
			if ( ! $previous_post instanceof WP_Post && ! $the_post_fired ) {
				unset( $GLOBALS['wp_actions']['the_post'] );
			}
		}
	}

	/**
	 * Formats a post date field as an ISO 8601 string, in the site's timezone or in GMT.
	 *
	 * In GMT, it reads the stored GMT date, deriving it from the local date when it is
	 * missing (e.g. drafts), mirroring the REST posts controller.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Post $post  The post object.
	 * @param string  $field Either 'date' or 'modified'.
	 * @param bool    $gmt   Whether to format the date in GMT instead of the site's timezone.
	 * @return string|null The ISO 8601 date, or null if unavailable.
	 */
	private function format_date( WP_Post $post, string $field, bool $gmt ): ?string {
		$datetime = $gmt ? get_post_datetime( $post, $field, 'gmt' ) : false;
		if ( ! $datetime ) {
			$datetime = get_post_datetime( $post, $field );
		}

		if ( ! $datetime ) {
			return null;
		}

		/*
		 * A malformed stored date can still parse: a zero month formats with a negative
		 * year. The `date-time` format rejects it, which would fail output validation for
		 * the whole call, so it is reported as null too.
		 */
		$date = ( $gmt ? $datetime->setTimezone( new DateTimeZone( 'UTC' ) ) : $datetime )->format( 'c' );

		return false !== rest_parse_date( $date ) ? $date : null;
	}

	/**
	 * Builds the ability output for a page of formatted posts.
	 *
	 * @since 7.2.0
	 *
	 * @param array<int, array<string, mixed>> $items       The formatted posts on the page.
	 * @param int                              $total       Total number of posts across all pages.
	 * @param int                              $total_pages Total number of pages.
	 * @return array<string, mixed> The ability output.
	 */
	private function prepare_output( array $items, int $total, int $total_pages ): array {
		return array(
			'items'      => $items,
			'pagination' => array(
				'total'       => $total,
				'total_pages' => $total_pages,
			),
		);
	}

	/**
	 * Builds the error for a page past the last page of results.
	 *
	 * A page that does not exist is reported as not found rather than as an empty list,
	 * so callers can tell it apart from a request that matches no posts.
	 *
	 * @since 7.2.0
	 *
	 * @return WP_Error The invalid page number error.
	 */
	private function invalid_page_number_error(): WP_Error {
		return new WP_Error(
			'content_invalid_page_number',
			__( 'The page number requested is larger than the number of pages available.' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Builds the error for a filter that cannot be honored.
	 *
	 * As in the REST API's `rest_invalid_param` errors, the error data maps the filter's
	 * input path to the message under `params`, so callers can tell which filter failed
	 * without parsing the translated message.
	 *
	 * @since 7.2.0
	 *
	 * @param string $param   The filter's input path, such as `where[ids]`.
	 * @param string $message The error message.
	 * @return WP_Error The invalid filter error.
	 */
	private function invalid_filter_error( string $param, string $message ): WP_Error {
		return new WP_Error(
			'content_invalid_filter',
			$message,
			array(
				'status' => 400,
				'params' => array( $param => $message ),
			)
		);
	}

	/**
	 * Builds the error for a post that cannot be found.
	 *
	 * Returned alike for a missing post, a post of another type, and a post type that is not
	 * exposed to abilities, so it never tells which one applies.
	 *
	 * @since 7.3.0
	 *
	 * @return WP_Error The not found error.
	 */
	private function not_found_error(): WP_Error {
		return new WP_Error(
			'content_not_found',
			__( 'The requested content was not found.' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Builds the error for a `data` attribute that cannot be applied.
	 *
	 * The error data maps the attribute's input path to the message under `params`, so
	 * callers can tell which attribute failed without parsing the translated message.
	 *
	 * @since 7.3.0
	 *
	 * @param string $param   The attribute's input path, such as `data[title]`.
	 * @param string $message The error message.
	 * @return WP_Error The invalid attribute error.
	 */
	private function invalid_attribute_error( string $param, string $message ): WP_Error {
		return new WP_Error(
			'content_invalid_attribute',
			$message,
			array(
				'status' => 400,
				'params' => array( $param => $message ),
			)
		);
	}

	/**
	 * Builds the error for a delete or trash call that failed or could not be confirmed.
	 *
	 * @since 7.3.0
	 *
	 * @return WP_Error The delete failed error.
	 */
	private function delete_failed_error(): WP_Error {
		return new WP_Error(
			'content_delete_failed',
			__( 'The post could not be deleted.' ),
			array( 'status' => 500 )
		);
	}
}
