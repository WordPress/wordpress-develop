<?php
/**
 * Unit tests covering WP_REST_Controller functionality
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 */
class WP_REST_Test_Controller extends WP_REST_Controller {
	/**
	 * Prepares the item for the REST response.
	 *
	 * @param mixed           $item    WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$context  = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$item     = $this->add_additional_fields_to_object( $item, $request );
		$item     = $this->filter_response_by_context( $item, $context );
		$response = rest_ensure_response( $item );
		return $response;
	}

	/**
	 * Get the item's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'type',
			'type'       => 'object',
			'properties' => array(
				'somestring'     => array(
					'type'        => 'string',
					'description' => 'A pretty string.',
					'context'     => array( 'view' ),
				),
				'someinteger'    => array(
					'type'    => 'integer',
					'context' => array( 'view' ),
				),
				'someboolean'    => array(
					'type'    => 'boolean',
					'context' => array( 'view' ),
				),
				'someurl'        => array(
					'type'    => 'string',
					'format'  => 'uri',
					'context' => array( 'view' ),
				),
				'somedate'       => array(
					'type'    => 'string',
					'format'  => 'date-time',
					'context' => array( 'view' ),
				),
				'someemail'      => array(
					'type'    => 'string',
					'format'  => 'email',
					'context' => array( 'view' ),
				),
				'somehex'        => array(
					'type'    => 'string',
					'format'  => 'hex-color',
					'context' => array( 'view' ),
				),
				'someuuid'       => array(
					'type'    => 'string',
					'format'  => 'uuid',
					'context' => array( 'view' ),
				),
				'someenum'       => array(
					'type'    => 'string',
					'enum'    => array( 'a', 'b', 'c' ),
					'context' => array( 'view' ),
				),
				'someargoptions' => array(
					'type'        => 'integer',
					'required'    => true,
					'arg_options' => array(
						'required'          => false,
						'sanitize_callback' => '__return_true',
					),
				),
				'somedefault'    => array(
					'type'    => 'string',
					'enum'    => array( 'a', 'b', 'c' ),
					'context' => array( 'view' ),
					'default' => 'a',
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

}
