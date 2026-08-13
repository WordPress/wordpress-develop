<?php
/**
 * REST API: WP_REST_Notes_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 7.2.0
 */

/**
 * Core controller used to access editorial notes via the REST API.
 *
 * Notes are stored as `note` comments, so this controller extends the comments
 * controller and keeps the same underlying object type: the same comment meta,
 * the same `rest_prepare_comment` filter, and the same fields registered through
 * `register_rest_field( 'comment', ... )` all continue to apply. What changes is
 * the shape of the collection, which is modelled on how notes are used rather
 * than on how comments are:
 *
 * - Notes are always scoped to a post. `post` is required, and access is a
 *   single question: can the current user edit that post?
 * - The collection returns *threads*. Replies are nested under their parent in
 *   a `replies` array instead of being interleaved as sibling rows, so
 *   pagination cuts between threads and never orphans a reply from its parent.
 * - Replies are prepared in the same context as their parent, so a thread
 *   fetched with `context=edit` carries `content.raw` all the way down. The
 *   `_embed` route on `wp/v2/comments` can only return them in `view` context.
 * - `type` is fixed to `note` and `status` defaults to `all`, because a note is
 *   equally interesting whether it is open (`hold`) or resolved (`approved`).
 *
 * Threads are one level deep, matching the editor UI. A reply to a reply is
 * stored fine but is not nested any further than its top-level ancestor.
 *
 * @since 7.2.0
 *
 * @see WP_REST_Comments_Controller
 */
class WP_REST_Notes_Controller extends WP_REST_Comments_Controller {

	/**
	 * Constructor.
	 *
	 * @since 7.2.0
	 */
	public function __construct() {
		parent::__construct();

		$this->rest_base = 'notes';
	}

	/**
	 * Checks if a given request has access to read notes.
	 *
	 * Notes live alongside a post and are visible to everyone who can edit that
	 * post, so the check collapses to `edit_post` for every requested post.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_notes_not_logged_in',
				__( 'Sorry, you are not allowed to read notes.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$post_ids = array_filter( array_map( 'absint', (array) $request['post'] ) );

		if ( empty( $post_ids ) ) {
			return new WP_Error(
				'rest_notes_missing_post',
				__( 'Notes must be requested for at least one post.' ),
				array( 'status' => 400 )
			);
		}

		foreach ( $post_ids as $post_id ) {
			$check = $this->check_note_post_permission( $post_id );

			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}

		return true;
	}

	/**
	 * Retrieves a list of note threads.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		/*
		 * `type` and `parent` are not exposed as collection parameters, so the
		 * comments controller never maps them onto the comment query. They are
		 * pinned here instead: without an explicit `type`, WP_Comment_Query
		 * excludes notes outright.
		 */
		$scope_to_threads = static function ( $prepared_args ) {
			$prepared_args['type']   = 'note';
			$prepared_args['parent'] = 0;

			return $prepared_args;
		};

		add_filter( 'rest_comment_query', $scope_to_threads, PHP_INT_MAX );

		try {
			$response = parent::get_items( $request );
		} finally {
			remove_filter( 'rest_comment_query', $scope_to_threads, PHP_INT_MAX );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->attach_replies( $response, $request );
	}

