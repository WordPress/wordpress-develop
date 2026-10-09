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
		// Keep assertEquals() because the objects are intentionally compared by value.
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

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: mixed, 1: array<string, mixed>, 2: string}>
	 */
	public static function data_wp_prepare_json_value_for_client() {
		return array(
			'empty object'                 => array(
				array(),
				array( 'type' => 'object' ),
				'{}',
			),
			'empty nullable object'        => array(
				array(),
				array( 'type' => array( 'object', 'null' ) ),
				'{}',
			),
			'empty object or array'        => array(
				array(),
				array( 'type' => array( 'object', 'array' ) ),
				'[]',
			),
			'empty array or object'        => array(
				array(),
				array( 'type' => array( 'array', 'object' ) ),
				'[]',
			),
			'empty array'                  => array(
				array(),
				array( 'type' => 'array' ),
				'[]',
			),
			'non-empty object'             => array(
				array( 'key' => 'value' ),
				array( 'type' => 'object' ),
				'{"key":"value"}',
			),
			'sparse mixed object or array' => array(
				array( 2 => array() ),
				array(
					'type'       => array( 'object', 'array' ),
					'properties' => array(
						2 => array( 'type' => 'object' ),
					),
					'items'      => array( 'type' => 'array' ),
				),
				'{"2":{}}',
			),
			'list mixed object or array'   => array(
				array( array() ),
				array(
					'type'       => array( 'object', 'array' ),
					'properties' => array(
						0 => array( 'type' => 'array' ),
					),
					'items'      => array( 'type' => 'object' ),
				),
				'[{}]',
			),
		);
	}

	/**
	 * Tests that JSON values are prepared according to their schema type.
	 *
	 * @ticket 66267
	 * @dataProvider data_wp_prepare_json_value_for_client
	 *
	 * @param mixed                $value    The value to prepare.
	 * @param array<string, mixed> $schema   The schema describing the value.
	 * @param string               $expected The expected encoded value.
	 */
	public function test_wp_prepare_json_value_for_client( $value, $schema, $expected ) {
		$prepared = wp_prepare_json_value_for_client( $value, $schema );

		$this->assertSame( $expected, wp_json_encode( $prepared ) );
	}

	/**
	 * Tests that nested JSON values are prepared using their sub-schemas.
	 *
	 * @ticket 66267
	 */
	public function test_wp_prepare_json_value_for_client_prepares_nested_values() {
		$object = (object) array( 'nested' => array() );
		$value  = array(
			'known'      => array(),
			'list'       => array( array() ),
			'empty_list' => array(),
			'container'  => $object,
			'extra'      => array(),
		);
		$schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'known'      => array( 'type' => 'object' ),
				'list'       => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'empty_list' => array( 'type' => 'array' ),
				'container'  => array(
					'type'       => 'object',
					'properties' => array(
						'nested' => array( 'type' => 'object' ),
					),
				),
			),
			'additionalProperties' => array( 'type' => 'object' ),
		);

		$prepared = wp_prepare_json_value_for_client( $value, $schema );

		$this->assertSame(
			'{"known":{},"list":[{}],"empty_list":[],"container":{"nested":{}},"extra":{}}',
			wp_json_encode( $prepared )
		);
		$this->assertSame( '[]', wp_json_encode( $object->nested ), 'The original object should not be modified.' );
	}

	/**
	 * Tests that values from JsonSerializable objects are prepared.
	 *
	 * @ticket 66267
	 */
	public function test_wp_prepare_json_value_for_client_prepares_json_serializable_values() {
		$value  = new class() implements JsonSerializable {
			/**
			 * Returns the value for JSON serialization.
			 *
			 * @return array<string, array<mixed>>
			 */
			#[\ReturnTypeWillChange]
			public function jsonSerialize() {
				return array( 'nested' => array() );
			}
		};
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'nested' => array( 'type' => 'object' ),
			),
		);

		$prepared = wp_prepare_json_value_for_client( $value, $schema );

		$this->assertSame( '{"nested":{}}', wp_json_encode( $prepared ) );
	}

	/**
	 * Tests that pattern properties take precedence over additional properties.
	 *
	 * @ticket 66267
	 */
	public function test_wp_prepare_json_value_for_client_uses_pattern_properties() {
		$value  = array( 'item-1' => array() );
		$schema = array(
			'type'                 => 'object',
			'patternProperties'    => array(
				'^item-' => array( 'type' => 'object' ),
			),
			'additionalProperties' => array( 'type' => 'array' ),
		);

		$prepared = wp_prepare_json_value_for_client( $value, $schema );

		$this->assertSame( '{"item-1":{}}', wp_json_encode( $prepared ) );
	}
}
