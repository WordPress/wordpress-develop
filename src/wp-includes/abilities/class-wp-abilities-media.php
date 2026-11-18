<?php
/**
 * Abilities API: WP_Abilities_Media class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 7.2.0
 */

declare( strict_types = 1 );

/**
 * Core class used to register media-related abilities.
 *
 * Registers `core/media-query`, `core/media-upload`, `core/media-update`, and
 * `core/media-delete` in the `content` category.
 *
 * @since 7.2.0
 *
 * @access private
 */
final class WP_Abilities_Media {

	/**
	 * The ability category used for media abilities.
	 *
	 * @since 7.2.0
	 * @var string
	 */
	private const CATEGORY = 'content';

	/**
	 * Default number of attachments returned per page.
	 *
	 * @since 7.2.0
	 * @var int
	 */
	private const DEFAULT_PER_PAGE = 10;

	/**
	 * Maximum number of attachments returned per page.
	 *
	 * @since 7.2.0
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Default field paths returned when the caller does not request a field subset.
	 *
	 * @since 7.2.0
	 * @var list<string>
	 */
	private const DEFAULT_FIELDS = array(
		'id',
		'status',
		'date',
		'slug',
		'title.rendered',
		'media_type',
		'mime_type',
		'source_url',
	);

	/**
	 * All selectable field paths for media responses.
	 *
	 * @since 7.2.0
	 * @var list<string>
	 */
	private const ALL_FIELDS = array(
		'id',
		'status',
		'date',
		'date.site',
		'date.gmt',
		'modified',
		'modified.site',
		'modified.gmt',
		'slug',
		'link',
		'title',
		'title.raw',
		'title.rendered',
		'caption',
		'caption.raw',
		'caption.rendered',
		'description',
		'description.raw',
		'description.rendered',
		'alt_text',
		'media_type',
		'mime_type',
		'source_url',
		'filename',
		'filesize',
		'media_details',
		'media_details.width',
		'media_details.height',
		'media_details.length',
		'media_details.sizes',
		'post',
		'author',
		'author.id',
		'author.name',
		'author.slug',
	);

	/**
	 * Whether each author seen in the current query is visible to the current user.
	 *
	 * @since 7.2.0
	 * @var array<int, bool>
	 */
	private $visible_authors = array();

	/**
	 * Registers all media abilities.
	 *
	 * Must run on the `wp_abilities_api_init` hook.
	 *
	 * @since 7.2.0
	 */
	public function register(): void {
		$this->register_media_query();
		$this->register_media_upload();
		$this->register_media_update();
		$this->register_media_delete();
	}

