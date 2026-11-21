<?php

/**
 * Unit test factory for comments.
 *
 * Note: The below @method notation is defined solely for the benefit of IDEs,
 * as a way to indicate the expected return value from the given factory method.
 *
 * @method WP_Comment create_and_get( $args = array(), $generation_definitions = null )
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
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param array<string, mixed> $args The comment details.
	 * @return positive-int The comment ID.
	 * @throws WP_UnitTest_Factory_Exception When the comment could not be created.
	 */
	public function create_object( $args ) {
		global $wpdb;

		$comment_id = wp_insert_comment( $this->addslashes_deep( $args ) );

		if ( false === $comment_id ) {
			$comment_id = new WP_Error(
				'db_insert_error',
				__( 'Could not insert comment into the database.' ),
				$wpdb->last_error
			);
		}

		$this->assert_valid_object_id( $comment_id, 'Unable to create the comment' );

		return $comment_id;
	}

	/**
	 * Updates a comment.
	 *
	 * @since UT (3.7.0)
	 * @since 6.2.0 Returns a WP_Error object on failure.
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param int                  $comment_id The comment ID.
	 * @param array<string, mixed> $fields     The comment details.
	 * @return positive-int The comment ID.
	 * @throws WP_UnitTest_Factory_Exception When the comment could not be updated.
	 */
	public function update_object( $comment_id, $fields ) {
		$fields['comment_ID'] = $comment_id;

		$result = wp_update_comment( $this->addslashes_deep( $fields ), true );

		// wp_update_comment() reports the number of affected rows, which is 0 when the values
		// written match what the row already held. Only a WP_Error means the update failed.
		$outcome = is_wp_error( $result ) ? $result : $comment_id;

		$this->assert_valid_object_id( $outcome, 'Unable to update the comment' );

		return $comment_id;
	}

	/**
	 * Creates multiple comments on a given post.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 Throws an exception instead of including a WP_Error object in the result.
	 *
	 * @param int                       $post_id                ID of the post to create comments for.
	 * @param int                       $count                  Total amount of comments to create.
	 * @param array<string, mixed>      $args                   The comment details.
	 * @param array<string, mixed>|null $generation_definitions Default values.
	 * @return positive-int[] Array with the comment IDs.
	 * @throws WP_UnitTest_Factory_Exception When one of the comments could not be created.
	 */
	public function create_post_comments( $post_id, $count = 1, $args = array(), $generation_definitions = null ) {
		$args['comment_post_ID'] = $post_id;
		return $this->create_many( $count, $args, $generation_definitions );
	}

	/**
	 * Retrieves a comment by a given ID.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 Throws an exception instead of returning null when the object cannot be retrieved.
	 *
	 * @param int $comment_id ID of the comment to retrieve.
	 * @return WP_Comment The comment object.
	 * @throws WP_UnitTest_Factory_Exception When the comment could not be retrieved.
	 */
	public function get_object_by_id( $comment_id ) {
		$comment = get_comment( $comment_id );

		$this->assert_valid_object( $comment, $comment_id, WP_Comment::class );

		return $comment;
	}
}
