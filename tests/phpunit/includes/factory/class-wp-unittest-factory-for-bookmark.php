<?php

/**
 * Factory for creating fixtures for the deprecated Links/Bookmarks API.
 *
 * @since 4.6.0
 */
class WP_UnitTest_Factory_For_Bookmark extends WP_UnitTest_Factory_For_Thing {

	public function __construct( $factory = null ) {
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'link_name' => new WP_UnitTest_Generator_Sequence( 'Bookmark name %s' ),
			'link_url'  => new WP_UnitTest_Generator_Sequence( 'Bookmark URL %s' ),
		);
	}

	/**
	 * Creates a link object.
	 *
	 * @since 4.6.0
	 * @since 6.2.0 Returns a WP_Error object on failure.
	 *
	 * @param array $args Arguments for the link object.
	 * @return int|WP_Error The link ID on success, WP_Error object on failure.
	 */
	public function create_object( $args ) {
		return wp_insert_link( $args, true );
	}

	/**
	 * Updates a link object.
	 *
	 * @since 4.6.0
	 * @since 6.2.0 Returns a WP_Error object on failure.
	 *
	 * @param int   $link_id ID of the link to update.
	 * @param array $fields  The fields to update.
	 * @return int|WP_Error The link ID on success, WP_Error object on failure.
	 */
	public function update_object( $link_id, $fields ) {
		$fields['link_id'] = $link_id;

		$result = wp_update_link( $fields );

		if ( 0 === $result ) {
			return new WP_Error( 'link_update_error', __( 'Could not update link.' ) );
		}

		return $result;
	}

	/**
	 * Retrieves a link by a given ID.
	 *
	 * @since 4.6.0
	 * @since 7.2.0 Throws an exception instead of returning null when the object
	 *              cannot be retrieved.
	 *
	 * @param int $link_id ID of the link to retrieve.
	 * @return stdClass The link object.
	 * @throws WP_UnitTest_Factory_Exception When the link could not be retrieved.
	 */
	public function get_object_by_id( $link_id ): stdClass {
		$link = get_bookmark( $link_id );

		$this->assert_valid_object( $link, $link_id, stdClass::class );

		return $link;
	}
}