	/**
	 * Registers the read-only `core/media-query` ability.
	 *
	 * @since 7.2.0
	 */
	private function register_media_query(): void {
		wp_register_ability(
			'core/media-query',
			array(
				'label'               => __( 'Query Media' ),
				'description'         => __( 'Retrieves a paginated list of media attachments the current user can read, filtered by IDs, slugs, status, media type, MIME type, parent post, or author.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_media_query_input_schema(),
				'output_schema'       => $this->get_media_query_output_schema(),
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_media_query( $input );
				},
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
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
	 * Registers the `core/media-upload` ability.
	 *
	 * @since 7.2.0
	 */
	private function register_media_upload(): void {
		wp_register_ability(
			'core/media-upload',
			array(
				'label'               => __( 'Upload Media' ),
				'description'         => __( 'Uploads a media attachment from an image URL or base64-encoded file bytes with optional metadata.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_media_upload_input_schema(),
				'output_schema'       => $this->get_media_item_schema(),
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_media_upload( $input );
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'upload_files' );
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
	}

	/**
	 * Registers the `core/media-update` ability.
	 *
	 * @since 7.2.0
	 */
	private function register_media_update(): void {
		wp_register_ability(
			'core/media-update',
			array(
				'label'               => __( 'Update Media' ),
				'description'         => __( 'Updates metadata, parent post association, or author of an existing media attachment.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_media_update_input_schema(),
				'output_schema'       => $this->get_media_item_schema(),
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_media_update( $input );
				},
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
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
	}

	/**
	 * Registers the `core/media-delete` ability.
	 *
	 * @since 7.2.0
	 */
	private function register_media_delete(): void {
		wp_register_ability(
			'core/media-delete',
			array(
				'label'               => __( 'Delete Media' ),
				'description'         => __( 'Trashes or permanently deletes a media attachment.' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_media_delete_input_schema(),
				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'outcome' ),
					'properties'           => array(
						'outcome' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ) {
					return $this->execute_media_delete( $input );
				},
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
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
	 * Executes the `core/media-query` ability.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $input Ability input.
	 * @return array<string, mixed>|WP_Error Query results with pagination or error.
	 */
	private function execute_media_query( $input = array() ) {
		$input = rest_sanitize_object( $input );
		$where = isset( $input['where'] ) && is_array( $input['where'] ) ? $input['where'] : array();

		$post_type_object = get_post_type_object( 'attachment' );
		if ( ! $post_type_object instanceof WP_Post_Type ) {
			return new WP_Error(
				'media_invalid_type',
				__( 'The attachment post type is not registered.' ),
				array( 'status' => 500 )
			);
		}

		$statuses = isset( $where['status'] ) && is_array( $where['status'] )
			? array_values( array_unique( array_map( 'strval', $where['status'] ) ) )
			: array( 'inherit' );

		if (
			( in_array( 'trash', $statuses, true ) && ! current_user_can( $post_type_object->cap->edit_posts ) ) ||
			( in_array( 'private', $statuses, true ) && ! current_user_can( $post_type_object->cap->read_private_posts ) )
		) {
			return new WP_Error(
				'media_cannot_filter_by_status',
				__( 'Sorry, you are not allowed to filter media by this status.' ),
				array( 'status' => 403 )
			);
		}

		$query_args = array(
			'post_type'     => 'attachment',
			'post_status'   => $statuses,
			'orderby'       => 'date',
			'order'         => 'DESC',
			'no_found_rows' => false,
		);

		$ids   = isset( $where['ids'] ) && is_array( $where['ids'] ) ? array_values( array_unique( array_map( 'intval', $where['ids'] ) ) ) : array();
		$slugs = isset( $where['slugs'] ) && is_array( $where['slugs'] ) ? array_values( array_unique( array_map( 'sanitize_title', $where['slugs'] ) ) ) : array();

		if ( ! empty( $ids ) ) {
			$query_args['post__in'] = $ids;
			$query_args['orderby']  = 'post__in';
		}

		if ( ! empty( $slugs ) ) {
			$query_args['post_name__in'] = $slugs;
		}

		if ( array_key_exists( 'post', $where ) ) {
			$query_args['post_parent'] = (int) $where['post'];
		}

		if ( isset( $where['author'] ) && is_array( $where['author'] ) ) {
			if ( isset( $where['author']['id'] ) ) {
				$query_args['author'] = (int) $where['author']['id'];
			} elseif ( isset( $where['author']['slug'] ) ) {
				$author_user = get_user_by( 'slug', (string) $where['author']['slug'] );
				if ( ! $author_user ) {
					return array(
						'items'      => array(),
						'pagination' => array(
							'total'       => 0,
							'total_pages' => 0,
						),
					);
				}
				$query_args['author'] = $author_user->ID;
			}
		}

		$mime_filters = $this->resolve_mime_type_filters( $where );
		if ( null !== $mime_filters ) {
			if ( empty( $mime_filters ) ) {
				return array(
					'items'      => array(),
					'pagination' => array(
						'total'       => 0,
						'total_pages' => 0,
					),
				);
			}
			$query_args['post_mime_type'] = $mime_filters;
		}

		$pagination = isset( $input['pagination'] ) && is_array( $input['pagination'] ) ? $input['pagination'] : array();
		$page       = isset( $pagination['page'] ) ? max( 1, (int) $pagination['page'] ) : 1;
		$per_page   = isset( $pagination['per_page'] )
			? min( self::MAX_PER_PAGE, max( 1, (int) $pagination['per_page'] ) )
			: ( ! empty( $ids ) || ! empty( $slugs ) ? self::MAX_PER_PAGE : self::DEFAULT_PER_PAGE );

		$query_args['paged']          = $page;
		$query_args['posts_per_page'] = $per_page;

		$query  = new WP_Query( $query_args );
		$fields = $this->normalize_fields( $input );

		$this->visible_authors = array();
		$items                 = array();
		foreach ( $query->posts as $attachment ) {
			if ( ! $attachment instanceof WP_Post ) {
				continue;
			}
			if ( ! $this->can_read_attachment( $attachment ) ) {
				continue;
			}
			$items[] = $this->format_attachment( $attachment, $fields );
		}

		$total       = (int) $query->found_posts;
		$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;

		return array(
			'items'      => $items,
			'pagination' => array(
				'total'       => $total,
				'total_pages' => $total_pages,
			),
		);
	}

	/**
	 * Executes the `core/media-upload` ability.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $input Ability input.
	 * @return array<string, mixed>|WP_Error Created attachment data or error.
	 */
	private function execute_media_upload( $input = array() ) {
		$input = rest_sanitize_object( $input );
		$data  = isset( $input['data'] ) && is_array( $input['data'] ) ? $input['data'] : array();
		$file  = isset( $data['file'] ) && is_array( $data['file'] ) ? $data['file'] : array();

		$parent_id = isset( $data['post'] ) ? (int) $data['post'] : 0;
		if ( $parent_id > 0 ) {
			$parent_check = $this->validate_parent_post( $parent_id );
			if ( is_wp_error( $parent_check ) ) {
				return $parent_check;
			}
		}

		$author_id = isset( $data['author'] ) ? (int) $data['author'] : get_current_user_id();
		if ( isset( $data['author'] ) && get_current_user_id() !== $author_id ) {
			$author_check = $this->validate_author_assignment( $author_id );
			if ( is_wp_error( $author_check ) ) {
				return $author_check;
			}
		}

		$this->load_media_admin_files();

		if ( isset( $file['url'] ) ) {
			$file_array = $this->prepare_url_upload( (string) $file['url'] );
		} elseif ( isset( $file['base64'], $file['filename'] ) ) {
			$file_array = $this->prepare_base64_upload( (string) $file['base64'], (string) $file['filename'] );
		} else {
			return new WP_Error(
				'media_invalid_file',
				__( 'A valid file URL or base64 payload with filename is required.' ),
				array( 'status' => 400 )
			);
		}

		if ( is_wp_error( $file_array ) ) {
			return $file_array;
		}

		$post_data = $this->build_attachment_post_data( $data, $author_id );
		$title     = isset( $data['title'] ) ? (string) $data['title'] : null;

		$attachment_id = media_handle_sideload( $file_array, $parent_id, $title, $post_data );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $file_array['tmp_name'] );
			return new WP_Error(
				'media_upload_failed',
				$attachment_id->get_error_message(),
				array( 'status' => 400 )
			);
		}

		if ( isset( $data['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $data['alt_text'] ) );
		}

		$attachment = get_post( $attachment_id );
		if ( ! $attachment instanceof WP_Post ) {
			return new WP_Error(
				'media_not_found',
				__( 'The uploaded attachment could not be retrieved.' ),
				array( 'status' => 500 )
			);
		}

		$this->visible_authors = array();
		return $this->format_attachment( $attachment, $this->normalize_fields( $input ) );
	}

	/**
	 * Executes the `core/media-update` ability.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $input Ability input.
	 * @return array<string, mixed>|WP_Error Updated attachment data or error.
	 */
	private function execute_media_update( $input = array() ) {
		$input = rest_sanitize_object( $input );
		$where = isset( $input['where'] ) && is_array( $input['where'] ) ? $input['where'] : array();
		$data  = isset( $input['data'] ) && is_array( $input['data'] ) ? $input['data'] : array();
		$id    = isset( $where['id'] ) ? (int) $where['id'] : 0;

		$attachment = $this->get_attachment_or_error( $id );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}

		if ( ! current_user_can( 'edit_post', $attachment->ID ) ) {
			return new WP_Error(
				'media_cannot_edit',
				__( 'Sorry, you are not allowed to update this media item.' ),
				array( 'status' => 403 )
			);
		}

		$post_data = array( 'ID' => $attachment->ID );

		if ( array_key_exists( 'post', $data ) ) {
			$parent_id = (int) $data['post'];
			if ( $parent_id > 0 ) {
				$parent_check = $this->validate_parent_post( $parent_id );
				if ( is_wp_error( $parent_check ) ) {
					return $parent_check;
				}
			}
			$post_data['post_parent'] = $parent_id;
		}

		if ( isset( $data['author'] ) ) {
			$author_id = (int) $data['author'];
			if ( $author_id !== (int) $attachment->post_author ) {
				$author_check = $this->validate_author_assignment( $author_id );
				if ( is_wp_error( $author_check ) ) {
					return $author_check;
				}
			}
			$post_data['post_author'] = $author_id;
		}

		if ( isset( $data['title'] ) ) {
			$post_data['post_title'] = (string) $data['title'];
		}
		if ( isset( $data['caption'] ) ) {
			$post_data['post_excerpt'] = (string) $data['caption'];
		}
		if ( isset( $data['description'] ) ) {
			$post_data['post_content'] = (string) $data['description'];
		}
		if ( isset( $data['slug'] ) ) {
			$post_data['post_name'] = sanitize_title( (string) $data['slug'] );
		}
		if ( isset( $data['date'] ) && is_array( $data['date'] ) ) {
			$dates = $this->resolve_date_values( $data['date'] );
			if ( is_wp_error( $dates ) ) {
				return $dates;
			}
			$post_data = array_merge( $post_data, $dates );
		}

		if ( count( $post_data ) > 1 ) {
			$updated = wp_update_post( wp_slash( $post_data ), true );
			if ( is_wp_error( $updated ) ) {
				return new WP_Error(
					'media_update_failed',
					$updated->get_error_message(),
					array( 'status' => 400 )
				);
			}
		}

		if ( isset( $data['alt_text'] ) ) {
			update_post_meta( $attachment->ID, '_wp_attachment_image_alt', sanitize_text_field( (string) $data['alt_text'] ) );
		}

		$updated_attachment = get_post( $attachment->ID );
		if ( ! $updated_attachment instanceof WP_Post ) {
			return new WP_Error(
				'media_not_found',
				__( 'The media item could not be found after updating.' ),
				array( 'status' => 404 )
			);
		}

		$this->visible_authors = array();
		return $this->format_attachment( $updated_attachment, $this->normalize_fields( $input ) );
	}

	/**
	 * Executes the `core/media-delete` ability.
	 *
	 * @since 7.2.0
	 *
	 * @param mixed $input Ability input.
	 * @return array<string, string>|WP_Error Deletion outcome or error.
	 */
	private function execute_media_delete( $input = array() ) {
		$input     = rest_sanitize_object( $input );
		$where     = isset( $input['where'] ) && is_array( $input['where'] ) ? $input['where'] : array();
		$id        = isset( $where['id'] ) ? (int) $where['id'] : 0;
		$permanent = ! empty( $input['permanent'] );

		$attachment = $this->get_attachment_or_error( $id );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}

		if ( ! current_user_can( 'delete_post', $attachment->ID ) ) {
			return new WP_Error(
				'media_cannot_delete',
				__( 'Sorry, you are not allowed to delete this media item.' ),
				array( 'status' => 403 )
			);
		}

		if ( ! $permanent && ( ! defined( 'MEDIA_TRASH' ) || ! MEDIA_TRASH ) ) {
			return new WP_Error(
				'media_trash_not_supported',
				__( 'The attachment does not support trashing. Set "permanent" to delete it.' ),
				array( 'status' => 501 )
			);
		}

		if ( $permanent ) {
			$deleted = wp_delete_attachment( $attachment->ID, true );
			if ( ! $deleted ) {
				return new WP_Error(
					'media_delete_failed',
					__( 'The attachment could not be deleted.' ),
					array( 'status' => 500 )
				);
			}

			return array( 'outcome' => 'deleted' );
		}

		if ( 'trash' === $attachment->post_status ) {
			return array( 'outcome' => 'trashed' );
		}

		$trashed = wp_trash_post( $attachment->ID );
		if ( ! $trashed ) {
			return new WP_Error(
				'media_delete_failed',
				__( 'The attachment could not be moved to the trash.' ),
				array( 'status' => 500 )
			);
		}

		return array( 'outcome' => 'trashed' );
	}

	/**
	 * Loads media administration helpers required for sideloading and metadata.
	 *
	 * @since 7.2.0
	 */
	private function load_media_admin_files(): void {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		if ( ! function_exists( 'wp_read_image_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	/**
	 * Downloads an image from a URL and prepares it for `media_handle_sideload()`.
	 *
	 * @since 7.2.0
	 *
	 * @param string $url Remote image URL.
	 * @return array{name: string, tmp_name: string}|WP_Error File array or error.
	 */
	private function prepare_url_upload( string $url ) {
		$tmp_name = download_url( $url );
		if ( is_wp_error( $tmp_name ) ) {
			return new WP_Error(
				'media_invalid_file',
				$tmp_name->get_error_message(),
				array(
					'status' => 400,
					'params' => array( 'data[file][url]' => $tmp_name->get_error_message() ),
				)
			);
		}

		$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
		$filename = sanitize_file_name( wp_basename( $path ) );
		if ( '' === $filename ) {
			$filename = 'upload';
		}

		$checked   = wp_check_filetype_and_ext( $tmp_name, $filename );
		$mime_type = is_string( $checked['type'] ) ? $checked['type'] : '';

		if ( '' === $mime_type || ! str_starts_with( $mime_type, 'image/' ) ) {
			@unlink( $tmp_name );
			$message = __( 'Only images can be uploaded from a URL.' );
			return new WP_Error(
				'media_invalid_file',
				$message,
				array(
					'status' => 400,
					'params' => array( 'data[file][url]' => $message ),
				)
			);
		}

		return array(
			'name'     => $filename,
			'tmp_name' => $tmp_name,
		);
	}

	/**
	 * Decodes base64 file data into a temporary file for `media_handle_sideload()`.
	 *
	 * @since 7.2.0
	 *
	 * @param string $base64   Base64-encoded file bytes.
	 * @param string $filename Target filename.
	 * @return array{name: string, tmp_name: string}|WP_Error File array or error.
	 */
	private function prepare_base64_upload( string $base64, string $filename ) {
		$bytes = base64_decode( $base64, true );
		if ( false === $bytes || '' === $bytes ) {
			return new WP_Error(
				'media_invalid_file',
				__( 'The base64 file payload is invalid.' ),
				array( 'status' => 400 )
			);
		}

		$sanitized_name = sanitize_file_name( $filename );
		if ( '' === $sanitized_name ) {
			return new WP_Error(
				'media_invalid_file',
				__( 'The filename is invalid.' ),
				array( 'status' => 400 )
			);
		}

		$tmp_name = wp_tempnam( $sanitized_name );
		if ( ! $tmp_name ) {
			return new WP_Error(
				'media_upload_failed',
				__( 'Could not create a temporary file for the upload.' ),
				array( 'status' => 500 )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $tmp_name, $bytes ) ) {
			@unlink( $tmp_name );
			return new WP_Error(
				'media_upload_failed',
				__( 'Could not write the temporary upload file.' ),
				array( 'status' => 500 )
			);
		}

		return array(
			'name'     => $sanitized_name,
			'tmp_name' => $tmp_name,
		);
	}

	/**
	 * Builds the post data array for `media_handle_sideload()`.
	 *
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $data      Upload input data.
	 * @param int                  $author_id Author user ID.
	 * @return array<string, mixed> Post data for attachment creation.
	 */
	private function build_attachment_post_data( array $data, int $author_id ): array {
		$post_data = array(
			'post_author' => $author_id,
		);

		if ( isset( $data['caption'] ) ) {
			$post_data['post_excerpt'] = (string) $data['caption'];
		}
		if ( isset( $data['description'] ) ) {
			$post_data['post_content'] = (string) $data['description'];
		}
		if ( isset( $data['slug'] ) ) {
			$post_data['post_name'] = sanitize_title( (string) $data['slug'] );
		}
		if ( isset( $data['date'] ) && is_array( $data['date'] ) ) {
			$dates = $this->resolve_date_values( $data['date'] );
			if ( ! is_wp_error( $dates ) ) {
				$post_data = array_merge( $post_data, $dates );
			}
		}

		return $post_data;
	}

	/**
	 * Resolves `date.site` / `date.gmt` into `post_date` and `post_date_gmt`.
	 *
	 * `site` wins when both are sent.
	 *
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $date_input Input `date` object.
	 * @return array{post_date: string, post_date_gmt: string}|WP_Error Resolved dates or error.
	 */
	private function resolve_date_values( array $date_input ) {
		if ( isset( $date_input['site'] ) ) {
			$parsed = rest_get_date_with_gmt( (string) $date_input['site'] );
		} elseif ( isset( $date_input['gmt'] ) ) {
			$parsed = rest_get_date_with_gmt( (string) $date_input['gmt'], true );
		} else {
			return new WP_Error(
				'media_invalid_date',
				__( 'A valid site or gmt date-time value is required.' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_array( $parsed ) ) {
			return new WP_Error(
				'media_invalid_date',
				__( 'Invalid date-time value.' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'post_date'     => $parsed[0],
			'post_date_gmt' => $parsed[1],
		);
	}

	/**
	 * Validates that a parent post exists and the current user can attach media to it.
	 *
	 * @since 7.2.0
	 *
	 * @param int $parent_id Parent post ID.
	 * @return true|WP_Error True if valid, or error.
	 */
	private function validate_parent_post( int $parent_id ) {
		$parent = get_post( $parent_id );
		if ( ! $parent instanceof WP_Post ) {
			return new WP_Error(
				'media_invalid_parent',
				__( 'The parent post does not exist.' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $parent->ID ) ) {
			return new WP_Error(
				'media_cannot_attach',
				__( 'Sorry, you are not allowed to attach media to this post.' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Validates that the current user can assign an attachment to the given author.
	 *
	 * @since 7.2.0
	 *
	 * @param int $author_id Target author user ID.
	 * @return true|WP_Error True if valid, or error.
	 */
	private function validate_author_assignment( int $author_id ) {
		$post_type_object = get_post_type_object( 'attachment' );
		if ( ! $post_type_object instanceof WP_Post_Type || ! current_user_can( $post_type_object->cap->edit_others_posts ) ) {
			return new WP_Error(
				'media_cannot_assign_author',
				__( 'Sorry, you are not allowed to assign media to another author.' ),
				array( 'status' => 403 )
			);
		}

		if ( ! get_userdata( $author_id ) ) {
			return new WP_Error(
				'media_invalid_author',
				__( 'The specified author does not exist.' ),
				array( 'status' => 404 )
			);
		}

		return true;
	}

	/**
	 * Retrieves an attachment post by ID or returns a `WP_Error`.
	 *
	 * @since 7.2.0
	 *
	 * @param int $id Attachment ID.
	 * @return WP_Post|WP_Error Attachment post or error.
	 */
	private function get_attachment_or_error( int $id ) {
		$attachment = get_post( $id );
		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type ) {
			return new WP_Error(
				'media_not_found',
				__( 'Media item not found.' ),
				array( 'status' => 404 )
			);
		}

		return $attachment;
	}

	/**
	 * Checks whether the current user can read an attachment.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Post $attachment Attachment post.
	 * @return bool True if readable, false otherwise.
	 */
	private function can_read_attachment( WP_Post $attachment ): bool {
		if ( 'inherit' === $attachment->post_status ) {
			if ( $attachment->post_parent > 0 ) {
				$parent = get_post( $attachment->post_parent );
				if ( $parent instanceof WP_Post ) {
					return is_post_publicly_viewable( $parent ) || current_user_can( 'read_post', $parent->ID );
				}
			}
			return true;
		}

		return current_user_can( 'read_post', $attachment->ID );
	}

	/**
	 * Resolves `where.media_type` and `where.mime_type` into `post_mime_type` query values.
	 *
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $where Query `where` filters.
	 * @return list<string>|null List of MIME types, or null when no MIME/media filter is set.
	 */
	private function resolve_mime_type_filters( array $where ): ?array {
		$media_types = isset( $where['media_type'] ) && is_array( $where['media_type'] ) ? $where['media_type'] : array();
		$mime_types  = isset( $where['mime_type'] ) && is_array( $where['mime_type'] ) ? $where['mime_type'] : array();

		if ( empty( $media_types ) && empty( $mime_types ) ) {
			return null;
		}

		$allowed_mimes_by_group = array();
		foreach ( get_allowed_mime_types() as $mime ) {
			$group                              = strstr( $mime, '/', true );
			$allowed_mimes_by_group[ $group ][] = $mime;
		}

		$from_media_type = array();
		foreach ( $media_types as $type ) {
			if ( isset( $allowed_mimes_by_group[ $type ] ) ) {
				$from_media_type = array_merge( $from_media_type, $allowed_mimes_by_group[ $type ] );
			}
		}

		if ( ! empty( $media_types ) && ! empty( $mime_types ) ) {
			return array_values( array_intersect( $from_media_type, $mime_types ) );
		}

		return ! empty( $media_types ) ? array_values( array_unique( $from_media_type ) ) : array_values( array_unique( $mime_types ) );
	}

	/**
	 * Normalizes requested output fields, falling back to `DEFAULT_FIELDS`.
	 *
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return list<string> Normalized field paths.
	 */
	private function normalize_fields( array $input ): array {
		if ( empty( $input['fields'] ) || ! is_array( $input['fields'] ) ) {
			return self::DEFAULT_FIELDS;
		}

		return array_values( array_unique( array_map( 'strval', $input['fields'] ) ) );
	}

	/**
	 * Checks whether a field path (or its parent object) was requested.
	 *
	 * @since 7.2.0
	 *
	 * @param string       $path   Field path.
	 * @param list<string> $fields Requested field paths.
	 * @return bool True if requested.
	 */
	private function is_field_requested( string $path, array $fields ): bool {
		if ( in_array( $path, $fields, true ) ) {
			return true;
		}

		$dot_pos = strpos( $path, '.' );
		if ( false !== $dot_pos ) {
			$parent = substr( $path, 0, $dot_pos );
			return in_array( $parent, $fields, true );
		}

		return false;
	}

	/**
	 * Formats an attachment post into the requested fields.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Post      $attachment Attachment post.
	 * @param list<string> $fields     Requested field paths.
	 * @return array<string, mixed> Formatted attachment object.
	 */
	private function format_attachment( WP_Post $attachment, array $fields ): array {
		$can_edit = current_user_can( 'edit_post', $attachment->ID );
		$item     = array(
			'id' => (int) $attachment->ID,
		);

		if ( $this->is_field_requested( 'status', $fields ) ) {
			$item['status'] = $attachment->post_status;
		}

		if ( $this->is_field_requested( 'date.site', $fields ) || $this->is_field_requested( 'date.gmt', $fields ) ) {
			$date = array();
			if ( $this->is_field_requested( 'date.site', $fields ) ) {
				$date['site'] = $this->format_date( $attachment->post_date );
			}
			if ( $this->is_field_requested( 'date.gmt', $fields ) ) {
				$date['gmt'] = $this->format_date( $attachment->post_date_gmt );
			}
			$item['date'] = $date;
		}

		if ( $this->is_field_requested( 'modified.site', $fields ) || $this->is_field_requested( 'modified.gmt', $fields ) ) {
			$modified = array();
			if ( $this->is_field_requested( 'modified.site', $fields ) ) {
				$modified['site'] = $this->format_date( $attachment->post_modified );
			}
			if ( $this->is_field_requested( 'modified.gmt', $fields ) ) {
				$modified['gmt'] = $this->format_date( $attachment->post_modified_gmt );
			}
			$item['modified'] = $modified;
		}

		if ( $this->is_field_requested( 'slug', $fields ) ) {
			$item['slug'] = $attachment->post_name;
		}

		if ( $this->is_field_requested( 'link', $fields ) ) {
			$item['link'] = (string) get_permalink( $attachment->ID );
		}

		if ( $this->is_field_requested( 'title.raw', $fields ) || $this->is_field_requested( 'title.rendered', $fields ) ) {
			$title = array();
			if ( $can_edit && $this->is_field_requested( 'title.raw', $fields ) ) {
				$title['raw'] = $attachment->post_title;
			}
			if ( $this->is_field_requested( 'title.rendered', $fields ) ) {
				$title['rendered'] = get_the_title( $attachment );
			}
			if ( ! empty( $title ) ) {
				$item['title'] = $title;
			}
		}

		if ( $this->is_field_requested( 'caption.raw', $fields ) || $this->is_field_requested( 'caption.rendered', $fields ) ) {
			$caption = array();
			if ( $can_edit && $this->is_field_requested( 'caption.raw', $fields ) ) {
				$caption['raw'] = $attachment->post_excerpt;
			}
			if ( $this->is_field_requested( 'caption.rendered', $fields ) ) {
				$caption['rendered'] = wpautop( $attachment->post_excerpt );
			}
			if ( ! empty( $caption ) ) {
				$item['caption'] = $caption;
			}
		}

		if ( $this->is_field_requested( 'description.raw', $fields ) || $this->is_field_requested( 'description.rendered', $fields ) ) {
			$description = array();
			if ( $can_edit && $this->is_field_requested( 'description.raw', $fields ) ) {
				$description['raw'] = $attachment->post_content;
			}
			if ( $this->is_field_requested( 'description.rendered', $fields ) ) {
				$description['rendered'] = (string) apply_filters( 'the_content', $attachment->post_content );
			}
			if ( ! empty( $description ) ) {
				$item['description'] = $description;
			}
		}

		if ( $this->is_field_requested( 'alt_text', $fields ) ) {
			$item['alt_text'] = (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
		}

		if ( $this->is_field_requested( 'media_type', $fields ) ) {
			$item['media_type'] = wp_attachment_is_image( $attachment->ID ) ? 'image' : 'file';
		}

		if ( $this->is_field_requested( 'mime_type', $fields ) ) {
			$item['mime_type'] = $attachment->post_mime_type;
		}

		if ( $this->is_field_requested( 'source_url', $fields ) ) {
			$item['source_url'] = (string) wp_get_attachment_url( $attachment->ID );
		}

		$attached_file = '';
		if ( $this->is_field_requested( 'filename', $fields ) || $this->is_field_requested( 'filesize', $fields ) ) {
			$attached_file = (string) get_attached_file( $attachment->ID );
		}

		if ( $this->is_field_requested( 'filename', $fields ) ) {
			$item['filename'] = '' !== $attached_file ? wp_basename( $attached_file ) : '';
		}

		$metadata = null;
		if (
			$this->is_field_requested( 'filesize', $fields ) ||
			$this->is_field_requested( 'media_details.width', $fields ) ||
			$this->is_field_requested( 'media_details.height', $fields ) ||
			$this->is_field_requested( 'media_details.length', $fields ) ||
			$this->is_field_requested( 'media_details.sizes', $fields )
		) {
			$raw_meta = wp_get_attachment_metadata( $attachment->ID );
			$metadata = is_array( $raw_meta ) ? $raw_meta : array();
		}

		if ( $this->is_field_requested( 'filesize', $fields ) ) {
			if ( isset( $metadata['filesize'] ) ) {
				$item['filesize'] = (int) $metadata['filesize'];
			} elseif ( '' !== $attached_file && file_exists( $attached_file ) ) {
				$item['filesize'] = (int) filesize( $attached_file );
			} else {
				$item['filesize'] = 0;
			}
		}

		if (
			$this->is_field_requested( 'media_details.width', $fields ) ||
			$this->is_field_requested( 'media_details.height', $fields ) ||
			$this->is_field_requested( 'media_details.length', $fields ) ||
			$this->is_field_requested( 'media_details.sizes', $fields )
		) {
			$media_details = array();
			if ( $this->is_field_requested( 'media_details.width', $fields ) && isset( $metadata['width'] ) ) {
				$media_details['width'] = (int) $metadata['width'];
			}
			if ( $this->is_field_requested( 'media_details.height', $fields ) && isset( $metadata['height'] ) ) {
				$media_details['height'] = (int) $metadata['height'];
			}
			if ( $this->is_field_requested( 'media_details.length', $fields ) && isset( $metadata['length'] ) ) {
				$media_details['length'] = (int) $metadata['length'];
			}
			if ( $this->is_field_requested( 'media_details.sizes', $fields ) ) {
				$sizes = array();
				if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
					foreach ( $metadata['sizes'] as $size => $size_data ) {
						$image_src = wp_get_attachment_image_src( $attachment->ID, $size );
						if ( ! $image_src ) {
							continue;
						}
						$sizes[ $size ] = array(
							'width'      => (int) ( $size_data['width'] ?? $image_src[1] ),
							'height'     => (int) ( $size_data['height'] ?? $image_src[2] ),
							'mime_type'  => (string) ( $size_data['mime-type'] ?? $attachment->post_mime_type ),
							'source_url' => (string) $image_src[0],
						);
					}
				}
				$media_details['sizes'] = $sizes;
			}
			$item['media_details'] = $media_details;
		}

		if ( $this->is_field_requested( 'post', $fields ) ) {
			$item['post'] = (int) $attachment->post_parent;
		}

		if (
			$this->is_field_requested( 'author.id', $fields ) ||
			$this->is_field_requested( 'author.name', $fields ) ||
			$this->is_field_requested( 'author.slug', $fields )
		) {
			$author_id = (int) $attachment->post_author;
			$author    = array();
			if ( $this->is_field_requested( 'author.id', $fields ) ) {
				$author['id'] = $author_id;
			}
			if ( $this->is_field_requested( 'author.name', $fields ) || $this->is_field_requested( 'author.slug', $fields ) ) {
				$user = $this->get_visible_author( $author_id );
				if ( $this->is_field_requested( 'author.name', $fields ) ) {
					$author['name'] = $user ? $user->display_name : null;
				}
				if ( $this->is_field_requested( 'author.slug', $fields ) ) {
					$author['slug'] = $user ? $user->user_nicename : null;
				}
			}
			$item['author'] = $author;
		}

		return $item;
	}

	/**
	 * Formats a MySQL datetime string as RFC 3339, or null when unset.
	 *
	 * @since 7.2.0
	 *
	 * @param string $mysql_date MySQL datetime string.
	 * @return string|null RFC 3339 string or null.
	 */
	private function format_date( string $mysql_date ): ?string {
		if ( '' === $mysql_date || '0000-00-00 00:00:00' === $mysql_date ) {
			return null;
		}

		return mysql_to_rfc3339( $mysql_date );
	}

	/**
	 * Returns the author user object if visible to the current user.
	 *
	 * @since 7.2.0
	 *
	 * @param int $user_id Author user ID.
	 * @return WP_User|null Visible user object, or null.
	 */
	private function get_visible_author( int $user_id ): ?WP_User {
		if ( $user_id <= 0 ) {
			return null;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			return null;
		}

		if ( ! isset( $this->visible_authors[ $user_id ] ) ) {
			$this->visible_authors[ $user_id ] = (
				get_current_user_id() === $user_id ||
				current_user_can( 'list_users' ) ||
				count_user_posts( $user_id, 'post', true ) > 0
			);
		}

		return $this->visible_authors[ $user_id ] ? $user : null;
	}

	/**
	 * Returns the `fields` input schema shared across media abilities.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> Fields schema.
	 */
	private function get_fields_schema(): array {
		return array(
			'type'        => 'array',
			'uniqueItems' => true,
			'items'       => array(
				'type' => 'string',
				'enum' => self::ALL_FIELDS,
			),
		);
	}

	/**
	 * Returns the input schema for `core/media-query`.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> Input schema.
	 */
	private function get_media_query_input_schema(): array {
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
						),
						'status'     => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'items'       => array(
								'type' => 'string',
								'enum' => array( 'inherit', 'private', 'trash' ),
							),
						),
						'media_type' => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'items'       => array(
								'type' => 'string',
								'enum' => array( 'application', 'audio', 'image', 'text', 'video' ),
							),
						),
						'mime_type'  => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'minItems'    => 1,
							'items'       => array(
								'type'      => 'string',
								'minLength' => 1,
							),
						),
						'post'       => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'author'     => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'minProperties'        => 1,
							'properties'           => array(
								'id'   => array(
									'type'    => 'integer',
									'minimum' => 1,
								),
								'slug' => array(
									'type'      => 'string',
									'minLength' => 1,
								),
							),
						),
					),
				),
				'fields'     => $this->get_fields_schema(),
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => self::MAX_PER_PAGE,
						),
					),
				),
			),
		);
	}

	/**
	 * Returns the output schema for `core/media-query`.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> Output schema.
	 */
	private function get_media_query_output_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'items', 'pagination' ),
			'properties'           => array(
				'items'      => array(
					'type'  => 'array',
					'items' => $this->get_media_item_schema(),
				),
				'pagination' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'total', 'total_pages' ),
					'properties'           => array(
						'total'       => array( 'type' => 'integer' ),
						'total_pages' => array( 'type' => 'integer' ),
					),
				),
			),
		);
	}

	/**
	 * Returns the input schema for `core/media-upload`.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> Input schema.
	 */
	private function get_media_upload_input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'data' ),
			'properties'           => array(
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'file' ),
					'properties'           => array(
						'file'        => array(
							'anyOf' => array(
								array(
									'type'                 => 'object',
									'additionalProperties' => false,
									'required'             => array( 'url' ),
									'properties'           => array(
										'url' => array(
											'type'   => 'string',
											'format' => 'uri',
										),
									),
								),
								array(
									'type'                 => 'object',
									'additionalProperties' => false,
									'required'             => array( 'base64', 'filename' ),
									'properties'           => array(
										'base64'   => array(
											'type'      => 'string',
											'minLength' => 1,
										),
										'filename' => array(
											'type'      => 'string',
											'minLength' => 1,
										),
									),
								),
							),
						),
						'title'       => array( 'type' => 'string' ),
						'caption'     => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'alt_text'    => array( 'type' => 'string' ),
						'slug'        => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'date'        => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'minProperties'        => 1,
							'properties'           => array(
								'site' => array(
									'type'   => 'string',
									'format' => 'date-time',
								),
								'gmt'  => array(
									'type'   => 'string',
									'format' => 'date-time',
								),
							),
						),
						'author'      => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'post'        => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
				),
				'fields' => $this->get_fields_schema(),
			),
		);
	}

	/**
	 * Returns the input schema for `core/media-update`.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> Input schema.
	 */
	private function get_media_update_input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where', 'data' ),
			'properties'           => array(
				'where'  => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'id' ),
					'properties'           => array(
						'id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
				),
				'data'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'minProperties'        => 1,
					'properties'           => array(
						'title'       => array( 'type' => 'string' ),
						'caption'     => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'alt_text'    => array( 'type' => 'string' ),
						'slug'        => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'date'        => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'minProperties'        => 1,
							'properties'           => array(
								'site' => array(
									'type'   => 'string',
									'format' => 'date-time',
								),
								'gmt'  => array(
									'type'   => 'string',
									'format' => 'date-time',
								),
							),
						),
						'author'      => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'post'        => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
					),
				),
				'fields' => $this->get_fields_schema(),
			),
		);
	}

	/**
	 * Returns the input schema for `core/media-delete`.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> Input schema.
	 */
	private function get_media_delete_input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'where' ),
			'properties'           => array(
				'where'     => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'id' ),
					'properties'           => array(
						'id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
				),
				'permanent' => array( 'type' => 'boolean' ),
			),
		);
	}

	/**
	 * Returns the output schema for a single formatted media item.
	 *
	 * @since 7.2.0
	 *
	 * @return array<string, mixed> Output item schema.
	 */
	private function get_media_item_schema(): array {
		$date_schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'site' => array(
					'type'   => array( 'string', 'null' ),
					'format' => 'date-time',
				),
				'gmt'  => array(
					'type'   => array( 'string', 'null' ),
					'format' => 'date-time',
				),
			),
		);

		$rendered_text_schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'raw'      => array( 'type' => 'string' ),
				'rendered' => array( 'type' => 'string' ),
			),
		);

		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id' ),
			'properties'           => array(
				'id'            => array( 'type' => 'integer' ),
				'status'        => array( 'type' => 'string' ),
				'date'          => $date_schema,
				'modified'      => $date_schema,
				'slug'          => array( 'type' => 'string' ),
				'link'          => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'title'         => $rendered_text_schema,
				'caption'       => $rendered_text_schema,
				'description'   => $rendered_text_schema,
				'alt_text'      => array( 'type' => 'string' ),
				'media_type'    => array( 'type' => 'string' ),
				'mime_type'     => array( 'type' => 'string' ),
				'source_url'    => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'filename'      => array( 'type' => 'string' ),
				'filesize'      => array( 'type' => 'integer' ),
				'media_details' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'width'  => array( 'type' => 'integer' ),
						'height' => array( 'type' => 'integer' ),
						'length' => array( 'type' => 'integer' ),
						'sizes'  => array(
							'type'                 => 'object',
							'additionalProperties' => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'required'             => array( 'width', 'height', 'mime_type', 'source_url' ),
								'properties'           => array(
									'width'      => array( 'type' => 'integer' ),
									'height'     => array( 'type' => 'integer' ),
									'mime_type'  => array( 'type' => 'string' ),
									'source_url' => array(
										'type'   => 'string',
										'format' => 'uri',
									),
								),
							),
						),
					),
				),
				'post'          => array( 'type' => 'integer' ),
				'author'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'id'   => array( 'type' => 'integer' ),
						'name' => array( 'type' => array( 'string', 'null' ) ),
						'slug' => array( 'type' => array( 'string', 'null' ) ),
					),
				),
			),
		);
	}
}
