<?php

/**
 * Unit test factory for comments.
 *
 * Note: The below @method notations are defined solely for the benefit of IDEs,
 * as a way to indicate expected return values from the given factory methods.
 *
 * @method int|WP_Error        create( $args = array(), $generation_definitions = null )
 * @method WP_Comment|WP_Error create_and_get( $args = array(), $generation_definitions = null )
 * @method (int|WP_Error)[]    create_many( $count, $args = array(), $generation_definitions = null )
 */
class WP_UnitTest_Factory_For_Comment extends WP_UnitTest_Factory_For_Thing {

	public function __construct( $factory = null ) {
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'comment_author'     => new WP_UnitTest_Generator_Sequence( 'Commenter %s' ),
			'comment_author_url' => new WP_UnitTest_Generator_Sequence( 'http://example.com/%s/' ),
			'comment_approved'   => 1,
			'comment_content'    => 'This is a comment',
		);
	}

	/**
	 * Inserts a comment.
	 *
	 * @since UT (3.7.0)
	 * @since 6.2.0 Returns a WP_Error object on failure.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param array $args The comment details.
	 *
	 * @return int|WP_Error The comment ID on success, WP_Error object on failure.
	 */
	public function create_object( $args ) {
		global $wpdb;

		$comment_id = wp_insert_comment( $this->addslashes_deep( $args ) );

		if ( false === $comment_id ) {
			return new WP_Error(
				'db_insert_error',
				__( 'Could not insert comment into the database.' ),
				$wpdb->last_error
			);
		}

		return $comment_id;
	}

	/**
	 * Updates a comment.
	 *
	 * @since UT (3.7.0)
	 * @since 6.2.0 Returns a WP_Error object on failure.
	 *
	 * @param int   $comment_id The comment ID.
	 * @param array $fields     The comment details.
	 *
	 * @return int|WP_Error The value 1 if the comment was updated, 0 if not updated.
	 *                      WP_Error object on failure.
	 */
	public function update_object( $comment_id, $fields ) {
		$fields['comment_ID'] = $comment_id;
		return wp_update_comment( $this->addslashes_deep( $fields ), true );
	}

	/**
	 * Creates multiple comments on a given post.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param int   $post_id                ID of the post to create comments for.
	 * @param int   $count                  Total amount of comments to create.
	 * @param array $args                   The comment details.
	 * @param null  $generation_definitions Default values.
	 *
	 * @return int[] Array with the comment IDs.
	 */
	public function create_post_comments( $post_id, $count = 1, $args = array(), $generation_definitions = null ) {
		$args['comment_post_ID'] = $post_id;
		return $this->create_many( $count, $args, $generation_definitions );
	}

	/**
	 * Retrieves a comment by a given ID.
	 *
	 * @since UT (3.7.0)
	 *
	 * @param int $comment_id ID of the comment to retrieve.
	 *
	 * @return WP_Comment|null WP_Comment object on success, null on failure.
	 */
	public function get_object_by_id( $comment_id ) {
		return get_comment( $comment_id );
	}
}
