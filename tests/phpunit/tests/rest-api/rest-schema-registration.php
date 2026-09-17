<?php

/**
 * Unit tests covering schema validation and sanitization functionality.
 *
 * @package    WordPress
 * @subpackage REST API
 *
 * @group restapi
 */
class WP_Test_REST_Schema_Registration extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$wp_rest_server = new Spy_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Tests registered schema are well rendered.
	 * Ensures both schema and accepted arguments are generated correctly in REST.
	 * Checks for title, methods, type, items and nested properties. Also check required and default value if applicable.
	 *
	 * @dataProvider schema_provider
	 *
	 * @return void
	 */
	public function test_schema_and_args_are_generated_correctly( $schema, $method ) {
		global $wp_rest_server;

		$this->register_route( $schema, $method );

		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/test' );
		$response = $wp_rest_server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSameSets( $schema, $response->get_data()['schema'] );
		$this->assertSame( $schema['title'], $response->get_data()['schema']['title'] );
		$this->assertSame( $schema['$schema'], $response->get_data()['schema']['$schema'] );
		$this->assertCount( 1, $response->get_data()['methods'] );
		$this->assertSame( $method, $response->get_data()['methods'][0] );
		$this->assertCount( 1, $response->get_data()['endpoints'][0]['methods'] );
		$this->assertSame( $method, $response->get_data()['endpoints'][0]['methods'][0] );
		$this->assertNotEmpty( $response->get_data()['endpoints'][0]['args'], 'Endpoint arguments should be found.' );
		$this->assertCount( count( $schema['properties'] ), $response->get_data()['endpoints'][0]['args'], 'There should be as much arguments as properies.' );

		$prop_args     = $response->get_data()['endpoints'][0]['args']['prop'];
		$expected_prop = $schema['properties']['prop'];

		$this->assertSame( $expected_prop['description'], $prop_args['description'] );
		$this->assertSame( $expected_prop['type'], $prop_args['type'] );
		$this->assertSame( $expected_prop['items'], $prop_args['items'] );

		if ( WP_REST_Server::CREATABLE === $method ) {
			$this->assertSame( isset( $expected_prop['required'] ) ? $expected_prop['required'] : false, $prop_args['required'] );

			if ( isset( $expected_prop['default'] ) ) {
				$this->assertSame( $expected_prop['default'], $prop_args['default'] );
			}
		} else {
			$this->assertFalse( $prop_args['required'] );
			$this->assertFalse( isset( $prop_args['default'] ) );
		}
	}

	/**
	 * Tests formats for string type.
	 *
	 * @testWith [ "date-time" ]
	 *           [ "email" ]
	 *           [ "ip" ]
	 *           [ "uuid" ]
	 *           [ "hex-color" ]
	 *
	 * @param string $format Format to test.
	 * @return void
	 */
	public function test_string_formats( $format ) {
		global $wp_rest_server;

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'The string',
			'properties' => array(
				'prop' => array(
					'type'   => 'string',
					'format' => $format,
				),
			),
		);

		$this->register_route( $schema, WP_REST_Server::CREATABLE );

		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/test' );
		$response = $wp_rest_server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $format, $response->get_data()['endpoints'][0]['args']['prop']['format'] );
		$this->assertSame( $format, $response->get_data()['schema']['properties']['prop']['format'] );
	}

	/**
	 * Tests property definition for object type.
	 *
	 * @testWith [ "additionalProperties", true ]
	 *           [ "additionalProperties", false ]
	 *           [ "maxProperties", 10 ]
	 *           [ "minProperties", 1 ]
	 *           [ "patternProperties", "Regex" ]
	 *
	 * @param string $setting The setting to test.
	 * @param mixed $value The value to test.
	 * @return void
	 */
	public function test_property_definition_for_object_type( $setting, $value ) {
		global $wp_rest_server;

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'The object',
			'properties' => array(
				'prop' => array(
					'type'   => 'object',
					$setting => $value,
				),
			),
		);

		$this->register_route( $schema, WP_REST_Server::CREATABLE );

		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/test' );
		$response = $wp_rest_server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $value, $response->get_data()['endpoints'][0]['args']['prop'][ $setting ] );
		$this->assertSame( $value, $response->get_data()['schema']['properties']['prop'][ $setting ] );
	}

	/**
	 * Tests property definition for array type.
	 *
	 * @testWith [ "maxItems", 10 ]
	 *           [ "minItems", 1 ]
	 *           [ "uniqueItems", true ]
	 *
	 * @param string $setting The setting to test.
	 * @param mixed $value The value to test.
	 * @return void
	 */
	public function test_property_definition_for_array_type( $setting, $value ) {
		global $wp_rest_server;

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'The array',
			'properties' => array(
				'prop' => array(
					'type'   => 'array',
					$setting => $value,
				),
			),
		);

		$this->register_route( $schema, WP_REST_Server::CREATABLE );

		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/test' );
		$response = $wp_rest_server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $value, $response->get_data()['endpoints'][0]['args']['prop'][ $setting ] );
		$this->assertSame( $value, $response->get_data()['schema']['properties']['prop'][ $setting ] );
	}

	/**
	 * Tests property definition for string type.
	 *
	 * @testWith [ "pattern", "Regex" ]
	 *           [ "minLength", 10 ]
	 *           [ "maxLength", 100 ]
	 *
	 * @param string $setting The setting to test.
	 * @param mixed $value The value to test.
	 * @return void
	 */
	public function test_property_definition_for_string_type( $setting, $value ) {
		global $wp_rest_server;

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'The string',
			'properties' => array(
				'prop' => array(
					'type'   => 'string',
					$setting => $value,
				),
			),
		);

		$this->register_route( $schema, WP_REST_Server::CREATABLE );

		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/test' );
		$response = $wp_rest_server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $value, $response->get_data()['endpoints'][0]['args']['prop'][ $setting ] );
		$this->assertSame( $value, $response->get_data()['schema']['properties']['prop'][ $setting ] );
	}

	/**
	 * Tests property definition for number type.
	 *
	 * @testWith [ "minimum", 10 ]
	 *           [ "maximum", 100 ]
	 *           [ "exclusiveMinimum", true ]
	 *           [ "exclusiveMaximum", true ]
	 *           [ "multipleOf", 10 ]
	 *
	 * @param string $setting The setting to test.
	 * @param mixed $value The value to test.
	 * @return void
	 */
	public function test_property_definition_for_number_type( $setting, $value ) {
		global $wp_rest_server;

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'The number',
			'properties' => array(
				'prop' => array(
					'type'   => 'number',
					$setting => $value,
				),
			),
		);

		$this->register_route( $schema, WP_REST_Server::CREATABLE );

		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/test' );
		$response = $wp_rest_server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $value, $response->get_data()['endpoints'][0]['args']['prop'][ $setting ] );
		$this->assertSame( $value, $response->get_data()['schema']['properties']['prop'][ $setting ] );
	}

	/**
	 * Tests type agnostic property definition.
	 *
	 * @testWith [ "anyOf", "array", [ { "type": "string" }, { "type": "number" } ] ]
	 *           [ "oneOf", "array", [ { "type": "string" }, { "type": "number" } ] ]
	 *           [ "enum", "string", [ "a", "b", "c" ] ]
	 *
	 * @param string $rule The rule to test.
	 * @return void
	 */
	public function test_type_agnostic_property_definition( $rule, $type, $rules ) {
		global $wp_rest_server;

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'Something',
			'properties' => array(
				'prop' => array(
					'type' => $type,
				),
			),
		);

		if ( 'array' === $type ) {
			$schema['properties']['prop']['items'] = array(
				$rule => $rules,
			);
		} else {
			$schema['properties']['prop'][ $rule ] = $rules;
		}

		$this->register_route( $schema, WP_REST_Server::CREATABLE );

		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/test' );
		$response = $wp_rest_server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		if ( 'array' === $type ) {
			$this->assertSame( $rules, $response->get_data()['endpoints'][0]['args']['prop']['items'][ $rule ] );
			$this->assertSame( $rules, $response->get_data()['schema']['properties']['prop']['items'][ $rule ] );
		} else {
			$this->assertSame( $rules, $response->get_data()['endpoints'][0]['args']['prop'][ $rule ] );
			$this->assertSame( $rules, $response->get_data()['schema']['properties']['prop'][ $rule ] );
		}
	}

	/**
	 * Generates schema data containing properties with known types.
	 *
	 * @return array Returns a two values array with the schema and the HTTP method.
	 */
	public function schema_provider() {
		$scalar_types = array(
			'string'  => 'default value',
			'number'  => 1.5,
			'boolean' => true,
			'integer' => 42,
			'null'    => null,
		);

		$main_types = array(
			'array'  => 'An array containing ',
			'object' => 'An object containing ',
			'scalar' => 'A ',
		);

		foreach ( array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ) as $method ) {
			foreach ( $main_types as $main_type => $main_type_desc ) {
				foreach ( array( true, false ) as $has_default_value ) {
					foreach ( array( true, false ) as $has_required_field ) {
						foreach ( $scalar_types as $scalar_type => $default_value ) {
							$prop_def = array(
								'description' => $main_type_desc . $scalar_type . ' item(s)',
								'type'        => $main_type,
								'items'       => array(
									'type' => $scalar_type,
								),
							);

							if ( $has_required_field && ! $has_default_value ) {
								$prop_def['required'] = (bool) wp_rand( 0, 1 );
							}

							if ( $has_default_value && ! $has_required_field ) {
								$prop_def['default'] = $default_value;
							}

							yield array(
								'schema' => array(
									'$schema'    => 'http://json-schema.org/draft-04/schema#',
									'title'      => 'The title',
									'properties' => array(
										'prop' => $prop_def,
									),
								),
								'method' => $method,
							);
						}
					}
				}
			}
		}
	}

	/**
	 * Registers a route with the given schema and method.
	 *
	 * @param array $schema The schema to register.
	 * @param string $method The HTTP method to register the route for.
	 * @return void
	 */
	private function register_route( $schema, $method ) {
		register_rest_route(
			'wp/v2',
			'/test',
			array(
				array(
					'methods'             => $method,
					'callback'            => function () {
						return new WP_REST_Response( array( 'message' => 'Test' ) );
					},
					'permission_callback' => '__return_true',
					'args'                => rest_get_endpoint_args_for_schema( $schema, $method ),
				),
				'schema' => function () use ( $schema ) {
					return $schema;
				},
			)
		);
	}
}
