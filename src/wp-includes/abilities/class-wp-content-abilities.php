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
 * Provides the read-only `core/content` ability, which retrieves one or more editable
 * posts of a post type that opts in via the `show_in_abilities` argument. It supports
 * fetching a single editable post by ID or slug, or querying multiple editable posts
 * with a small set of filters, and returns a basic, support-aware set of fields per post.
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
	 * The fields a post object may expose, in output order.
	 *
	 * Base fields (id, type, status, date, modified, slug, link) are always available.
	 * The remaining fields are only returned when the post type supports them.
	 *
	 * @since 7.1.0
	 * @var string[]
	 */
	private array $fields = array(
		'id',
		'type',
		'status',
		'date',
		'modified',
		'slug',
		'link',
		'title',
		'excerpt',
		'raw_content',
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
	 * Registers the read-only `core/content` ability.
	 *
	 * @since 7.1.0
	 */
	private function register_get_content(): void {
		// Compute once; check_permission()/execute_get_content() reuse this set.
		$this->exposed_post_types = $this->get_exposed_post_types();

		$post_types = array_keys( $this->exposed_post_types );
		$statuses   = $this->get_available_statuses();

		wp_register_ability(
			'core/content',
			array(
				'label'               => __( 'Get Content' ),
				'description'         => __( 'Retrieves one or more editable posts of a post type exposed to abilities. Fetch a single editable post by ID or by slug, or query multiple editable posts filtered by post type, status, author, or parent. Returns a basic, support-aware set of fields per post.' ),
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
	 * Permission callback for the `core/content` ability.
	 *
	 * Implements defense in depth: this gate decides whether the request may proceed at
	 * all (coarse, by post type capabilities), while the per-post `edit_post` meta
	 * capability check in {@see self::execute_get_content()} is the authoritative,
	 * row-level enforcement of author-scoped visibility.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	public function check_permission( $input = array() ): bool {
		$input   = is_array( $input ) ? $input : array();
		$exposed = $this->exposed_post_types ?? $this->get_exposed_post_types();

		// Single-post mode (by ID).
		if ( ! empty( $input['id'] ) ) {
			$post = get_post( $this->input_int( $input['id'] ) );

			if ( ! $post
				|| ! isset( $exposed[ $post->post_type ] )
				|| ( ! empty( $input['post_type'] ) && $post->post_type !== $input['post_type'] )
			) {
				return false;
			}

			return current_user_can( 'edit_post', $post->ID );
		}

		// Query / slug mode requires an exposed post type.
		$post_type = isset( $input['post_type'] ) && is_string( $input['post_type'] ) ? $input['post_type'] : '';
		if ( '' === $post_type || ! isset( $exposed[ $post_type ] ) ) {
			return false;
		}

		$post_type_object      = $exposed[ $post_type ];
		$edit_posts_capability = $this->capability( $post_type_object, 'edit_posts', 'edit_posts' );

		return current_user_can( $edit_posts_capability );
	}

	/**
	 * Resolves a capability name from a post type's capability object, with a fallback.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Post_Type $post_type_object The post type object.
	 * @param string       $name             Capability key on the post type's `cap` object.
	 * @param string       $fallback         Fallback capability name if unset or non-string.
	 * @return string The resolved capability name.
	 */
	private function capability( WP_Post_Type $post_type_object, string $name, string $fallback ): string {
		$capability = $post_type_object->cap->$name ?? $fallback;

		return is_string( $capability ) ? $capability : $fallback;
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
	 * Executes the `core/content` ability.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return array<string, mixed>|WP_Error A map with a `posts` list, or a WP_Error on failure.
	 */
	public function execute_get_content( $input = array() ) {
		$input   = is_array( $input ) ? $input : array();
		$exposed = $this->exposed_post_types ?? $this->get_exposed_post_types();
		$fields  = $this->normalize_fields( $input );

		// Single-post mode (by ID).
		if ( ! empty( $input['id'] ) ) {
			$post = get_post( $this->input_int( $input['id'] ) );

			if ( ! $post
				|| ! isset( $exposed[ $post->post_type ] )
				|| ( ! empty( $input['post_type'] ) && $post->post_type !== $input['post_type'] )
				|| ! current_user_can( 'edit_post', $post->ID )
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
			'post_type'           => $post_type,
			'post_status'         => $this->normalize_statuses( $input ),
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'perm'                => 'editable',
			'ignore_sticky_posts' => true,
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
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$posts[] = $this->format_post( $post, $fields );
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
		$exposed = array();

		foreach ( get_post_types( array(), 'objects' ) as $post_type_object ) {
			if ( empty( $post_type_object->show_in_abilities ) ) {
				continue;
			}
			$exposed[ $post_type_object->name ] = $post_type_object;
		}

		return $exposed;
	}

	/**
	 * Returns the post statuses that may be requested through the ability.
	 *
	 * Internal statuses (auto-draft, inherit, trash) are excluded.
	 *
	 * @since 7.1.0
	 *
	 * @return string[] List of public, non-internal post status slugs.
	 */
	private function get_available_statuses(): array {
		return array_values( get_post_stati( array( 'internal' => false ) ) );
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
	 * An empty or absent `fields` value selects every field.
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

		$requested = array_filter( $input['fields'], 'is_string' );
		$fields    = array_intersect( $this->fields, $requested );

		return array() === $fields ? $this->fields : array_values( $fields );
	}

	/**
	 * Builds the input schema for the `core/content` ability.
	 *
	 * The ability has two mutually exclusive modes, modeled as a `oneOf` so invalid
	 * combinations are rejected rather than silently ignored:
	 *
	 *   - Get a single editable post by `id` (optionally guarded by `post_type`).
	 *   - Query a set of editable posts by `post_type` plus filters (`slug`, `status`,
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
			'description' => __( 'Limit each returned post to these fields. If omitted, all supported fields are returned.' ),
		);

		return array(
			'type'  => 'object',
			'oneOf' => array(
				// Mode 1: retrieve a single editable post by ID.
				array(
					'title'                => __( 'Get a single editable post by ID' ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
					'properties'           => array(
						'id'        => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Retrieve a single editable post by ID.' ),
						),
						'post_type' => array(
							'type'        => 'string',
							'enum'        => $post_types,
							'description' => __( 'Optional. Restrict the lookup to this post type; the post is returned only if it matches and the current user can edit it.' ),
						),
						'fields'    => $fields,
					),
				),
				// Mode 2: query a set of editable posts by post type and filters.
				array(
					'title'                => __( 'Query editable posts by type and filters' ),
					'required'             => array( 'post_type' ),
					'additionalProperties' => false,
					'properties'           => array(
						'post_type' => array(
							'type'        => 'string',
							'enum'        => $post_types,
							'description' => __( 'Post type to query for editable posts.' ),
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
							'description' => __( 'Filter editable posts by one or more post statuses. Defaults to publish. Non-published statuses require the appropriate capabilities.' ),
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
	 * Builds the output schema for the `core/content` ability.
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
				'id'          => array(
					'type'        => 'integer',
					'description' => __( 'The post ID.' ),
				),
				'type'        => array(
					'type'        => 'string',
					'description' => __( 'The post type.' ),
				),
				'status'      => array(
					'type'        => 'string',
					'description' => __( 'The post status.' ),
				),
				'date'        => array(
					'type'        => 'string',
					'description' => __( 'The publication date, in ISO 8601 format (GMT).' ),
				),
				'modified'    => array(
					'type'        => 'string',
					'description' => __( 'The last modified date, in ISO 8601 format (GMT).' ),
				),
				'slug'        => array(
					'type'        => 'string',
					'description' => __( 'The post slug.' ),
				),
				'link'        => array(
					'type'        => 'string',
					'description' => __( 'The permalink URL.' ),
				),
				'title'       => array(
					'type'        => 'string',
					'description' => __( 'The post title. Present when the post type supports titles.' ),
				),
				'excerpt'     => array(
					'type'        => 'string',
					'description' => __( 'The post excerpt. Present when the post type supports excerpts. Empty when withheld for a password-protected post.' ),
				),
				'raw_content' => array(
					'type'        => 'string',
					'description' => __( 'The raw, unfiltered post content (block markup). Present when the post type supports the editor.' ),
				),
				'author'      => array(
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
				'parent'      => array(
					'type'        => 'integer',
					'description' => __( 'The parent post ID. Present for hierarchical post types.' ),
				),
			),
		);

		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'posts'       => array(
					'type'        => 'array',
					'description' => __( 'The editable posts matching the request. A single-element list when requested by ID.' ),
					'items'       => $post_schema,
				),
				'total'       => array(
					'type'        => 'integer',
					'description' => __( 'Total number of posts matching the query, across all pages, after applying the editable permission filter to the query. Surfaced over REST as the X-WP-Total header.' ),
				),
				'total_pages' => array(
					'type'        => 'integer',
					'description' => __( 'Total number of query result pages available after applying the editable permission filter to the query. Surfaced over REST as the X-WP-TotalPages header.' ),
				),
			),
		);
	}

	/**
	 * Formats a post into the ability output shape.
	 *
	 * Only the requested fields that the post type supports are included. Content and
	 * excerpt are withheld for password-protected posts unless the current user can edit
	 * the post, mirroring the REST API behavior.
	 *
	 * @since 7.1.0
	 *
	 * @param WP_Post  $post   The post object.
	 * @param string[] $fields The requested field names.
	 * @return array<string, mixed> The formatted post data.
	 */
	private function format_post( WP_Post $post, array $fields ): array {
		$type      = $post->post_type;
		$wants     = static function ( string $field ) use ( $fields ): bool {
			return in_array( $field, $fields, true );
		};
		$can_edit  = current_user_can( 'edit_post', $post->ID );
		$protected = post_password_required( $post ) && ! $can_edit;

		$data = array();

		if ( $wants( 'id' ) ) {
			$data['id'] = (int) $post->ID;
		}
		if ( $wants( 'type' ) ) {
			$data['type'] = $type;
		}
		if ( $wants( 'status' ) ) {
			$data['status'] = $post->post_status;
		}
		if ( $wants( 'date' ) ) {
			$data['date'] = $this->format_gmt_date( $post, 'date' );
		}
		if ( $wants( 'modified' ) ) {
			$data['modified'] = $this->format_gmt_date( $post, 'modified' );
		}
		if ( $wants( 'slug' ) ) {
			$data['slug'] = $post->post_name;
		}
		if ( $wants( 'link' ) ) {
			$data['link'] = (string) get_permalink( $post );
		}

		if ( $wants( 'title' ) && post_type_supports( $type, 'title' ) ) {
			$data['title'] = $this->get_title( $post );
		}

		if ( $wants( 'excerpt' ) && post_type_supports( $type, 'excerpt' ) ) {
			$data['excerpt'] = $protected ? '' : (string) get_the_excerpt( $post );
		}

		if ( $wants( 'raw_content' ) && post_type_supports( $type, 'editor' ) ) {
			$data['raw_content'] = $can_edit && ! $protected ? (string) $post->post_content : '';
		}

		if ( $wants( 'author' ) && post_type_supports( $type, 'author' ) ) {
			$author         = get_userdata( (int) $post->post_author );
			$data['author'] = array(
				'id'           => (int) $post->post_author,
				'display_name' => $author ? $author->display_name : '',
			);
		}

		if ( $wants( 'parent' ) && is_post_type_hierarchical( $type ) ) {
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
