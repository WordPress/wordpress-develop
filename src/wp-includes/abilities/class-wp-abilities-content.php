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
 * Registers the read-only `core/content-query` ability, which retrieves readable posts of a
 * post type exposed to abilities via `show_in_abilities`. Supports fetching a single
 * readable post by ID or by post type and slug, or querying multiple readable posts filtered
 * by post type, status, author, parent, or included IDs. Raw fields are only returned for
 * posts the current user can edit.
 *
 * The class is intentionally structured around shared building blocks (exposed post type
 * discovery, schema generation, per-post formatting and permission checks) so future
 * write-oriented content abilities can reuse them.
 *
 * This class is part of WordPress' internal implementation of the core abilities and is
 * not part of the public API. It may be changed or removed at any time without notice.
 * Do not use it directly or rely on its existence.
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
	 * Default number of posts returned per page in query mode.
	 *
	 * @since 7.2.0
	 * @var int
	 */
	private const DEFAULT_PER_PAGE = 10;

	/**
	 * Maximum number of posts returned per page in query mode.
	 *
	 * @since 7.2.0
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Fields that expose edit-context post data.
	 *
	 * Requests that explicitly include any of these fields require edit access.
	 *
	 * @since 7.2.0
	 * @var list<string>
	 */
	private const EDIT_FIELDS = array( 'title_raw', 'excerpt_raw', 'content_raw' );

	/**
	 * Fields whose output may read post meta or terms.
	 *
	 * Requests that include any of these prime the post meta and term caches for the
	 * page. Rendered excerpts and content may read either, and permalinks read terms
	 * when the permalink structure contains `%category%`. Other fields, such as the
	 * rendered title, do not need that cache priming.
	 *
	 * @since 7.2.0
	 * @var list<string>
	 */
	private const CACHE_PRIMING_FIELDS = array( 'link', 'excerpt_rendered', 'content_rendered' );

	/**
	 * Default fields returned when the caller does not request a field subset.
	 *
	 * @since 7.2.0
	 * @var list<string>
	 */
	private const DEFAULT_FIELDS = array( 'id', 'post_type', 'status', 'date', 'slug', 'title_rendered' );

	/**
	 * Globals that rendering a post changes: the global post and the globals that
	 * setup_postdata() populates.
	 *
	 * @since 7.2.0
	 * @var list<string>
	 */
	private const LOOP_GLOBALS = array( 'post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages' );

	/**
	 * Registers all content abilities.
	 *
	 * Must run on the `wp_abilities_api_init` hook.
	 *
	 * @since 7.2.0
	 */
	public function register(): void {
		$this->register_content_query();

		/*
		 * Future write-oriented content abilities can be registered here, reusing the
		 * shared helpers below (get_exposed_post_type(), format_post(), check_permission()).
		 */
	}

	/**
	 * Registers the read-only `core/content-query` ability.
	 *
	 * @since 7.2.0
	 */
	private function register_content_query(): void {
		/*
		 * Post types must be registered with `show_in_abilities` before the ability is
		 * registered so they are included in its input schema.
		 */
		$post_types = array_values( get_post_types( array( 'show_in_abilities' => true ) ) );
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
			'core/content-query',
			array(
				'label'               => __( 'Query Content' ),
				'description'         => __( 'Reads content from post types exposed to abilities. Single-post lookups by ID or by post type and slug return the post object directly. Query mode returns readable posts filtered by post type, status, author, parent, or included IDs. Requires an authenticated user. Lookups and filters are exact-match only; the ability does not perform full-text search.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_content_query_input_schema( $post_types, $statuses ),
				'output_schema'       => $this->get_content_query_output_schema(),
				'execute_callback'    => array( $this, 'execute_content_query' ),
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
	 * Permission callback for the `core/content-query` ability.
	 *
	 * This gate is the authoritative permission decision for single-post modes: it
	 * resolves the requested post and denies missing, mismatched, or unreadable posts
	 * before execution. Query mode is only gated coarsely here (collection status
	 * capabilities); {@see self::execute_content_query()} enforces row-level read/edit
	 * permissions, since individual rows are unknown until the query runs. Requests
	 * that explicitly ask for edit-context fields require edit access before execution.
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

		$requires_edit = $this->has_explicit_edit_fields( $input );

		// Single-post modes, by ID or by post type and slug.
		if ( isset( $input['id'] ) || isset( $input['slug'] ) ) {
			$post = $this->get_requested_post( $input );
			if ( ! $post ) {
				return false;
			}

			return $requires_edit ? current_user_can( 'edit_post', $post->ID ) : $this->check_read_permission( $post );
		}

		// Query mode requires an exposed post type.
		$post_type_object = $this->get_exposed_post_type( $input['post_type'] ?? null );
		if ( ! $post_type_object ) {
			return false;
		}

		if ( $requires_edit ) {
			return current_user_can( $post_type_object->cap->edit_posts );
		}

		return $this->can_query_statuses( $input, $post_type_object );
	}

	/**
	 * Parses a raw input value into an integer of at least a minimum, or null when invalid.
	 *
	 * Accepts native integers and unsigned integer strings. Only the REST run controller
	 * converts input to the schema types, so other callers, such as a direct
	 * WP_Ability::execute() call, can pass an integer string, which schema validation
	 * accepts. Anything else is rejected rather than read as 0, which as a `parent`
	 * filter would ask for top-level posts.
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

		return null;
	}

	/**
	 * Parses a raw list input into a list of strings.
	 *
	 * Like schema validation, it also accepts a scalar or a comma-separated string, and
	 * parses it the same way, with wp_parse_list().
	 *
	 * @since 7.2.0
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
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return bool True if edit-context fields were explicitly requested.
	 */
	private function has_explicit_edit_fields( array $input ): bool {
		return array() !== array_intersect( self::EDIT_FIELDS, $this->parse_list_input( $input, 'fields' ) );
	}

	/**
	 * Checks whether the current user may query the requested statuses.
	 *
	 * This mirrors the REST posts controller's conservative collection-status gate:
	 * requesting non-default statuses requires edit access, except `private`, which
	 * may be queried by users who can read private posts.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input            The ability input.
	 * @param WP_Post_Type $post_type_object The post type object.
	 * @return bool True if the requested statuses may be queried.
	 */
	private function can_query_statuses( array $input, WP_Post_Type $post_type_object ): bool {
		foreach ( $this->normalize_statuses( $input ) as $status ) {
			if ( 'publish' === $status ) {
				continue;
			}

			if ( 'private' === $status && current_user_can( $post_type_object->cap->read_private_posts ) ) {
				continue;
			}

			if ( current_user_can( $post_type_object->cap->edit_posts ) ) {
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
		 * ability fail-closed for missing parents or parent loops.
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
	 * {@see WP_Ability::execute()} always runs {@see self::check_permission()} first, so the
	 * single-post modes only re-validate the lookup itself: existence, exposure, and a
	 * matching post type. Query mode still filters every row by read or edit permission,
	 * because the gate cannot resolve rows before the query runs.
	 *
	 * A post is returned as an empty object when its field projection is empty, so callers
	 * must not assume array access on a post. See {@see self::format_post()}.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return array<string, mixed>|stdClass|WP_Error A single post, a `posts` list with totals in query mode, or a WP_Error.
	 */
	public function execute_content_query( $input = array() ) {
		$input         = rest_sanitize_object( $input );
		$fields        = $this->normalize_fields( $input );
		$requires_edit = $this->has_explicit_edit_fields( $input );

		// Single-post modes, by ID or by post type and slug.
		if ( isset( $input['id'] ) || isset( $input['slug'] ) ) {
			$post = $this->get_requested_post( $input );

			return $post ? $this->format_post( $post, $fields ) : $this->not_found_error();
		}

		$post_type_object = $this->get_exposed_post_type( $input['post_type'] ?? null );
		if ( ! $post_type_object ) {
			return $this->not_found_error();
		}

		$post_type = $post_type_object->name;

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
				return $this->invalid_filter_error( __( 'The parent filter is only supported for hierarchical post types.' ) );
			}

			$parent = $this->parse_filter_int( $input['parent'], 0 );
			if ( null === $parent ) {
				return $this->invalid_filter_error( __( 'The parent filter must be a non-negative integer.' ) );
			}
		}

		$author = null;
		if ( isset( $input['author_slug'] ) ) {
			if ( ! post_type_supports( $post_type, 'author' ) ) {
				return $this->invalid_filter_error(
					/* translators: %s: Parameter. */
					sprintf( __( 'The %s filter is only supported for post types that support authors.' ), 'author_slug' )
				);
			}

			$author = $this->get_author_by_slug( $input['author_slug'], $post_type_object );
			if ( ! $author ) {
				return $this->invalid_filter_error(
					/* translators: %s: Parameter. */
					sprintf( __( 'The %s filter must be the slug of an existing user.' ), 'author_slug' )
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
			return $this->invalid_filter_error( __( 'The include filter must list one or more valid post IDs.' ) );
		}

		/*
		 * Read `page` and `per_page` with absint(), as the REST posts controller does, not with
		 * parse_filter_int(). The integer schema also accepts whole floats such as 2.0, which
		 * JSON encoders and ceil() produce, and strings such as "2.0" or "+2", and callers other
		 * than the REST run controller pass them on unconverted, such as the MCP adapter or a
		 * direct WP_Ability::execute() call. parse_filter_int() rejects them, so the query
		 * would silently fall back to page 1 and the default page size: a client paging with
		 * 2.0, 3.0, and so on would get page 1 every time and never reach the error for a page
		 * past the last one. The schema's minimum of 1 keeps out the negative values that
		 * absint() would turn positive.
		 */
		$per_page = $this->normalize_per_page( $input, $include );
		$page     = isset( $input['page'] ) ? max( 1, absint( $input['page'] ) ) : 1;

		$prime_post_caches = $this->should_prime_post_caches( $fields );

		/*
		 * `orderby` is left unset, which orders by `post_date` descending, matching the
		 * default of the REST posts controller.
		 */
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
			$query_args['author'] = $author->ID;
		}

		if ( null !== $parent ) {
			$query_args['post_parent'] = $parent;
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
			if ( $requires_edit && ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			if ( ! $requires_edit && ! $this->check_read_permission( $post ) ) {
				continue;
			}
			// Keep rows whose field projection is empty so a caller can still count them.
			$posts[] = $this->format_post( $post, $fields );
		}

		/*
		 * Mirror the REST posts controller: totals come from the underlying WP_Query,
		 * while the row-level checks above may withhold individual returned rows.
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
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input       The ability input.
	 * @param list<int>    $include_ids Normalized included post IDs; empty when not requested.
	 * @return int The clamped per-page value.
	 */
	private function normalize_per_page( array $input, array $include_ids ): int {
		// absint(), not parse_filter_int(): see where execute_content_query() reads `page`.
		$per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 0;
		if ( $per_page < 1 ) {
			$per_page = array() === $include_ids ? self::DEFAULT_PER_PAGE : count( $include_ids );
		}

		return min( self::MAX_PER_PAGE, $per_page );
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
		unset( $count_args['paged'], $count_args['no_found_rows'] );

		$count_query = new WP_Query( $count_args );

		return (int) $count_query->found_posts;
	}

	/**
	 * Checks whether requested fields benefit from page-level cache priming.
	 *
	 * @since 7.2.0
	 *
	 * @param list<string> $fields The requested field names.
	 * @return bool True when post meta and term caches should be primed.
	 */
	private function should_prime_post_caches( array $fields ): bool {
		return array() !== array_intersect( self::CACHE_PRIMING_FIELDS, $fields );
	}

	/**
	 * Looks up the single post an ID or slug request resolves to.
	 *
	 * By ID, the post must exist, belong to a post type exposed to abilities, and match the
	 * `post_type` guard when one is given. As with the integer filters, only an integer or
	 * an unsigned integer string is accepted, so a malformed ID or one beyond the integer
	 * range cannot be coerced onto another post. By slug, the post type must be exposed to
	 * abilities, and {@see self::get_post_by_slug()} resolves the post.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return WP_Post|null The post, or null when it cannot be resolved.
	 */
	private function get_requested_post( array $input ): ?WP_Post {
		if ( isset( $input['id'] ) ) {
			$post_id = $this->parse_filter_int( $input['id'], 1 );
			$post    = null === $post_id ? null : get_post( $post_id );

			if ( ! $post instanceof WP_Post || ! $this->get_exposed_post_type( $post->post_type ) ) {
				return null;
			}

			return empty( $input['post_type'] ) || $post->post_type === $input['post_type'] ? $post : null;
		}

		$slug             = $input['slug'] ?? null;
		$post_type_object = $this->get_exposed_post_type( $input['post_type'] ?? null );

		return is_string( $slug ) && $post_type_object ? $this->get_post_by_slug( $post_type_object->name, $slug ) : null;
	}

	/**
	 * Looks up the user an author slug names.
	 *
	 * The slug is the user's nicename, which the REST API users endpoint returns as `slug`, and
	 * must match it exactly. A user the current user may not see is reported like a
	 * missing one. The current user can see themselves, any user of the site when they can list
	 * users, and authors with posts in a publicly viewable post type. A user who can edit others'
	 * posts of the post type can also see any user of the site, since they may make any of them
	 * the author, so for them the lookup does tell whether such an account exists.
	 *
	 * On multisite the lookup searches the whole network, so posts by authors who are not
	 * members of the site, such as super admins, can still be filtered. Those users are only
	 * visible as the current user or as authors with posts in a publicly viewable post type, so
	 * the lookup does not tell whether an account exists elsewhere on the network.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed        $slug             The author slug.
	 * @param WP_Post_Type $post_type_object The post type the author is looked up for.
	 * @return WP_User|null The user, or null when the slug does not name a visible user.
	 */
	private function get_author_by_slug( $slug, WP_Post_Type $post_type_object ): ?WP_User {
		$user = is_string( $slug ) ? get_user_by( 'slug', $slug ) : false;

		// The database compares nicenames without regard to case, so keep an exact match only.
		if ( ! $user || $user->user_nicename !== $slug ) {
			return null;
		}

		if ( get_current_user_id() === $user->ID ) {
			return $user;
		}

		// The capabilities only reveal users of the site, not of the whole network.
		if ( ( ! is_multisite() || is_user_member_of_blog( $user->ID ) )
			&& ( current_user_can( 'list_users' ) || current_user_can( $post_type_object->cap->edit_others_posts ) )
		) {
			return $user;
		}

		$public_post_types = array_values( array_filter( get_post_types(), 'is_post_type_viewable' ) );

		return array() !== $public_post_types && count_user_posts( $user->ID, $public_post_types ) > 0 ? $user : null;
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
	 * In hierarchical post types, posts under different parents can also share a
	 * slug. The lookup cannot tell them apart and resolves to one of them by the
	 * same rules, so callers that need a specific post should look it up by ID.
	 *
	 * @since 7.2.0
	 *
	 * @param string $post_type The post type.
	 * @param string $slug      The post slug.
	 * @return WP_Post|null The matching readable post, or null when none exists.
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

		// Candidates come newest first; a publicly viewable post is always readable here.
		$readable = null;
		foreach ( $query->posts as $candidate ) {
			/*
			 * Skip posts of other post types, which query filters can add, such as a
			 * `pre_get_posts` callback that adds post types to single views without an
			 * is_main_query() check, since a `name` query is a single view.
			 */
			if ( ! $candidate instanceof WP_Post || $post_type !== $candidate->post_type ) {
				continue;
			}

			if ( is_post_publicly_viewable( $candidate ) ) {
				return $candidate;
			}

			if ( null === $readable && $this->check_read_permission( $candidate ) ) {
				$readable = $candidate;
			}
		}

		return $readable;
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
	 * Normalizes the requested statuses to a non-empty, sanitized list defaulting to publish.
	 *
	 * @since 7.2.0
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
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return list<int> Unique positive post IDs.
	 */
	private function normalize_include( array $input ): array {
		$include = $input['include'] ?? null;
		if ( ! is_array( $include ) && ! is_string( $include ) && ! is_int( $include ) ) {
			return array();
		}

		/*
		 * wp_parse_id_list() also parses a single ID or a comma-separated string, as
		 * schema validation does.
		 */
		return array_values( array_filter( wp_parse_id_list( $include ) ) );
	}

	/**
	 * Returns the requested fields, or a lean default set when none are given.
	 *
	 * An empty or absent `fields` value selects a lean set of common read fields.
	 * Otherwise the requested fields are returned as-is. The input schema has already
	 * validated them against the supported set before the ability executes.
	 *
	 * @since 7.2.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return list<string> List of requested field names.
	 */
	private function normalize_fields( array $input ): array {
		$fields = $this->parse_list_input( $input, 'fields' );

		return array() === $fields ? self::DEFAULT_FIELDS : $fields;
	}

	/**
	 * Returns the post field definitions, keyed by field name in output order.
	 *
	 * This is the single source of truth for the ability's post fields: the output
	 * schema uses the definitions directly, while the input schema fields enum uses
	 * the keys. Read-context fields are returned for readable posts; the edit-context
	 * fields listed in {@see self::EDIT_FIELDS} additionally require edit access.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> Post field definitions.
	 */
	private function get_post_properties(): array {
		return array(
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
			'author_slug'       => array(
				'type'        => 'string',
				'description' => __( "The author's user slug (nicename), as the REST API users endpoint returns it in `slug`. Present when the post type supports authors. Empty when the author no longer exists." ),
			),
			'parent'            => array(
				'type'        => 'integer',
				'description' => __( 'The parent post ID. Present for hierarchical post types.' ),
			),
		);
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
				'enum' => array_keys( $this->get_post_properties() ),
			),
			'description' => __( 'Limit each returned post to these fields. If omitted, a lean set of common read fields is returned. Explicit raw field requests require edit access.' ),
		);
	}

	/**
	 * Builds the input schema for the `core/content-query` ability.
	 *
	 * The ability has three mutually exclusive modes, modeled as a `oneOf` so invalid
	 * combinations are rejected rather than silently ignored:
	 *
	 *   - Get a single post by `id` (optionally guarded by `post_type`).
	 *   - Get a single post by `post_type` and `slug`.
	 *   - Query a set of posts by `post_type` plus filters (`status`, `author_slug`, `parent`,
	 *     `include`, `page`, `per_page`).
	 *
	 * Each mode sets `additionalProperties: false`, so e.g. passing `per_page` alongside `id`
	 * fails validation instead of being dropped. `fields` is accepted in every mode.
	 *
	 * @since 7.2.0
	 *
	 * @param list<string> $post_types Exposed post type names.
	 * @param list<string> $statuses   Requestable post status slugs.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_content_query_input_schema( array $post_types, array $statuses ): array {
		$fields  = $this->get_fields_input_schema();
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
							'description' => __( 'Retrieve a single readable post by slug. Resolves to the newest readable match, preferring published posts. In hierarchical post types, posts under different parents can share a slug; use `id` to get a specific one.' ),
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
						'post_type'   => array(
							'type'        => 'string',
							'enum'        => $post_types,
							'description' => __( 'Post type to query for readable posts.' ),
						),
						'status'      => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'items'       => array(
								'type' => 'string',
								'enum' => $statuses,
							),
							'description' => __( 'Filter readable posts by one or more post statuses. Defaults to publish. Non-published statuses require the appropriate capabilities.' ),
						),
						'author_slug' => array(
							'type'        => 'string',
							'minLength'   => 1,
							'description' => __( "Filter by the author's user slug (nicename), as the REST API users endpoint returns it in `slug`. Only supported for post types that support authors." ),
						),
						'parent'      => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => __( 'Filter by parent post ID. Only supported for hierarchical post types. Use 0 for top-level posts.' ),
						),
						'include'     => $include,
						'fields'      => $fields,
						'page'        => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Page of results to return. Requesting a page beyond the last one is an error. Check `total_pages` before requesting later pages.' ),
						),
						'per_page'    => array(
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
	 * Builds the output schema of a single post, shared by all content abilities.
	 *
	 * No field is marked required because the `fields` input lets the caller request any
	 * subset, and a field is only present when its post type supports it.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> The post JSON Schema.
	 */
	private function get_content_output_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => $this->get_post_properties(),
		);
	}

	/**
	 * Builds the output schema for the `core/content-query` ability.
	 *
	 * No field is marked required because the `fields` input lets the caller request any
	 * subset, and a field is only present when its post type supports it. Single-post
	 * mode returns the post object directly, while query mode returns a paginated wrapper.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> The output JSON Schema.
	 */
	private function get_content_query_output_schema(): array {
		$post_schema = $this->get_content_output_schema();

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
	 * Formats a post into the ability output shape.
	 *
	 * As the REST posts controller does, the post is set up as the global post while its
	 * fields are built, so filters that rely on loop globals, including title filters, see
	 * the requested post. For an editor of a password-protected post, the cookie-based
	 * password gate is also suspended, so rendered fields resolve to real values instead of
	 * protected-post placeholders. The field projection itself is delegated to
	 * {@see self::build_post_fields()}.
	 *
	 * A field projection can legitimately be empty, for example when the only requested
	 * field is one the post type does not support. An empty PHP array encodes as `[]`,
	 * which would break the `object` output schema, so an empty object is returned instead.
	 * The REST posts controller returns `[]` in that case.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Post      $post   The post object.
	 * @param list<string> $fields The requested field names.
	 * @return array<string, mixed>|stdClass The formatted post data, or an empty object when the projection is empty.
	 */
	private function format_post( WP_Post $post, array $fields ) {
		$can_edit          = current_user_can( 'edit_post', $post->ID );
		$password_required = post_password_required( $post );
		$unlock_password   = $password_required && $can_edit;
		$previous_context  = $this->set_up_post_context( $post );

		/*
		 * The filter unlocks only posts the current user can edit, mirroring the REST posts
		 * controller's check_password_required(): an unconditional bypass (e.g. __return_false)
		 * would also expose other protected posts that the content filter may render, such as
		 * posts pulled in by a Query Loop block.
		 */
		if ( $unlock_password ) {
			add_filter( 'post_password_required', array( $this, 'allow_password_content' ), 10, 2 );
		}

		/*
		 * Undo both in a finally block, so a throw mid-render cannot leave the password gate
		 * disabled or the global post pointing at this post for the rest of the request.
		 */
		try {
			$data = $this->build_post_fields( $post, $fields, $can_edit, $password_required && ! $can_edit );
		} finally {
			if ( $unlock_password ) {
				remove_filter( 'post_password_required', array( $this, 'allow_password_content' ), 10 );
			}

			$this->restore_post_context( $previous_context );
		}

		return array() === $data ? (object) array() : $data;
	}

	/**
	 * Builds the requested field projection for a post.
	 *
	 * Only the requested fields that the post type supports and the current user can see are
	 * included. Raw fields are edit-context fields; rendered fields are read-context fields and
	 * are withheld for password-protected posts unless the current user can edit the post,
	 * mirroring the REST API behavior.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Post      $post         The post object.
	 * @param list<string> $fields       The requested field names.
	 * @param bool         $can_edit     Whether the current user can edit the post.
	 * @param bool         $is_protected Whether rendered fields must be withheld as password-protected.
	 * @return array<string, mixed> The formatted post data.
	 */
	private function build_post_fields( WP_Post $post, array $fields, bool $can_edit, bool $is_protected ): array {
		$post_type = $post->post_type;

		// Edit-context fields require edit access; drop them so EDIT_FIELDS is the single gate.
		if ( ! $can_edit ) {
			$fields = array_diff( $fields, self::EDIT_FIELDS );
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
			$data['date'] = $this->format_date( $post, 'date', false );
		}
		if ( isset( $requested['date_gmt'] ) ) {
			$data['date_gmt'] = $this->format_date( $post, 'date', true );
		}
		if ( isset( $requested['modified'] ) ) {
			$data['modified'] = $this->format_date( $post, 'modified', false );
		}
		if ( isset( $requested['modified_gmt'] ) ) {
			$data['modified_gmt'] = $this->format_date( $post, 'modified', true );
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

		if ( isset( $requested['author_slug'] ) && post_type_supports( $post_type, 'author' ) ) {
			$author              = get_userdata( (int) $post->post_author );
			$data['author_slug'] = $author ? $author->user_nicename : '';
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
	 * @since 7.2.0
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
	 * Sets up the global post context for rendering a post.
	 *
	 * Sets the global post and calls setup_postdata(), as the REST posts controller does
	 * before it renders a post, so filters that rely on loop globals render against the
	 * requested post.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Post $post The post to render.
	 * @return array<string, mixed> The previous loop globals, keyed by name, leaving out those that were not set.
	 */
	private function set_up_post_context( WP_Post $post ): array {
		/*
		 * Copy each global by value. A calling function that binds a global with `global`,
		 * as load_template() and WP_Block::render() do, makes it a reference, which
		 * array_intersect_key( $GLOBALS, ... ) would keep. The saved copy would then follow
		 * the global to this post, and the restore would put this post back.
		 */
		$previous_context = array();
		foreach ( self::LOOP_GLOBALS as $name ) {
			if ( ! array_key_exists( $name, $GLOBALS ) ) {
				continue;
			}

			$previous_context[ $name ] = $GLOBALS[ $name ];
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporarily mirrors REST post context for rendering.
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		return $previous_context;
	}

	/**
	 * Restores the global post context saved by {@see self::set_up_post_context()}.
	 *
	 * Each loop global gets its previous value back, or is unset again when it was not set.
	 * wp_reset_postdata() alone is not enough: it does nothing when the main query has no
	 * post, which would leave the rendered post's data in the globals. When a post was set
	 * up before, setup_postdata() first runs for it again, as wp_reset_postdata() would, so
	 * callbacks on the `the_post` action can restore their own globals too. A global post
	 * that was never set up keeps the loop globals that setup_postdata() gives it.
	 *
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $previous_context The loop globals that set_up_post_context() returned.
	 */
	private function restore_post_context( array $previous_context ): void {
		$previous_post = $previous_context['post'] ?? null;
		if ( $previous_post instanceof WP_Post ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the previous global post context.
			$GLOBALS['post'] = $previous_post;
			setup_postdata( $previous_post );

			/*
			 * A global post that was never set up, such as the main post before the loop
			 * starts, keeps what setup_postdata() just gave it. Do not put back the values
			 * saved before: `the_post` has fired now, so get_the_content() called without a
			 * post, as the Post Content block and the_content() outside the loop do, reads the
			 * loop globals instead of the post, and an unset `$pages` makes it throw a
			 * TypeError that breaks the page being rendered.
			 */
			if ( ! is_array( $previous_context['pages'] ?? null ) ) {
				return;
			}
		}

		foreach ( self::LOOP_GLOBALS as $name ) {
			if ( array_key_exists( $name, $previous_context ) ) {
				$GLOBALS[ $name ] = $previous_context[ $name ];
			} else {
				unset( $GLOBALS[ $name ] );
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
	 * @return string The ISO 8601 date, or an empty string if unavailable.
	 */
	private function format_date( WP_Post $post, string $field, bool $gmt ): string {
		$datetime = $gmt ? get_post_datetime( $post, $field, 'gmt' ) : false;
		if ( ! $datetime ) {
			$datetime = get_post_datetime( $post, $field );
		}

		if ( ! $datetime ) {
			return '';
		}

		return ( $gmt ? $datetime->setTimezone( new DateTimeZone( 'UTC' ) ) : $datetime )->format( 'c' );
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
	 * @since 7.2.0
	 *
	 * @return WP_Error The not-found error.
	 */
	private function not_found_error(): WP_Error {
		return new WP_Error(
			'content_not_found',
			__( 'The requested content was not found.' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Builds the error for a query filter that cannot be honored.
	 *
	 * @since 7.2.0
	 *
	 * @param string $message The error message.
	 * @return WP_Error The invalid filter error.
	 */
	private function invalid_filter_error( string $message ): WP_Error {
		return new WP_Error( 'content_invalid_filter', $message, array( 'status' => 400 ) );
	}
}
