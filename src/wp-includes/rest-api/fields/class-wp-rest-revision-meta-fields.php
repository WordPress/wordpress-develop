<?php
/**
 * REST API: WP_REST_Revision_Meta_Fields class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 7.2.0
 */

/**
 * Core class used to read the meta values of post revisions via the REST API.
 *
 * Only the meta keys that are revisioned for the parent post type are exposed,
 * because those are the only values that are stored on a revision. Any other
 * meta key registered for the post type would be read from the revision ID,
 * find nothing, and be reported with its default value instead.
 *
 * @since 7.2.0
 *
 * @see WP_REST_Post_Meta_Fields
 * @see wp_post_revision_meta_keys()
 */
class WP_REST_Revision_Meta_Fields extends WP_REST_Post_Meta_Fields {

	/**
	 * Retrieves the registered meta fields that are revisioned for the parent post type.
	 *
	 * @since 7.2.0
	 *
	 * @return array Registered fields, limited to the revisioned meta keys.
	 */
	protected function get_registered_fields() {
		return array_intersect_key(
			parent::get_registered_fields(),
			array_flip( wp_post_revision_meta_keys( $this->post_type ) )
		);
	}
}
