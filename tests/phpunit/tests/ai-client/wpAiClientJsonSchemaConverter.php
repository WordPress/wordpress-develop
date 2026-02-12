<?php
/**
 * Tests for WP_AI_Client_JSON_Schema_Converter.
 *
 * @group ai-client
 * @covers WP_AI_Client_JSON_Schema_Converter
 */
class Tests_AI_Client_JSON_Schema_Converter extends WP_UnitTestCase {

	/**
	 * Test basic object with required properties.
	 *
	 * @ticket TBD
	 */
	public function test_convert_basic_object_with_required() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'name' => array(
					'type' => 'string',
				),
				'age'  => array(
					'type' => 'integer',
				),
			),
			'required'   => array( 'name' ),
		);

		$result = WP_AI_Client_JSON_Schema_Converter::convert( $schema );

		$this->assertArrayNotHasKey( 'required', $result );
		$this->assertTrue( $result['properties']['name']['required'] );
		$this->assertArrayNotHasKey( 'required', $result['properties']['age'] );
	}

	/**
	 * Test schema without required array.
	 *
	 * @ticket TBD
	 */
	public function test_convert_without_required() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'name' => array(
					'type' => 'string',
				),
			),
		);

		$result = WP_AI_Client_JSON_Schema_Converter::convert( $schema );

		$this->assertArrayNotHasKey( 'required', $result['properties']['name'] );
	}

	/**
	 * Test nested sub-objects with required.
	 *
	 * @ticket TBD
	 */
	public function test_convert_nested_objects() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'address' => array(
					'type'       => 'object',
					'properties' => array(
						'street' => array(
							'type' => 'string',
						),
						'city'   => array(
							'type' => 'string',
						),
					),
					'required'   => array( 'street', 'city' ),
				),
			),
			'required'   => array( 'address' ),
		);

		$result = WP_AI_Client_JSON_Schema_Converter::convert( $schema );

		$this->assertTrue( $result['properties']['address']['required'] );
		$this->assertTrue( $result['properties']['address']['properties']['street']['required'] );
		$this->assertTrue( $result['properties']['address']['properties']['city']['required'] );
	}

	/**
	 * Test array items with required.
	 *
	 * @ticket TBD
	 */
	public function test_convert_array_items() {
		$schema = array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'id'   => array(
						'type' => 'string',
					),
					'name' => array(
						'type' => 'string',
					),
				),
				'required'   => array( 'id' ),
			),
		);

		$result = WP_AI_Client_JSON_Schema_Converter::convert( $schema );

		$this->assertTrue( $result['items']['properties']['id']['required'] );
		$this->assertArrayNotHasKey( 'required', $result['items']['properties']['name'] );
	}

	/**
	 * Test oneOf combiner.
	 *
	 * @ticket TBD
	 */
	public function test_convert_one_of() {
		$schema = array(
			'oneOf' => array(
				array(
					'type'       => 'object',
					'properties' => array(
						'type' => array(
							'type' => 'string',
						),
					),
					'required'   => array( 'type' ),
				),
				array(
					'type' => 'string',
				),
			),
		);

		$result = WP_AI_Client_JSON_Schema_Converter::convert( $schema );

		$this->assertTrue( $result['oneOf'][0]['properties']['type']['required'] );
	}

	/**
	 * Test anyOf combiner.
	 *
	 * @ticket TBD
	 */
	public function test_convert_any_of() {
		$schema = array(
			'anyOf' => array(
				array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type' => 'string',
						),
					),
					'required'   => array( 'name' ),
				),
			),
		);

		$result = WP_AI_Client_JSON_Schema_Converter::convert( $schema );

		$this->assertTrue( $result['anyOf'][0]['properties']['name']['required'] );
	}

	/**
	 * Test allOf combiner.
	 *
	 * @ticket TBD
	 */
	public function test_convert_all_of() {
		$schema = array(
			'allOf' => array(
				array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type' => 'string',
						),
					),
					'required'   => array( 'id' ),
				),
			),
		);

		$result = WP_AI_Client_JSON_Schema_Converter::convert( $schema );

		$this->assertTrue( $result['allOf'][0]['properties']['id']['required'] );
	}

	/**
	 * Test schema with no properties returns unchanged.
	 *
	 * @ticket TBD
	 */
	public function test_convert_no_properties() {
		$schema = array(
			'type'        => 'string',
			'description' => 'A simple string.',
		);

		$result = WP_AI_Client_JSON_Schema_Converter::convert( $schema );

		$this->assertSame( $schema, $result );
	}
}
