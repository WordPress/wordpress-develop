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
class WP_Test_REST_Controller extends WP_Test_REST_TestCase {

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Load the WP_REST_Test_Controller class if not already loaded.
		require_once __DIR__ . '/rest-test-controller.php';
	}

	public function setUp() {
		parent::setUp();
		$this->request = new WP_REST_Request(
			'GET',
			'/wp/v2/testroute',
			array(
				'args' => array(
					'someinteger' => array(
						'type' => 'integer',
					),
					'someboolean' => array(
						'type' => 'boolean',
					),
					'somestring'  => array(
						'type' => 'string',
					),
					'somehex'     => array(
						'type'   => 'string',
						'format' => 'hex-color',
					),
					'someenum'    => array(
						'type' => 'string',
						'enum' => array( 'a' ),
					),
					'somedate'    => array(
						'type'   => 'string',
						'format' => 'date-time',
					),
					'someemail'   => array(
						'type'   => 'string',
						'format' => 'email',
					),
					'someuuid'    => array(
						'type'   => 'string',
						'format' => 'uuid',
					),
				),
			)
		);
	}

	public function tearDown() {
		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();

		parent::tearDown();
	}

	public function test_validate_schema_type_integer() {

		$this->assertTrue(
			rest_validate_request_arg( '123', $this->request, 'someinteger' )
		);

		$this->assertErrorResponse(
			'rest_invalid_type',
			rest_validate_request_arg( 'abc', $this->request, 'someinteger' )
		);
	}

	public function test_validate_schema_type_boolean() {

		$this->assertTrue(
			rest_validate_request_arg( true, $this->request, 'someboolean' )
		);
		$this->assertTrue(
			rest_validate_request_arg( false, $this->request, 'someboolean' )
		);

		$this->assertTrue(
			rest_validate_request_arg( 'true', $this->request, 'someboolean' )
		);
		$this->assertTrue(
			rest_validate_request_arg( 'TRUE', $this->request, 'someboolean' )
		);
		$this->assertTrue(
			rest_validate_request_arg( 'false', $this->request, 'someboolean' )
		);
		$this->assertTrue(
			rest_validate_request_arg( 'False', $this->request, 'someboolean' )
		);
		$this->assertTrue(
			rest_validate_request_arg( '1', $this->request, 'someboolean' )
		);
		$this->assertTrue(
			rest_validate_request_arg( '0', $this->request, 'someboolean' )
		);
		$this->assertTrue(
			rest_validate_request_arg( 1, $this->request, 'someboolean' )
		);
		$this->assertTrue(
			rest_validate_request_arg( 0, $this->request, 'someboolean' )
		);

		// Check sanitize testing.
		$this->assertSame(
			false,
			rest_sanitize_request_arg( 'false', $this->request, 'someboolean' )
		);
		$this->assertSame(
			false,
			rest_sanitize_request_arg( '0', $this->request, 'someboolean' )
		);
		$this->assertSame(
			false,
			rest_sanitize_request_arg( 0, $this->request, 'someboolean' )
		);
		$this->assertSame(
			false,
			rest_sanitize_request_arg( 'FALSE', $this->request, 'someboolean' )
		);
		$this->assertSame(
			true,
			rest_sanitize_request_arg( 'true', $this->request, 'someboolean' )
		);
		$this->assertSame(
			true,
			rest_sanitize_request_arg( '1', $this->request, 'someboolean' )
		);
		$this->assertSame(
			true,
			rest_sanitize_request_arg( 1, $this->request, 'someboolean' )
		);
		$this->assertSame(
			true,
			rest_sanitize_request_arg( 'TRUE', $this->request, 'someboolean' )
		);

		$this->assertErrorResponse(
			'rest_invalid_type',
			rest_validate_request_arg( '123', $this->request, 'someboolean' )
		);
	}

	public function test_validate_schema_type_string() {

		$this->assertTrue(
			rest_validate_request_arg( '123', $this->request, 'somestring' )
		);

		$this->assertErrorResponse(
			'rest_invalid_type',
			rest_validate_request_arg( array( 'foo' => 'bar' ), $this->request, 'somestring' )
		);
	}

	public function test_validate_schema_enum() {

		$this->assertTrue(
			rest_validate_request_arg( 'a', $this->request, 'someenum' )
		);

		$this->assertErrorResponse(
			'rest_not_in_enum',
			rest_validate_request_arg( 'd', $this->request, 'someenum' )
		);
	}

	public function test_validate_schema_format_email() {

		$this->assertTrue(
			rest_validate_request_arg( 'joe@foo.bar', $this->request, 'someemail' )
		);

		$this->assertErrorResponse(
			'rest_invalid_email',
			rest_validate_request_arg( 'd', $this->request, 'someemail' )
		);
	}

	/**
	 * @ticket 49270
	 */
	public function test_validate_schema_format_hex_color() {

		$this->assertTrue(
			rest_validate_request_arg( '#000000', $this->request, 'somehex' )
		);

		$this->assertErrorResponse(
			'rest_invalid_hex_color',
			rest_validate_request_arg( 'wibble', $this->request, 'somehex' )
		);
	}

	public function test_validate_schema_format_date_time() {

		$this->assertTrue(
			rest_validate_request_arg( '2010-01-01T12:00:00', $this->request, 'somedate' )
		);

		$this->assertErrorResponse(
			'rest_invalid_date',
			rest_validate_request_arg( '2010-18-18T12:00:00', $this->request, 'somedate' )
		);
	}

	/**
	 * @ticket 50053
	 */
	public function test_validate_schema_format_uuid() {
		$this->assertTrue(
			rest_validate_request_arg( '123e4567-e89b-12d3-a456-426655440000', $this->request, 'someuuid' )
		);

		$this->assertErrorResponse(
			'rest_invalid_uuid',
			rest_validate_request_arg( '123e4567-e89b-12d3-a456-426655440000X', $this->request, 'someuuid' )
		);
	}

	/**
	 * @ticket 50876
	 */
	public function test_get_endpoint_args_for_item_schema() {
		$controller = new WP_REST_Test_Controller();
		$args       = $controller->get_endpoint_args_for_item_schema();

		$this->assertArrayHasKey( 'somestring', $args );
		$this->assertArrayHasKey( 'someinteger', $args );
		$this->assertArrayHasKey( 'someboolean', $args );
		$this->assertArrayHasKey( 'someurl', $args );
		$this->assertArrayHasKey( 'somedate', $args );
		$this->assertArrayHasKey( 'someemail', $args );
		$this->assertArrayHasKey( 'somehex', $args );
		$this->assertArrayHasKey( 'someuuid', $args );
		$this->assertArrayHasKey( 'someenum', $args );
		$this->assertArrayHasKey( 'someargoptions', $args );
		$this->assertArrayHasKey( 'somedefault', $args );
		$this->assertArrayHasKey( 'somearray', $args );
		$this->assertArrayHasKey( 'someobject', $args );
	}

	public function test_get_endpoint_args_for_item_schema_description() {
		$controller = new WP_REST_Test_Controller();
		$args       = rest_get_endpoint_args_for_schema( $controller->get_item_schema() );

		$this->assertSame( 'A pretty string.', $args['somestring']['description'] );
		$this->assertArrayNotHasKey( 'description', $args['someinteger'] );
	}

	public function test_get_endpoint_args_for_item_schema_arg_options() {

		$controller = new WP_REST_Test_Controller();
		$args       = rest_get_endpoint_args_for_schema( $controller->get_item_schema() );

		$this->assertFalse( $args['someargoptions']['required'] );
		$this->assertSame( '__return_true', $args['someargoptions']['sanitize_callback'] );
	}

	public function test_get_endpoint_args_for_item_schema_default_value() {

		$controller = new WP_REST_Test_Controller();
		$args       = rest_get_endpoint_args_for_schema( $controller->get_item_schema() );

		$this->assertSame( 'a', $args['somedefault']['default'] );
	}

	/**
	 * @ticket 50301
	 */
	public function test_get_endpoint_args_for_item_schema_arg_properties() {

		$controller = new WP_REST_Test_Controller();
		$args       = rest_get_endpoint_args_for_schema( $controller->get_item_schema() );

		foreach ( array( 'minLength', 'maxLength', 'pattern' ) as $property ) {
			$this->assertArrayHasKey( $property, $args['somestring'] );
		}

		foreach ( array( 'multipleOf', 'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum' ) as $property ) {
			$this->assertArrayHasKey( $property, $args['someinteger'] );
		}

		$this->assertArrayHasKey( 'items', $args['somearray'] );

		foreach ( array( 'minItems', 'maxItems', 'uniqueItems' ) as $property ) {
			$this->assertArrayHasKey( $property, $args['somearray'] );
		}

		$object_properties = array(
			'properties',
			'patternProperties',
			'additionalProperties',
			'minProperties',
			'maxProperties',
			'anyOf',
			'oneOf',
		);
		foreach ( $object_properties as $property ) {
			$this->assertArrayHasKey( $property, $args['someobject'] );
		}

		// Ignored properties.
		$this->assertArrayNotHasKey( 'ignored_prop', $args['someobject'] );

	}

	/**
	 * @dataProvider data_get_fields_for_response
	 */
	public function test_get_fields_for_response( $param, $expected ) {
		$controller = new WP_REST_Test_Controller();
		$request    = new WP_REST_Request( 'GET', '/wp/v2/testroute' );
		$fields     = $controller->get_fields_for_response( $request );
		$this->assertSame(
			array(
				'somestring',
				'someinteger',
				'someboolean',
				'someurl',
				'somedate',
				'someemail',
				'somehex',
				'someuuid',
				'someenum',
				'someargoptions',
				'somedefault',
				'somearray',
				'someobject',
			),
			$fields
		);
		$request->set_param( '_fields', $param );
		$fields = $controller->get_fields_for_response( $request );
		$this->assertSame( $expected, $fields );
	}

	public function data_get_fields_for_response() {
		return array(
			array(
				'somestring,someinteger,someinvalidkey',
				array(
					'somestring',
					'someinteger',
				),
			),
			array(
				',,',
				array(
					'somestring',
					'someinteger',
					'someboolean',
					'someurl',
					'somedate',
					'someemail',
					'somehex',
					'someuuid',
					'someenum',
					'someargoptions',
					'somedefault',
					'somearray',
					'someobject',
				),
			),
		);
	}

	public function test_get_fields_for_response_filters_by_context() {
		$controller = new WP_REST_Test_Controller();

		$request = new WP_REST_Request( 'GET', '/wp/v2/testroute' );
		$request->set_param( 'context', 'view' );

		$schema = $controller->get_item_schema();
		$field  = 'somefield';

		$listener = new MockAction();
		$method   = 'action';

		register_rest_field(
			$schema['title'],
			$field,
			array(
				'schema'       => array(
					'type'    => 'string',
					'context' => array( 'embed' ),
				),
				'get_callback' => array( $listener, $method ),
			)
		);

		$controller->prepare_item_for_response( array(), $request );

		$this->assertSame( 0, $listener->get_call_count( $method ) );

		$request->set_param( 'context', 'embed' );

		$controller->prepare_item_for_response( array(), $request );

		$this->assertGreaterThan( 0, $listener->get_call_count( $method ) );
	}

	public function test_filtering_fields_for_response_by_context_returns_fields_with_no_context() {
		$controller = new WP_REST_Test_Controller();

		$request = new WP_REST_Request( 'GET', '/wp/v2/testroute' );
		$request->set_param( 'context', 'view' );

		$schema = $controller->get_item_schema();
		$field  = 'somefield';

		$listener = new MockAction();
		$method   = 'action';

		register_rest_field(
			$schema['title'],
			$field,
			array(
				'schema'       => array(
					'type' => 'string',
				),
				'get_callback' => array( $listener, $method ),
			)
		);

		$controller->prepare_item_for_response( array(), $request );

		$this->assertGreaterThan( 0, $listener->get_call_count( $method ) );
	}

	public function test_filtering_fields_for_response_by_context_returns_fields_with_no_schema() {
		$controller = new WP_REST_Test_Controller();

		$request = new WP_REST_Request( 'GET', '/wp/v2/testroute' );
		$request->set_param( 'context', 'view' );

		$schema = $controller->get_item_schema();
		$field  = 'somefield';

		$listener = new MockAction();
		$method   = 'action';

		register_rest_field(
			$schema['title'],
			$field,
			array(
				'get_callback' => array( $listener, $method ),
			)
		);

		$controller->prepare_item_for_response( array(), $request );

		$this->assertGreaterThan( 0, $listener->get_call_count( $method ) );
	}

	/**
	 * @ticket 48785
	 */
	public function test_get_public_item_schema_with_properties() {
		$schema = ( new WP_REST_Test_Controller() )->get_public_item_schema();

		// Double-check that the public item schema set in WP_REST_Test_Controller still has properties.
		$this->assertArrayHasKey( 'properties', $schema );

		// But arg_options should be removed.
		$this->assertArrayNotHasKey( 'arg_options', $schema['properties']['someargoptions'] );
	}

	/**
	 * @ticket 48785
	 */
	public function test_get_public_item_schema_no_properties() {
		$controller = new WP_REST_Test_Configurable_Controller(
			array(
				'$schema'     => 'http://json-schema.org/draft-04/schema#',
				'title'       => 'foo',
				'type'        => 'string',
				'description' => 'This is my magical endpoint that just returns a string.',
			)
		);

		// Initial check that the test class is working as expected.
		$this->assertArrayNotHasKey( 'properties', $controller->get_public_item_schema() );

		// Test that the schema lacking 'properties' is returned as expected.
		$this->assertSameSetsWithIndex( $controller->get_public_item_schema(), $controller->get_test_schema() );
	}

	public function test_add_additional_fields_to_object_respects_fields_param() {
		$controller = new WP_REST_Test_Controller();
		$request    = new WP_REST_Request( 'GET', '/wp/v2/testroute' );
		$schema     = $controller->get_item_schema();
		$field      = 'somefield';

		$listener = new MockAction();
		$method   = 'action';

		register_rest_field(
			$schema['title'],
			$field,
			array(
				'get_callback' => array( $listener, $method ),
				'schema'       => array(
					'type' => 'string',
				),
			)
		);

		$item = array();

		$controller->prepare_item_for_response( $item, $request );

		$first_call_count = $listener->get_call_count( $method );

		$this->assertTrue( $first_call_count > 0 );

		$request->set_param( '_fields', 'somestring' );

		$controller->prepare_item_for_response( $item, $request );

		$this->assertSame( $first_call_count, $listener->get_call_count( $method ) );

		$request->set_param( '_fields', $field );

		$controller->prepare_item_for_response( $item, $request );

		$this->assertTrue( $listener->get_call_count( $method ) > $first_call_count );
	}

	/**
	 * @dataProvider data_filter_nested_registered_rest_fields
	 * @ticket 49648
	 */
	public function test_filter_nested_registered_rest_fields( $filter, $expected ) {
		$controller = new WP_REST_Test_Controller();

		register_rest_field(
			'type',
			'field',
			array(
				'schema'       => array(
					'type'        => 'object',
					'description' => 'A complex object',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'a' => array(
							'i'  => 'string',
							'ii' => 'string',
						),
						'b' => array(
							'iii' => 'string',
							'iv'  => 'string',
						),
					),
				),
				'get_callback' => array( $this, 'register_nested_rest_field_get_callback' ),
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/testroute' );
		$request->set_param( '_fields', $filter );

		$response = $controller->prepare_item_for_response( array(), $request );
		$response = rest_filter_response_fields( $response, rest_get_server(), $request );

		$this->assertSame( $expected, $response->get_data() );
	}

	public function register_nested_rest_field_get_callback() {
		return array(
			'a' => array(
				'i'  => 'value i',
				'ii' => 'value ii',
			),
			'b' => array(
				'iii' => 'value iii',
				'iv'  => 'value iv',
			),
		);
	}

	public function data_filter_nested_registered_rest_fields() {
		return array(
			array(
				'field',
				array(
					'field' => array(
						'a' => array(
							'i'  => 'value i',
							'ii' => 'value ii',
						),
						'b' => array(
							'iii' => 'value iii',
							'iv'  => 'value iv',
						),
					),
				),
			),
			array(
				'field.a',
				array(
					'field' => array(
						'a' => array(
							'i'  => 'value i',
							'ii' => 'value ii',
						),
					),
				),
			),
			array(
				'field.b',
				array(
					'field' => array(
						'b' => array(
							'iii' => 'value iii',
							'iv'  => 'value iv',
						),
					),
				),
			),
			array(
				'field.a.i,field.b.iv',
				array(
					'field' => array(
						'a' => array(
							'i' => 'value i',
						),
						'b' => array(
							'iv' => 'value iv',
						),
					),
				),
			),
			array(
				'field.a,field.b.iii',
				array(
					'field' => array(
						'a' => array(
							'i'  => 'value i',
							'ii' => 'value ii',
						),
						'b' => array(
							'iii' => 'value iii',
						),
					),
				),
			),
		);
	}
}
