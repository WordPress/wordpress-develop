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
 * Provides the read-only `core/read-content` ability, which retrieves one or more readable
 * posts of a post type that opts in via the `show_in_abilities` argument. It supports
 * fetching a single readable post by ID or slug, or querying multiple readable posts
 * with a small set of filters, and returns a support-aware set of fields per post.
 * Raw fields are only returned for posts the current user can edit.
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
	 * Mirrors the REST API collection ceiling.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Fields that expose edit-context post data.
	 *
	 * Requests that explicitly include any of these fields require edit access. When
	 * fields are omitted, these fields are returned only for posts the current user
	 * can edit.
	 *
	 * @since 7.1.0
	 * @var string[]
	 */
	private const EDIT_FIELDS = array(
		'title_raw',
		'excerpt_raw',
		'content_raw',
	);

	/**
	 * The fields a post object may expose, in output order.
	 *
	 * Read-context fields are returned for readable posts. Edit-context fields are
	 * returned only when explicitly requested by a user with edit access, or when
	 * fields are omitted and the user can edit the post.
	 *
	 * @since 7.1.0
	 * @var string[]
	 */
	private array $fields = array(
		'id',
		'type',
		'status',
		'date',
		'date_gmt',
		'modified',
		'modified_gmt',
		'slug',
		'link',
		'title_raw',
		'title_rendered',
		'excerpt_raw',
		'excerpt_rendered',
		'excerpt_protected',
		'content_raw',
		'content_rendered',
		'content_protected',
		'author',
		'parent',
	);

	/**
	 * Post types exposed through the Abilities API, computed once at registration.
	 *
	 * Cached so the input schema and the permission/execute callbacks derive from the exact
	 * same set, and the post type list is only walked once per request.
	 *
	 * @since 7.1.0
	 * @var array<string, WP_Post_Type>|null
	 */
	private ?array $exposed_post_types = null;

	/**
	 * Registers all content abilities.
	 *
	 * Must run on the `wp_abilities_api_init` hook.
	 *
	 * @since 7.1.0
	 */
	public function register(): void {
		$this->register_get_content();

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
	private function register_get_content(): void {
		// Compute once; check_permission()/execute_get_content() reuse this set.
		$this->exposed_post_types = $this->get_exposed_post_types();

		$post_types = array_keys( $this->exposed_post_types );
		$statuses   = array_values( get_post_stati( array( 'internal' => false ) ) );

		wp_register_ability(
			'core/read-content',
			array(
				'label'               => __( 'Read Content' ),
				'description'         => __( 'Retrieves one or more readable posts of a post type exposed to abilities. Fetch a single readable post by ID or by slug, or query multiple readable posts filtered by post type, status, author, or parent. Returns a basic, support-aware set of fields per post, with raw fields limited to users who can edit the post.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_content_input_schema( $post_types, $statuses ),
				'output_schema'       => $this->get_content_output_schema(),
				'execute_callback'    => array( $this, 'execute_get_content' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
					// Opt into REST-level pagination: query mode accepts `page`/`per_page`
					// and returns `total`/`total_pages`, which the run controller turns into
					// the standard X-WP-Total / X-WP-TotalPages response headers.
					'pagination'   => true,
				),
			)
		);
	}

	/**
	 * Permission callback for the `core/read-content` ability.
	 *
	 * Implements defense in depth: this gate decides whether the request may proceed at
	 * all, while the per-post read/edit checks in {@see self::execute_get_content()}
	 * are the authoritative, row-level enforcement. Requests that explicitly ask for
	 * edit-context fields require edit access before execution.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	public function check_permission( $input = array() ): bool {
		$input   = is_array( $input ) ? $input : array();
		$exposed = $this->exposed_post_types ?? $this->get_exposed_post_types();

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

		// Query / slug mode requires an exposed post type.
		$post_type = isset( $input['post_type'] ) && is_string( $input['post_type'] ) ? $input['post_type'] : '';
		if ( '' === $post_type || ! isset( $exposed[ $post_type ] ) ) {
			return false;
		}

		$post_type_object = $exposed[ $post_type ];
		if ( $requires_edit ) {
			return current_user_can( $post_type_object->cap->edit_posts );
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
		if ( empty( $input['fields'] ) || ! is_array( $input['fields'] ) ) {
			return false;
		}

		$requested_fields = array_filter( $input['fields'], 'is_string' );

		return array() !== array_intersect( self::EDIT_FIELDS, $requested_fields );
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
	 * @since 7.1.0
	 *
	 * @param WP_Post $post Post object.
	 * @return bool Whether the post can be read.
	 */
	private function check_read_permission( WP_Post $post ): bool {
		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type instanceof WP_Post_Type || empty( $post_type->show_in_abilities ) ) {
			return false;
		}

		if ( 'publish' === $post->post_status || current_user_can( 'read_post', $post->ID ) ) {
			return true;
		}

		$post_status_object = get_post_status_object( $post->post_status );
		if ( $post_status_object && $post_status_object->public ) {
			return true;
		}

		if ( 'inherit' === $post->post_status && $post->post_parent > 0 ) {
			$parent = get_post( $post->post_parent );
			if ( $parent instanceof WP_Post ) {
				return $this->check_read_permission( $parent );
			}
		}

		return 'inherit' === $post->post_status;
	}

	/**
	 * Executes the `core/read-content` ability.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return array<string, mixed>|WP_Error A map with a `posts` list, or a WP_Error on failure.
	 */
	public function execute_get_content( $input = array() ) {
		$input         = is_array( $input ) ? $input : array();
		$exposed       = $this->exposed_post_types ?? $this->get_exposed_post_types();
		$fields        = $this->normalize_fields( $input );
		$requires_edit = $this->has_explicit_edit_fields( $input );

		// Single-post mode (by ID).
		if ( ! empty( $input['id'] ) ) {
			$post = get_post( $this->input_int( $input['id'] ) );

			if ( ! $post
				|| ! isset( $exposed[ $post->post_type ] )
				|| ( ! empty( $input['post_type'] ) && $post->post_type !== $input['post_type'] )
				|| ( $requires_edit && ! current_user_can( 'edit_post', $post->ID ) )
				|| ( ! $requires_edit && ! $this->check_read_permission( $post ) )
			) {
				return $this->not_found_error();
			}

			return array(
				'posts'       => array( $this->format_post( $post, $fields ) ),
				'total'       => 1,
				'total_pages' => 1,
			);
		}

		// Query / slug mode.
		$post_type = isset( $input['post_type'] ) && is_string( $input['post_type'] ) ? $input['post_type'] : '';
		if ( '' === $post_type || ! isset( $exposed[ $post_type ] ) ) {
			return $this->not_found_error();
		}

		$per_page = $this->normalize_per_page( $input );
		$page     = isset( $input['page'] ) ? max( 1, $this->input_int( $input['page'] ) ) : 1;

		$query_args = array(
			'post_type'              => $post_type,
			'post_status'            => $this->normalize_statuses( $input ),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'perm'                   => $requires_edit ? 'editable' : 'readable',
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( ! empty( $input['slug'] ) && is_string( $input['slug'] ) ) {
			$query_args['name'] = sanitize_title( $input['slug'] );
		}

		if ( ! empty( $input['author'] ) ) {
			$query_args['author'] = $this->input_int( $input['author'] );
		}

		if ( isset( $input['parent'] ) ) {
			$query_args['post_parent'] = $this->input_int( $input['parent'] );
		}

		$query = new WP_Query( $query_args );

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
				$formatted = $this->format_post( $post, $fields );
			if ( array() === $formatted ) {
				continue;
			}
				$posts[] = $formatted;
		}

		return array(
			'posts'       => $posts,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
		);
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
	 * Returns the post types exposed through the Abilities API, keyed by name.
	 *
	 * Only post types whose `show_in_abilities` argument is truthy are exposed.
	 *
	 * @since 7.1.0
	 *
	 * @return array<string, WP_Post_Type> Exposed post type objects keyed by name.
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
	 * @return string[] Normalized list of post status slugs.
	 */
	private function normalize_statuses( array $input ): array {
		$statuses = $input['status'] ?? array( 'publish' );
		if ( ! is_array( $statuses ) ) {
			return array( 'publish' );
		}

		$statuses = array_values( array_filter( $statuses, 'is_string' ) );

		return array() === $statuses ? array( 'publish' ) : array_map( 'sanitize_key', $statuses );
	}

	/**
	 * Normalizes the requested fields to the supported set, defaulting to all fields.
	 *
	 * An empty or absent `fields` value selects every field. Edit-context fields are
	 * still omitted per post when the current user cannot edit that post.
	 *
	 * @since 7.1.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return string[] List of requested field names.
	 */
	private function normalize_fields( array $input ): array {
		if ( empty( $input['fields'] ) || ! is_array( $input['fields'] ) ) {
			return $this->fields;
		}

		$requested_fields = array_filter( $input['fields'], 'is_string' );
		$fields           = array_intersect( $this->fields, $requested_fields );

		return array() === $fields ? $this->fields : array_values( $fields );
	}

	/**
	 * Builds the input schema for the `core/read-content` ability.
	 *
	 * The ability has two mutually exclusive modes, modeled as a `oneOf` so invalid
	 * combinations are rejected rather than silently ignored:
	 *
	 *   - Get a single readable post by `id` (optionally guarded by `post_type`).
	 *   - Query a set of readable posts by `post_type` plus filters (`slug`, `status`,
	 *     `author`, `parent`, `page`, `per_page`).
	 *
	 * Each mode sets `additionalProperties: false`, so e.g. passing `per_page` alongside `id`
	 * fails validation instead of being dropped. `fields` is accepted in both modes.
	 *
	 * @since 7.1.0
	 *
	 * @param string[] $post_types Exposed post type names.
	 * @param string[] $statuses   Requestable post status slugs.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_content_input_schema( array $post_types, array $statuses ): array {
		$fields = array(
			'type'        => 'array',
			'uniqueItems' => true,
			'items'       => array(
				'type' => 'string',
				'enum' => $this->fields,
			),
			'description' => __( 'Limit each returned post to these fields. If omitted, all fields visible to the current user are returned. Explicit raw field requests require edit access.' ),
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
				// Mode 2: query a set of readable posts by post type and filters.
				array(
					'title'                => __( 'Query readable posts by type and filters' ),
					'required'             => array( 'post_type' ),
					'additionalProperties' => false,
					'properties'           => array(
						'post_type' => array(
							'type'        => 'string',
							'enum'        => $post_types,
							'description' => __( 'Post type to query for readable posts.' ),
						),
						'slug'      => array(
							'type'        => 'string',
							'description' => __( 'Filter by slug. Combined with `post_type`, as slugs are not unique across post types.' ),
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
							'description' => __( 'Filter by author user ID.' ),
						),
						'parent'    => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => __( 'Filter by parent post ID, for hierarchical post types. Use 0 for top-level posts.' ),
						),
						'fields'    => $fields,
						'page'      => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Page of results to return.' ),
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
	 * subset, and a field is only present when its post type supports it.
	 *
	 * @since 7.1.0
	 *
	 * @return array<string, mixed> The output JSON Schema.
	 */
	private function get_content_output_schema(): array {
		$post_schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'id'                => array(
					'type'        => 'integer',
					'description' => __( 'The post ID.' ),
				),
				'type'              => array(
					'type'        => 'string',
					'description' => __( 'The post type.' ),
				),
				'status'            => array(
					'type'        => 'string',
					'description' => __( 'The post status.' ),
				),
				'date'              => array(
					'type'        => 'string',
					'description' => __( "The publication date, in ISO 8601 format using the site's timezone." ),
				),
				'date_gmt'          => array(
					'type'        => 'string',
					'description' => __( 'The publication date, in ISO 8601 format as GMT.' ),
				),
				'modified'          => array(
					'type'        => 'string',
					'description' => __( "The last modified date, in ISO 8601 format using the site's timezone." ),
				),
				'modified_gmt'      => array(
					'type'        => 'string',
					'description' => __( 'The last modified date, in ISO 8601 format as GMT.' ),
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
					'description' => __( 'The rendered post excerpt. Present when the post type supports excerpts. Empty when withheld for a password-protected post.' ),
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
						'id'           => array(
							'type'        => 'integer',
							'description' => __( 'The author user ID.' ),
						),
						'display_name' => array(
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
			),
		);

			return array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'posts', 'total', 'total_pages' ),
				'properties'           => array(
					'posts'       => array(
						'type'        => 'array',
						'description' => __( 'The readable posts matching the request. A single-element list when requested by ID.' ),
						'items'       => $post_schema,
					),
					'total'       => array(
						'type'        => 'integer',
						'description' => __( 'Total number of posts matching the query, across all pages, after applying the permission filter to the query. Surfaced over REST as the X-WP-Total header.' ),
					),
					'total_pages' => array(
						'type'        => 'integer',
						'description' => __( 'Total number of query result pages available after applying the permission filter to the query. Surfaced over REST as the X-WP-TotalPages header.' ),
					),
				),
			);
	}

	/**
	 * Formats a post into the ability output shape.
	 *
	 * Only the requested fields that the post type supports and the current user can see
	 * are included. Raw fields are edit-context fields; rendered fields are read-context
	 * fields and are withheld for password-protected posts unless the current user can edit
	 * the post, mirroring the REST API behavior.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Post  $post   The post object.
	 * @param string[] $fields The requested field names.
	 * @return array<string, mixed> The formatted post data.
	 */
	private function format_post( WP_Post $post, array $fields ): array {
		$post_type        = $post->post_type;
		$fields_requested = static function ( string $field ) use ( $fields ): bool {
			return in_array( $field, $fields, true );
		};
		$can_edit         = current_user_can( 'edit_post', $post->ID );
		$protected        = post_password_required( $post ) && ! $can_edit;

		$data = array();

		if ( $fields_requested( 'id' ) ) {
			$data['id'] = (int) $post->ID;
		}
		if ( $fields_requested( 'type' ) ) {
			$data['type'] = $post_type;
		}
		if ( $fields_requested( 'status' ) ) {
			$data['status'] = $post->post_status;
		}
		if ( $fields_requested( 'date' ) ) {
			$data['date'] = $this->format_local_date( $post, 'date' );
		}
		if ( $fields_requested( 'date_gmt' ) ) {
			$data['date_gmt'] = $this->format_gmt_date( $post, 'date' );
		}
		if ( $fields_requested( 'modified' ) ) {
			$data['modified'] = $this->format_local_date( $post, 'modified' );
		}
		if ( $fields_requested( 'modified_gmt' ) ) {
			$data['modified_gmt'] = $this->format_gmt_date( $post, 'modified' );
		}
		if ( $fields_requested( 'slug' ) ) {
			$data['slug'] = $post->post_name;
		}
		if ( $fields_requested( 'link' ) ) {
			$data['link'] = (string) get_permalink( $post );
		}

		if ( $fields_requested( 'title_raw' ) && post_type_supports( $post_type, 'title' ) && $can_edit ) {
			$data['title_raw'] = $post->post_title;
		}

		if ( $fields_requested( 'title_rendered' ) && post_type_supports( $post_type, 'title' ) ) {
			$data['title_rendered'] = $this->get_title( $post );
		}

		if ( $fields_requested( 'excerpt_raw' ) && post_type_supports( $post_type, 'excerpt' ) && $can_edit ) {
			$data['excerpt_raw'] = $post->post_excerpt;
		}

		if ( $fields_requested( 'excerpt_rendered' ) && post_type_supports( $post_type, 'excerpt' ) ) {
			$data['excerpt_rendered'] = $protected ? '' : (string) get_the_excerpt( $post );
		}

		if ( $fields_requested( 'excerpt_protected' ) && post_type_supports( $post_type, 'excerpt' ) ) {
			$data['excerpt_protected'] = (bool) $post->post_password;
		}

		if ( $fields_requested( 'content_raw' ) && post_type_supports( $post_type, 'editor' ) && $can_edit ) {
			$data['content_raw'] = $post->post_content;
		}

		if ( $fields_requested( 'content_rendered' ) && post_type_supports( $post_type, 'editor' ) ) {
			$data['content_rendered'] = $protected ? '' : $this->get_rendered_content( $post );
		}

		if ( $fields_requested( 'content_protected' ) && post_type_supports( $post_type, 'editor' ) ) {
			$data['content_protected'] = (bool) $post->post_password;
		}

		if ( $fields_requested( 'author' ) && post_type_supports( $post_type, 'author' ) ) {
			$author         = get_userdata( (int) $post->post_author );
			$data['author'] = array(
				'id'           => (int) $post->post_author,
				'display_name' => $author ? $author->display_name : '',
			);
		}

		if ( $fields_requested( 'parent' ) && is_post_type_hierarchical( $post_type ) ) {
			$data['parent'] = (int) $post->post_parent;
		}

		return $data;
	}

	/**
	 * Returns the post title with the protected/private prefixes stripped.
	 *
	 * Mirrors the REST API, which removes the "Protected: " / "Private: " prefixes for
	 * machine consumers while still applying the_title filters.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Post $post The post object.
	 * @return string The post title.
	 */
	private function get_title( WP_Post $post ): string {
		$strip = array( $this, 'return_raw_title_format' );
		add_filter( 'protected_title_format', $strip );
		add_filter( 'private_title_format', $strip );
		$title = get_the_title( $post );
		remove_filter( 'protected_title_format', $strip );
		remove_filter( 'private_title_format', $strip );

		return $title;
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
	 * Returns post content transformed for display.
	 *
	 * Mirrors the REST posts controller by preparing post globals before applying
	 * `the_content`, then restoring the previous global post context.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Post $post The post object.
	 * @return string Rendered post content.
	 */
	private function get_rendered_content( WP_Post $post ): string {
		$previous_post = $GLOBALS['post'] ?? null;

		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		/** This filter is documented in wp-includes/post-template.php. */
		$content = apply_filters( 'the_content', $post->post_content );

		if ( $previous_post instanceof WP_Post ) {
			$GLOBALS['post'] = $previous_post;
			setup_postdata( $previous_post );
		} else {
			unset( $GLOBALS['post'] );
			wp_reset_postdata();
		}

		return (string) $content;
	}

	/**
	 * Formats a post date field as an ISO 8601 string in the site's timezone.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Post $post  The post object.
	 * @param string  $field Either 'date' or 'modified'.
	 * @return string The ISO 8601 date, or an empty string if unavailable.
	 */
	private function format_local_date( WP_Post $post, string $field ): string {
		$field    = 'modified' === $field ? 'modified' : 'date';
		$datetime = get_post_datetime( $post, $field, 'local' );
		if ( $datetime ) {
			return $datetime->format( 'c' );
		}

		$local     = 'modified' === $field ? $post->post_modified : $post->post_date;
		$timestamp = mysql2date( 'U', $local, false );

		return $timestamp ? wp_date( 'c', (int) $timestamp ) : '';
	}

	/**
	 * Formats a post date field as an ISO 8601 string in GMT.
	 *
	 * Uses get_post_datetime() so that posts without a GMT timestamp (e.g. some drafts)
	 * still resolve to a valid date.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Post $post  The post object.
	 * @param string  $field Either 'date' or 'modified'.
	 * @return string The ISO 8601 date, or an empty string if unavailable.
	 */
	private function format_gmt_date( WP_Post $post, string $field ): string {
		$field    = 'modified' === $field ? 'modified' : 'date';
		$datetime = get_post_datetime( $post, $field, 'gmt' );
		if ( $datetime ) {
			return $datetime->format( 'c' );
		}

		// Fallback for posts without a resolvable timestamp.
		$local     = 'modified' === $field ? $post->post_modified : $post->post_date;
		$timestamp = mysql2date( 'U', $local, false );

		return $timestamp ? gmdate( 'c', (int) $timestamp ) : '';
	}

	/**
	 * Builds the uniform not-found error.
	 *
	 * Used by execution when content cannot be resolved or edited after permission
	 * checks. The permission callback fails closed for uncertain by-ID lookups before
	 * execution runs.
	 *
	 * @since 7.1.0
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
}
