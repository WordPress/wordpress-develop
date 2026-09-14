<?php
/**
 * JSON Schema functions.
 *
 * @package WordPress
 * @subpackage JSON_Schema
 */

/**
 * @group json-schema
 */
class Tests_JSON_Schema extends WP_UnitTestCase {

	/**
	 * @ticket 64955
	 */
	public function test_wp_get_json_schema_allowed_keywords_uses_rest_keywords_by_default() {
		$this->assertSame( rest_get_allowed_schema_keywords(), wp_get_json_schema_allowed_keywords() );
		$this->assertSame( rest_get_allowed_schema_keywords(), wp_get_json_schema_allowed_keywords( 'rest-api' ) );
		$this->assertSame( rest_get_allowed_schema_keywords(), wp_get_json_schema_allowed_keywords( 'unknown-context' ) );
	}

	/**
	 * @ticket 64955
	 */
	public function test_wp_get_json_schema_allowed_keywords_includes_draft_04_keywords() {
		$keywords = wp_get_json_schema_allowed_keywords( 'draft-04' );

		// Keywords the draft-04 profile adds on top of the REST keyword set.
		foreach ( array( '$schema', 'id', '$ref', 'required', 'allOf', 'not', 'definitions', 'dependencies', 'additionalItems' ) as $keyword ) {
			$this->assertContains( $keyword, $keywords );
		}

		// 'type' is a base REST keyword, not a draft-04 addition. Checking it
		// confirms the draft-04 profile is a superset that keeps the REST keywords.
		$this->assertContains( 'type', $keywords );
	}

	/**
	 * @ticket 64955
	 */
	public function test_wp_get_json_schema_allowed_keywords_filter_receives_schema_profile() {
		$schema_profiles = array();
		$filter          = static function ( $keywords, $schema_profile ) use ( &$schema_profiles ) {
			$schema_profiles[] = $schema_profile;
			$keywords[]        = 'xCustomKeyword';

			return $keywords;
		};

		add_filter( 'wp_json_schema_allowed_keywords', $filter, 10, 2 );
		$keywords = wp_get_json_schema_allowed_keywords( 'draft-04' );
		remove_filter( 'wp_json_schema_allowed_keywords', $filter, 10 );

		$this->assertContains( 'xCustomKeyword', $keywords );
		$this->assertSame( array( 'draft-04' ), $schema_profiles );
	}