	/**
	 * Retrieves a single note thread.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$response = parent::get_item( $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->attach_replies( $response, $request );
	}

	/**
	 * Checks if a given request has access to read a note.
	 *
	 * Reading a note in any context is the same permission as editing it: the
	 * comments controller's `moderate_comments` gate does not apply, because a
	 * note belongs to whoever is editing the post rather than to the site's
	 * comment moderators.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		$note = $this->get_comment( $request['id'] );

		if ( is_wp_error( $note ) ) {
			return $note;
		}

		if ( ! current_user_can( 'edit_comment', $note->comment_ID ) ) {
			return new WP_Error(
				'rest_cannot_read_notes',
				__( 'Sorry, you are not allowed to read notes for this post.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Creates a note.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		$request['type'] = 'note';

		return parent::create_item( $request );
	}

	/**
	 * Checks if a given request has access to create a note.
	 *
	 * A note is an editorial act on a post, so none of the public commenting
	 * rules apply: there is no anonymous path, no `comments_open` check, and a
	 * draft is exactly the kind of post that gets annotated.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to create items, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_notes_not_logged_in',
				__( 'Sorry, you are not allowed to create notes.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( isset( $request['author'] ) && get_current_user_id() !== (int) $request['author'] ) {
			return new WP_Error(
				'rest_note_invalid_author',
				/* translators: %s: Request parameter. */
				sprintf( __( "Sorry, you are not allowed to edit '%s' for notes." ), 'author' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$post = get_post( (int) $request['post'] );

		if ( ! $post ) {
			return new WP_Error(
				'rest_note_invalid_post_id',
				__( 'Sorry, you are not allowed to create a note without a post.' ),
				array( 'status' => 403 )
			);
		}

		if ( ! $this->check_post_type_supports_notes( $post->post_type ) ) {
			return new WP_Error(
				'rest_note_not_supported_post_type',
				__( 'Sorry, this post type does not support notes.' ),
				array( 'status' => 403 )
			);
		}

		if ( 'trash' === $post->post_status ) {
			return new WP_Error(
				'rest_note_trash_post',
				__( 'Sorry, you are not allowed to create a note on this post.' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'rest_cannot_create_note',
				__( 'Sorry, you are not allowed to create notes for this post.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Gets the note, if the ID is valid.
	 *
	 * Guards the single-note routes so `wp/v2/notes/<id>` cannot be used to read
	 * or edit an ordinary comment that happens to share the ID space.
	 *
	 * @since 7.2.0
	 *
	 * @param int $id Supplied ID.
	 * @return WP_Comment|WP_Error Comment object if the ID is a note, WP_Error object otherwise.
	 */
	protected function get_comment( $id ) {
		$comment = parent::get_comment( $id );

		if ( is_wp_error( $comment ) ) {
			return $comment;
		}

		if ( 'note' !== $comment->comment_type ) {
			return new WP_Error(
				'rest_note_invalid_id',
				__( 'Invalid note ID.' ),
				array( 'status' => 404 )
			);
		}

		return $comment;
	}

	/**
	 * Prepares links for the request.
	 *
	 * Drops the `children` link the comments controller builds. Replies already
	 * travel inside the response, and assembling that link costs a `COUNT` query
	 * per note, the single most expensive part of rendering a large thread list.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Comment $comment Comment object.
	 * @return array Links for the given note.
	 */
	protected function prepare_links( $comment ) {
		$links = parent::prepare_links( $comment );

		unset( $links['children'] );

		return $links;
	}

	/**
	 * Retrieves the note's schema, conforming to JSON Schema.
	 *
	 * @since 7.2.0
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		/*
		 * The schema title stays `comment`: a note is a comment record, so
		 * comment meta and anything registered through
		 * `register_rest_field( 'comment', ... )` must keep applying here.
		 */
		$schema = parent::get_item_schema();

		/*
		 * Notes are authored by logged-in users, so the anonymous commenter
		 * identity fields never carry a value, and a note has no permalink.
		 */
		unset(
			$schema['properties']['author_email'],
			$schema['properties']['author_ip'],
			$schema['properties']['author_url'],
			$schema['properties']['author_user_agent'],
			$schema['properties']['link']
		);

		$schema['properties']['reply_count'] = array(
			'description' => __( 'The number of replies in the thread.' ),
			'type'        => 'integer',
			'context'     => array( 'view', 'edit' ),
			'readonly'    => true,
		);

		$schema['properties']['replies'] = array(
			'description' => __( 'The replies in the thread, oldest first.' ),
			'type'        => 'array',
			'context'     => array( 'view', 'edit' ),
			'readonly'    => true,
			'items'       => array(
				'type' => 'object',
			),
		);

		return $schema;
	}

	/**
	 * Retrieves the query params for the notes collection.
	 *
	 * @since 7.2.0
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		// A note is always read by someone editing the post it belongs to.
		$query_params['context']['default'] = 'edit';

		/*
		 * The collection is threads-only and always `note` typed, so the
		 * parameters that would let a client ask for anything else are not
		 * exposed.
		 */
		unset(
			$query_params['type'],
			$query_params['parent'],
			$query_params['parent_exclude'],
			$query_params['author_email'],
			$query_params['password']
		);

		/*
		 * Dropping the default is what makes `required` bite: an empty array
		 * would otherwise satisfy the presence check.
		 */
		unset( $query_params['post']['default'] );
		$query_params['post']['required'] = true;

		/*
		 * Open and resolved notes are both interesting, so `approve` is not a
		 * useful default for a collection that models a review workflow.
		 */
		$query_params['status']['default'] = 'all';

		return $query_params;
	}

	/**
	 * Checks that a post exists, supports notes, and is editable by the current user.
	 *
	 * @since 7.2.0
	 *
	 * @param int $post_id Post ID.
	 * @return true|WP_Error True when notes on the post are readable, WP_Error object otherwise.
	 */
	protected function check_note_post_permission( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error(
				'rest_post_invalid_id',
				__( 'Invalid post ID.' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $this->check_post_type_supports_notes( $post->post_type ) ) {
			return new WP_Error(
				'rest_note_not_supported_post_type',
				__( 'Sorry, this post type does not support notes.' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'rest_cannot_read_notes',
				__( 'Sorry, you are not allowed to read notes for this post.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Determines whether a post type opts into notes.
	 *
	 * @since 7.2.0
	 *
	 * @param string $post_type Post type name.
	 * @return bool True when the post type's editor support declares notes.
	 */
	protected function check_post_type_supports_notes( $post_type ) {
		$supports = get_all_post_type_supports( $post_type );

		if ( ! isset( $supports['editor'] ) || ! is_array( $supports['editor'] ) ) {
			return false;
		}

		return array_any( $supports['editor'], fn( $item ) => ! empty( $item['notes'] ) );
	}

	/**
	 * Restricts the create route to notes.
	 *
	 * @since 7.2.0
	 *
	 * @return string[] Comment types accepted by the create route.
	 */
	protected function get_allowed_comment_types() {
		return array( 'note' );
	}

	/**
	 * Carries the resolution status into the content check.
	 *
	 * Resolving a note posts no text of its own, so the check needs the meta to
	 * tell an intentional empty note from an empty one.
	 *
	 * @since 7.2.0
	 *
	 * @param array           $prepared_comment Prepared comment data.
	 * @param WP_REST_Request $request          Full details about the request.
	 * @return array Prepared comment data for the content check.
	 */
	protected function prepare_comment_for_content_check( $prepared_comment, $request ) {
		if ( isset( $request['meta']['_wp_note_status'] ) ) {
			$prepared_comment['meta']['_wp_note_status'] = $request['meta']['_wp_note_status'];
		}

		return $prepared_comment;
	}

	/**
	 * Approves every note on creation.
	 *
	 * Notes are written by users who can already edit the post, so there is
	 * nothing for duplicate and flood control to protect against. `status` on a
	 * note means open or resolved, and is set separately.
	 *
	 * @since 7.2.0
	 *
	 * @param array $prepared_comment Prepared comment data.
	 * @return string The approval status.
	 */
	protected function determine_comment_approval( $prepared_comment ) {
		return '1';
	}

	/**
	 * Allows a note with no text when it records a resolution.
	 *
	 * @since 7.2.0
	 *
	 * @param array $prepared_comment Prepared comment data.
	 * @return bool True if the content is allowed, false otherwise.
	 */
	protected function check_is_comment_content_allowed( $prepared_comment ) {
		if (
			isset( $prepared_comment['meta']['_wp_note_status'] ) &&
			in_array( $prepared_comment['meta']['_wp_note_status'], array( 'resolved', 'reopen' ), true )
		) {
			return true;
		}

		return parent::check_is_comment_content_allowed( $prepared_comment );
	}

	/**
	 * Nests each thread's replies into the prepared response.
	 *
	 * Replies for the whole page are fetched in one query, so the cost does not
	 * grow with the number of threads on the page.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Response $response Prepared response holding one thread or a page of them.
	 * @param WP_REST_Request  $request  Full details about the request.
	 * @return WP_REST_Response Response with `replies` and `reply_count` filled in.
	 */
	protected function attach_replies( $response, $request ) {
		if ( $request->is_method( 'HEAD' ) ) {
			return $response;
		}

		$fields       = $this->get_fields_for_response( $request );
		$want_replies = rest_is_field_included( 'replies', $fields );
		$want_count   = rest_is_field_included( 'reply_count', $fields );

		if ( ! $want_replies && ! $want_count ) {
			return $response;
		}

		$data      = $response->get_data();
		$is_single = ! wp_is_numeric_array( $data );
		$threads   = $is_single ? array( $data ) : $data;

		$thread_ids = array();
		foreach ( $threads as $thread ) {
			/*
			 * `_fields` can omit the ID, and without it there is nothing to
			 * hang replies off of.
			 */
			if ( isset( $thread['id'] ) ) {
				$thread_ids[] = (int) $thread['id'];
			}
		}

		if ( empty( $thread_ids ) ) {
			return $response;
		}

		$replies_by_parent = $this->get_replies( $thread_ids, $request );

		foreach ( $threads as $index => $thread ) {
			if ( ! isset( $thread['id'] ) ) {
				continue;
			}

			$replies = isset( $replies_by_parent[ $thread['id'] ] ) ? $replies_by_parent[ $thread['id'] ] : array();

			if ( $want_replies ) {
				$threads[ $index ]['replies'] = $replies;
			}

			if ( $want_count ) {
				$threads[ $index ]['reply_count'] = count( $replies );
			}
		}

		$response->set_data( $is_single ? $threads[0] : $threads );

		return $response;
	}

	/**
	 * Fetches and prepares the replies belonging to a set of threads.
	 *
	 * @since 7.2.0
	 *
	 * @param int[]           $thread_ids Top-level note IDs.
	 * @param WP_REST_Request $request    Full details about the request.
	 * @return array Prepared reply arrays keyed by parent note ID, oldest first.
	 */
	protected function get_replies( $thread_ids, $request ) {
		$query = new WP_Comment_Query();

		$replies = $query->query(
			array(
				'parent__in'                => $thread_ids,
				'type'                      => 'note',
				'status'                    => 'all',
				'orderby'                   => 'comment_date_gmt',
				'order'                     => 'ASC',
				'number'                    => 0,
				'no_found_rows'             => true,
				'update_comment_post_cache' => true,
			)
		);

		$replies_by_parent = array();

		foreach ( $replies as $reply ) {
			if ( ! $this->check_read_permission( $reply, $request ) ) {
				continue;
			}

			$prepared = $this->prepare_item_for_response( $reply, $request );

			$replies_by_parent[ (int) $reply->comment_parent ][] = $this->prepare_response_for_collection( $prepared );
		}

		return $replies_by_parent;
	}
}
