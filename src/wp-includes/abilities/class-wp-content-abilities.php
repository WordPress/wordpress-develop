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
 * Provides the read-only `core/content` ability, which retrieves one or more posts of a
 * post type that opts in via the `show_in_abilities` argument. It supports fetching a
 * single post by ID or slug, or querying multiple posts with a small set of filters, and
 * returns a basic, support-aware set of fields per post.
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
class WP_Content_Abilities {

	/**
	 * The ability category used for content abilities.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	const CATEGORY = 'content';

	/**
	 * The fields a post object may expose, in output order.
	 *
	 * Base fields (id, type, status, date, modified, slug, link) are always available.
	 * The remaining fields are only returned when the post type supports them.
	 *
	 * @since 7.1.0
	 * @var string[]
	 */
	const FIELDS = array(
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
	 * Default number of posts returned per page in query mode.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 10;

	/**
	 * Maximum number of posts returned per page in query mode.
	 *
	 * Mirrors the REST API collection ceiling.
	 *
	 * @since 7.1.0
	 * @var int
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Registers all content abilities.
	 *
	 * Must run on the `wp_abilities_api_init` hook.
	 *
	 * @since 7.1.0
	 */
	public static function register(): void {
		self::register_get_content();

		/*
		 * A future write-oriented ability can be registered here, reusing the shared
		 * helpers below (get_exposed_post_types(), format_post(), check_permission()):
		 *
		 *     self::register_manage_content();
		 */
	}

	/**
	 * Registers the read-only `core/content` ability.
	 *
	 * @since 7.1.0
	 */
	public static function register_get_content(): void {
		$post_types = array_keys( self::get_exposed_post_types() );
		$statuses   = self::get_available_statuses();

		wp_register_ability(
			'core/content',
			array(
				'label'               => __( 'Get Content' ),
				'description'         => __( 'Retrieves one or more posts of a post type exposed to abilities. Fetch a single post by ID or by slug, or query multiple posts filtered by post type, status, author, or parent. Returns a basic, support-aware set of fields per post.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::get_content_input_schema( $post_types, $statuses ),
				'output_schema'       => self::get_content_output_schema(),
				'execute_callback'    => array( self::class, 'execute_get_content' ),
				'permission_callback' => array( self::class, 'check_permission' ),
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
	 * all (coarse, by post type capabilities and requested statuses), while the per-post
	 * `read_post` meta capability check in {@see self::execute_get_content()} is the
	 * authoritative, row-level enforcement of author-scoped visibility.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	public static function check_permission( $input = array() ): bool {
		$input   = is_array( $input ) ? $input : array();
		$exposed = self::get_exposed_post_types();

		// Single-post mode (by ID).
		if ( ! empty( $input['id'] ) ) {
			$post = get_post( (int) $input['id'] );

			/*
			 * For a missing post, an unexposed post type, or a post type that does not
			 * match the requested one, fall back to a generic capability check rather
			 * than a row-level check on a guessed ID, so the response cannot be used to
			 * enumerate IDs or probe post-type membership. Execution returns a uniform
			 * 404 in these cases.
			 */
			if ( ! $post
				|| ! isset( $exposed[ $post->post_type ] )
				|| ( ! empty( $input['post_type'] ) && $post->post_type !== $input['post_type'] )
			) {
				return current_user_can( 'read' );
			}

			return current_user_can( 'read_post', $post->ID );
		}

		// Query / slug mode requires an exposed post type.
		$post_type = isset( $input['post_type'] ) ? (string) $input['post_type'] : '';
		if ( '' === $post_type || ! isset( $exposed[ $post_type ] ) ) {
			return false;
		}

		$post_type_object = $exposed[ $post_type ];

		// Base gate: must be able to read this post type at all.
		if ( ! current_user_can( $post_type_object->cap->read ?? 'read' ) ) {
			return false;
		}

		$statuses = self::normalize_statuses( $input );

		// Only published posts requested: always allowed for readers.
		if ( array( 'publish' ) === $statuses ) {
			return true;
		}

		// Editors/authors of this post type may request any status set.
		if ( current_user_can( $post_type_object->cap->edit_posts ?? 'edit_posts' ) ) {
			return true;
		}

		// Otherwise, private posts are allowed only with read_private_posts.
		if ( current_user_can( $post_type_object->cap->read_private_posts ?? 'read_private_posts' ) ) {
			foreach ( $statuses as $status ) {
				if ( 'private' !== $status && 'publish' !== $status ) {
					return false;
				}
			}
			return true;
		}

		return false;
	}

	/**
	 * Executes the `core/content` ability.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $input Optional. The ability input. Default empty array.
	 * @return array<string, mixed>|WP_Error A map with a `posts` list, or a WP_Error on failure.
	 */
	public static function execute_get_content( $input = array() ) {
		$input   = is_array( $input ) ? $input : array();
		$exposed = self::get_exposed_post_types();
		$fields  = self::normalize_fields( $input );

		// Single-post mode (by ID).
		if ( ! empty( $input['id'] ) ) {
			$post = get_post( (int) $input['id'] );

			if ( ! $post
				|| ! isset( $exposed[ $post->post_type ] )
				|| ( ! empty( $input['post_type'] ) && $post->post_type !== $input['post_type'] )
				|| ! current_user_can( 'read_post', $post->ID )
			) {
				return self::not_found_error();
			}

			return array(
				'posts'       => array( self::format_post( $post, $fields ) ),
				'total'       => 1,
				'total_pages' => 1,
			);
		}

		// Query / slug mode.
		$post_type = isset( $input['post_type'] ) ? (string) $input['post_type'] : '';
		if ( '' === $post_type || ! isset( $exposed[ $post_type ] ) ) {
			return self::not_found_error();
		}

		$per_page = self::normalize_per_page( $input );
		$page     = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;

		$query_args = array(
			'post_type'           => $post_type,
			'post_status'         => self::normalize_statuses( $input ),
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
		);

		if ( ! empty( $input['slug'] ) ) {
			$query_args['name'] = sanitize_title( (string) $input['slug'] );
		}

		if ( ! empty( $input['author'] ) ) {
			$query_args['author'] = (int) $input['author'];
		}

		if ( isset( $input['parent'] ) ) {
			$query_args['post_parent'] = (int) $input['parent'];
		}

		$query = new WP_Query( $query_args );

		$posts = array();
		foreach ( $query->posts as $post ) {
			// Authoritative, row-level visibility check (author/status scoped).
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}
			$posts[] = self::format_post( $post, $fields );
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
	 * @param array<string, mixed> $input The ability input.
	 * @return int The clamped per-page value.
	 */
	protected static function normalize_per_page( array $input ): int {
		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : self::DEFAULT_PER_PAGE;

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
	protected static function get_exposed_post_types(): array {
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
	protected static function get_available_statuses(): array {
		return array_values( get_post_stati( array( 'internal' => false ) ) );
	}

	/**
	 * Normalizes the requested statuses to a non-empty, sanitized list defaulting to publish.
	 *
	 * @since 7.1.0
	 *
	 * @param array<string, mixed> $input The ability input.
	 * @return string[] Normalized list of post status slugs.
	 */
	protected static function normalize_statuses( array $input ): array {
		$statuses = $input['status'] ?? array( 'publish' );
		if ( ! is_array( $statuses ) || array() === $statuses ) {
			return array( 'publish' );
		}

		return array_map( 'sanitize_key', $statuses );
	}

	/**
	 * Normalizes the requested fields to the supported set, defaulting to all fields.
	 *
	 * An empty or absent `fields` value selects every field.
	 *
	 * @since 7.1.0
	 *
	 * @param array<string, mixed> $input The ability input.
	 * @return string[] List of requested field names.
	 */
	protected static function normalize_fields( array $input ): array {
		if ( empty( $input['fields'] ) || ! is_array( $input['fields'] ) ) {
			return self::FIELDS;
		}

		$fields = array_intersect( self::FIELDS, array_map( 'strval', $input['fields'] ) );

		return array() === $fields ? self::FIELDS : array_values( $fields );
	}

	/**
	 * Builds the input schema for the `core/content` ability.
	 *
	 * @since 7.1.0
	 *
	 * @param string[] $post_types Exposed post type names.
	 * @param string[] $statuses   Requestable post status slugs.
	 * @return array<string, mixed> The input JSON Schema.
	 */
	protected static function get_content_input_schema( array $post_types, array $statuses ): array {
		return array(
			'type'                 => 'object',
			'default'              => array(),
			// `post_type` is required unless a single post is requested by `id`.
			'anyOf'                => array(
				array( 'required' => array( 'id' ) ),
				array( 'required' => array( 'post_type' ) ),
			),
			'properties'           => array(
				'post_type' => array(
					'type'        => 'string',
					'enum'        => $post_types,
					'description' => __( 'Post type to retrieve. Required unless `id` is provided.' ),
				),
				'id'        => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'Retrieve a single post by ID. When provided, `post_type` is optional.' ),
				),
				'slug'      => array(
					'type'        => 'string',
					'description' => __( 'Retrieve posts by slug. Requires `post_type`, as slugs are not unique across post types.' ),
				),
				'status'    => array(
					'type'        => 'array',
					'uniqueItems' => true,
					'default'     => array( 'publish' ),
					'items'       => array(
						'type' => 'string',
						'enum' => $statuses,
					),
					'description' => __( 'Filter by one or more post statuses. Defaults to publish. Non-published statuses require the appropriate capabilities.' ),
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
				'fields'    => array(
					'type'        => 'array',
					'uniqueItems' => true,
					'items'       => array(
						'type' => 'string',
						'enum' => self::FIELDS,
					),
					'description' => __( 'Limit each returned post to these fields. If omitted, all supported fields are returned.' ),
				),
				'page'      => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'default'     => 1,
					'description' => __( 'Page of results to return in query mode. Ignored when retrieving a single post by ID.' ),
				),
				'per_page'  => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => self::MAX_PER_PAGE,
					'default'     => self::DEFAULT_PER_PAGE,
					'description' => __( 'Maximum number of posts to return per page in query mode.' ),
				),
			),
			'additionalProperties' => false,
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
	protected static function get_content_output_schema(): array {
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
					'description' => __( 'The raw, unfiltered post content (block markup). Present when the post type supports the editor. Empty when withheld for a password-protected post.' ),
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
					'description' => __( 'The posts matching the request. A single-element list when requested by ID.' ),
					'items'       => $post_schema,
				),
				'total'       => array(
					'type'        => 'integer',
					'description' => __( 'Total number of posts matching the query, across all pages. Surfaced over REST as the X-WP-Total header.' ),
				),
				'total_pages' => array(
					'type'        => 'integer',
					'description' => __( 'Total number of pages available. Surfaced over REST as the X-WP-TotalPages header.' ),
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
	protected static function format_post( WP_Post $post, array $fields ): array {
		$type      = $post->post_type;
		$wants     = static function ( string $field ) use ( $fields ): bool {
			return in_array( $field, $fields, true );
		};
		$protected = post_password_required( $post ) && ! current_user_can( 'edit_post', $post->ID );

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
			$data['date'] = self::format_gmt_date( $post, 'date' );
		}
		if ( $wants( 'modified' ) ) {
			$data['modified'] = self::format_gmt_date( $post, 'modified' );
		}
		if ( $wants( 'slug' ) ) {
			$data['slug'] = $post->post_name;
		}
		if ( $wants( 'link' ) ) {
			$data['link'] = (string) get_permalink( $post );
		}

		if ( $wants( 'title' ) && post_type_supports( $type, 'title' ) ) {
			$data['title'] = self::get_title( $post );
		}

		if ( $wants( 'excerpt' ) && post_type_supports( $type, 'excerpt' ) ) {
			$data['excerpt'] = $protected ? '' : (string) get_the_excerpt( $post );
		}

		if ( $wants( 'raw_content' ) && post_type_supports( $type, 'editor' ) ) {
			$data['raw_content'] = $protected ? '' : (string) $post->post_content;
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
	protected static function get_title( WP_Post $post ): string {
		$strip = array( self::class, 'return_raw_title_format' );
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
	public static function return_raw_title_format(): string {
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
	protected static function format_gmt_date( WP_Post $post, string $field ): string {
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
	 * The same generic 404 is returned for a missing post, an unexposed post type, a
	 * type mismatch, or a post the user cannot read, so the ability cannot be used to
	 * enumerate IDs or probe post-type membership.
	 *
	 * @since 7.1.0
	 *
	 * @return WP_Error The not-found error.
	 */
	protected static function not_found_error(): WP_Error {
		return new WP_Error(
			'content_not_found',
			__( 'The requested content was not found.' ),
			array( 'status' => 404 )
		);
	}
}
