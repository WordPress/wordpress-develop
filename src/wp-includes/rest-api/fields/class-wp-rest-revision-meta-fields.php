<?php
/**
 * REST API: WP_REST_Revision_Meta_Fields class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 6.4.0
 */

/**
 * Core class used to manage meta values for revisions via the REST API.
 *
 * @since 6.4.0
 *
 * @see WP_REST_Meta_Fields
 */
class WP_REST_Revision_Meta_Fields extends WP_REST_Meta_Fields {

	/**
	 * Revision type to register fields for.
	 *
	 * @since 6.4.0
	 * @var string
	 */
	protected $post_type;

	/**
	 * Constructor.
	 *
	 * @since 6.4.0
	 *
	 * @param string $post_type Revision type to register fields for.
	 */
	public function __construct( $post_type ) {
		$this->post_type = $post_type;
	}

	/**
	 * Retrieves the post meta type.
	 *
	 * @since 6.4.0
	 *
	 * @return string The meta type.
	 */
	protected function get_meta_type() {
		return 'revision';
	}

	/**
	 * Retrieves the post meta subtype.
	 *
	 * @since 6.4.0
	 *
	 * @return string Subtype for the meta type, or empty string if no specific subtype.
	 */
	protected function get_meta_subtype() {
		return $this->post_type;
	}

	/**
	 * Retrieves the type for register_rest_field().
	 *
	 * @since 6.4.0
	 *
	 * @see register_rest_field()
	 *
	 * @return string The REST field type.
	 */
	public function get_rest_field_type() {
		return $this->post_type;
	}

	/**
	 * Retrieves the meta field value.
	 *
	 * @since 4.7.0
	 *
	 * @param int             $object_id Object ID to fetch meta for.
	 * @param WP_REST_Request $request   Full details about the request.
	 * @return array Array containing the meta values keyed by name.
	 */
	public function get_value( $object_id, $request ) {
		$data = get_post_meta( $object_id );
		return $data;
	}

	/**
	 * Retrieves raw metadata value for the specified object.
	 *
	 * @since 5.5.0
	 *
	 * @param string $meta_type Type of object metadata is for. Accepts 'post', 'comment', 'term', 'user',
	 *                          or any other object type with an associated meta table.
	 * @param int    $object_id ID of the object metadata is for.
	 * @param string $meta_key  Optional. Metadata key. If not specified, retrieve all metadata for
	 *                          the specified object. Default empty string.
	 * @param bool   $single    Optional. If true, return only the first value of the specified `$meta_key`.
	 *                          This parameter has no effect if `$meta_key` is not specified. Default false.
	 * @return mixed An array of values if `$single` is false.
	 *               The value of the meta field if `$single` is true.
	 *               False for an invalid `$object_id` (non-numeric, zero, or negative value),
	 *               or if `$meta_type` is not specified.
	 *               Null if the value does not exist.
	 */
	function get_metadata_raw( $meta_type, $object_id, $meta_key = '', $single = false ) {
		$data = get_post_meta( $object_id, $request );
		return $data;
	}


}
