<?php
/**
 * WP_REST_Navigation_Controller class
 *
 * REST Controller to create/fetch Navigation custom post types.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 6.3.0
 */

/**
 * REST Controller to fetch Navigation custom post types.
 *
 * @since 6.3.0
 */
class WP_REST_Navigation_Controller extends WP_REST_Posts_Controller {
	/**
	 * Retrieves the post's schema, conforming to JSON Schema.
	 *
	 * @since 6.3.0
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = parent::get_item_schema();

		$schema['title']            = 'navigation-fallback';
		$schema['properties']['id'] = array(
			'description' => __( 'Unique identifier for the object.' ),
			'type'        => 'integer',
			'context'     => array( 'view', 'edit' ),
			'readonly'    => true,
		);

		// Expose top level fields.
		$schema['properties']['status']['context']  = array_merge( $schema['properties']['status']['context'], array( 'embed' ) );
		$schema['properties']['content']['context'] = array_merge( $schema['properties']['content']['context'], array( 'embed' ) );

		/*
		 * Exposes sub properties of content field.
		 * These sub properties aren't exposed by the posts controller by default,
		 * for requests where context is `embed`.
		 *
		 * @see WP_REST_Posts_Controller::get_item_schema()
		 */
		$schema['properties']['content']['properties']['raw']['context']           = array_merge( $schema['properties']['content']['properties']['raw']['context'], array( 'embed' ) );
		$schema['properties']['content']['properties']['rendered']['context']      = array_merge( $schema['properties']['content']['properties']['rendered']['context'], array( 'embed' ) );
		$schema['properties']['content']['properties']['block_version']['context'] = array_merge( $schema['properties']['content']['properties']['block_version']['context'], array( 'embed' ) );

		/*
		 * Exposes sub properties of title field.
		 * These sub properties aren't exposed by the posts controller by default,
		 * for requests where context is `embed`.
		 *
		 * @see WP_REST_Posts_Controller::get_item_schema()
		 */
		$schema['properties']['title']['properties']['raw']['context'] = array_merge( $schema['properties']['title']['properties']['raw']['context'], array( 'embed' ) );

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