	/**
	 * @ticket 64955
	 */
	public function test_wp_prepare_json_schema_for_client_gets_allowed_keywords_once_per_run() {
		$filter_count = 0;
		$filter       = static function ( $keywords ) use ( &$filter_count ) {
			++$filter_count;

			return $keywords;
		};
		$schema       = array(
			'type'       => 'object',
			'properties' => array(
				'config' => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type' => 'string',
						),
					),
				),
			),
			'anyOf'      => array(
				array(
					'type' => 'object',
				),
			),
		);

		add_filter( 'wp_json_schema_allowed_keywords', $filter );
		wp_prepare_json_schema_for_client( $schema );
		remove_filter( 'wp_json_schema_allowed_keywords', $filter );

		$this->assertSame( 1, $filter_count );
	}

	/**
	 * @ticket 64955
	 */
	public function test_wp_prepare_json_schema_for_client_normalizes_schema_for_clients() {
		$schema = array(
			'type'                 => 'object',
			'$ref'                 => '#/definitions/example',
			'sanitize_callback'    => 'sanitize_text_field',
			'properties'           => array(
				'title'    => array(
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => 'is_string',
				),
				'settings' => array(
					'type'    => 'object',
					'default' => array(),
				),
			),
			'dependencies'         => array(
				'title'    => array( 'settings' ),
				'settings' => array(
					'type'              => 'object',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
			'additionalProperties' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		$prepared = wp_prepare_json_schema_for_client( $schema );

		$this->assertSame( '#/definitions/example', $prepared['$ref'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $prepared );
		$this->assertSame( array( 'title' ), $prepared['required'] );
		$this->assertArrayNotHasKey( 'required', $prepared['properties']['title'] );
		$this->assertArrayNotHasKey( 'validate_callback', $prepared['properties']['title'] );
		$this->assertEquals( new stdClass(), $prepared['properties']['settings']['default'] );
		$this->assertSame( array( 'settings' ), $prepared['dependencies']['title'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $prepared['dependencies']['settings'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $prepared['additionalProperties'] );
	}

	/**
	 * @ticket 64955
	 */
	public function test_wp_prepare_json_schema_for_client_strips_keywords_from_nested_sub_schemas() {
		$schema = array(
			'type'                 => 'object',
			'$ref'                 => '#/definitions/address',
			'anyOf'                => array(
				array(
					'type'              => 'object',
					'sanitize_callback' => 'sanitize_text_field',
					'properties'        => array(
						'value' => array(
							'type'              => 'string',
							'validate_callback' => 'is_string',
						),
					),
				),
				array(
					'type'        => 'number',
					'arg_options' => array( 'sanitize_callback' => 'absint' ),
				),
			),
			'oneOf'                => array(
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
			'allOf'                => array(
				array(
					'type'              => 'object',
					'validate_callback' => 'rest_validate_request_arg',
				),
			),
			'not'                  => array(
				'type'        => 'null',
				'arg_options' => array( 'sanitize_callback' => 'absint' ),
			),
			'patternProperties'    => array(
				'^S_' => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
			'definitions'          => array(
				'address' => array(
					'type'              => 'object',
					'validate_callback' => 'rest_validate_request_arg',
					'properties'        => array(
						'street' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			),
			'dependencies'         => array(
				'bar' => array(
					'type'              => 'object',
					'validate_callback' => 'rest_validate_request_arg',
					'properties'        => array(
						'baz' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				'qux' => array( 'bar' ),
			),
			'additionalProperties' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		$prepared = wp_prepare_json_schema_for_client( $schema );

		$this->assertSame( '#/definitions/address', $prepared['$ref'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $prepared['anyOf'][0] );
		$this->assertArrayNotHasKey( 'validate_callback', $prepared['anyOf'][0]['properties']['value'] );
		$this->assertArrayNotHasKey( 'arg_options', $prepared['anyOf'][1] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $prepared['oneOf'][0] );
		$this->assertArrayNotHasKey( 'validate_callback', $prepared['allOf'][0] );
		$this->assertArrayNotHasKey( 'arg_options', $prepared['not'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $prepared['patternProperties']['^S_'] );
		$this->assertArrayNotHasKey( 'validate_callback', $prepared['definitions']['address'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $prepared['definitions']['address']['properties']['street'] );
		$this->assertArrayNotHasKey( 'validate_callback', $prepared['dependencies']['bar'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $prepared['dependencies']['bar']['properties']['baz'] );
		$this->assertSame( array( 'bar' ), $prepared['dependencies']['qux'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $prepared['additionalProperties'] );
	}

	/**
	 * @ticket 64955
	 */
	public function test_wp_prepare_json_schema_for_client_strips_keywords_from_array_sub_schemas() {
		$schema = array(
			'type'            => 'array',
			'items'           => array(
				array(
					'type'              => 'string',
					'validate_callback' => 'is_string',
				),
				array(
					'type'        => 'number',
					'arg_options' => array( 'sanitize_callback' => 'absint' ),
				),
			),
			'additionalItems' => array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
		);

		$prepared = wp_prepare_json_schema_for_client( $schema );

		$this->assertArrayNotHasKey( 'validate_callback', $prepared['items'][0] );
		$this->assertSame( 'string', $prepared['items'][0]['type'] );
		$this->assertArrayNotHasKey( 'arg_options', $prepared['items'][1] );
		$this->assertSame( 'number', $prepared['items'][1]['type'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $prepared['additionalItems'] );
		$this->assertSame( 'boolean', $prepared['additionalItems']['type'] );
	}

	/**
	 * @ticket 64955
	 */
	public function test_wp_prepare_json_schema_for_client_converts_required_property_booleans_to_draft_04_array() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'title'    => array(
					'type'     => 'string',
					'required' => true,
				),
				'content'  => array(
					'type'     => 'string',
					'required' => true,
				),
				'optional' => array(
					'type' => 'string',
				),
			),
		);

		$prepared = wp_prepare_json_schema_for_client( $schema );

		$this->assertSameSets( array( 'title', 'content' ), $prepared['required'] );
		$this->assertArrayNotHasKey( 'required', $prepared['properties']['title'] );
		$this->assertArrayNotHasKey( 'required', $prepared['properties']['content'] );
		$this->assertArrayNotHasKey( 'required', $prepared['properties']['optional'] );
	}

	/**
	 * @ticket 64955
	 */
	public function test_wp_prepare_json_schema_for_client_converts_required_booleans_in_nested_object_schemas() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'address' => array(
					'type'       => 'object',
					'required'   => true,
					'properties' => array(
						'street' => array(
							'type'     => 'string',
							'required' => true,
						),
						'city'   => array(
							'type' => 'string',
						),
					),
				),
			),
		);

		$prepared = wp_prepare_json_schema_for_client( $schema );
		$address  = $prepared['properties']['address'];

		$this->assertSame( array( 'address' ), $prepared['required'] );
		$this->assertSame( array( 'street' ), $address['required'] );
		$this->assertArrayNotHasKey( 'required', $address['properties']['street'] );
		$this->assertArrayNotHasKey( 'required', $address['properties']['city'] );
	}

	/**
	 * @ticket 64955
	 */
	public function test_wp_prepare_json_schema_for_client_removes_required_false_booleans_without_required_array() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'maybe' => array(
					'type'     => 'string',
					'required' => false,
				),
			),
		);

		$prepared = wp_prepare_json_schema_for_client( $schema );

		$this->assertArrayNotHasKey( 'required', $prepared );
		$this->assertArrayNotHasKey( 'required', $prepared['properties']['maybe'] );
	}

	/**
	 * @ticket 64955
	 */
	public function test_wp_prepare_json_schema_for_client_required_array_takes_precedence_over_booleans() {
		$schema = array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'   => array(
					'type'     => 'string',
					'required' => true,
				),
				'content' => array(
					'type'     => 'string',
					'required' => true,
				),
			),
		);

		$prepared = wp_prepare_json_schema_for_client( $schema );

		$this->assertSame( array( 'title' ), $prepared['required'] );
		$this->assertArrayNotHasKey( 'required', $prepared['properties']['title'] );
		$this->assertArrayNotHasKey( 'required', $prepared['properties']['content'] );
	}

	/**
	 * @ticket 64955
	 */
	public function test_wp_prepare_json_schema_for_client_removes_boolean_required_on_scalar_schema() {
		$schema = array(
			'type'        => 'string',
			'description' => 'The text to analyze.',
			'required'    => true,
		);

		$prepared = wp_prepare_json_schema_for_client( $schema );

		$this->assertArrayNotHasKey( 'required', $prepared );
		$this->assertSame( 'string', $prepared['type'] );
	}

	/**
	 * @ticket 64955
	 */
	public function test_wp_prepare_json_schema_for_client_converts_required_booleans_in_array_items_object_schemas() {
		$schema = array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'id'    => array(
						'type'     => 'integer',
						'required' => true,
					),
					'label' => array(
						'type' => 'string',
					),
				),
			),
		);

		$prepared = wp_prepare_json_schema_for_client( $schema );

		$this->assertSame( array( 'id' ), $prepared['items']['required'] );
		$this->assertArrayNotHasKey( 'required', $prepared['items']['properties']['id'] );
		$this->assertArrayNotHasKey( 'required', $prepared['items']['properties']['label'] );
	}
}
