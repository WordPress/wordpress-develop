<?php

/**
 * Unit test factory for posts.
 *
 * Note: The below @method notation is defined solely for the benefit of IDEs,
 * as a way to indicate the expected return value from the given factory method.
 *
 * @method WP_Post create_and_get( $args = array(), $generation_definitions = null )
 */
class WP_UnitTest_Factory_For_Post extends WP_UnitTest_Factory_For_Thing {

	public function __construct( $factory = null ) {
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'post_status'  => 'publish',
			'post_title'   => new WP_UnitTest_Generator_Sequence( 'Post title %s' ),
			'post_content' => new WP_UnitTest_Generator_Sequence( 'Post content %s' ),
			'post_excerpt' => new WP_UnitTest_Generator_Sequence( 'Post excerpt %s' ),
			'post_type'    => 'post',
		);
	}

	/**
	 * Creates a post object.
	 *
	 * @since UT (3.7.0)
	 * @since 6.2.0 Returns a WP_Error object on failure.
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param array<string, mixed> $args Array with elements for the post.
	 * @return positive-int The post ID.
	 * @throws WP_UnitTest_Factory_Exception When the post could not be created.
	 */
	public function create_object( $args ) {
		$post_id = wp_insert_post( $args, true );

		$this->assert_valid_object_id( $post_id, 'Unable to create the post' );

		return $post_id;
	}

	/**
	 * Updates an existing post object.
	 *
	 * @since UT (3.7.0)
	 * @since 6.2.0 Returns a WP_Error object on failure.
	 * @since 7.2.0 Throws an exception instead of returning a WP_Error object on failure.
	 *
	 * @param int                  $post_id ID of the post to update.
	 * @param array<string, mixed> $fields  Post data.
	 * @return positive-int The post ID.
	 * @throws WP_UnitTest_Factory_Exception When the post could not be updated.
	 */
	public function update_object( $post_id, $fields ) {
		$fields['ID'] = $post_id;

		$updated_id = wp_update_post( $fields, true );

		$this->assert_valid_object_id( $updated_id, 'Unable to update the post' );

		return $updated_id;
	}

	/**
	 * Retrieves a post by a given ID.
	 *
	 * @since UT (3.7.0)
	 * @since 7.2.0 Throws an exception instead of returning null when the object cannot be retrieved.
	 *
	 * @param int $post_id ID of the post to retrieve.
	 * @return WP_Post The post object.
	 * @throws WP_UnitTest_Factory_Exception When the post could not be retrieved.
	 */
	public function get_object_by_id( $post_id ) {
		$post = get_post( $post_id );

		$this->assert_valid_object( $post, $post_id, WP_Post::class );

		return $post;
	}
}
