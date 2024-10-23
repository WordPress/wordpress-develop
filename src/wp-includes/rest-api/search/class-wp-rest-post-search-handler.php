<?php
/**
 * REST API: WP_REST_Post_Search_Handler class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.0.0
 */

/**
 * Core class representing a search handler for posts in the REST API.
 *
 * @since 5.0.0
 *
 * @see WP_REST_Search_Handler
 */
class WP_REST_Post_Search_Handler extends WP_REST_Search_Handler {

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 */
	public function __construct() {
		$this->type = 'post';

		// Support all public post types except attachments.
		$this->subtypes = array_diff(
			array_values(
				get_post_types(
					array(
						'public'       => true,
						'show_in_rest' => true,
					),
					'names'
				)
			),
			array( 'attachment' )
		);
	}

	/**
	 * Searches posts for a given search request.
	 *
	 * @since 5.0.0
	 *
	 * @param WP_REST_Request $request Full REST request.
	 * @return array {
	 *     Associative array containing found IDs and total count for the matching search results.
	 *
	 *     @type int[] $ids   Array containing the matching post IDs.
	 *     @type int   $total Total count for the matching search results.
	 * }
	 */
	public function search_items( WP_REST_Request $request ) {

		// Get the post types to search for the current request.
		$post_types = $request[ WP_REST_Search_Controller::PROP_SUBTYPE ];
		if ( in_array( WP_REST_Search_Controller::TYPE_ANY, $post_types, true ) ) {
			$post_types = $this->subtypes;
		}

		$query_args = array(
			'post_type'           => $post_types,
			'post_status'         => 'publish',
			'paged'               => (int) $request['page'],
			'posts_per_page'      => (int) $request['per_page'],
			'ignore_sticky_posts' => true,
		);

		if ( ! empty( $request['search'] ) ) {
			$query_args['s'] = $request['search'];
		}

		if ( ! empty( $request['exclude'] ) ) {
			$query_args['post__not_in'] = $request['exclude'];
		}

		if ( ! empty( $request['include'] ) ) {
			$query_args['post__in'] = $request['include'];
		}

		/**
		 * Filters the query arguments for a REST API post search request.
		 *
		 * Enables adding extra arguments or setting defaults for a post search request.
		 *
		 * @since 5.1.0
		 *
		 * @param array           $query_args Key value array of query var to query value.
		 * @param WP_REST_Request $request    The request used.
		 */
		$query_args = apply_filters( 'rest_post_search_query', $query_args, $request );

		$query = new WP_Query();
		$posts = $query->query( $query_args );
		// Querying the whole post object will warm the object cache, avoiding an extra query per result.
		$found_ids = wp_list_pluck( $posts, 'ID' );
		$total     = $query->found_posts;

		return array(
			self::RESULT_IDS   => $found_ids,
			self::RESULT_TOTAL => $total,
		);
	}

	/**
	 * Prepares the search result for a given post ID.
	 *
	 * @since 5.0.0
	 *
	 * @param int   $id     Post ID.
	 * @param array $fields Fields to include for the post.
	 * @return array {
	 *     Associative array containing fields for the post based on the `$fields` parameter.
	 *
	 *     @type int    $id    Optional. Post ID.
	 *     @type string $title Optional. Post title.
	 *     @type string $url   Optional. Post permalink URL.
	 *     @type string $type  Optional. Post type.
	 * }
	 */
	public function prepare_item( $id, array $fields ) {
		$post = get_post( $id );

		$data = array();

		if ( in_array( WP_REST_Search_Controller::PROP_ID, $fields, true ) ) {
			$data[ WP_REST_Search_Controller::PROP_ID ] = (int) $post->ID;
		}

		if ( in_array( WP_REST_Search_Controller::PROP_TITLE, $fields, true ) ) {
			if ( post_type_supports( $post->post_type, 'title' ) ) {
				add_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );
				add_filter( 'private_title_format', array( $this, 'protected_title_format' ) );
				$data[ WP_REST_Search_Controller::PROP_TITLE ] = get_the_title( $post->ID );
				remove_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );
				remove_filter( 'private_title_format', array( $this, 'protected_title_format' ) );
			} else {
				$data[ WP_REST_Search_Controller::PROP_TITLE ] = '';
			}
		}

		if ( in_array( WP_REST_Search_Controller::PROP_URL, $fields, true ) ) {
			$data[ WP_REST_Search_Controller::PROP_URL ] = get_permalink( $post->ID );
		}

		if ( in_array( WP_REST_Search_Controller::PROP_TYPE, $fields, true ) ) {
			$data[ WP_REST_Search_Controller::PROP_TYPE ] = $this->type;
		}

		if ( in_array( WP_REST_Search_Controller::PROP_SUBTYPE, $fields, true ) ) {
			$data[ WP_REST_Search_Controller::PROP_SUBTYPE ] = $post->post_type;
		}

		if ( in_array( WP_REST_Search_Controller::PROP_LABEL, $fields, true ) ) {
			$ptype                                         = get_post_type_object( $post->post_type );
			$label                                         = $ptype ? $ptype->labels->singular_name : $post->post_type;
			$data[ WP_REST_Search_Controller::PROP_LABEL ] = $label;
		}

		return $data;
	}

	/**
	 * Prepares links for the search result of a given ID.
	 *
	 * @since 5.0.0
	 *
	 * @param int $id Item ID.
	 * @return array Links for the given item.
	 */
	public function prepare_item_links( $id ) {
		$post = get_post( $id );

		$links = array();

		$item_route = rest_get_route_for_post( $post );
		if ( ! empty( $item_route ) ) {
			$links['self'] = array(
				'href'       => rest_url( $item_route ),
				'embeddable' => true,
			);
		}

		$links['about'] = array(
			'href' => rest_url( 'wp/v2/types/' . $post->post_type ),
		);

		return $links;
	}

	/**
	 * Overwrites the default protected and private title format.
	 *
	 * By default, WordPress will show password protected or private posts with a title of
	 * "Protected: %s" or "Private: %s", as the REST API communicates the status of a post
	 * in a machine-readable format, we remove the prefix.
	 *
	 * @since 5.0.0
	 *
	 * @return string Title format.
	 */
	public function protected_title_format() {
		return '%s';
	}

	/**
	 * Attempts to detect the route to access a single item.
	 *
	 * @since 5.0.0
	 * @deprecated 5.5.0 Use rest_get_route_for_post()
	 * @see rest_get_route_for_post()
	 *
	 * @param WP_Post $post Post object.
	 * @return string REST route relative to the REST base URI, or empty string if unknown.
	 */
	protected function detect_rest_item_route( $post ) {
		_deprecated_function( __METHOD__, '5.5.0', 'rest_get_route_for_post()' );

		return rest_get_route_for_post( $post );
	}
}
