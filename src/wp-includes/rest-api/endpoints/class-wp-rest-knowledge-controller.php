<?php
/**
 * REST API: WP_REST_Knowledge_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 7.2.0
 */

/**
 * Core controller used to access knowledge rows via the REST API.
 *
 * Knowledge is private-by-default storage. Reads require an authenticated user
 * with the post type's read capability, collection queries are scoped to the
 * rows the current user can read, callers without the publish capability may
 * only use the `private` status, and new rows default to the `private` status.
 *
 * @since 7.2.0
 *
 * @see WP_REST_Posts_Controller
 */
class WP_REST_Knowledge_Controller extends WP_REST_Posts_Controller {

	/**
	 * Checks if a given request has access to read knowledge rows.
	 *
	 * Knowledge is private storage, so the collection is available only to an
	 * authenticated user with the post type's read capability.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		$post_type = get_post_type_object( $this->post_type );
		if ( ! $post_type || ! current_user_can( $post_type->cap->read ) ) {
			return new WP_Error(
				'rest_cannot_read',
				__( 'Sorry, you are not allowed to view knowledge.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return parent::get_items_permissions_check( $request );
	}

	/**
	 * Determines the allowed query_vars for a get_items() response and prepares
	 * them for WP_Query.
	 *
	 * Scopes the collection to rows readable by the current user so that the
	 * total count and pagination headers reflect per-user visibility.
	 *
	 * @since 7.2.0
	 *
	 * @param array<string, mixed> $prepared_args Optional. Prepared WP_Query arguments. Default empty array.
	 * @param WP_REST_Request|null $request       Optional. Full details about the request.
	 * @return array<string, mixed> Items query arguments.
	 */
	protected function prepare_items_query( $prepared_args = array(), $request = null ) {
		$query_args         = parent::prepare_items_query( $prepared_args, $request );
		$query_args['perm'] = 'readable';

		return $query_args;
	}

	/**
	 * Checks if a knowledge row can be read.
	 *
	 * A row is readable only when the current user passes the `read_post`
	 * capability check, which accounts for the row's author and status.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Post $post Post object.
	 * @return bool Whether the post can be read.
	 */
	public function check_read_permission( $post ) {
		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return false;
		}

		return parent::check_read_permission( $post );
	}

	/**
	 * Determines validity and normalizes the given status parameter.
	 *
	 * Callers without the publish capability may only set the `private` status.
	 *
	 * @since 7.2.0
	 *
	 * @param string       $post_status The post status.
	 * @param WP_Post_Type $post_type   The post type object.
	 * @return string|WP_Error Post status or WP_Error if not allowed.
	 */
	protected function handle_status_param( $post_status, $post_type ) {
		if ( ! current_user_can( $post_type->cap->publish_posts ) ) {
			if ( 'private' !== $post_status ) {
				return new WP_Error(
					'rest_cannot_publish',
					__( 'Sorry, you are only allowed to set knowledge to a private status.' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}

			return $post_status;
		}

		return parent::handle_status_param( $post_status, $post_type );
	}

	/**
	 * Prepares a single knowledge row for create or update.
	 *
	 * New rows default to the `private` status when no status is supplied.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return stdClass|WP_Error Post object or WP_Error.
	 */
	protected function prepare_item_for_database( $request ) {
		if ( ! isset( $request['id'] ) && null === $request['status'] ) {
			$request->set_param( 'status', 'private' );
		}

		return parent::prepare_item_for_database( $request );
	}
}
