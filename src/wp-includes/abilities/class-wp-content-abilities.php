<?php
/**
 * Abilities API: WP_Content_Abilities class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.1.0
 */

declare( strict_types = 1 );

/**
 * Core class used to register content-related abilities.
 *
 * Registers the read-only `core/read-content` ability, which retrieves readable posts of a
 * post type exposed to abilities via `show_in_abilities`. Supports fetching a single
 * readable post by ID or by post type and slug, or querying multiple readable posts filtered
 * by post type, status, author, parent, or included IDs. Raw fields are only returned for
 * posts the current user can edit.
 *
 * The class is intentionally structured around shared building blocks (exposed post type
 * discovery, schema generation, per-post formatting and permission checks) so a future
 * write-oriented `core/manage-content` ability can reuse them.
 *
 * This class is part of WordPress' internal implementation of the core abilities and is
 * not part of the public API. It may be changed or removed at any time without notice.
 * Do not use it directly or rely on its existence.
 *
 * @since 7.1.0
 *
 * @access private
 */
final class WP_Content_Abilities {

	/**
	 * The ability category used for content abilities.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	private const CATEGORY = 'content';

	/**
	 * Default number of posts returned per page in query mode.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	private const DEFAULT_PER_PAGE = 10;

	/**
	 * Maximum number of posts returned per page in query mode.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Fields that expose edit-context post data.
	 *
	 * Requests that explicitly include any of these fields require edit access.
	 *
	 * @since 7.1.0
	 * @var list<string>
	 */
	private array $edit_fields = array(
		'title_raw',
		'excerpt_raw',
		'content_raw',
	);

	/**
	 * Fields whose rendering may read post meta or terms.
	 *
	 * Requests that include any of these prime the post meta and term caches for the
	 * page. Other rendered fields, such as the title, do not need that cache priming.
	 *
	 * @since 7.1.0
	 * @var list<string>
	 */
	private array $cache_priming_fields = array(
		'excerpt_rendered',
		'content_rendered',
	);

	/**
	 * Cached post field definitions, keyed by field name in output order.
	 *
	 * @since 7.1.0
	 * @var array<string, mixed>|null
	 */
	private ?array $post_properties = null;

	/**
	 * Default fields returned when the caller does not request a field subset.
	 *
	 * @since 7.1.0
	 * @var list<string>
	 */
	private array $default_fields = array(
		'id',
		'post_type',
		'status',
		'date',
		'slug',
		'title_rendered',
	);

	/**
	 * Registers all content abilities.
	 *
	 * Must run on the `wp_abilities_api_init` hook.
	 *
	 * @since 7.1.0
	 */
	public function register(): void {
		$this->register_read_content();

		/*
		 * A future write-oriented ability can be registered here, reusing the shared
		 * helpers below (get_exposed_post_types(), format_post(), check_permission()):
		 *
		 *     $this->register_manage_content();
		 */
	}

