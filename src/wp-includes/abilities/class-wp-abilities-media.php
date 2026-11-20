<?php
/**
 * Abilities API: WP_Abilities_Media class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.3.0
 */

declare( strict_types = 1 );

/**
 * Core class used to register the media abilities.
 *
 * Registers `core/media-query`, `core/media-upload`, `core/media-update` and
 * `core/media-delete`, which read and write media attachments. Every ability requires a
 * logged-in user and the `attachment` post type to have `show_in_rest` on. Files are
 * stored through the regular upload handling, so the type and size checks of an upload
 * apply as for any other upload.
 *
 * Only register() is public. The ability callbacks are closures that call private
 * methods, so callers go through the Abilities API, such as
 * `wp_get_ability( 'core/media-query' )->execute()`, which validates the input and
 * checks permissions before running them.
 *
 * @since 7.3.0
 *
 * @access private
 */
final class WP_Abilities_Media {

	/**
	 * The ability category used for media abilities.
	 *
	 * @since 7.3.0
	 * @var string
	 */
	private const CATEGORY = 'content';

	/**
	 * Default number of attachments returned per page, unless `where` lists them by ID or slug.
	 *
	 * @since 7.3.0
	 * @var int
	 */
	private const DEFAULT_PER_PAGE = 10;

	/**
	 * Maximum number of attachments returned per page.
	 *
	 * @since 7.3.0
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * The statuses an attachment query can filter by.
	 *
	 * @since 7.3.0
	 * @var list<string>
	 */
	private const STATUSES = array( 'inherit', 'private', 'trash' );

	/**
	 * The media type groups the `media_type` filter accepts.
	 *
	 * @since 7.3.0
	 * @var list<string>
	 */
	private const MEDIA_TYPES = array( 'application', 'audio', 'image', 'text', 'video' );

	/**
	 * The attributes an upload or an update can write.
	 *
	 * @since 7.3.0
	 * @var list<string>
	 */
	private const ATTRIBUTES = array( 'title', 'caption', 'description', 'alt_text', 'slug', 'date', 'author', 'post' );

	/**
	 * Default field paths returned when the caller does not request a field subset.
	 *
	 * @since 7.3.0
	 * @var list<string>
	 */
	private const DEFAULT_FIELDS = array( 'id', 'status', 'date', 'slug', 'title.rendered', 'media_type', 'mime_type', 'source_url' );

	/**
	 * Globals that rendering a post changes: the global post and the globals that
	 * setup_postdata() populates.
	 *
	 * @since 7.3.0
	 * @var list<string>
	 */
	private const LOOP_GLOBALS = array( 'post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages' );

	/**
	 * Whether each author seen in the current call has posts the current user may read.
	 *
	 * @since 7.3.0
	 * @var array<int, bool>
	 */
	private $visible_authors = array();

