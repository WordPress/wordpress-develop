<?php
/**
 * REST API: WP_REST_Site_Meta_Fields class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 7.2.0
 */

/**
 * Core class to manage site meta via the REST API.
 *
 * @since 7.2.0
 *
 * @see WP_REST_Meta_Fields
 */
class WP_REST_Site_Meta_Fields extends WP_REST_Meta_Fields {

	/**
	 * Retrieves the object type for site meta.
	 *
	 * Site meta lives in blogmeta. The `site` type would resolve to sitemeta,
	 * which holds network meta.
	 *
	 * @since 7.2.0
	 *
	 * @return string The meta type.
	 */
	protected function get_meta_type() {
		return 'blog';
	}

	/**
	 * Retrieves the object meta subtype.
	 *
	 * @since 7.2.0
	 *
	 * @return string '' There are no subtypes.
	 */
	protected function get_meta_subtype() {
		return '';
	}

	/**
	 * Retrieves the type for register_rest_field() in the context of sites.
	 *
	 * @since 7.2.0
	 *
	 * @return string The REST field type.
	 */
	public function get_rest_field_type() {
		return 'site';
	}
}
