<?php
/**
 * REST API: WP_REST_Settings_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 4.7.0
 */

/**
 * Core class used to manage a site's settings via the REST API.
 *
 * @since 4.7.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Settings_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 */
	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'settings';
	}

	/**
	 * Registers the routes for the site's settings.
	 *
	 * @since 4.7.0
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'args'                => array(),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Checks if a given request has access to read and manage settings.
	 *
	 * @since 4.7.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool True if the request has read access for the item, otherwise false.
	 */
	public function get_item_permissions_check( $request ) {
		return wp_current_user_can_manage_settings();
	}

	/**
	 * Retrieves the settings.
	 *
	 * @since 4.7.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error Array on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		return wp_get_settings_values();
	}

	/**
	 * Prepares a value for output based off a schema array.
	 *
	 * @since 4.7.0
	 *
	 * @param mixed $value  Value to prepare.
	 * @param array $schema Schema to match.
	 * @return mixed The prepared value.
	 */
	protected function prepare_value( $value, $schema ) {
		return wp_prepare_setting_value( $value, $schema );
	}

	/**
	 * Updates settings for the settings object.
	 *
	 * @since 4.7.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error Array on success, or error object on failure.
	 */
	public function update_item( $request ) {
		return wp_update_settings_values( $request->get_params() );
	}

	/**
	 * Retrieves all of the registered options for the Settings API.
	 *
	 * @since 4.7.0
	 *
	 * @return array Array of registered options.
	 */
	protected function get_registered_options() {
		return wp_get_registered_setting_options();
	}

	/**
	 * Retrieves the site setting schema, conforming to JSON Schema.
	 *
	 * @since 4.7.0
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$options = $this->get_registered_options();

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'settings',
			'type'       => 'object',
			'properties' => array(),
		);

		foreach ( $options as $option_name => $option ) {
			$schema['properties'][ $option_name ]                = $option['schema'];
			$schema['properties'][ $option_name ]['arg_options'] = array(
				'sanitize_callback' => array( $this, 'sanitize_callback' ),
			);
		}

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Custom sanitize callback used for all options to allow the use of 'null'.
	 *
	 * By default, the schema of settings will throw an error if a value is set to
	 * `null` as it's not a valid value for something like "type => string". We
	 * provide a wrapper sanitizer to allow the use of `null`.
	 *
	 * @since 4.7.0
	 *
	 * @param mixed           $value   The value for the setting.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $param   The parameter name.
	 * @return mixed|WP_Error
	 */
	public function sanitize_callback( $value, $request, $param ) {
		if ( is_null( $value ) ) {
			return $value;
		}

		return rest_parse_request_arg( $value, $request, $param );
	}

	/**
	 * Recursively add additionalProperties = false to all objects in a schema
	 * if no additionalProperties setting is specified.
	 *
	 * This is needed to restrict properties of objects in settings values to only
	 * registered items, as the REST API will allow additional properties by
	 * default.
	 *
	 * @since 4.9.0
	 * @deprecated 6.1.0 Use {@see rest_default_additional_properties_to_false()} instead.
	 *
	 * @param array $schema The schema array.
	 * @return array
	 */
	protected function set_additional_properties_to_false( $schema ) {
		_deprecated_function( __METHOD__, '6.1.0', 'rest_default_additional_properties_to_false()' );

		return rest_default_additional_properties_to_false( $schema );
	}
}