	/**
	 * Registers all media abilities.
	 *
	 * Must run on the `wp_abilities_api_init` hook.
	 *
	 * @since 7.3.0
	 */
	public function register(): void {
		$item_schema = $this->get_item_schema();

		wp_register_ability(
			'core/media-query',
			array(
				'label'               => __( 'Query Media' ),
				'description'         => __( 'Retrieves a paginated list of the media attachments the current user can read, filtered by any of IDs, slugs, status, media type, MIME type, parent post, and author. Requires an authenticated user. Filters are exact-match only; the ability does not perform full-text search.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_query_input_schema(),
				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'items', 'pagination' ),
					'properties'           => array(
						'items'      => array(
							'type'        => 'array',
							'items'       => $item_schema,
							'description' => __( 'The readable attachments on the requested page, newest first. Can hold fewer attachments than `per_page`, because the totals can count matching attachments the current user cannot read.' ),
						),
						'pagination' => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'required'             => array( 'total', 'total_pages' ),
							'properties'           => array(
								'total'       => array(
									'type'        => 'integer',
									'description' => __( 'Total number of attachments matching the query, across all pages.' ),
								),
								'total_pages' => array(
									'type'        => 'integer',
									'description' => __( 'Total number of pages for the query.' ),
								),
							),
							'description'          => __( 'The totals of the query, across all pages.' ),
						),
					),
				),
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_media_query( $input );
				},
				'permission_callback' => function ( $input = array() ): bool {
					return $this->can_use_media();
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

		wp_register_ability(
			'core/media-upload',
			array(
				'label'               => __( 'Upload Media' ),
				'description'         => __( 'Stores a file in the media library and returns the new attachment. The file is an image from a URL, or any file the current user may upload as base64 data. Requires an authenticated user who can upload files.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_upload_input_schema(),
				'output_schema'       => $item_schema,
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_media_upload( $input );
				},
				'permission_callback' => function ( $input = array() ): bool {
					return $this->can_use_media() && current_user_can( 'upload_files' );
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
			'core/media-update',
			array(
				'label'               => __( 'Update Media' ),
				'description'         => __( 'Updates the given attributes of a media attachment and returns it. Attributes that are left out keep their stored values. Requires an authenticated user who can edit the attachment.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_update_input_schema(),
				'output_schema'       => $item_schema,
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_media_update( $input );
				},
				'permission_callback' => function ( $input = array() ): bool {
					return $this->check_item_permission( $input, 'edit_post' );
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
			'core/media-delete',
			array(
				'label'               => __( 'Delete Media' ),
				'description'         => __( 'Moves a media attachment to the Trash, or deletes it and its files permanently when `permanent` is true. Unless the site keeps trashed media, only a permanent deletion is possible. Requires an authenticated user who can delete the attachment.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'where' ),
					'properties'           => array(
						'where'     => $this->get_where_id_schema( __( 'Selects the attachment to delete.' ) ),
						'permanent' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether to delete the attachment and its files permanently instead of moving it to the Trash.' ),
						),
					),
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'outcome' ),
					'properties'           => array(
						'outcome' => array(
							'type'        => 'string',
							'description' => __( 'What happened to the attachment: `deleted` or `trashed`.' ),
						),
					),
				),
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_media_delete( $input );
				},
				'permission_callback' => function ( $input = array() ): bool {
					return $this->check_item_permission( $input, 'delete_post' );
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
	 * Checks the conditions every media ability shares: a logged-in user, and the
	 * `attachment` post type with `show_in_rest` on.
	 *
	 * @since 7.3.0
	 *
	 * @return bool Whether the media abilities may be used.
	 */
	private function can_use_media(): bool {
		$post_type_object = get_post_type_object( 'attachment' );

		// Intentionally strict: every media ability requires a logged-in user, the query included.
		return is_user_logged_in() && $post_type_object instanceof WP_Post_Type && $post_type_object->show_in_rest;
	}

	/**
	 * Permission callback for the `core/media-update` and `core/media-delete` abilities.
	 *
	 * The input is read defensively, as WP_Ability::check_permissions() does not prepare it.
	 * `where.id` must be a whole number of at least 1. When it names an attachment, the
	 * given capability on it is required. When it names nothing or another kind of post,
	 * the call is let through, so that the execute callback can report it as not found.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $input      The ability input.
	 * @param string $capability The capability required on the attachment: `edit_post` or `delete_post`.
	 * @return bool True if the request may proceed, false otherwise.
	 */
	private function check_item_permission( $input, string $capability ): bool {
		if ( ! $this->can_use_media() ) {
			return false;
		}

		$input = rest_sanitize_object( $input );
		$where = isset( $input['where'] ) && rest_is_object( $input['where'] ) ? rest_sanitize_object( $input['where'] ) : array();
		$id    = $this->parse_id( $where['id'] ?? null );
		if ( null === $id ) {
			return false;
		}

		$post = get_post( $id );

		return ! $post instanceof WP_Post || 'attachment' !== $post->post_type || current_user_can( $capability, $id );
	}

	/**
	 * Parses a raw input value into an ID, or null when it is not a whole number of at least 1.
	 *
	 * Accepts the whole numbers that the integer schema accepts: native integers, floats such
	 * as the 7.0 that JSON encoders produce, and integer strings such as "7" or "007".
	 * Anything else, such as a fraction, is rejected rather than coerced, so a malformed ID
	 * can never resolve to another post.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed $value The raw input value.
	 * @return int|null The ID, or null when the value is not a valid ID.
	 */
	private function parse_id( $value ): ?int {
		if ( is_int( $value ) ) {
			return $value >= 1 ? $value : null;
		}

		if ( is_string( $value ) && '' !== $value && ctype_digit( $value ) ) {
			$int = (int) $value;

			return $int >= 1 ? $int : null;
		}

		if ( ! rest_is_integer( $value ) ) {
			return null;
		}

		/*
		 * Other whole numbers are read as floats, which hold every integer only up to 2 ** 53.
		 * Past that, the value may not be the one that was sent.
		 */
		$number = (float) $value;

		return $number >= 1 && $number <= 2 ** 53 ? (int) $number : null;
	}

	/**
	 * Executes the `core/media-query` ability.
	 *
	 * The `status` filter is checked first, its values and then the capabilities they need.
	 * The other filters follow, and every attachment the query returns passes the row check
	 * of {@see self::is_readable_media()} before it is listed.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input, as prepared by WP_Ability::execute().
	 * @return array<string, mixed>|WP_Error A page of attachments, or a WP_Error on failure.
	 */
	private function execute_media_query( array $input ) {
		// The author visibility memo is per call; see get_visible_author().
		$this->visible_authors = array();

		$where            = $input['where'] ?? array();
		$post_type_object = get_post_type_object( 'attachment' );

		$statuses = $where['status'] ?? array( 'inherit' );
		if ( empty( $statuses ) || ! empty( array_diff( $statuses, self::STATUSES ) ) ) {
			return $this->invalid_filter_error( 'where[status]', __( 'The status filter must list one or more of the statuses inherit, private, and trash.' ) );
		}

		foreach ( $statuses as $status ) {
			$can_use_status = 'inherit' === $status
				|| ( 'private' === $status && current_user_can( $post_type_object->cap->read_private_posts ) )
				|| current_user_can( $post_type_object->cap->edit_posts );
			if ( ! $can_use_status ) {
				return new WP_Error(
					'media_cannot_filter_by_status',
					__( 'Sorry, you are not allowed to filter media by this status.' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}
		}

		/*
		 * Intentionally strict: the count leaves out the private attachments of other users
		 * unless the current user can read private posts, so the totals never count them.
		 */
		$query_args = array(
			'post_type'           => 'attachment',
			'post_status'         => array_values( $statuses ),
			'perm'                => current_user_can( $post_type_object->cap->read_private_posts ) ? '' : 'readable',
			'orderby'             => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			'ignore_sticky_posts' => true,
		);

		if ( array_key_exists( 'ids', $where ) ) {
			$ids = array_values( array_unique( array_filter( $where['ids'], static fn( $id ) => $id > 0 ) ) );
			if ( empty( $ids ) ) {
				return $this->invalid_filter_error( 'where[ids]', __( 'The ids filter must list one or more attachment IDs.' ) );
			}
			$query_args['post__in'] = $ids;
		}

		if ( array_key_exists( 'slugs', $where ) ) {
			$slugs = array_values( array_unique( array_filter( array_map( 'sanitize_title', $where['slugs'] ), static fn( $slug ) => '' !== $slug ) ) );
			if ( empty( $slugs ) ) {
				return $this->invalid_filter_error( 'where[slugs]', __( 'The slugs filter must list one or more attachment slugs.' ) );
			}
			$query_args['post_name__in'] = $slugs;
		}

		/*
		 * Intentionally strict: a media type group or a MIME type that selects no type the
		 * current user may upload cannot be applied, so it is an error instead of matching
		 * nothing or being dropped.
		 */
		$allowed_types = array_values( get_allowed_mime_types() );
		$mime_types    = array();
		if ( array_key_exists( 'media_type', $where ) ) {
			if ( empty( $where['media_type'] ) ) {
				return $this->invalid_filter_error( 'where[media_type]', __( 'The media_type filter must list one or more media types.' ) );
			}
			foreach ( $where['media_type'] as $group ) {
				$group_types = array_filter( $allowed_types, static fn( $type ) => str_starts_with( $type, $group . '/' ) );
				if ( empty( $group_types ) ) {
					return $this->invalid_filter_error( 'where[media_type]', __( 'The media_type filter must list media types that have allowed MIME types.' ) );
				}
				$mime_types = array_merge( $mime_types, $group_types );
			}
		}

		if ( array_key_exists( 'mime_type', $where ) ) {
			if ( empty( $where['mime_type'] ) ) {
				return $this->invalid_filter_error( 'where[mime_type]', __( 'The mime_type filter must list one or more MIME types.' ) );
			}
			foreach ( $where['mime_type'] as $mime_type ) {
				if ( ! in_array( $mime_type, $allowed_types, true ) ) {
					return $this->invalid_filter_error( 'where[mime_type]', __( 'The mime_type filter must list allowed MIME types.' ) );
				}
				$mime_types[] = $mime_type;
			}
		}

		// With both filters, an attachment matches when it matches either.
		if ( ! empty( $mime_types ) ) {
			$query_args['post_mime_type'] = array_values( array_unique( $mime_types ) );
		}

		// The parent post is never looked up.
		if ( array_key_exists( 'post', $where ) ) {
			if ( $where['post'] < 0 ) {
				return $this->invalid_filter_error( 'where[post]', __( 'The post filter must be a non-negative integer.' ) );
			}
			$query_args['post_parent'] = $where['post'];
		}

		/*
		 * Null, int, or false: no author filter, the user ID to query, or a filter that names
		 * no user, which matches nothing.
		 */
		$author_id = null;
		if ( array_key_exists( 'author', $where ) ) {
			$author = $where['author'];
			if ( ! isset( $author['id'] ) && ! isset( $author['slug'] ) ) {
				return $this->invalid_filter_error( 'where[author]', __( 'The author filter must be an object with an id or a slug.' ) );
			}

			// An `id` is never looked up: it matches the stored author ID.
			if ( isset( $author['id'] ) ) {
				$author_id = $author['id'];
				if ( $author_id < 1 ) {
					return $this->invalid_filter_error( 'where[author][id]', __( 'The author ID must be a positive integer.' ) );
				}
			}

			/*
			 * A slug that names no user, or a user the current user may not see, or another
			 * user than `id`, matches nothing. The visibility rule is the one the `author`
			 * field follows, so the filter cannot confirm a slug the field withholds.
			 */
			if ( isset( $author['slug'] ) ) {
				if ( '' === $author['slug'] ) {
					return $this->invalid_filter_error( 'where[author][slug]', __( 'The author slug must be a non-empty string.' ) );
				}

				$user      = get_user_by( 'slug', $author['slug'] );
				$user      = $user ? $this->get_visible_author( $user->ID ) : null;
				$author_id = ! $user || ( null !== $author_id && $user->ID !== $author_id ) ? false : $user->ID;
			}
		}

		if ( false === $author_id ) {
			return $this->prepare_query_output( array(), 0, 0 );
		}
		if ( null !== $author_id ) {
			$query_args['author'] = $author_id;
		}

		$pagination       = $input['pagination'] ?? array();
		$lists_items      = isset( $query_args['post__in'] ) || isset( $query_args['post_name__in'] );
		$default_per_page = $lists_items ? self::MAX_PER_PAGE : self::DEFAULT_PER_PAGE;
		$page             = max( 1, $pagination['page'] ?? 1 );
		$per_page         = $pagination['per_page'] ?? $default_per_page;

		$query_args['paged']          = $page;
		$query_args['posts_per_page'] = $per_page < 1 ? $default_per_page : min( self::MAX_PER_PAGE, $per_page );

		$query = new WP_Query( $query_args );
		$total = $this->get_query_total( $query, $query_args, $page );

		/*
		 * Count the pages with the page size the query ran with, since query filters such
		 * as `pre_get_posts` callbacks can change it. Without paging, the query returns
		 * every attachment on one page.
		 */
		$query_per_page = (int) $query->get( 'posts_per_page' );
		if ( $query->get( 'nopaging' ) || $query_per_page < 1 ) {
			$total_pages = $total > 0 ? 1 : 0;
		} else {
			$total_pages = (int) ceil( $total / $query_per_page );
		}

		if ( $total > 0 && $page > $total_pages ) {
			return new WP_Error(
				'media_invalid_page_number',
				__( 'The page number requested is larger than the number of pages available.' ),
				array( 'status' => 404 )
			);
		}

		update_post_parent_caches( $query->posts );
		update_post_author_caches( $query->posts );

		$fields = $this->normalize_fields( $input );
		$items  = array();
		foreach ( $query->posts as $post ) {
			// Query filters can add posts of other types.
			if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || ! $this->is_readable_media( $post ) ) {
				continue;
			}
			$items[] = $this->format_media( $post, $fields );
		}

		return $this->prepare_query_output( $items, $total, $total_pages );
	}

	/**
	 * Executes the `core/media-upload` ability.
	 *
	 * The capability to upload is checked first, then the attributes, and only then is the
	 * file read, so a refused call never requests a URL, decodes data or writes a file.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input, as prepared by WP_Ability::execute().
	 * @return array<string, mixed>|WP_Error The new attachment, or a WP_Error on failure.
	 */
	private function execute_media_upload( array $input ) {
		// The author visibility memo is per call; see get_visible_author().
		$this->visible_authors = array();

		// Checked again after the permission callback, whose result a filter can change.
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'media_cannot_upload',
				__( 'Sorry, you are not allowed to upload media on this site.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$data    = $input['data'] ?? array();
		$postarr = $this->prepare_attributes( $data, null );
		if ( is_wp_error( $postarr ) ) {
			return $postarr;
		}

		if ( ! isset( $data['file'] ) ) {
			return $this->invalid_file_error( 'data[file]', __( 'No data supplied.' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// The post date does not usually matter for pages, so an upload to one is not backdated.
		$time   = null;
		$parent = isset( $postarr['post_parent'] ) ? get_post( $postarr['post_parent'] ) : null;
		if ( $parent instanceof WP_Post && 'page' !== $parent->post_type && substr( $parent->post_date, 0, 4 ) > 0 ) {
			$time = $parent->post_date;
		}

		$file   = $data['file'];
		$stored = isset( $file['url'] )
			? $this->store_from_url( $file['url'], $time )
			: $this->store_from_base64( $file['base64'], $file['filename'], $time );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}

		$image_meta = wp_read_image_metadata( $stored['file'] );

		if ( '' === ( $postarr['post_title'] ?? '' ) ) {
			$postarr['post_title'] = ! empty( $image_meta ) && trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) )
				? $image_meta['title']
				: preg_replace( '/\.[^.]+$/', '', wp_basename( $stored['file'] ) );
		}

		if ( '' === ( $postarr['post_excerpt'] ?? '' ) && ! empty( $image_meta ) && trim( $image_meta['caption'] ) ) {
			$postarr['post_excerpt'] = $image_meta['caption'];
		}

		$alt_text = null;
		if ( array_key_exists( 'alt_text', $data ) ) {
			$alt_text = $data['alt_text'];
		} elseif ( ! empty( $image_meta ) && trim( $image_meta['alt'] ) ) {
			$alt_text = $image_meta['alt'];
		}

		$postarr['post_status']    = 'inherit';
		$postarr['post_mime_type'] = $stored['type'];
		$postarr['guid']           = $stored['url'];
		$postarr['post_author']  ??= get_current_user_id();
		$postarr['post_parent']  ??= 0;

		$attachment_id = wp_insert_attachment( wp_slash( $postarr ), $stored['file'], 0, true, false );
		if ( is_wp_error( $attachment_id ) ) {
			$this->remove_stored_file( $stored );

			return $this->save_failed_error( $attachment_id );
		}

		if ( null !== $alt_text ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( $alt_text ) ) );
		}

		wp_after_insert_post( $attachment_id, false, null );

		/*
		 * Create the image sub-sizes and save the metadata. When the metadata cannot be
		 * generated, the attachment stays and is returned without it.
		 */
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $stored['file'] ) );

		return $this->prepare_item( $attachment_id, $this->normalize_fields( $input ) );
	}

	/**
	 * Downloads an image from a URL and stores it in the uploads folder.
	 *
	 * The name, the extension and the URL are checked before anything is requested. The
	 * only request is made by download_url(), to the URL that passed wp_http_validate_url(),
	 * and its response is limited to the maximum upload size plus one byte.
	 *
	 * @since 7.3.0
	 *
	 * @param string      $url  The URL of the image.
	 * @param string|null $time The time the uploads folder is chosen by, or null for now.
	 * @return array<string, mixed>|WP_Error The stored file from store_file(), or a WP_Error.
	 */
	private function store_from_url( string $url, ?string $time ) {
		$param   = 'data[file][url]';
		$invalid = __( 'Invalid URL. Provide a valid, publicly reachable HTTP or HTTPS image URL.' );
		if ( '' === $url ) {
			return $this->invalid_file_error( $param, $invalid );
		}

		// The name is taken from the URL's path as it is, without percent-decoding.
		$url_path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$filename = '' === $url_path ? '' : wp_basename( $url_path );
		if ( '' === $filename ) {
			return $this->invalid_file_error( $param, __( 'Could not determine a filename from the provided URL.' ) );
		}

		$only_images = __( 'Only images can be uploaded from a URL.' );
		$filetype    = wp_check_filetype( $filename );
		if ( ! $filetype['type'] || ! str_starts_with( $filetype['type'], 'image/' ) ) {
			return $this->invalid_file_error( $param, $only_images );
		}

		if ( false === wp_http_validate_url( $url ) ) {
			return $this->invalid_file_error( $param, $invalid );
		}

		// Intentionally strict: a site without upload space left is refused before any request.
		if ( $this->is_space_checked() && get_upload_space_available() < 1 ) {
			return new WP_Error(
				'media_quota_exceeded',
				__( 'You have used your space quota. Please delete files before uploading.' ),
				array( 'status' => 400 )
			);
		}

		/*
		 * Intentionally strict: a network whose maximum file size is below 1 KB lets no file
		 * through the size check, so the call is refused before any request.
		 */
		if ( $this->is_space_checked() ) {
			$max_kilobytes = get_site_option( 'fileupload_maxk', 1500 );
			if ( (int) $max_kilobytes < 1 ) {
				return $this->invalid_file_error(
					$param,
					/* translators: %s: Maximum allowed file size in kilobytes. */
					sprintf( __( 'This file is too big. Files must be less than %s KB in size.' ), $max_kilobytes )
				);
			}
		}

		/*
		 * One byte over the maximum is enough to fail the size check below, so an oversized
		 * file is never written to disk in full. The limit applies to requests for this URL
		 * only and is removed again whatever the download gives.
		 */
		$max_size            = (int) wp_max_upload_size();
		$limit_response_size = static function ( $args, $request_url ) use ( $url, $max_size ) {
			if ( $request_url === $url && is_array( $args ) ) {
				$args['limit_response_size'] = $max_size + 1;
			}

			return $args;
		};

		$tmp_file = null;
		try {
			if ( $max_size > 0 ) {
				add_filter( 'http_request_args', $limit_response_size, PHP_INT_MAX, 2 );
			}

			try {
				$tmp_file = download_url( $url );
			} finally {
				remove_filter( 'http_request_args', $limit_response_size, PHP_INT_MAX );
			}

			/*
			 * Intentionally strict: a failed download gives one fixed error, which holds
			 * nothing of the remote answer or of the error behind it.
			 */
			if ( is_wp_error( $tmp_file ) ) {
				return new WP_Error( 'media_download_failed', __( 'Download failed.' ), array( 'status' => 500 ) );
			}

			$size_error = $this->check_file_size( (int) wp_filesize( $tmp_file ), $max_size, $param );
			if ( $size_error ) {
				return $size_error;
			}

			/*
			 * Intentionally strict: the content must be an image as the file check sees it, for
			 * every user, also one who may upload any type. The upload handling below still
			 * runs its own type check.
			 */
			$checked = wp_check_filetype_and_ext( $tmp_file, $filename, get_allowed_mime_types() );
			if ( ! $checked['ext'] || ! $checked['type'] || ! str_starts_with( $checked['type'], 'image/' ) ) {
				return $this->invalid_file_error( $param, $only_images );
			}

			return $this->store_file( $filename, $tmp_file, $time, $param );
		} finally {
			// Of the temporary files, only the one download_url() returned is ever removed.
			if ( is_string( $tmp_file ) && file_exists( $tmp_file ) ) {
				wp_delete_file( $tmp_file );
			}
		}
	}

	/**
	 * Decodes a file sent as base64 data and stores it in the uploads folder.
	 *
	 * @since 7.3.0
	 *
	 * @param string      $base64   The base64 data.
	 * @param string      $filename The file name, which the upload handling sanitizes.
	 * @param string|null $time     The time the uploads folder is chosen by, or null for now.
	 * @return array<string, mixed>|WP_Error The stored file from store_file(), or a WP_Error.
	 */
	private function store_from_base64( string $base64, string $filename, ?string $time ) {
		$param = 'data[file][base64]';
		$bytes = base64_decode( $base64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- The file is sent as base64 data.
		if ( false === $bytes ) {
			return $this->invalid_file_error( $param, __( 'The file data is not valid base64.' ) );
		}

		if ( '' === $bytes ) {
			return $this->invalid_file_error( $param, __( 'File is empty. Please upload something more substantial.' ) );
		}

		$size_error = $this->check_file_size( strlen( $bytes ), (int) wp_max_upload_size(), $param );
		if ( $size_error ) {
			return $size_error;
		}

		$tmp_file = wp_tempnam( $filename );
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes the temporary file.
			if ( false === file_put_contents( $tmp_file, $bytes ) ) {
				return new WP_Error( 'media_upload_failed', __( 'Could not create temporary file.' ), array( 'status' => 500 ) );
			}

			return $this->store_file( $filename, $tmp_file, $time, 'data[file][filename]' );
		} finally {
			if ( file_exists( $tmp_file ) ) {
				wp_delete_file( $tmp_file );
			}
		}
	}

	/**
	 * Checks the size of a file against the network limits and the maximum upload size.
	 *
	 * @since 7.3.0
	 *
	 * @param int    $size     The file size in bytes.
	 * @param int    $max_size The maximum upload size in bytes, 0 for no limit.
	 * @param string $param    The input path of the file, such as `data[file][url]`.
	 * @return WP_Error|null The error, or null when the size is accepted.
	 */
	private function check_file_size( int $size, int $max_size, string $param ): ?WP_Error {
		/* translators: %s: Maximum allowed file size in kilobytes. */
		$too_big = __( 'This file is too big. Files must be less than %s KB in size.' );

		if ( $this->is_space_checked() ) {
			$space_left = get_upload_space_available();
			if ( $space_left < $size ) {
				return new WP_Error(
					'media_quota_exceeded',
					/* translators: %s: Required disk space in kilobytes. */
					sprintf( __( 'Not enough space to upload. %s KB needed.' ), number_format( ( $size - $space_left ) / KB_IN_BYTES ) ),
					array( 'status' => 400 )
				);
			}

			$max_kilobytes = get_site_option( 'fileupload_maxk', 1500 );
			if ( $size > KB_IN_BYTES * (int) $max_kilobytes ) {
				return $this->invalid_file_error( $param, sprintf( $too_big, $max_kilobytes ) );
			}
		}

		// Intentionally strict: a file sent as base64 data is held to the maximum upload size too.
		if ( $max_size > 0 && $size > $max_size ) {
			return $this->invalid_file_error( $param, sprintf( $too_big, number_format( $max_size / KB_IN_BYTES ) ) );
		}

		return null;
	}

	/**
	 * Checks whether the network checks the upload space of its sites.
	 *
	 * @since 7.3.0
	 *
	 * @return bool Whether the upload space is checked.
	 */
	private function is_space_checked(): bool {
		return is_multisite() && ! get_site_option( 'upload_space_check_disabled' );
	}

	/**
	 * Stores a temporary file in the uploads folder through the regular upload handling.
	 *
	 * The upload handling decides, for the current user, whether the file may be stored,
	 * from its name and its content. It is given no type, so the type always comes from
	 * that check. While it runs, a callback records whether the stored file is one this
	 * call created, which {@see self::remove_stored_file()} relies on.
	 *
	 * @since 7.3.0
	 *
	 * @param string      $filename   The file name.
	 * @param string      $tmp_file   The path of the temporary file.
	 * @param string|null $time       The time the uploads folder is chosen by, or null for now.
	 * @param string      $type_param The input path a refused file type is reported for.
	 * @return array<string, mixed>|WP_Error The `file`, `url` and `type` of the stored file, with
	 *                                       `removable` and `hash` for the cleanup, or a WP_Error.
	 */
	private function store_file( string $filename, string $tmp_file, ?string $time, string $type_param ) {
		/*
		 * The facts start as those of a file this call did not create, so the file counts as
		 * removable only when the callback below recorded otherwise.
		 */
		$move = array(
			'path'       => '',
			'existed'    => true,
			'taken_over' => true,
			'hash'       => '',
		);

		/*
		 * Records the path the upload handling chose, whether a file was there already,
		 * whether another callback took over the move, and the bytes that are moved. The
		 * filtered value is returned unchanged.
		 */
		$record_move = static function ( $move_new_file, $file, $new_file ) use ( &$move ) {
			$move = array(
				'path'       => $new_file,
				'existed'    => file_exists( $new_file ),
				'taken_over' => null !== $move_new_file,
				'hash'       => (string) hash_file( 'sha256', $file['tmp_name'] ),
			);

			return $move_new_file;
		};

		$upload = array(
			'name'     => $filename,
			'tmp_name' => $tmp_file,
			'type'     => '',
		);

		add_filter( 'pre_move_uploaded_file', $record_move, PHP_INT_MAX, 3 );
		try {
			$stored = wp_handle_sideload( $upload, array( 'test_form' => false ), $time );
		} finally {
			remove_filter( 'pre_move_uploaded_file', $record_move, PHP_INT_MAX );
		}

		if ( isset( $stored['error'] ) ) {
			if ( __( 'Sorry, you are not allowed to upload this file type.' ) === $stored['error'] ) {
				return $this->invalid_file_error( $type_param, $stored['error'] );
			}

			return new WP_Error( 'media_upload_failed', $stored['error'], array( 'status' => 500 ) );
		}

		$stored['removable'] = ! $move['existed'] && ! $move['taken_over'] && $move['path'] === $stored['file'];
		$stored['hash']      = $move['hash'];

		return $stored;
	}

	/**
	 * Removes a stored file after its attachment could not be saved.
	 *
	 * The file is removed only when this call created it: the path did not exist when the
	 * upload handling chose it, it is the path the upload handling returned, it lies inside
	 * the uploads folder, and it still holds the bytes this call stored. Otherwise, such as
	 * when a callback swapped the returned path for an existing file, nothing is removed.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $stored The stored file from store_file().
	 */
	private function remove_stored_file( array $stored ): void {
		if ( ! $stored['removable'] || ! is_file( $stored['file'] ) || hash_file( 'sha256', $stored['file'] ) !== $stored['hash'] ) {
			return;
		}

		wp_delete_file_from_directory( $stored['file'], wp_get_upload_dir()['basedir'] );
	}

	/**
	 * Executes the `core/media-update` ability.
	 *
	 * The attachment is looked up first, then the capability to edit it is checked, then
	 * every attribute rule, and only then is it saved, so a refused call writes nothing.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input, as prepared by WP_Ability::execute().
	 * @return array<string, mixed>|WP_Error The updated attachment, or a WP_Error on failure.
	 */
	private function execute_media_update( array $input ) {
		// The author visibility memo is per call; see get_visible_author().
		$this->visible_authors = array();

		$attachment = $this->get_requested_attachment( $input );
		if ( ! $attachment ) {
			return $this->not_found_error();
		}

		// Checked again after the permission callback, whose result a filter can change.
		if ( ! current_user_can( 'edit_post', $attachment->ID ) ) {
			return new WP_Error(
				'media_cannot_edit',
				__( 'Sorry, you are not allowed to edit this post.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$data = $input['data'] ?? array();
		if ( empty( array_intersect_key( $data, array_flip( self::ATTRIBUTES ) ) ) ) {
			return $this->invalid_attribute_error( 'data', __( 'The data must be an object with at least one attribute to write.' ) );
		}

		$postarr = $this->prepare_attributes( $data, $attachment );
		if ( is_wp_error( $postarr ) ) {
			return $postarr;
		}

		// Every update is saved, also one that only sends the alt text, so its modified date changes.
		$postarr['ID'] = $attachment->ID;
		$saved         = wp_update_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $saved ) ) {
			return $this->save_failed_error( $saved );
		}

		// The alt text, when sent, is written once the save succeeded.
		if ( array_key_exists( 'alt_text', $data ) ) {
			update_post_meta( $attachment->ID, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( $data['alt_text'] ) ) );
		}

		/*
		 * The save does not run the after-insert action for an attachment, so it runs here,
		 * once, after the alt text is written, with the attachment as it was before the save.
		 */
		wp_after_insert_post( $attachment->ID, true, $attachment );

		return $this->prepare_item( $attachment->ID, $this->normalize_fields( $input ) );
	}

	/**
	 * Executes the `core/media-delete` ability.
	 *
	 * The attachment is looked up first, then the capability to delete it is checked. It is
	 * deleted permanently when `permanent` is true; otherwise it is moved to the Trash. The
	 * outcome is confirmed against the stored attachment afterwards.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input, as prepared by WP_Ability::execute().
	 * @return array<string, string>|WP_Error The outcome, or a WP_Error on failure.
	 */
	private function execute_media_delete( array $input ) {
		$attachment = $this->get_requested_attachment( $input );
		if ( ! $attachment ) {
			return $this->not_found_error();
		}

		// Checked again after the permission callback, whose result a filter can change.
		if ( ! current_user_can( 'delete_post', $attachment->ID ) ) {
			return new WP_Error(
				'media_cannot_delete',
				__( 'Sorry, you are not allowed to delete this post.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		/*
		 * Intentionally strict: the outcome is read from the stored attachment, so a deletion
		 * that a filter made Core skip is an error, not a reported deletion.
		 */
		if ( true === ( $input['permanent'] ?? false ) ) {
			wp_delete_attachment( $attachment->ID, true );

			return null === get_post( $attachment->ID ) ? array( 'outcome' => 'deleted' ) : $this->delete_failed_error();
		}

		/*
		 * Checked before wp_trash_post() is called: without trash days that call would delete
		 * the attachment, and with media trashing off it would still move the attachment to
		 * the Trash.
		 */
		if ( EMPTY_TRASH_DAYS < 1 || ! MEDIA_TRASH ) {
			return new WP_Error(
				'media_trash_not_supported',
				__( 'The attachment does not support trashing. Set "permanent" to delete it.' ),
				array( 'status' => 501 )
			);
		}

		if ( 'trash' === $attachment->post_status ) {
			return new WP_Error(
				'media_already_trashed',
				__( 'The attachment has already been trashed.' ),
				array( 'status' => 410 )
			);
		}

		wp_trash_post( $attachment->ID );
		$trashed = get_post( $attachment->ID );

		return $trashed instanceof WP_Post && 'trash' === $trashed->post_status ? array( 'outcome' => 'trashed' ) : $this->delete_failed_error();
	}

	/**
	 * Returns the attachment that `where.id` names.
	 *
	 * The ID is read once, and that same integer is used for the lookup, the capability
	 * checks, and the write.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return WP_Post|null The attachment, or null when `where.id` names no attachment.
	 */
	private function get_requested_attachment( array $input ): ?WP_Post {
		$id = $input['where']['id'] ?? 0;
		if ( $id < 1 ) {
			return null;
		}

		$post = get_post( $id );

		return $post instanceof WP_Post && 'attachment' === $post->post_type ? $post : null;
	}

	/**
	 * Checks the sent attributes and builds the attachment fields to save from them.
	 *
	 * Shared by the upload and update abilities. The array is built from scratch, one
	 * attribute at a time, so only the attributes of {@see self::ATTRIBUTES} reach the save,
	 * each mapped by name. The alt text is saved apart from the attachment and is left out.
	 * The checks run in the order `author`, `post`, `date`, `slug`, and for each attribute
	 * the value is checked before any capability. The first failure is returned.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $data       The `data` input.
	 * @param WP_Post|null $attachment The stored attachment on update, or null on upload.
	 * @return array<string, mixed>|WP_Error The unslashed attachment fields, or a WP_Error.
	 */
	private function prepare_attributes( array $data, ?WP_Post $attachment ) {
		$postarr          = array();
		$post_type_object = get_post_type_object( 'attachment' );

		if ( array_key_exists( 'author', $data ) ) {
			$author_id = $data['author'];
			if ( $author_id < 1 ) {
				return $this->invalid_attribute_error( 'author', __( 'Invalid author ID.' ) );
			}

			// The stored author sent again changes nothing.
			if ( ! $attachment || (int) $attachment->post_author !== $author_id ) {
				if ( get_current_user_id() !== $author_id ) {
					// Checked before the user is looked up, so the error never tells whether a user exists.
					if ( ! current_user_can( $post_type_object->cap->edit_others_posts ) ) {
						return new WP_Error(
							'media_cannot_edit_others',
							$attachment
								? __( 'Sorry, you are not allowed to update posts as this user.' )
								: __( 'Sorry, you are not allowed to create posts as this user.' ),
							array( 'status' => rest_authorization_required_code() )
						);
					}

					// Intentionally strict: the author must be an existing user who belongs to this site.
					if ( ! get_userdata( $author_id ) || ( is_multisite() && ! is_user_member_of_blog( $author_id ) ) ) {
						return $this->invalid_attribute_error( 'author', __( 'Invalid author ID.' ) );
					}
				}

				$postarr['post_author'] = $author_id;
			}
		}

		if ( array_key_exists( 'post', $data ) ) {
			$parent_id = $data['post'];
			if ( $parent_id < ( $attachment ? 0 : 1 ) ) {
				return $this->invalid_attribute_error( 'post', __( 'Invalid post ID.' ) );
			}

			// The stored parent sent again needs no capability, also when that post no longer exists.
			$is_current = $attachment && (int) $attachment->post_parent === $parent_id;

			// On update, 0 detaches the attachment and needs nothing more.
			if ( 0 !== $parent_id ) {
				/*
				 * Checked before the post is looked up, so a missing and a forbidden post give
				 * the same error. Intentionally strict: on update, attaching to a post also
				 * needs the capability to edit that post.
				 */
				if ( ! $is_current && ! current_user_can( 'edit_post', $parent_id ) ) {
					return new WP_Error(
						'media_cannot_attach',
						$attachment
							? __( 'Sorry, you are not allowed to attach media to this post.' )
							: __( 'Sorry, you are not allowed to upload media to this post.' ),
						array( 'status' => rest_authorization_required_code() )
					);
				}

				if ( in_array( get_post_type( $parent_id ), array( 'revision', 'attachment' ), true ) ) {
					return $this->invalid_attribute_error( 'post', __( 'Invalid parent type.' ) );
				}
			}

			if ( ! $is_current ) {
				$postarr['post_parent'] = $parent_id;
			}
		}

		if ( array_key_exists( 'date', $data ) ) {
			$date  = $data['date'];
			$dates = null;
			if ( isset( $date['site'] ) ) {
				$dates        = rest_get_date_with_gmt( $date['site'] );
				$is_unchanged = $attachment && null !== $dates && $attachment->post_date === $dates[0];
			} elseif ( isset( $date['gmt'] ) ) {
				$dates        = rest_get_date_with_gmt( $date['gmt'], true );
				$is_unchanged = $attachment && null !== $dates && $attachment->post_date_gmt === $dates[1];
			}

			if ( null === $dates ) {
				return $this->invalid_attribute_error( 'date', __( 'Invalid date.' ) );
			}

			// On update, a date equal to the stored one counts as not sent.
			if ( empty( $is_unchanged ) ) {
				$postarr['post_date']     = $dates[0];
				$postarr['post_date_gmt'] = $dates[1];
				if ( $attachment ) {
					$postarr['edit_date'] = true;
				}
			}
		}

		if ( array_key_exists( 'slug', $data ) ) {
			$slug = sanitize_title( $data['slug'] );

			// Intentionally strict: a slug that sanitizes to nothing is an error.
			if ( '' === $slug ) {
				return $this->invalid_attribute_error( 'slug', __( 'The slug must contain at least one character that is valid in a slug.' ) );
			}

			$postarr['post_name'] = $slug;
		}

		$text_fields = array(
			'title'       => 'post_title',
			'caption'     => 'post_excerpt',
			'description' => 'post_content',
		);
		foreach ( $text_fields as $attribute => $field ) {
			if ( array_key_exists( $attribute, $data ) ) {
				$postarr[ $field ] = $data[ $attribute ];
			}
		}

		return $postarr;
	}

	/**
	 * Returns a written attachment in the requested fields.
	 *
	 * @since 7.3.0
	 *
	 * @param int          $attachment_id The attachment ID.
	 * @param list<string> $fields        The requested field paths.
	 * @return array<string, mixed> The attachment, or only its ID when the current user may not read it.
	 */
	private function prepare_item( int $attachment_id, array $fields ): array {
		$attachment = get_post( $attachment_id );

		/*
		 * Intentionally strict: the written attachment passes the same row check as every
		 * query row. When the current user may not read it, the write stands but only its
		 * ID is returned, whatever the fields ask for.
		 */
		if ( ! $attachment instanceof WP_Post || ! $this->is_readable_media( $attachment ) ) {
			return array( 'id' => $attachment_id );
		}

		return $this->format_media( $attachment, $fields );
	}

	/**
	 * Checks whether the current user can read an attachment.
	 *
	 * The parent chain is walked with get_post() alone first: a loop of two or more parents
	 * is not readable, and it must be found before any capability is checked, because the
	 * status lookup does not end for attachments that are each other's parent. An
	 * attachment that is its own parent counts as unattached.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_Post $post The attachment.
	 * @return bool Whether the attachment can be read.
	 */
	private function is_readable_media( WP_Post $post ): bool {
		$seen    = array( $post->ID => true );
		$current = $post;
		while ( (int) $current->post_parent > 0 && (int) $current->post_parent !== $current->ID ) {
			if ( isset( $seen[ (int) $current->post_parent ] ) ) {
				return false;
			}

			$current = get_post( (int) $current->post_parent );
			if ( ! $current instanceof WP_Post ) {
				break;
			}

			$seen[ $current->ID ] = true;
		}

		return $this->can_read_post( $post );
	}

	/**
	 * Checks the read rule for an attachment or one of its parents, once the parent chain is
	 * known to be free of loops.
	 *
	 * A post is readable when it is published or the current user can read it, when its
	 * status is public, or when it inherits its status: then from its parent, whose post
	 * type must have `show_in_rest` on and which must pass this check itself, or, without
	 * a parent or with one that no longer exists, as readable.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_Post $post The attachment or a parent of it.
	 * @return bool Whether the post can be read.
	 */
	private function can_read_post( WP_Post $post ): bool {
		if ( 'publish' === $post->post_status || current_user_can( 'read_post', $post->ID ) ) {
			return true;
		}

		$status_object = get_post_status_object( $post->post_status );
		if ( $status_object && $status_object->public ) {
			return true;
		}

		if ( 'inherit' !== $post->post_status ) {
			return false;
		}

		$parent_id = (int) $post->post_parent;
		$parent    = $parent_id > 0 && $parent_id !== $post->ID ? get_post( $parent_id ) : null;
		if ( ! $parent instanceof WP_Post ) {
			return true;
		}

		$parent_type = get_post_type_object( $parent->post_type );

		return $parent_type instanceof WP_Post_Type && $parent_type->show_in_rest && $this->can_read_post( $parent );
	}

	/**
	 * Returns the query total, recovering it when WP_Query skipped the count.
	 *
	 * WP_Query leaves `found_posts` at 0 when a requested page has no rows. A minimal
	 * unpaged query recovers it, so a page past the last one can be told apart from a
	 * query that matches nothing.
	 *
	 * @since 7.3.0
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
	 * Returns the requested field paths, or the default set when none are given.
	 *
	 * @since 7.3.0
	 *
	 * @param array<mixed> $input The ability input.
	 * @return list<string> The requested field paths.
	 */
	private function normalize_fields( array $input ): array {
		$fields = $input['fields'] ?? array();

		return empty( $fields ) ? self::DEFAULT_FIELDS : array_values( $fields );
	}

	/**
	 * Returns a user when the current user may see them as an author.
	 *
	 * The current user may see themselves, the users they can edit, every user when they can
	 * list users, and otherwise the authors of posts they can read in a publicly viewable post
	 * type exposed to abilities. A user the current user may not see is reported like a
	 * missing one. The post count is kept per author for the call, since count_user_posts()
	 * is not cached.
	 *
	 * @since 7.3.0
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
	 * Formats an attachment into the ability output shape.
	 *
	 * The attachment is set up as the global post while its fields are built, so filters
	 * that rely on loop globals see it, and the previous global post is restored afterwards.
	 * Only the requested fields are included, and `id` always is. Raw sub-fields are only
	 * included when the current user can edit the attachment. A field none of whose
	 * requested sub-fields remain is left out.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_Post      $post   The attachment.
	 * @param list<string> $fields The requested field paths.
	 * @return array<string, mixed> The formatted attachment.
	 */
	private function format_media( WP_Post $post, array $fields ): array {
		$previous_context = $this->get_post_context();
		$the_post_fired   = did_action( 'the_post' ) > 0;

		/*
		 * Set up inside the try block and undone in the finally block, so a throw from a
		 * `the_post` callback or mid-render cannot leave the global post pointing at this
		 * attachment for the rest of the request.
		 */
		try {
			$this->set_up_post_context( $post );

			$can_edit = current_user_can( 'edit_post', $post->ID );
			$data     = array( 'id' => $post->ID );

			if ( rest_is_field_included( 'status', $fields ) ) {
				$data['status'] = $post->post_status;
			}

			foreach ( array( 'date', 'modified' ) as $field ) {
				$dates = array();
				if ( rest_is_field_included( "{$field}.site", $fields ) ) {
					$dates['site'] = $this->format_date( $post, $field, false );
				}
				if ( rest_is_field_included( "{$field}.gmt", $fields ) ) {
					$dates['gmt'] = $this->format_date( $post, $field, true );
				}
				if ( ! empty( $dates ) ) {
					$data[ $field ] = $dates;
				}
			}

			if ( rest_is_field_included( 'slug', $fields ) ) {
				$data['slug'] = $post->post_name;
			}
			if ( rest_is_field_included( 'link', $fields ) ) {
				$data['link'] = (string) get_permalink( $post );
			}

			$rendered_fields = array(
				'title'       => array( 'post_title', 'get_title' ),
				'caption'     => array( 'post_excerpt', 'get_rendered_excerpt' ),
				'description' => array( 'post_content', 'get_rendered_content' ),
			);
			foreach ( $rendered_fields as $field => $spec ) {
				list( $property, $render ) = $spec;

				$value = array();
				if ( $can_edit && rest_is_field_included( "{$field}.raw", $fields ) ) {
					$value['raw'] = $post->$property;
				}
				if ( rest_is_field_included( "{$field}.rendered", $fields ) ) {
					$value['rendered'] = $this->$render( $post );
				}
				if ( ! empty( $value ) ) {
					$data[ $field ] = $value;
				}
			}

			if ( rest_is_field_included( 'alt_text', $fields ) ) {
				$data['alt_text'] = (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
			}
			if ( rest_is_field_included( 'media_type', $fields ) ) {
				$data['media_type'] = wp_attachment_is_image( $post->ID ) ? 'image' : 'file';
			}
			if ( rest_is_field_included( 'mime_type', $fields ) ) {
				$data['mime_type'] = $post->post_mime_type;
			}

			$source_url = wp_get_attachment_url( $post->ID );
			if ( $source_url && rest_is_field_included( 'source_url', $fields ) ) {
				$data['source_url'] = $source_url;
			}

			$original_path = wp_get_original_image_path( $post->ID );
			$file_path     = $original_path ? $original_path : get_attached_file( $post->ID );
			if ( $file_path && rest_is_field_included( 'filename', $fields ) ) {
				$data['filename'] = wp_basename( $file_path );
			}

			$metadata = wp_get_attachment_metadata( $post->ID );
			$metadata = is_array( $metadata ) ? $metadata : array();
			if ( rest_is_field_included( 'filesize', $fields ) ) {
				if ( isset( $metadata['filesize'] ) && is_numeric( $metadata['filesize'] ) && $metadata['filesize'] > 0 ) {
					$data['filesize'] = (int) $metadata['filesize'];
				} elseif ( $file_path && is_readable( $file_path ) ) {
					$data['filesize'] = wp_filesize( $file_path );
				}
			}

			if ( rest_is_field_included( 'media_details', $fields ) ) {
				$details = array();
				foreach ( array( 'width', 'height', 'length' ) as $key ) {
					if ( isset( $metadata[ $key ] ) && is_numeric( $metadata[ $key ] ) && rest_is_field_included( "media_details.{$key}", $fields ) ) {
						$details[ $key ] = (int) $metadata[ $key ];
					}
				}

				/*
				 * Each size holds its width, height, MIME type and URL, and a size that lacks
				 * one of them is left out. The full size is added when the stored metadata has
				 * sub-sizes, also when none of them is kept.
				 */
				if ( ! empty( $metadata ) && rest_is_field_included( 'media_details.sizes', $fields ) ) {
					$stored_sizes = is_array( $metadata['sizes'] ?? null ) ? $metadata['sizes'] : array();

					/*
					 * The image URL lookup of a size reads the `file`, `width` and `height` of
					 * that size's stored entry, and for a file that is not an image those of the
					 * stored `full` entry, without checking them. A size whose lookup would read
					 * a missing one is left out before the lookup, so that incomplete stored
					 * metadata raises no warning.
					 */
					$is_complete  = static function ( $entry ): bool {
						return is_array( $entry ) && isset( $entry['file'], $entry['width'], $entry['height'] );
					};
					$can_look_up  = static function ( string $name ) use ( $stored_sizes, $is_complete ): bool {
						return empty( $stored_sizes[ $name ] ) || $is_complete( $stored_sizes[ $name ] );
					};
					$full_is_safe = wp_attachment_is_image( $post->ID ) || $can_look_up( 'full' );

					$sizes = array();
					foreach ( $stored_sizes as $name => $size ) {
						$name = (string) $name;
						if ( ! $full_is_safe || ! $is_complete( $size ) || empty( $size['mime-type'] ) || ! is_string( $size['mime-type'] ) ) {
							continue;
						}

						$source = wp_get_attachment_image_src( $post->ID, $name );
						if ( $source ) {
							$sizes[ $name ] = array(
								'width'      => (int) $size['width'],
								'height'     => (int) $size['height'],
								'mime_type'  => $size['mime-type'],
								'source_url' => $source[0],
							);
						}
					}

					$full = ! empty( $stored_sizes ) && $full_is_safe && $can_look_up( 'full' ) ? wp_get_attachment_image_src( $post->ID, 'full' ) : false;
					if ( $full && '' !== $post->post_mime_type ) {
						$sizes['full'] = array(
							'width'      => (int) $full[1],
							'height'     => (int) $full[2],
							'mime_type'  => $post->post_mime_type,
							'source_url' => $full[0],
						);
					}

					// An object without keys is an stdClass, so it encodes as an object for every caller.
					$details['sizes'] = empty( $sizes ) ? new stdClass() : $sizes;
				}

				// The whole field is an empty object without metadata; a sub-field left empty is left out.
				if ( ! empty( $details ) ) {
					$data['media_details'] = $details;
				} elseif ( in_array( 'media_details', $fields, true ) ) {
					$data['media_details'] = new stdClass();
				}
			}

			if ( rest_is_field_included( 'post', $fields ) ) {
				$data['post'] = (int) $post->post_parent;
			}

			if ( rest_is_field_included( 'author', $fields ) ) {
				$author_id = (int) $post->post_author;
				$user      = null;
				if ( rest_is_field_included( 'author.name', $fields ) || rest_is_field_included( 'author.slug', $fields ) ) {
					$user = $this->get_visible_author( $author_id );
				}

				$author = array();
				if ( rest_is_field_included( 'author.id', $fields ) ) {
					$author['id'] = $author_id;
				}
				if ( rest_is_field_included( 'author.name', $fields ) ) {
					$author['name'] = $user ? $user->display_name : null;
				}
				if ( rest_is_field_included( 'author.slug', $fields ) ) {
					$author['slug'] = $user ? $user->user_nicename : null;
				}
				if ( ! empty( $author ) ) {
					$data['author'] = $author;
				}
			}

			return $data;
		} finally {
			$this->restore_post_context( $previous_context, $the_post_fired );
		}
	}

	/**
	 * Returns the attachment title with the protected and private prefixes stripped.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_Post $post The attachment.
	 * @return string The title.
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

			return is_string( $title ) ? $title : '';
		} finally {
			remove_filter( 'protected_title_format', $strip );
			remove_filter( 'private_title_format', $strip );
		}
	}

	/**
	 * Returns the attachment caption transformed for display.
	 *
	 * Applies the `get_the_excerpt` and `the_excerpt` filter chains, so an empty caption
	 * renders an excerpt of the description.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_Post $post The attachment.
	 * @return string The rendered caption.
	 */
	private function get_rendered_excerpt( WP_Post $post ): string {
		/** This filter is documented in wp-includes/post-template.php */
		$excerpt = apply_filters( 'get_the_excerpt', $post->post_excerpt, $post );

		/** This filter is documented in wp-includes/post-template.php */
		$excerpt = apply_filters( 'the_excerpt', $excerpt );

		return is_string( $excerpt ) ? $excerpt : '';
	}

	/**
	 * Returns the attachment description transformed for display.
	 *
	 * Applies `the_content`, which adds the attachment markup in front of the text.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_Post $post The attachment.
	 * @return string The rendered description.
	 */
	private function get_rendered_content( WP_Post $post ): string {
		/** This filter is documented in wp-includes/post-template.php */
		$content = apply_filters( 'the_content', $post->post_content );

		return is_string( $content ) ? $content : '';
	}

	/**
	 * Returns the global post context, so it can be restored after a post is rendered.
	 *
	 * @since 7.3.0
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
	 * Sets the global post and calls setup_postdata(), so filters that rely on loop globals
	 * render against the requested post.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_Post $post The post to render.
	 */
	private function set_up_post_context( WP_Post $post ): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporarily sets the global post for rendering.
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
	 * @since 7.3.0
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
		 * post reads the loop globals instead of the post, and an unset `$pages` makes it
		 * throw a TypeError that breaks the page being rendered.
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
			 * and no global post was set. Once it has fired, get_the_content() called without
			 * a post reads the loop globals unset above instead of the post.
			 */
			if ( ! $previous_post instanceof WP_Post && ! $the_post_fired ) {
				unset( $GLOBALS['wp_actions']['the_post'] );
			}
		}
	}

	/**
	 * Formats a post date field as an ISO 8601 string, in the site's timezone or in GMT.
	 *
	 * In GMT, it reads the stored GMT date, deriving it from the local date when it is missing.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_Post $post  The attachment.
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

		return rest_parse_date( $date ) ? $date : null;
	}

	/**
	 * Builds the query output for a page of formatted attachments.
	 *
	 * @since 7.3.0
	 *
	 * @param array<int, array<string, mixed>> $items       The formatted attachments on the page.
	 * @param int                              $total       Total number of attachments across all pages.
	 * @param int                              $total_pages Total number of pages.
	 * @return array<string, mixed> The ability output.
	 */
	private function prepare_query_output( array $items, int $total, int $total_pages ): array {
		return array(
			'items'      => $items,
			'pagination' => array(
				'total'       => $total,
				'total_pages' => $total_pages,
			),
		);
	}

	/**
	 * Returns the attachment field definitions, keyed by field name in output order.
	 *
	 * The output schema uses the definitions directly, while the `fields` input lists each
	 * field, followed by the path of each of its sub-fields, such as `title.rendered`.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The attachment field definitions.
	 */
	private function get_item_properties(): array {
		$date_schema = static function ( string $description, string $site, string $gmt ): array {
			return array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'site' => array(
						'type'        => array( 'string', 'null' ),
						'format'      => 'date-time',
						'description' => $site,
					),
					'gmt'  => array(
						'type'        => array( 'string', 'null' ),
						'format'      => 'date-time',
						'description' => $gmt,
					),
				),
				'description'          => $description,
			);
		};

		$text_schema = static function ( string $description, string $raw, string $rendered ): array {
			return array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'raw'      => array(
						'type'        => 'string',
						'description' => $raw,
					),
					'rendered' => array(
						'type'        => 'string',
						'description' => $rendered,
					),
				),
				'description'          => $description,
			);
		};

		return array(
			'id'            => array(
				'type'        => 'integer',
				'description' => __( 'The attachment ID.' ),
			),
			'status'        => array(
				'type'        => 'string',
				'description' => __( 'The attachment status.' ),
			),
			'date'          => $date_schema(
				__( 'The upload date.' ),
				__( "The upload date, in ISO 8601 format using the site's timezone. Null when the date cannot be resolved." ),
				__( 'The upload date, in ISO 8601 format as GMT. Null when the date cannot be resolved.' )
			),
			'modified'      => $date_schema(
				__( 'The last modified date.' ),
				__( "The last modified date, in ISO 8601 format using the site's timezone. Null when the date cannot be resolved." ),
				__( 'The last modified date, in ISO 8601 format as GMT. Null when the date cannot be resolved.' )
			),
			'slug'          => array(
				'type'        => 'string',
				'description' => __( 'The attachment slug.' ),
			),
			'link'          => array(
				'type'        => 'string',
				'format'      => 'uri',
				'description' => __( 'The URL of the attachment page.' ),
			),
			'title'         => $text_schema(
				__( 'The attachment title.' ),
				__( 'The title as stored. Present when the current user can edit the attachment.' ),
				__( 'The title as it is displayed.' )
			),
			'caption'       => $text_schema(
				__( 'The attachment caption.' ),
				__( 'The caption as stored. Present when the current user can edit the attachment.' ),
				__( 'The caption as it is displayed (HTML).' )
			),
			'description'   => $text_schema(
				__( 'The attachment description.' ),
				__( 'The description as stored. Present when the current user can edit the attachment.' ),
				__( 'The description as it is displayed (HTML), with the attachment markup in front of it.' )
			),
			'alt_text'      => array(
				'type'        => 'string',
				'description' => __( 'The alternative text, empty when there is none.' ),
			),
			'media_type'    => array(
				'type'        => 'string',
				'description' => __( 'The kind of attachment: `image` or `file`.' ),
			),
			'mime_type'     => array(
				'type'        => 'string',
				'description' => __( 'The MIME type of the file.' ),
			),
			'source_url'    => array(
				'type'        => 'string',
				'format'      => 'uri',
				'description' => __( 'The URL of the file. Left out when the attachment has no file.' ),
			),
			'filename'      => array(
				'type'        => 'string',
				'description' => __( 'The name of the original file. Left out when the attachment has no file.' ),
			),
			'filesize'      => array(
				'type'        => 'integer',
				'description' => __( 'The size of the original file in bytes. Left out when it is not known.' ),
			),
			'media_details' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'width'  => array(
						'type'        => 'integer',
						'description' => __( 'The width in pixels, for images and videos.' ),
					),
					'height' => array(
						'type'        => 'integer',
						'description' => __( 'The height in pixels, for images and videos.' ),
					),
					'length' => array(
						'type'        => 'integer',
						'description' => __( 'The length in seconds, for audio and video.' ),
					),
					'sizes'  => array(
						'type'                 => 'object',
						'additionalProperties' => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'required'             => array( 'width', 'height', 'mime_type', 'source_url' ),
							'properties'           => array(
								'width'      => array(
									'type'        => 'integer',
									'description' => __( 'The width in pixels.' ),
								),
								'height'     => array(
									'type'        => 'integer',
									'description' => __( 'The height in pixels.' ),
								),
								'mime_type'  => array(
									'type'        => 'string',
									'description' => __( 'The MIME type of the size.' ),
								),
								'source_url' => array(
									'type'        => 'string',
									'format'      => 'uri',
									'description' => __( 'The URL of the size.' ),
								),
							),
						),
						'description'          => __( 'The image sizes, keyed by size name, with `full` for the full size when there are sub-sizes. Empty when there are none.' ),
					),
				),
				'description'          => __( 'Details of the file from its stored metadata. Empty when there is no metadata.' ),
			),
			'post'          => array(
				'type'        => 'integer',
				'description' => __( 'The ID of the post the attachment is attached to, 0 when it is unattached.' ),
			),
			'author'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'id'   => array(
						'type'        => 'integer',
						'description' => __( 'The user ID of the author, as the `author.id` filter takes it.' ),
					),
					'name' => array(
						'type'        => array( 'string', 'null' ),
						'description' => __( "The author's display name. Null when the author no longer exists or the current user may not see them." ),
					),
					'slug' => array(
						'type'        => array( 'string', 'null' ),
						'description' => __( "The author's user slug (nicename), as the `author.slug` filter takes it. Null when the author no longer exists or the current user may not see them." ),
					),
				),
				'description'          => __( 'The user who uploaded the attachment.' ),
			),
		);
	}

	/**
	 * Builds the output schema of a single attachment, shared by all media abilities.
	 *
	 * Only `id` is required, because it is always returned. The other fields are optional
	 * because the `fields` input lets the caller request any subset.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The attachment JSON Schema.
	 */
	private function get_item_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id' ),
			'properties'           => $this->get_item_properties(),
		);
	}

	/**
	 * Builds the schema of the `fields` input, shared by the query, upload and update abilities.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The `fields` JSON Schema.
	 */
	private function get_fields_input_schema(): array {
		$paths = array();
		foreach ( $this->get_item_properties() as $name => $schema ) {
			$paths[] = $name;
			foreach ( array_keys( $schema['properties'] ?? array() ) as $sub_field ) {
				$paths[] = "{$name}.{$sub_field}";
			}
		}

		return array(
			'type'        => 'array',
			'uniqueItems' => true,
			'items'       => array(
				'type' => 'string',
				'enum' => $paths,
			),
			'description' => sprintf(
				/* translators: %s: Comma-separated list of field paths. */
				__( 'Limit each returned attachment to these fields. A field such as `title` returns all of its sub-fields the current user can see, while a path such as `title.rendered` returns only that sub-field. The `id` is always included. If omitted or empty, the default fields are returned: %s. Raw sub-fields, such as `title.raw`, are left out when the current user cannot edit the attachment.' ),
				'`' . implode( '`, `', self::DEFAULT_FIELDS ) . '`'
			),
		);
	}

	/**
	 * Builds the input schema for the `core/media-query` ability.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_query_input_schema(): array {
		return array(
			'type'                 => 'object',
			'default'              => array(),
			'additionalProperties' => false,
			'properties'           => array(
				'where'      => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'ids'        => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'maxItems'    => self::MAX_PER_PAGE,
							'items'       => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'description' => __( 'Limit the query to these attachment IDs. If `pagination.per_page` is omitted, every matching attachment is returned on the first page.' ),
						),
						'slugs'      => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'maxItems'    => self::MAX_PER_PAGE,
							'items'       => array(
								'type'      => 'string',
								'minLength' => 1,
							),
							'description' => __( 'Limit the query to attachments with these slugs. Each slug is sanitized as a post slug is, so `Team-Photo` matches `team-photo`.' ),
						),
						'status'     => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'items'       => array(
								'type' => 'string',
								'enum' => self::STATUSES,
							),
							'description' => __( 'Filter by one or more statuses. Defaults to `inherit`, the status of every attachment that is neither private nor trashed. `private` requires the capability to read private posts or to edit posts, and `trash` the capability to edit posts.' ),
						),
						'media_type' => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'items'       => array(
								'type' => 'string',
								'enum' => self::MEDIA_TYPES,
							),
							'description' => __( 'Filter by one or more media types, the part of the MIME type before the slash. A media type without any MIME type the current user may upload is an error.' ),
						),
						'mime_type'  => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'items'       => array(
								'type'      => 'string',
								'minLength' => 1,
							),
							'description' => __( 'Filter by one or more MIME types, such as `image/jpeg`. Each must be a MIME type the current user may upload. With `media_type`, an attachment matches when it matches either filter.' ),
						),
						'post'       => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => __( 'Filter by the ID of the post the attachments are attached to. Use 0 for unattached attachments.' ),
						),
						'author'     => array(
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
									'description' => __( "The author's user slug (nicename), as `author.slug` is returned." ),
								),
							),
							'description'          => __( 'Filter by the author, given as the `id` or the `slug` the `author` field returns. A `slug` that names no user or a user the current user may not see, or an `id` and a `slug` that name different users, match nothing.' ),
						),
					),
					'description'          => __( 'Selects the attachments to return. An attachment must match every given filter.' ),
				),
				'fields'     => $this->get_fields_input_schema(),
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'page'     => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Page of results to return. Defaults to 1. Requesting a page beyond the last one is an error, unless nothing matches the query.' ),
						),
						'per_page' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => self::MAX_PER_PAGE,
							'description' => sprintf(
								/* translators: 1: Default number of attachments per page, 2: Maximum number of attachments per page. */
								__( 'Maximum number of attachments to return per page. Defaults to %1$d, or to %2$d when `where.ids` or `where.slugs` is given.' ),
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
	 * Returns the schemas of the attributes an upload or an update can write.
	 *
	 * @since 7.3.0
	 *
	 * @param int $min_post The smallest `post` value: 1 on upload, 0 on update.
	 * @return array<string, mixed> The attribute schemas, keyed by name.
	 */
	private function get_attribute_properties( int $min_post ): array {
		return array(
			'title'       => array(
				'type'        => 'string',
				'description' => __( 'The attachment title.' ),
			),
			'caption'     => array(
				'type'        => 'string',
				'description' => __( 'The attachment caption.' ),
			),
			'description' => array(
				'type'        => 'string',
				'description' => __( 'The attachment description.' ),
			),
			'alt_text'    => array(
				'type'        => 'string',
				'description' => __( 'The alternative text of the image.' ),
			),
			'slug'        => array(
				'type'        => 'string',
				'minLength'   => 1,
				'description' => __( 'The attachment slug. It is sanitized as a post slug is, and made unique when it is taken.' ),
			),
			'date'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'minProperties'        => 1,
				'properties'           => array(
					'site' => array(
						'type'        => 'string',
						'format'      => 'date-time',
						'description' => __( "The date in the site's timezone." ),
					),
					'gmt'  => array(
						'type'        => 'string',
						'format'      => 'date-time',
						'description' => __( 'The date as GMT.' ),
					),
				),
				'description'          => __( 'The attachment date, as `site`, `gmt` or both; `site` wins when both are given.' ),
			),
			'author'      => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'The user ID of the author. Another user than the current one requires the capability to edit the posts of others.' ),
			),
			'post'        => array(
				'type'        => 'integer',
				'minimum'     => $min_post,
				'description' => 0 === $min_post
					? __( 'The ID of the post to attach the attachment to, or 0 to detach it. Requires the capability to edit that post.' )
					: __( 'The ID of the post to attach the attachment to. Requires the capability to edit that post.' ),
			),
		);
	}

	/**
	 * Builds the input schema for the `core/media-upload` ability.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_upload_input_schema(): array {
		$file = array(
			'file' => array(
				'anyOf'       => array(
					array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'url' ),
						'properties'           => array(
							'url' => array(
								'type'        => 'string',
								'format'      => 'uri',
								'description' => __( 'The public HTTP or HTTPS URL of an image to download.' ),
							),
						),
					),
					array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'base64', 'filename' ),
						'properties'           => array(
							'base64'   => array(
								'type'        => 'string',
								'minLength'   => 1,
								'description' => __( 'The file content as base64 data, without a `data:` prefix.' ),
							),
							'filename' => array(
								'type'        => 'string',
								'minLength'   => 1,
								'description' => __( 'The file name, with the extension that matches its content.' ),
							),
						),
					),
				),
				'description' => __( 'The file: an image from a `url`, or any file the current user may upload as `base64` data with its `filename`.' ),
			),
		);

		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'data' ),
			'properties'           => array(
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'file' ),
					'properties'           => array_merge( $file, $this->get_attribute_properties( 1 ) ),
					'description'          => __( 'The file and the attributes of the new attachment. Without a title, the title is taken from the image metadata or the file name.' ),
				),
				'fields' => $this->get_fields_input_schema(),
			),
		);
	}

	/**
	 * Builds the input schema for the `core/media-update` ability.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The input JSON Schema.
	 */
	private function get_update_input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where', 'data' ),
			'properties'           => array(
				'where'  => $this->get_where_id_schema( __( 'Selects the attachment to update.' ) ),
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'minProperties'        => 1,
					'properties'           => $this->get_attribute_properties( 0 ),
					'description'          => __( 'The attributes to change. Attributes that are left out keep their stored values.' ),
				),
				'fields' => $this->get_fields_input_schema(),
			),
		);
	}

	/**
	 * Builds the schema of a `where` input that selects one attachment by ID.
	 *
	 * @since 7.3.0
	 *
	 * @param string $description The description of `where`.
	 * @return array<string, mixed> The `where` JSON Schema.
	 */
	private function get_where_id_schema( string $description ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id' ),
			'properties'           => array(
				'id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'The attachment ID.' ),
				),
			),
			'description'          => $description,
		);
	}

	/**
	 * Builds the error for a filter that cannot be applied.
	 *
	 * @since 7.3.0
	 *
	 * @param string $param   The filter's input path, such as `where[ids]`.
	 * @param string $message The error message.
	 * @return WP_Error The invalid filter error.
	 */
	private function invalid_filter_error( string $param, string $message ): WP_Error {
		return new WP_Error(
			'media_invalid_filter',
			$message,
			array(
				'status' => 400,
				'params' => array( $param => $message ),
			)
		);
	}

	/**
	 * Builds the error for a `data` attribute that cannot be applied.
	 *
	 * @since 7.3.0
	 *
	 * @param string $attribute The attribute name, such as `slug`, or `data` for the whole object.
	 * @param string $message   The error message.
	 * @return WP_Error The invalid attribute error.
	 */
	private function invalid_attribute_error( string $attribute, string $message ): WP_Error {
		$param = 'data' === $attribute ? 'data' : "data[{$attribute}]";

		return new WP_Error(
			'media_invalid_attribute',
			$message,
			array(
				'status' => 400,
				'params' => array( $param => $message ),
			)
		);
	}

	/**
	 * Builds the error for a file that cannot be stored.
	 *
	 * @since 7.3.0
	 *
	 * @param string $param   The file's input path, such as `data[file][url]`.
	 * @param string $message The error message.
	 * @return WP_Error The invalid file error.
	 */
	private function invalid_file_error( string $param, string $message ): WP_Error {
		return new WP_Error(
			'media_invalid_file',
			$message,
			array(
				'status' => 400,
				'params' => array( $param => $message ),
			)
		);
	}

	/**
	 * Builds the error for a save that failed, from the error the save returned.
	 *
	 * @since 7.3.0
	 *
	 * @param WP_Error $error The error the save returned.
	 * @return WP_Error The save failed error.
	 */
	private function save_failed_error( WP_Error $error ): WP_Error {
		$is_database_error = in_array( $error->get_error_code(), array( 'db_insert_error', 'db_update_error' ), true );

		return new WP_Error(
			'media_save_failed',
			$error->get_error_message(),
			array( 'status' => $is_database_error ? 500 : 400 )
		);
	}

	/**
	 * Builds the error for an ID that names no attachment.
	 *
	 * @since 7.3.0
	 *
	 * @return WP_Error The not found error.
	 */
	private function not_found_error(): WP_Error {
		return new WP_Error(
			'media_not_found',
			__( 'The requested media was not found.' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Builds the error for a deletion that failed or could not be confirmed.
	 *
	 * @since 7.3.0
	 *
	 * @return WP_Error The delete failed error.
	 */
	private function delete_failed_error(): WP_Error {
		return new WP_Error(
			'media_delete_failed',
			__( 'The attachment could not be deleted.' ),
			array( 'status' => 500 )
		);
	}
}
