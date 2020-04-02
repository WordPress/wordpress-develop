<?php
/**
 * REST API: WP_REST_Network_Meta_Fields class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since Unknown
 */

/**
 * Core class to manage network meta via the REST API.
 *
 * @since Unknown
 *
 * @see WP_REST_Meta_Fields
 */
class WP_REST_Network_Meta_Fields extends WP_REST_Meta_Fields {

	/**
	 * Retrieves the object type for network meta.
	 *
	 * @since Unknown
	 *
	 * @return string The meta type.
	 */
	protected function get_meta_type() {
		return 'site';
	}

	/**
	 * Retrieves the type for register_rest_field() in the context of networks.
	 *
	 * @since Unknown
	 *
	 * @return string The REST field type.
	 */
	public function get_rest_field_type() {
		return 'site';
	}
}