	/**
	 * Registers the read-only `core/read-content` ability.
	 *
	 * @since 7.1.0
	 */
	private function register_read_content(): void {
		/*
		 * Post types must be registered with `show_in_abilities` before the ability is
		 * registered so they are included in its input schema.
		 */
		$post_types = array_keys( $this->get_exposed_post_types() );
		if ( empty( $post_types ) ) {
			return;
		}

		/*
		 * Internal statuses (e.g. `inherit`) are excluded, so post types that rely on
		 * them (attachments) are only reachable by ID. Revisit if such a post type is
		 * ever exposed via `show_in_abilities`.
		 */
		$statuses = array_values( get_post_stati( array( 'internal' => false ) ) );

		wp_register_ability(
			'core/read-content',
			array(
				'label'               => __( 'Read Content' ),
				'description'         => __( 'Reads content from post types exposed to abilities. Single-post lookups by ID or by post type and slug return the post object directly. Query mode returns readable posts filtered by post type, status, author, parent, or included IDs. Requires an authenticated user. Lookups and filters are exact-match only; the ability does not perform full-text search.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_read_content_input_schema( $post_types, $statuses ),
				'output_schema'       => $this->get_read_content_output_schema(),
				'execute_callback'    => array( $this, 'execute_read_content' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
						// MCP clients assume open-world (may reach external systems) when the
						// hint is absent; this ability only reads the local database.
						'open_world'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Permission callback for the `core/read-content` ability.
	 *
	 * This gate is the authoritative permission decision for single-post modes: it
	 * resolves the requested post and denies missing, mismatched, or unreadable posts
	 * before execution. Query mode is only gated coarsely here (collection status
	 * capabilities); {@see self::execute_read_content()} enforces row-level read/edit
	 * permissions, since individual rows are unknown until the query runs. Requests
	 * that explicitly ask for edit-context fields require edit access before execution.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	public function check_permission( $input = array() ): bool {
		$input   = rest_sanitize_object( $input );
		$exposed = $this->get_exposed_post_types();

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$requires_edit = $this->has_explicit_edit_fields( $input );

		// Single-post mode (by ID).
		if ( ! empty( $input['id'] ) ) {
			$post = get_post( $this->input_int( $input['id'] ) );

			if ( ! $post
				|| ! isset( $exposed[ $post->post_type ] )
				|| ( ! empty( $input['post_type'] ) && $post->post_type !== $input['post_type'] )
			) {
				return false;
			}

			return $requires_edit ? current_user_can( 'edit_post', $post->ID ) : $this->check_read_permission( $post );
		}

		// Single-post mode (by slug) and query mode require an exposed post type.
		$post_type = isset( $input['post_type'] ) && is_string( $input['post_type'] ) ? $input['post_type'] : '';
		if ( '' === $post_type || ! isset( $exposed[ $post_type ] ) ) {
			return false;
		}

		if ( isset( $input['slug'] ) && is_string( $input['slug'] ) && '' !== $input['slug'] ) {
			$post = $this->get_post_by_slug( $post_type, $input['slug'] );
			if ( ! $post ) {
				return false;
			}

			return $requires_edit ? current_user_can( 'edit_post', $post->ID ) : $this->check_read_permission( $post );
		}

		$post_type_object = $exposed[ $post_type ];
		if ( $requires_edit ) {
			return current_user_can( $this->post_type_cap( $post_type_object, 'edit_posts' ) ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability is resolved from the post type's capability object.
		}

		return $this->can_query_statuses( $input, $post_type_object );
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
	 * Parses a raw filter value into an integer of at least a minimum, or null when invalid.
	 *
	 * Unlike {@see self::input_int()}, which coerces any non-integer to 0, this rejects
	 * values that are not integers so a filter whose value cannot be honored can fail
	 * loudly instead of silently widening the query: `author => 0` drops the author
	 * filter (matching every author) and `post_parent => 0` becomes a top-level query.
	 * Accepts native integers and unsigned integer strings, mirroring how the JSON
	 * Schema `integer` type and the query-string transport respectively deliver them.
	 *
	 * @since 7.1.0
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

		return null;
	}

	/**
	 * Resolves a capability name from a post type's capability map.
	 *
	 * The capability map is a plain object with untyped properties, so guard the
	 * lookup and fail closed with `do_not_allow` when the name cannot be resolved.
	 *
	 * @since 7.1.0
	 *
	 * @param \WP_Post_Type $post_type_object The post type object.
	 * @param string        $capability       The capability key, e.g. 'edit_posts'.
	 * @return string The resolved capability name, or 'do_not_allow' when unresolved.
	 */
	private function post_type_cap( \WP_Post_Type $post_type_object, string $capability ): string {
		$cap = $post_type_object->cap->$capability ?? null;

		return is_string( $cap ) && '' !== $cap ? $cap : 'do_not_allow';
	}

	/**
	 * Parses a raw list input into a list of strings.
	 *
	 * A GET request delivers list inputs as scalar/CSV strings; this parses them the
	 * same way schema validation did (wp_parse_list) so they are honored regardless of
	 * transport, until core sanitizes ability input itself.
	 *
	 * @since 7.1.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @param string       $key   The input key holding the list.
	 * @return list<string> The parsed string values; empty when absent or unparseable.
	 */
	private function parse_list_input( array $input, string $key ): array {
		$value = $input[ $key ] ?? null;
		if ( ! is_array( $value ) && ! is_string( $value ) ) {
			return array();
		}

		return array_values( array_filter( wp_parse_list( $value ), 'is_string' ) );
	}

	/**
	 * Checks whether the input explicitly requests edit-context fields.
	 *
	 * Omitted fields are not treated as edit-intent: default responses include the
	 * fields visible for each individual post.
	 *
	 * @since 7.1.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return bool True if edit-context fields were explicitly requested.
	 */
	private function has_explicit_edit_fields( array $input ): bool {
		return array() !== array_intersect( $this->edit_fields, $this->parse_list_input( $input, 'fields' ) );
	}

	/**
	 * Checks whether the current user may query the requested statuses.
	 *
	 * This mirrors the REST posts controller's conservative collection-status gate:
	 * requesting non-default statuses requires edit access, except `private`, which
	 * may be queried by users who can read private posts.
	 *
	 * @since 7.1.0
	 *
	 * @param array<mixed>  $input            The ability input.
	 * @param \WP_Post_Type $post_type_object The post type object.
	 * @return bool True if the requested statuses may be queried.
	 */
	private function can_query_statuses( array $input, \WP_Post_Type $post_type_object ): bool {
		foreach ( $this->normalize_statuses( $input ) as $status ) {
			if ( 'publish' === $status ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability is resolved from the post type's capability object.
			if ( 'private' === $status && current_user_can( $this->post_type_cap( $post_type_object, 'read_private_posts' ) ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability is resolved from the post type's capability object.
			if ( current_user_can( $this->post_type_cap( $post_type_object, 'edit_posts' ) ) ) {
				continue;
			}

			return false;
		}

		return true;
	}

	/**
	 * Checks if a post can be read by the current user.
	 *
	 * Mirrors the REST posts controller's read permission, while keeping this ability
	 * authenticated-only via {@see self::check_permission()}.
	 *
	 * @since 7.1.0
	 *
	 * @param \WP_Post         $post             Post object.
	 * @param array<int, true> $checked_post_ids Post IDs already checked while walking inherited parents.
	 * @return bool Whether the post can be read.
	 */
	private function check_read_permission( WP_Post $post, array $checked_post_ids = array() ): bool {
		if ( isset( $checked_post_ids[ $post->ID ] ) ) {
			return false;
		}

		$checked_post_ids[ $post->ID ] = true;

		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type instanceof \WP_Post_Type || empty( $post_type->show_in_abilities ) ) {
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
		if ( ! $post_status_object instanceof \stdClass ) {
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
		 * ability fail-closed for missing parents or parent loops.
		 */
		if (
			'inherit' === $post->post_status &&
			$post->post_parent > 0 &&
			(int) $post->post_parent !== (int) $post->ID
		) {
			$parent = get_post( $post->post_parent );
			if ( $parent instanceof WP_Post ) {
				return $this->check_read_permission( $parent, $checked_post_ids );
			}
		}

		return false;
	}

	/**
	 * Executes the `core/read-content` ability.
	 *
	 * {@see WP_Ability::execute()} always runs {@see self::check_permission()} first, so the
	 * single-post modes only re-validate the lookup itself: existence, exposure, and a
	 * matching post type. Query mode still filters every row by read or edit permission,
	 * because the gate cannot resolve rows before the query runs.
	 *
	 * A post is returned as an empty object when its field projection is empty, so callers
	 * must not assume array access on a post. See {@see self::to_output_post()}.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return array<string, mixed>|\stdClass|\WP_Error A single post, a `posts` list with totals in query mode, or a WP_Error.
	 */
	public function execute_read_content( $input = array() ) {
		$input         = rest_sanitize_object( $input );
		$exposed       = $this->get_exposed_post_types();
		$fields        = $this->normalize_fields( $input );
		$requires_edit = $this->has_explicit_edit_fields( $input );

		// Single-post mode (by ID).
		if ( ! empty( $input['id'] ) ) {
			$post = get_post( $this->input_int( $input['id'] ) );

			if ( ! $post
				|| ! isset( $exposed[ $post->post_type ] )
				|| ( ! empty( $input['post_type'] ) && $post->post_type !== $input['post_type'] )
			) {
				return $this->not_found_error();
			}

			return $this->to_output_post( $this->format_post( $post, $fields ) );
		}

		// Single-post mode (by slug) and query mode.
		$post_type = isset( $input['post_type'] ) && is_string( $input['post_type'] ) ? $input['post_type'] : '';
		if ( '' === $post_type || ! isset( $exposed[ $post_type ] ) ) {
			return $this->not_found_error();
		}

		if ( isset( $input['slug'] ) && is_string( $input['slug'] ) && '' !== $input['slug'] ) {
			$post = $this->get_post_by_slug( $post_type, $input['slug'] );

			if ( ! $post ) {
				return $this->not_found_error();
			}

			return $this->to_output_post( $this->format_post( $post, $fields ) );
		}

		/*
		 * REST only registers the equivalent collection filters for post types that
		 * support them; a shared input schema cannot express that per post type. On
		 * transports that skip schema validation a malformed value would otherwise
		 * coerce to a benign default and silently *widen* the query (`author => 0`
		 * drops the author filter, an empty `post__in` is ignored, `post_parent => 0`
		 * becomes a top-level query). Reject unsupported filters and invalid filter
		 * values loudly so a filter that cannot be honored fails closed instead.
		 */
		$parent = null;
		if ( isset( $input['parent'] ) ) {
			if ( ! is_post_type_hierarchical( $post_type ) ) {
				return new WP_Error(
					'content_invalid_filter',
					__( 'The parent filter is only supported for hierarchical post types.' ),
					array( 'status' => 400 )
				);
			}

			$parent = $this->parse_filter_int( $input['parent'], 0 );
			if ( null === $parent ) {
				return new WP_Error(
					'content_invalid_filter',
					__( 'The parent filter must be a non-negative integer.' ),
					array( 'status' => 400 )
				);
			}
		}

		$author = null;
		if ( isset( $input['author'] ) ) {
			if ( ! post_type_supports( $post_type, 'author' ) ) {
				return new WP_Error(
					'content_invalid_filter',
					__( 'The author filter is only supported for post types that support authors.' ),
					array( 'status' => 400 )
				);
			}

			$author = $this->parse_filter_int( $input['author'], 1 );
			if ( null === $author ) {
				return new WP_Error(
					'content_invalid_filter',
					__( 'The author filter must be a positive integer.' ),
					array( 'status' => 400 )
				);
			}
		}

		$include = $this->normalize_include( $input );

		/*
		 * An include filter that was supplied but parsed to no valid IDs must not fall
		 * through to an unrestricted query: WP_Query ignores an empty `post__in`, which
		 * would return every post of the type — the opposite of the caller's intent.
		 */
		if ( isset( $input['include'] ) && array() === $include ) {
			return new WP_Error(
				'content_invalid_filter',
				__( 'The include filter must list one or more valid post IDs.' ),
				array( 'status' => 400 )
			);
		}

		$per_page = $this->normalize_per_page( $input, $include );
		$page     = isset( $input['page'] ) ? max( 1, $this->input_int( $input['page'] ) ) : 1;

		$prime_post_caches = $this->should_prime_post_caches( $fields );

		// `orderby` is left unset, which orders by `post_date` descending, matching the
		// default of the REST posts controller.
		$query_args = array(
			'post_type'              => $post_type,
			'post_status'            => $this->normalize_statuses( $input ),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'perm'                   => $requires_edit ? 'editable' : 'readable',
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => $prime_post_caches,
			'update_post_term_cache' => $prime_post_caches,
		);

		if ( array() !== $include ) {
			$query_args['post__in'] = $include;
		}

		if ( null !== $author ) {
			$query_args['author'] = $author;
		}

		if ( null !== $parent ) {
			$query_args['post_parent'] = $parent;
		}

		$query       = new WP_Query( $query_args );
		$total       = $this->get_query_total( $query, $query_args, $page );
		$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 0;

		/*
		 * Paging past the last page is a caller error rather than an empty collection, so
		 * report it instead of returning a bare empty list. A genuinely empty result set
		 * still returns zero totals and no error.
		 */
		if ( $total > 0 && $page > $total_pages ) {
			return new WP_Error(
				'content_invalid_page_number',
				__( 'The page number requested is larger than the number of pages available.' ),
				array( 'status' => 400 )
			);
		}

		/*
		 * Prime the author caches with a single query instead of one user lookup
		 * per post, mirroring the REST posts controller.
		 */
		if ( in_array( 'author', $fields, true ) && post_type_supports( $post_type, 'author' ) ) {
			$query_posts = array_filter(
				$query->posts,
				static function ( $queried_post ): bool {
					return $queried_post instanceof WP_Post;
				}
			);
			update_post_author_caches( $query_posts );
		}

		$posts = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			if ( $requires_edit && ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			if ( ! $requires_edit && ! $this->check_read_permission( $post ) ) {
				continue;
			}
			// Keep rows whose field projection is empty so a caller can still count them.
			$posts[] = $this->to_output_post( $this->format_post( $post, $fields ) );
		}

		/*
		 * Mirror the REST posts controller: totals come from the underlying WP_Query,
		 * while row-level permission checks above may withhold individual returned rows.
		 */
		return array(
			'posts'       => $posts,
			'total'       => $total,
			'total_pages' => $total_pages,
		);
	}

	/**
	 * Normalizes the requested per-page value to the supported bounds.
	 *
	 * An explicit `per_page` always wins. Otherwise an `include` request pages to the
	 * number of requested IDs, so a caller loading a known set of posts receives all of
	 * them in one call rather than silently losing the ones past the default page size.
	 * The input schema caps `include` at {@see self::MAX_PER_PAGE} so it always fits.
	 *
	 * @since 7.1.0
	 *
	 * @param array<mixed> $input       The ability input.
	 * @param list<int>    $include_ids Normalized included post IDs; empty when not requested.
	 * @return int The clamped per-page value.
	 */
	private function normalize_per_page( array $input, array $include_ids = array() ): int {
		if ( isset( $input['per_page'] ) ) {
			return max( 1, min( self::MAX_PER_PAGE, $this->input_int( $input['per_page'] ) ) );
		}

		if ( array() !== $include_ids ) {
			return max( 1, min( self::MAX_PER_PAGE, count( $include_ids ) ) );
		}

		return self::DEFAULT_PER_PAGE;
	}

	/**
	 * Returns the query total, recovering it when WP_Query skipped the count.
	 *
	 * WP_Query leaves `found_posts` at 0 when a requested page has no rows. Re-run a
	 * minimal unpaged query so the caller can distinguish an out-of-range page from
	 * an empty result set, matching the REST posts controller behavior.
	 *
	 * @since 7.1.0
	 *
	 * @param \WP_Query    $query      The executed query.
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
	 * @since 7.1.0
	 *
	 * @param list<string> $fields The requested field names.
	 * @return bool True when post meta and term caches should be primed.
	 */
	private function should_prime_post_caches( array $fields ): bool {
		return array() !== array_intersect( $this->cache_priming_fields, $fields );
	}

	/**
	 * Looks up the single post a slug request resolves to.
	 *
	 * Slugs are not unique across statuses (drafts skip slug uniqueness), so the
	 * lookup returns the newest match the current user can read, preferring
	 * publicly viewable posts — a newer draft sharing the slug cannot shadow a
	 * published post. This mirrors the REST API, where slug queries default to
	 * the `publish` status. Which post a slug resolves to is independent of the
	 * requested fields; edit-field requests are gated afterwards on the resolved
	 * post by {@see self::check_permission()}.
	 *
	 * @since 7.1.0
	 *
	 * @param string $post_type The post type.
	 * @param string $slug      The post slug.
	 * @return \WP_Post|null The matching readable post, or null when none exists.
	 */
	private function get_post_by_slug( string $post_type, string $slug ): ?WP_Post {
		$name = sanitize_title( $slug );
		if ( '' === $name ) {
			return null;
		}

		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'name'                   => $name,
				'post_status'            => array_values( get_post_stati( array( 'internal' => false ) ) ),
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$viewable = array();
		$hidden   = array();
		foreach ( $query->posts as $candidate ) {
			if ( ! $candidate instanceof WP_Post ) {
				continue;
			}

			if ( is_post_publicly_viewable( $candidate ) ) {
				$viewable[] = $candidate;
				continue;
			}

			$hidden[] = $candidate;
		}

		// Both groups keep the query's newest-first ordering.
		foreach ( array_merge( $viewable, $hidden ) as $candidate ) {
			if ( ! $this->check_read_permission( $candidate ) ) {
				continue;
			}

			return $candidate;
		}

		return null;
	}

	/**
	 * Returns the post types exposed through the Abilities API, keyed by name.
	 *
	 * Deliberately resolved on every call rather than cached: post types can be
	 * unregistered or re-registered with different arguments between the ability
	 * being registered and the ability being used.
	 *
	 * @since 7.1.0
	 *
	 * @return array<string, \WP_Post_Type> Exposed post type objects keyed by name.
	 */
	private function get_exposed_post_types(): array {
		$exposed_post_types = array();

		foreach ( get_post_types( array( 'show_in_abilities' => true ), 'objects' ) as $post_type_object ) {
			$exposed_post_types[ $post_type_object->name ] = $post_type_object;
		}

		return $exposed_post_types;
	}

	/**
	 * Normalizes the requested statuses to a non-empty, sanitized list defaulting to publish.
	 *
	 * @since 7.1.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return list<string> Normalized list of post status slugs.
	 */
	private function normalize_statuses( array $input ): array {
		$statuses = $this->parse_list_input( $input, 'status' );

		return array() === $statuses ? array( 'publish' ) : array_map( 'sanitize_key', $statuses );
	}

	/**
	 * Normalizes query-mode included post IDs.
	 *
	 * @since 7.1.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return list<int> Unique positive post IDs.
	 */
	private function normalize_include( array $input ): array {
		$include = $input['include'] ?? null;
		if ( ! is_array( $include ) && ! is_string( $include ) ) {
			return array();
		}

		// A GET request delivers list inputs as scalar/CSV strings; wp_parse_id_list()
		// accepts both and yields unique positive IDs, matching schema validation.
		return array_values( array_filter( wp_parse_id_list( $include ) ) );
	}

	/**
	 * Returns the requested fields, or a lean default set when none are given.
	 *
	 * An empty or absent `fields` value selects a lean set of common read fields.
	 * Otherwise the requested fields are returned as-is. The input schema has already
	 * validated them against the supported set before the ability executes.
	 *
	 * @since 7.1.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return list<string> List of requested field names.
	 */
	private function normalize_fields( array $input ): array {
		$fields = $this->parse_list_input( $input, 'fields' );

		return array() === $fields ? $this->default_fields : $fields;
	}

	/**
	 * Returns the post field definitions, keyed by field name in output order.
	 *
	 * This is the single source of truth for the ability's post fields: the output
	 * schema uses the definitions directly, while the input schema fields enum uses
	 * the keys. Read-context fields are returned for readable posts; the edit-context
	 * fields listed in {@see self::$edit_fields} additionally require edit access.
	 *
	 * @since 7.1.0
	 *
	 * @return array<string, mixed> Post field definitions.
	 */
	private function get_post_properties(): array {
		if ( null !== $this->post_properties ) {
			return $this->post_properties;
		}

		$this->post_properties = array(
			'id'                => array(
				'type'        => 'integer',
				'description' => __( 'The post ID.' ),
			),
			'post_type'         => array(
				'type'        => 'string',
				'description' => __( 'The post type.' ),
			),
			'status'            => array(
				'type'        => 'string',
				'description' => __( 'The post status.' ),
			),
			'date'              => array(
				'type'        => 'string',
				'description' => __( "The publication date, in ISO 8601 format using the site's timezone. Empty string when the date cannot be resolved." ),
			),
			'date_gmt'          => array(
				'type'        => 'string',
				'description' => __( 'The publication date, in ISO 8601 format as GMT. Empty string when the date cannot be resolved.' ),
			),
			'modified'          => array(
				'type'        => 'string',
				'description' => __( "The last modified date, in ISO 8601 format using the site's timezone. Empty string when the date cannot be resolved." ),
			),
			'modified_gmt'      => array(
				'type'        => 'string',
				'description' => __( 'The last modified date, in ISO 8601 format as GMT. Empty string when the date cannot be resolved.' ),
			),
			'slug'              => array(
				'type'        => 'string',
				'description' => __( 'The post slug.' ),
			),
			'link'              => array(
				'type'        => 'string',
				'description' => __( 'The permalink URL.' ),
			),
			'title_raw'         => array(
				'type'        => 'string',
				'description' => __( 'The raw post title. Present when the post type supports titles and the current user can edit the post.' ),
			),
			'title_rendered'    => array(
				'type'        => 'string',
				'description' => __( 'The rendered post title. Present when the post type supports titles.' ),
			),
			'excerpt_raw'       => array(
				'type'        => 'string',
				'description' => __( 'The raw post excerpt. Present when the post type supports excerpts and the current user can edit the post.' ),
			),
			'excerpt_rendered'  => array(
				'type'        => 'string',
				'description' => __( 'The rendered post excerpt (HTML). Present when the post type supports excerpts. Empty when withheld for a password-protected post.' ),
			),
			'excerpt_protected' => array(
				'type'        => 'boolean',
				'description' => __( 'Whether the excerpt is protected with a password. Present when the post type supports excerpts.' ),
			),
			'content_raw'       => array(
				'type'        => 'string',
				'description' => __( 'The raw, unfiltered post content (block markup). Present when the post type supports the editor and the current user can edit the post.' ),
			),
			'content_rendered'  => array(
				'type'        => 'string',
				'description' => __( 'The rendered post content. Present when the post type supports the editor. Empty when withheld for a password-protected post.' ),
			),
			'content_protected' => array(
				'type'        => 'boolean',
				'description' => __( 'Whether the content is protected with a password. Present when the post type supports the editor.' ),
			),
			'author'            => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'id'   => array(
						'type'        => 'integer',
						'description' => __( 'The author user ID.' ),
					),
					'name' => array(
						'type'        => 'string',
						'description' => __( 'The author display name.' ),
					),
				),
				'description'          => __( 'The post author. Present when the post type supports authors.' ),
			),
			'parent'            => array(
				'type'        => 'integer',
				'description' => __( 'The parent post ID. Present for hierarchical post types.' ),
			),
		);

		return $this->post_properties;
	}

	/**
	 * Builds the input schema for the `core/read-content` ability.
	 *
	 * The ability has three mutually exclusive modes, modeled as a `oneOf` so invalid
	 * combinations are rejected rather than silently ignored:
	 *
	 *   - Get a single post by `id` (optionally guarded by `post_type`).
	 *   - Get a single post by `post_type` and `slug`.
	 *   - Query a set of posts by `post_type` plus filters (`status`, `author`, `parent`,
	 *     `include`, `page`, `per_page`).
	 *
	 * Each mode sets `additionalProperties: false`, so e.g. passing `per_page` alongside `id`
	 * fails validation instead of being dropped. `fields` is accepted in every mode.
	 *
	 * @since 7.1.0
	 *
	 * @param list<string> $post_types Exposed post type names.
	 * @param list<string> $statuses   Requestable post status slugs.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_read_content_input_schema( array $post_types, array $statuses ): array {
		$fields  = array(
			'type'        => 'array',
			'uniqueItems' => true,
			'items'       => array(
				'type' => 'string',
				'enum' => array_keys( $this->get_post_properties() ),
			),
			'description' => __( 'Limit each returned post to these fields. If omitted, a lean set of common read fields is returned. Explicit raw field requests require edit access.' ),
		);
		$include = array(
			'type'        => 'array',
			'minItems'    => 1,
			'maxItems'    => self::MAX_PER_PAGE,
			'uniqueItems' => true,
			'items'       => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
			'description' => __( 'Limit the query to these post IDs. The order of the IDs does not affect the order of the results. If `per_page` is omitted, the page size defaults to the number of included IDs, capped at the maximum.' ),
		);

		return array(
			'type'  => 'object',
			'oneOf' => array(
				// Mode 1: retrieve a single readable post by ID.
				array(
					'title'                => __( 'Get a single readable post by ID' ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
					'properties'           => array(
						'id'        => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Retrieve a single readable post by ID.' ),
						),
						'post_type' => array(
							'type'        => 'string',
							'enum'        => $post_types,
							'description' => __( 'Optional. Restrict the lookup to this post type; the post is returned only if it matches and the current user can read it.' ),
						),
						'fields'    => $fields,
					),
				),
				// Mode 2: retrieve a single readable post by post type and slug.
				array(
					'title'                => __( 'Get a single readable post by slug' ),
					'required'             => array( 'post_type', 'slug' ),
					'additionalProperties' => false,
					'properties'           => array(
						'post_type' => array(
							'type'        => 'string',
							'enum'        => $post_types,
							'description' => __( 'Post type containing the slug. Slugs are not unique across post types.' ),
						),
						'slug'      => array(
							'type'        => 'string',
							'minLength'   => 1,
							'description' => __( 'Retrieve a single readable post by slug. Resolves to the newest readable match, preferring published posts.' ),
						),
						'fields'    => $fields,
					),
				),
				// Mode 3: query a set of readable posts by post type and filters.
				array(
					'title'                => __( 'Query readable posts by post type and filters' ),
					'required'             => array( 'post_type' ),
					'additionalProperties' => false,
					'properties'           => array(
						'post_type' => array(
							'type'        => 'string',
							'enum'        => $post_types,
							'description' => __( 'Post type to query for readable posts.' ),
						),
						'status'    => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'items'       => array(
								'type' => 'string',
								'enum' => $statuses,
							),
							'description' => __( 'Filter readable posts by one or more post statuses. Defaults to publish. Non-published statuses require the appropriate capabilities.' ),
						),
						'author'    => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Filter by author user ID. Only supported for post types that support authors.' ),
						),
						'parent'    => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => __( 'Filter by parent post ID. Only supported for hierarchical post types. Use 0 for top-level posts.' ),
						),
						'include'   => $include,
						'fields'    => $fields,
						'page'      => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Page of results to return. Requesting a page beyond the last one is an error. Check `total_pages` before requesting later pages.' ),
						),
						'per_page'  => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => self::MAX_PER_PAGE,
							'description' => __( 'Maximum number of posts to return per page.' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Builds the output schema for the `core/read-content` ability.
	 *
	 * No field is marked required because the `fields` input lets the caller request any
	 * subset, and a field is only present when its post type supports it. Single-post
	 * mode returns the post object directly, while query mode returns a paginated wrapper.
	 *
	 * @since 7.1.0
	 *
	 * @return array<string, mixed> The output JSON Schema.
	 */
	private function get_read_content_output_schema(): array {
		$post_schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => $this->get_post_properties(),
		);

		$query_schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'posts', 'total', 'total_pages' ),
			'properties'           => array(
				'posts'       => array(
					'type'        => 'array',
					'description' => __( 'The readable posts matching the query, ordered by post date, newest first.' ),
					'items'       => $post_schema,
				),
				'total'       => array(
					'type'        => 'integer',
					'description' => __( 'Total number of posts matching the underlying query, across all pages. May exceed the number of returned posts when row-level permission checks withhold some of them.' ),
				),
				'total_pages' => array(
					'type'        => 'integer',
					'description' => __( 'Total number of query result pages available for the underlying query. May include pages whose rows are withheld by row-level permission checks.' ),
				),
			),
		);

		return array(
			'type'  => 'object',
			'oneOf' => array(
				$post_schema,
				$query_schema,
			),
		);
	}

	/**
	 * Prepares a formatted post for output.
	 *
	 * A field projection can legitimately be empty, for example when the only requested
	 * field is one the post type does not support. An empty PHP array encodes as `[]`,
	 * which would break the `object` output schema, so return an empty object instead.
	 *
	 * This deliberately improves on the REST posts controller, which encodes the same
	 * case as `[]` even though it types the response as an object
	 * (`GET /wp/v2/posts/<id>?_fields=parent` on a non-hierarchical post type).
	 *
	 * @since 7.1.0
	 *
	 * @param array<string, mixed> $formatted The formatted post data.
	 * @return array<string, mixed>|\stdClass The post data, or an empty object when the projection is empty.
	 */
	private function to_output_post( array $formatted ) {
		return array() === $formatted ? (object) array() : $formatted;
	}

	/**
	 * Formats a post into the ability output shape.
	 *
	 * For an editor of a password-protected post, the cookie-based password gate is suspended
	 * while the fields are built so rendered fields resolve to real values instead of
	 * protected-post placeholders. The field projection itself is delegated to
	 * {@see self::build_post_fields()}.
	 *
	 * @since 7.1.0
	 *
	 * @param \WP_Post $post   The post object.
	 * @param list<string> $fields The requested field names.
	 * @return array<string, mixed> The formatted post data.
	 */
	private function format_post( WP_Post $post, array $fields ): array {
		$can_edit          = current_user_can( 'edit_post', $post->ID );
		$password_required = post_password_required( $post );
		$protected         = $password_required && ! $can_edit;

		/*
		 * Suspend the cookie-based password gate for an editor of this protected post, so
		 * helpers with their own gate (e.g. get_the_excerpt()) resolve the real values. The
		 * filter unlocks only posts the current user can edit, mirroring the REST posts
		 * controller's check_password_required(): an unconditional bypass (e.g. __return_false)
		 * would also expose other protected posts that the content filter may render, such as
		 * posts pulled in by a Query Loop block. The filter is removed in a finally block so a
		 * throw mid-render cannot leave the gate globally disabled for the rest of the request.
		 */
		if ( $password_required && $can_edit ) {
			add_filter( 'post_password_required', array( $this, 'allow_password_content' ), 10, 2 );

			try {
				return $this->build_post_fields( $post, $fields, $can_edit, $protected );
			} finally {
				remove_filter( 'post_password_required', array( $this, 'allow_password_content' ), 10 );
			}
		}

		return $this->build_post_fields( $post, $fields, $can_edit, $protected );
	}

	/**
	 * Builds the requested field projection for a post.
	 *
	 * Only the requested fields that the post type supports and the current user can see are
	 * included. Raw fields are edit-context fields; rendered fields are read-context fields and
	 * are withheld for password-protected posts unless the current user can edit the post,
	 * mirroring the REST API behavior.
	 *
	 * @since 7.1.0
	 *
	 * @param \WP_Post $post         The post object.
	 * @param list<string> $fields       The requested field names.
	 * @param bool     $can_edit     Whether the current user can edit the post.
	 * @param bool     $is_protected Whether rendered fields must be withheld as password-protected.
	 * @return array<string, mixed> The formatted post data.
	 */
	private function build_post_fields( WP_Post $post, array $fields, bool $can_edit, bool $is_protected ): array {
		$post_type = $post->post_type;

		// Edit-context fields require edit access; drop them so $edit_fields is the single gate.
		if ( ! $can_edit ) {
			$fields = array_diff( $fields, $this->edit_fields );
		}

		$requested = array_flip( $fields );
		$data      = array();

		if ( isset( $requested['id'] ) ) {
			$data['id'] = (int) $post->ID;
		}
		if ( isset( $requested['post_type'] ) ) {
			$data['post_type'] = $post_type;
		}
		if ( isset( $requested['status'] ) ) {
			$data['status'] = $post->post_status;
		}
		if ( isset( $requested['date'] ) ) {
			$data['date'] = $this->format_local_date( $post, 'date' );
		}
		if ( isset( $requested['date_gmt'] ) ) {
			$data['date_gmt'] = $this->format_gmt_date( $post, 'date' );
		}
		if ( isset( $requested['modified'] ) ) {
			$data['modified'] = $this->format_local_date( $post, 'modified' );
		}
		if ( isset( $requested['modified_gmt'] ) ) {
			$data['modified_gmt'] = $this->format_gmt_date( $post, 'modified' );
		}
		if ( isset( $requested['slug'] ) ) {
			$data['slug'] = $post->post_name;
		}
		if ( isset( $requested['link'] ) ) {
			$data['link'] = (string) get_permalink( $post );
		}

		if ( isset( $requested['title_raw'] ) && post_type_supports( $post_type, 'title' ) ) {
			$data['title_raw'] = $post->post_title;
		}

		if ( isset( $requested['title_rendered'] ) && post_type_supports( $post_type, 'title' ) ) {
			$data['title_rendered'] = $this->get_title( $post );
		}

		if ( isset( $requested['excerpt_raw'] ) && post_type_supports( $post_type, 'excerpt' ) ) {
			$data['excerpt_raw'] = $post->post_excerpt;
		}

		if ( isset( $requested['excerpt_rendered'] ) && post_type_supports( $post_type, 'excerpt' ) ) {
			$data['excerpt_rendered'] = $is_protected ? '' : $this->get_rendered_excerpt( $post );
		}

		if ( isset( $requested['excerpt_protected'] ) && post_type_supports( $post_type, 'excerpt' ) ) {
			$data['excerpt_protected'] = (bool) $post->post_password;
		}

		if ( isset( $requested['content_raw'] ) && post_type_supports( $post_type, 'editor' ) ) {
			$data['content_raw'] = $post->post_content;
		}

		if ( isset( $requested['content_rendered'] ) && post_type_supports( $post_type, 'editor' ) ) {
			$data['content_rendered'] = $is_protected ? '' : $this->get_rendered_content( $post );
		}

		if ( isset( $requested['content_protected'] ) && post_type_supports( $post_type, 'editor' ) ) {
			$data['content_protected'] = (bool) $post->post_password;
		}

		if ( isset( $requested['author'] ) && post_type_supports( $post_type, 'author' ) ) {
			$author         = get_userdata( (int) $post->post_author );
			$data['author'] = array(
				'id'   => (int) $post->post_author,
				'name' => $author ? $author->display_name : '',
			);
		}

		if ( isset( $requested['parent'] ) && is_post_type_hierarchical( $post_type ) ) {
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
	 * @since 7.1.0
	 *
	 * @param mixed $required Whether the post currently requires a password.
	 * @param mixed $post     The post being checked; a WP_Post when invoked by the core filter.
	 * @return bool Whether the post still requires a password.
	 */
	public function allow_password_content( $required, $post ): bool {
		if ( ! $required || ! $post instanceof WP_Post ) {
			return (bool) $required;
		}

		return ! current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Returns the post title with the protected/private prefixes stripped.
	 *
	 * @since 7.1.0
	 *
	 * @param \WP_Post $post The post object.
	 * @return string The post title.
	 */
	private function get_title( WP_Post $post ): string {
		$strip = array( $this, 'return_raw_title_format' );
		add_filter( 'protected_title_format', $strip );
		add_filter( 'private_title_format', $strip );

		/*
		 * The format filters are removed in a finally block so a throw from a title
		 * filter cannot leave them attached for the rest of the request.
		 */
		try {
			return get_the_title( $post );
		} finally {
			remove_filter( 'protected_title_format', $strip );
			remove_filter( 'private_title_format', $strip );
		}
	}

	/**
	 * Returns the raw title format, used to strip protected/private title prefixes.
	 *
	 * @since 7.1.0
	 *
	 * @return string The unprefixed title format.
	 */
	public function return_raw_title_format(): string {
		return '%s';
	}

	/**
	 * Returns the post excerpt transformed for display.
	 *
	 * Mirrors the REST posts controller by preparing post globals before applying
	 * the `get_the_excerpt` and `the_excerpt` filter chains, then restoring the
	 * previous global post context. This ensures filters that rely on loop globals
	 * render against the requested post.
	 *
	 * @since 7.1.0
	 *
	 * @param \WP_Post $post The post object.
	 * @return string Rendered post excerpt.
	 */
	private function get_rendered_excerpt( WP_Post $post ): string {
		$previous_post = $GLOBALS['post'] ?? null;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporarily mirrors REST post context for excerpt rendering.
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		/*
		 * The global post context is restored in a finally block so a throw from an
		 * excerpt filter cannot leave it pointing at the rendered post for the rest
		 * of the request.
		 */
		try {
			/** This filter is documented in wp-includes/post-template.php. */
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Applying the core excerpt filter to mirror REST rendering.
			$excerpt = apply_filters( 'get_the_excerpt', $post->post_excerpt, $post );

			/** This filter is documented in wp-includes/post-template.php. */
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Applying the core excerpt filter to mirror REST rendering.
			$excerpt = apply_filters( 'the_excerpt', $excerpt );

			return is_string( $excerpt ) ? $excerpt : '';
		} finally {
			if ( $previous_post instanceof WP_Post ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the previous global post context.
				$GLOBALS['post'] = $previous_post;
				setup_postdata( $previous_post );
			} else {
				unset( $GLOBALS['post'] );
				wp_reset_postdata();
			}
		}
	}

	/**
	 * Returns post content transformed for display.
	 *
	 * Mirrors the REST posts controller by preparing post globals before applying
	 * `the_content`, then restoring the previous global post context.
	 *
	 * @since 7.1.0
	 *
	 * @param \WP_Post $post The post object.
	 * @return string Rendered post content.
	 */
	private function get_rendered_content( WP_Post $post ): string {
		$previous_post = $GLOBALS['post'] ?? null;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporarily mirrors REST post context for content rendering.
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		/*
		 * The global post context is restored in a finally block so a throw from a
		 * content filter cannot leave it pointing at the rendered post for the rest
		 * of the request.
		 */
		try {
			/** This filter is documented in wp-includes/post-template.php. */
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Applying the core content filter to mirror REST rendering.
			$content = apply_filters( 'the_content', $post->post_content );

			return is_string( $content ) ? $content : '';
		} finally {
			if ( $previous_post instanceof WP_Post ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the previous global post context.
				$GLOBALS['post'] = $previous_post;
				setup_postdata( $previous_post );
			} else {
				unset( $GLOBALS['post'] );
				wp_reset_postdata();
			}
		}
	}

	/**
	 * Formats a post date field as an ISO 8601 string in the site's timezone.
	 *
	 * @since 7.1.0
	 *
	 * @param \WP_Post $post  The post object.
	 * @param string   $field Either 'date' or 'modified'. Default 'date'.
	 * @return string The ISO 8601 date, or an empty string if unavailable.
	 */
	private function format_local_date( WP_Post $post, string $field = 'date' ): string {
		$field    = 'modified' === $field ? 'modified' : 'date';
		$datetime = get_post_datetime( $post, $field, 'local' );

		return $datetime ? $datetime->format( 'c' ) : '';
	}

	/**
	 * Formats a post date field as an ISO 8601 string in GMT.
	 *
	 * Reads the stored GMT date directly, deriving it from the local date when missing
	 * (e.g. drafts), mirroring the REST posts controller. get_post_datetime() is avoided
	 * here because it reprojects even GMT-sourced dates into the site timezone, which
	 * would label the returned instant with the site offset instead of UTC.
	 *
	 * @since 7.1.0
	 *
	 * @param \WP_Post $post  The post object.
	 * @param string   $field Either 'date' or 'modified'. Default 'date'.
	 * @return string The ISO 8601 date, or an empty string if unavailable.
	 */
	private function format_gmt_date( WP_Post $post, string $field = 'date' ): string {
		$field = 'modified' === $field ? 'modified' : 'date';
		$gmt   = 'modified' === $field ? $post->post_modified_gmt : $post->post_date_gmt;

		if ( ! $this->is_usable_date( $gmt ) ) {
			$local = 'modified' === $field ? $post->post_modified : $post->post_date;
			$gmt   = $this->is_usable_date( $local ) ? get_gmt_from_date( $local ) : '';
		}

		/*
		 * Guard the empty string before `strtotime()`: `strtotime( ' UTC' )` resolves to the
		 * current time, which would report a fabricated date instead of the documented
		 * empty-string sentinel.
		 */
		$timestamp = '' === $gmt ? false : strtotime( $gmt . ' UTC' );

		return false === $timestamp ? '' : gmdate( 'c', $timestamp );
	}

	/**
	 * Checks whether a raw post date column holds a usable date.
	 *
	 * The columns are `NOT NULL` in core's schema, but a post object can reach this class
	 * from a filter or an in-memory row where a date is null or a zero date.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $date The raw date column value.
	 * @return bool True when the value is a non-empty, non-zero date string.
	 */
	private function is_usable_date( $date ): bool {
		return is_string( $date ) && '' !== $date && '0000-00-00 00:00:00' !== $date;
	}

	/**
	 * Builds the uniform not-found error.
	 *
	 * Unreachable through gated transports, which run {@see self::check_permission()}
	 * first and deny the same lookups. It is kept so that a direct call to the execute
	 * callback still fails closed on a structural lookup failure: a missing post, a post
	 * type that is not exposed, or a post type that does not match the requested one.
	 *
	 * This is not a permission check. The execute callback deliberately does not repeat
	 * the read/edit checks that {@see self::check_permission()} already performed, so a
	 * direct call bypasses them. Only invoke the callback through
	 * {@see WP_Ability::execute()}, which always runs the permission callback first.
	 *
	 * @since 7.1.0
	 *
	 * @return \WP_Error The not-found error.
	 */
	private function not_found_error(): WP_Error {
		return new WP_Error(
			'content_not_found',
			__( 'The requested content was not found.' ),
			array( 'status' => 404 )
		);
	}
}
