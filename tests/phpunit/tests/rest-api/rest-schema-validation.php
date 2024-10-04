<?php
/**
 * Unit tests covering schema validation and sanitization functionality.
 *
 * @package    WordPress
 * @subpackage REST API
 *
 * @group restapi
 */
class WP_Test_REST_Schema_Validation extends WP_UnitTestCase {

	public function test_type_number() {
		$schema = array(
			'type'    => 'number',
			'minimum' => 1,
			'maximum' => 2,
		);
		$this->assertTrue( rest_validate_value_from_schema( 1, $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( 2, $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 0.9, $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 3, $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( true, $schema ) );
	}

	public function test_type_integer() {
		$schema = array(
			'type'    => 'integer',
			'minimum' => 1,
			'maximum' => 2,
		);
		$this->assertTrue( rest_validate_value_from_schema( 1, $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( 2, $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 0, $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 3, $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 1.1, $schema ) );
	}

	public function test_type_string() {
		$schema = array(
			'type' => 'string',
		);
		$this->assertTrue( rest_validate_value_from_schema( 'Hello :)', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( '1', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 1, $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( array(), $schema ) );
	}

	public function test_type_boolean() {
		$schema = array(
			'type' => 'boolean',
		);
		$this->assertTrue( rest_validate_value_from_schema( true, $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( false, $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( 1, $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( 0, $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( 'true', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( 'false', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 'no', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 'yes', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 1123, $schema ) );
	}

	public function test_format_email() {
		$schema = array(
			'type'   => 'string',
			'format' => 'email',
		);
		$this->assertTrue( rest_validate_value_from_schema( 'email@example.com', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( 'a@b.co', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 'email', $schema ) );
	}

	/**
	 * @ticket 49270
	 */
	public function test_format_hex_color() {
		$schema = array(
			'type'   => 'string',
			'format' => 'hex-color',
		);
		$this->assertTrue( rest_validate_value_from_schema( '#000000', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( '#FFF', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 'WordPress', $schema ) );
	}

	/**
	 * @ticket 50053
	 */
	public function test_format_uuid() {
		$schema = array(
			'type'   => 'string',
			'format' => 'uuid',
		);
		$this->assertTrue( rest_validate_value_from_schema( '123e4567-e89b-12d3-a456-426655440000', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( '123e4567-e89b-12d3-a456-426655440000X', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( '123e4567-e89b-?2d3-a456-426655440000', $schema ) );
	}

	public function test_format_date_time() {
		$schema = array(
			'type'   => 'string',
			'format' => 'date-time',
		);
		$this->assertTrue( rest_validate_value_from_schema( '2016-06-30T05:43:21', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( '2016-06-30T05:43:21Z', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( '2016-06-30T05:43:21+00:00', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( '20161027T163355Z', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( '2016', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( '2016-06-30', $schema ) );
	}

	public function test_format_ip() {
		$schema = array(
			'type'   => 'string',
			'format' => 'ip',
		);

		// IPv4.
		$this->assertTrue( rest_validate_value_from_schema( '127.0.0.1', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( '3333.3333.3333.3333', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( '1', $schema ) );

		// IPv6.
		$this->assertTrue( rest_validate_value_from_schema( '::1', $schema ) ); // Loopback, compressed, non-routable.
		$this->assertTrue( rest_validate_value_from_schema( '::', $schema ) ); // Unspecified, compressed, non-routable.
		$this->assertTrue( rest_validate_value_from_schema( '0:0:0:0:0:0:0:1', $schema ) ); // Loopback, full.
		$this->assertTrue( rest_validate_value_from_schema( '0:0:0:0:0:0:0:0', $schema ) ); // Unspecified, full.
		$this->assertTrue( rest_validate_value_from_schema( '2001:DB8:0:0:8:800:200C:417A', $schema ) ); // Unicast, full.
		$this->assertTrue( rest_validate_value_from_schema( 'FF01:0:0:0:0:0:0:101', $schema ) ); // Multicast, full.
		$this->assertTrue( rest_validate_value_from_schema( '2001:DB8::8:800:200C:417A', $schema ) ); // Unicast, compressed.
		$this->assertTrue( rest_validate_value_from_schema( 'FF01::101', $schema ) ); // Multicast, compressed.
		$this->assertTrue( rest_validate_value_from_schema( 'fe80::217:f2ff:fe07:ed62', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( '', $schema ) ); // Empty string.
		$this->assertWPError( rest_validate_value_from_schema( '2001:DB8:0:0:8:800:200C:417A:221', $schema ) ); // Unicast, full.
		$this->assertWPError( rest_validate_value_from_schema( 'FF01::101::2', $schema ) ); // Multicast, compressed.
	}

	/**
	 * @ticket 50189
	 */
	public function test_format_validation_is_skipped_if_non_string_type() {
		$schema = array(
			'type'   => 'array',
			'items'  => array(
				'type' => 'string',
			),
			'format' => 'email',
		);
		$this->assertTrue( rest_validate_value_from_schema( 'email@example.com', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( 'email', $schema ) );
	}

	/**
	 * @ticket 50189
	 */
	public function test_format_validation_is_applied_if_missing_type() {
		if ( PHP_VERSION_ID >= 80000 ) {
			$this->expectWarning(); // For the undefined index.
		} else {
			$this->expectNotice(); // For the undefined index.
		}

		$this->setExpectedIncorrectUsage( 'rest_validate_value_from_schema' );

		$schema = array( 'format' => 'email' );
		$this->assertTrue( rest_validate_value_from_schema( 'email@example.com', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 'email', $schema ) );
	}

	/**
	 * @ticket 50189
	 */
	public function test_format_validation_is_applied_if_unknown_type() {
		$this->setExpectedIncorrectUsage( 'rest_validate_value_from_schema' );

		$schema = array(
			'format' => 'email',
			'type'   => 'str',
		);
		$this->assertTrue( rest_validate_value_from_schema( 'email@example.com', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 'email', $schema ) );
	}

	public function test_type_array() {
		$schema = array(
			'type'  => 'array',
			'items' => array(
				'type' => 'number',
			),
		);
		$this->assertTrue( rest_validate_value_from_schema( array( 1 ), $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( array( true ), $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( null, $schema ) );
	}

	public function test_type_array_nested() {
		$schema = array(
			'type'  => 'array',
			'items' => array(
				'type'  => 'array',
				'items' => array(
					'type' => 'number',
				),
			),
		);
		$this->assertTrue( rest_validate_value_from_schema( array( array( 1 ), array( 2 ) ), $schema ) );
	}

	public function test_type_array_as_csv() {
		$schema = array(
			'type'  => 'array',
			'items' => array(
				'type' => 'number',
			),
		);
		$this->assertTrue( rest_validate_value_from_schema( '1', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( '1,2,3', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 'lol', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( '1,,', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( '', $schema ) );
	}

	public function test_type_array_with_enum() {
		$schema = array(
			'type'  => 'array',
			'items' => array(
				'enum' => array( 'chicken', 'ribs', 'brisket' ),
				'type' => 'string',
			),
		);
		$this->assertTrue( rest_validate_value_from_schema( array( 'ribs', 'brisket' ), $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( array( 'coleslaw' ), $schema ) );
	}

	public function test_type_array_with_enum_as_csv() {
		$schema = array(
			'type'  => 'array',
			'items' => array(
				'enum' => array( 'chicken', 'ribs', 'brisket' ),
				'type' => 'string',
			),
		);
		$this->assertTrue( rest_validate_value_from_schema( 'ribs,chicken', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 'chicken,coleslaw', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( 'ribs,chicken,', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( '', $schema ) );
	}

	/**
	 * @ticket 51911
	 * @ticket 52932
	 *
	 * @dataProvider data_different_types_of_value_and_enum_elements
	 *
	 * @param mixed $value
	 * @param array $args
	 * @param bool  $expected
	 */
	public function test_different_types_of_value_and_enum_elements( $value, $args, $expected ) {
		$result = rest_validate_value_from_schema( $value, $args );
		if ( $expected ) {
			$this->assertTrue( $result );
		} else {
			$this->assertWPError( $result );
		}
	}

	/**
	 * @return array
	 */
	public function data_different_types_of_value_and_enum_elements() {
		return array(
			// enum with integers
			array(
				0,
				array(
					'type' => 'integer',
					'enum' => array( 0, 1 ),
				),
				true,
			),
			array(
				0.0,
				array(
					'type' => 'integer',
					'enum' => array( 0, 1 ),
				),
				true,
			),
			array(
				'0',
				array(
					'type' => 'integer',
					'enum' => array( 0, 1 ),
				),
				true,
			),
			array(
				1,
				array(
					'type' => 'integer',
					'enum' => array( 0, 1 ),
				),
				true,
			),
			array(
				1,
				array(
					'type' => 'integer',
					'enum' => array( 0.0, 1.0 ),
				),
				true,
			),
			array(
				1.0,
				array(
					'type' => 'integer',
					'enum' => array( 0, 1 ),
				),
				true,
			),
			array(
				'1',
				array(
					'type' => 'integer',
					'enum' => array( 0, 1 ),
				),
				true,
			),
			array(
				2,
				array(
					'type' => 'integer',
					'enum' => array( 0, 1 ),
				),
				false,
			),
			array(
				2.0,
				array(
					'type' => 'integer',
					'enum' => array( 0, 1 ),
				),
				false,
			),
			array(
				'2',
				array(
					'type' => 'integer',
					'enum' => array( 0, 1 ),
				),
				false,
			),

			// enum with floats
			array(
				0,
				array(
					'type' => 'number',
					'enum' => array( 0.0, 1.0 ),
				),
				true,
			),
			array(
				0.0,
				array(
					'type' => 'number',
					'enum' => array( 0.0, 1.0 ),
				),
				true,
			),
			array(
				'0',
				array(
					'type' => 'number',
					'enum' => array( 0.0, 1.0 ),
				),
				true,
			),
			array(
				1,
				array(
					'type' => 'number',
					'enum' => array( 0.0, 1.0 ),
				),
				true,
			),
			array(
				1,
				array(
					'type' => 'number',
					'enum' => array( 0, 1 ),
				),
				true,
			),
			array(
				1.0,
				array(
					'type' => 'number',
					'enum' => array( 0.0, 1.0 ),
				),
				true,
			),
			array(
				'1',
				array(
					'type' => 'number',
					'enum' => array( 0.0, 1.0 ),
				),
				true,
			),
			array(
				2,
				array(
					'type' => 'number',
					'enum' => array( 0.0, 1.0 ),
				),
				false,
			),
			array(
				2.0,
				array(
					'type' => 'number',
					'enum' => array( 0.0, 1.0 ),
				),
				false,
			),
			array(
				'2',
				array(
					'type' => 'number',
					'enum' => array( 0.0, 1.0 ),
				),
				false,
			),

			// enum with booleans
			array(
				true,
				array(
					'type' => 'boolean',
					'enum' => array( true ),
				),
				true,
			),
			array(
				1,
				array(
					'type' => 'boolean',
					'enum' => array( true ),
				),
				true,
			),
			array(
				'true',
				array(
					'type' => 'boolean',
					'enum' => array( true ),
				),
				true,
			),
			array(
				false,
				array(
					'type' => 'boolean',
					'enum' => array( true ),
				),
				false,
			),
			array(
				0,
				array(
					'type' => 'boolean',
					'enum' => array( true ),
				),
				false,
			),
			array(
				'false',
				array(
					'type' => 'boolean',
					'enum' => array( true ),
				),
				false,
			),
			array(
				false,
				array(
					'type' => 'boolean',
					'enum' => array( false ),
				),
				true,
			),
			array(
				0,
				array(
					'type' => 'boolean',
					'enum' => array( false ),
				),
				true,
			),
			array(
				'false',
				array(
					'type' => 'boolean',
					'enum' => array( false ),
				),
				true,
			),
			array(
				true,
				array(
					'type' => 'boolean',
					'enum' => array( false ),
				),
				false,
			),
			array(
				1,
				array(
					'type' => 'boolean',
					'enum' => array( false ),
				),
				false,
			),
			array(
				'true',
				array(
					'type' => 'boolean',
					'enum' => array( false ),
				),
				false,
			),

			// enum with arrays
			array(
				array( 0, 1 ),
				array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
					'enum'  => array( array( 0, 1 ), array( 1, 2 ) ),
				),
				true,
			),
			array(
				array( '0', 1 ),
				array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
					'enum'  => array( array( 0, 1 ), array( 1, 2 ) ),
				),
				true,
			),
			array(
				array( 0, '1' ),
				array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
					'enum'  => array( array( 0, 1 ), array( 1, 2 ) ),
				),
				true,
			),
			array(
				array( '0', '1' ),
				array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
					'enum'  => array( array( 0, 1 ), array( 1, 2 ) ),
				),
				true,
			),
			array(
				array( 1, 2 ),
				array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
					'enum'  => array( array( 0, 1 ), array( 1, 2 ) ),
				),
				true,
			),
			array(
				array( 2, 3 ),
				array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
					'enum'  => array( array( 0, 1 ), array( 1, 2 ) ),
				),
				false,
			),
			array(
				array( 1, 0 ),
				array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
					'enum'  => array( array( 0, 1 ), array( 1, 2 ) ),
				),
				false,
			),

			// enum with objects
			array(
				array(
					'a' => 1,
					'b' => 2,
				),
				array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'integer' ),
					'enum'                 => array(
						array(
							'a' => 1,
							'b' => 2,
						),
						array(
							'b' => 2,
							'c' => 3,
						),
					),
				),
				true,
			),
			array(
				array(
					'a' => '1',
					'b' => 2,
				),
				array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'integer' ),
					'enum'                 => array(
						array(
							'a' => 1,
							'b' => 2,
						),
						array(
							'b' => 2,
							'c' => 3,
						),
					),
				),
				true,
			),
			array(
				array(
					'a' => 1,
					'b' => '2',
				),
				array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'integer' ),
					'enum'                 => array(
						array(
							'a' => 1,
							'b' => 2,
						),
						array(
							'b' => 2,
							'c' => 3,
						),
					),
				),
				true,
			),
			array(
				array(
					'a' => '1',
					'b' => '2',
				),
				array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'integer' ),
					'enum'                 => array(
						array(
							'a' => 1,
							'b' => 2,
						),
						array(
							'b' => 2,
							'c' => 3,
						),
					),
				),
				true,
			),
			array(
				array(
					'b' => 2,
					'a' => 1,
				),
				array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'integer' ),
					'enum'                 => array(
						array(
							'a' => 1,
							'b' => 2,
						),
						array(
							'b' => 2,
							'c' => 3,
						),
					),
				),
				true,
			),
			array(
				array(
					'b' => 2,
					'c' => 3,
				),
				array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'integer' ),
					'enum'                 => array(
						array(
							'a' => 1,
							'b' => 2,
						),
						array(
							'b' => 2,
							'c' => 3,
						),
					),
				),
				true,
			),
			array(
				array(
					'a' => 1,
					'b' => 3,
				),
				array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'integer' ),
					'enum'                 => array(
						array(
							'a' => 1,
							'b' => 2,
						),
						array(
							'b' => 2,
							'c' => 3,
						),
					),
				),
				false,
			),
			array(
				array(
					'c' => 3,
					'd' => 4,
				),
				array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'integer' ),
					'enum'                 => array(
						array(
							'a' => 1,
							'b' => 2,
						),
						array(
							'b' => 2,
							'c' => 3,
						),
					),
				),
				false,
			),
		);
	}

	public function test_type_array_is_associative() {
		$schema = array(
			'type'  => 'array',
			'items' => array(
				'type' => 'string',
			),
		);
		$this->assertWPError(
			rest_validate_value_from_schema(
				array(
					'first'  => '1',
					'second' => '2',
				),
				$schema
			)
		);
	}

	public function test_type_object() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'a' => array(
					'type' => 'number',
				),
			),
		);
		$this->assertTrue( rest_validate_value_from_schema( array( 'a' => 1 ), $schema ) );
		$this->assertTrue(
			rest_validate_value_from_schema(
				array(
					'a' => 1,
					'b' => 2,
				),
				$schema
			)
		);
		$this->assertWPError( rest_validate_value_from_schema( array( 'a' => 'invalid' ), $schema ) );
	}

	/**
	 * @ticket 51024
	 *
	 * @dataProvider data_type_object_pattern_properties
	 *
	 * @param array $pattern_properties
	 * @param array $value
	 * @param bool $expected
	 */
	public function test_type_object_pattern_properties( $pattern_properties, $value, $expected ) {
		$schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'propA' => array( 'type' => 'string' ),
			),
			'patternProperties'    => $pattern_properties,
			'additionalProperties' => false,
		);

		if ( $expected ) {
			$this->assertTrue( rest_validate_value_from_schema( $value, $schema ) );
		} else {
			$this->assertWPError( rest_validate_value_from_schema( $value, $schema ) );
		}
	}

	/**
	 * @return array
	 */
	public function data_type_object_pattern_properties() {
		return array(
			array( array(), array(), true ),
			array( array(), array( 'propA' => 'a' ), true ),
			array(
				array(),
				array(
					'propA' => 'a',
					'propB' => 'b',
				),
				false,
			),
			array(
				array(
					'propB' => array( 'type' => 'string' ),
				),
				array( 'propA' => 'a' ),
				true,
			),
			array(
				array(
					'propB' => array( 'type' => 'string' ),
				),
				array(
					'propA' => 'a',
					'propB' => 'b',
				),
				true,
			),
			array(
				array(
					'.*C' => array( 'type' => 'string' ),
				),
				array(
					'propA' => 'a',
					'propC' => 'c',
				),
				true,
			),
			array(
				array(
					'[0-9]' => array( 'type' => 'integer' ),
				),
				array(
					'propA' => 'a',
					'prop0' => 0,
				),
				true,
			),
			array(
				array(
					'[0-9]' => array( 'type' => 'integer' ),
				),
				array(
					'propA' => 'a',
					'prop0' => 'notAnInteger',
				),
				false,
			),
			array(
				array(
					'.+' => array( 'type' => 'string' ),
				),
				array(
					''      => '',
					'propA' => 'a',
				),
				false,
			),
		);
	}

	public function test_type_object_additional_properties_false() {
		$schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'a' => array(
					'type' => 'number',
				),
			),
			'additionalProperties' => false,
		);
		$this->assertTrue( rest_validate_value_from_schema( array( 'a' => 1 ), $schema ) );
		$this->assertWPError(
			rest_validate_value_from_schema(
				array(
					'a' => 1,
					'b' => 2,
				),
				$schema
			)
		);
	}

	public function test_type_object_nested() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'a' => array(
					'type'       => 'object',
					'properties' => array(
						'b' => array( 'type' => 'number' ),
						'c' => array( 'type' => 'number' ),
					),
				),
			),
		);
		$this->assertTrue(
			rest_validate_value_from_schema(
				array(
					'a' => array(
						'b' => '1',
						'c' => 3,
					),
				),
				$schema
			)
		);
		$this->assertWPError(
			rest_validate_value_from_schema(
				array(
					'a' => array(
						'b' => 1,
						'c' => 'invalid',
					),
				),
				$schema
			)
		);
		$this->assertWPError( rest_validate_value_from_schema( array( 'a' => 1 ), $schema ) );
	}

	public function test_type_object_stdclass() {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'a' => array(
					'type' => 'number',
				),
			),
		);
		$this->assertTrue( rest_validate_value_from_schema( (object) array( 'a' => 1 ), $schema ) );
	}

	/**
	 * @ticket 42961
	 */
	public function test_type_object_allows_empty_string() {
		$this->assertTrue( rest_validate_value_from_schema( '', array( 'type' => 'object' ) ) );
	}

	public function test_type_unknown() {
		$this->setExpectedIncorrectUsage( 'rest_validate_value_from_schema' );

		$schema = array(
			'type' => 'lalala',
		);
		$this->assertTrue( rest_validate_value_from_schema( 'Best lyrics', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( 1, $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( array(), $schema ) );
	}

	public function test_type_null() {
		$this->assertTrue( rest_validate_value_from_schema( null, array( 'type' => 'null' ) ) );
		$this->assertWPError( rest_validate_value_from_schema( '', array( 'type' => 'null' ) ) );
		$this->assertWPError( rest_validate_value_from_schema( 'null', array( 'type' => 'null' ) ) );
	}

	public function test_nullable_date() {
		$schema = array(
			'type'   => array( 'string', 'null' ),
			'format' => 'date-time',
		);

		$this->assertTrue( rest_validate_value_from_schema( null, $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( '2019-09-19T18:00:00', $schema ) );

		$error = rest_validate_value_from_schema( 'some random string', $schema );
		$this->assertWPError( $error );
		$this->assertSame( 'Invalid date.', $error->get_error_message() );
	}

	public function test_object_or_string() {
		$schema = array(
			'type'       => array( 'object', 'string' ),
			'properties' => array(
				'raw' => array(
					'type' => 'string',
				),
			),
		);

		$this->assertTrue( rest_validate_value_from_schema( 'My Value', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( array( 'raw' => 'My Value' ), $schema ) );

		$error = rest_validate_value_from_schema( array( 'raw' => array( 'a list' ) ), $schema );
		$this->assertWPError( $error );
		$this->assertSame( '[raw] is not of type string.', $error->get_error_message() );
	}

	/**
	 * @ticket 50300
	 */
	public function test_null_or_integer() {
		$schema = array(
			'type'    => array( 'null', 'integer' ),
			'minimum' => 10,
			'maximum' => 20,
		);

		$this->assertTrue( rest_validate_value_from_schema( null, $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( 15, $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( '15', $schema ) );

		$error = rest_validate_value_from_schema( 30, $schema, 'param' );
		$this->assertWPError( $error );
		$this->assertSame( 'param must be between 10 (inclusive) and 20 (inclusive)', $error->get_error_message() );
	}

	/**
	 * @ticket 51022
	 *
	 * @dataProvider data_multiply_of
	 *
	 * @param int|float $value
	 * @param int|float $divisor
	 * @param bool      $expected
	 */
	public function test_numeric_multiple_of( $value, $divisor, $expected ) {
		$schema = array(
			'type'       => 'number',
			'multipleOf' => $divisor,
		);

		$result = rest_validate_value_from_schema( $value, $schema );

		if ( $expected ) {
			$this->assertTrue( $result );
		} else {
			$this->assertWPError( $result );
		}
	}

	public function data_multiply_of() {
		return array(
			array( 0, 2, true ),
			array( 4, 2, true ),
			array( 3, 1.5, true ),
			array( 2.4, 1.2, true ),
			array( 1, 2, false ),
			array( 2, 1.5, false ),
			array( 2.1, 1.5, false ),
		);
	}

	/**
	 * @ticket 50300
	 */
	public function test_multi_type_with_no_known_types() {
		$this->setExpectedIncorrectUsage( 'rest_handle_multi_type_schema' );
		$this->setExpectedIncorrectUsage( 'rest_validate_value_from_schema' );

		$schema = array(
			'type' => array( 'invalid', 'type' ),
		);

		$this->assertTrue( rest_validate_value_from_schema( 'My Value', $schema ) );
	}

	/**
	 * @ticket 50300
	 */
	public function test_multi_type_with_some_unknown_types() {
		$this->setExpectedIncorrectUsage( 'rest_handle_multi_type_schema' );
		$this->setExpectedIncorrectUsage( 'rest_validate_value_from_schema' );

		$schema = array(
			'type' => array( 'object', 'type' ),
		);

		$this->assertTrue( rest_validate_value_from_schema( 'My Value', $schema ) );
	}

	/**
	 * @ticket 48820
	 */
	public function test_string_min_length() {
		$schema = array(
			'type'      => 'string',
			'minLength' => 2,
		);

		// longer
		$this->assertTrue( rest_validate_value_from_schema( 'foo', $schema ) );
		// exact
		$this->assertTrue( rest_validate_value_from_schema( 'fo', $schema ) );
		// non-strings does not validate
		$this->assertWPError( rest_validate_value_from_schema( 1, $schema ) );
		// to short
		$this->assertWPError( rest_validate_value_from_schema( 'f', $schema ) );
		// one supplementary Unicode code point is not long enough
		$mb_char = mb_convert_encoding( '&#x1000;', 'UTF-8', 'HTML-ENTITIES' );
		$this->assertWPError( rest_validate_value_from_schema( $mb_char, $schema ) );
		// two supplementary Unicode code point is long enough
		$this->assertTrue( rest_validate_value_from_schema( $mb_char . $mb_char, $schema ) );
	}

	/**
	 * @ticket 48820
	 */
	public function test_string_max_length() {
		$schema = array(
			'type'      => 'string',
			'maxLength' => 2,
		);

		// shorter
		$this->assertTrue( rest_validate_value_from_schema( 'f', $schema ) );
		// exact
		$this->assertTrue( rest_validate_value_from_schema( 'fo', $schema ) );
		// to long
		$this->assertWPError( rest_validate_value_from_schema( 'foo', $schema ) );
		// non string
		$this->assertWPError( rest_validate_value_from_schema( 100, $schema ) );
		// two supplementary Unicode code point is long enough
		$mb_char = mb_convert_encoding( '&#x1000;', 'UTF-8', 'HTML-ENTITIES' );
		$this->assertTrue( rest_validate_value_from_schema( $mb_char, $schema ) );
		// three supplementary Unicode code point is to long
		$this->assertWPError( rest_validate_value_from_schema( $mb_char . $mb_char . $mb_char, $schema ) );
	}

	/**
	 * @ticket 48818
	 *
	 * @dataProvider data_required_property
	 */
	public function test_property_is_required( $data, $expected ) {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'my_prop'          => array(
					'type' => 'string',
				),
				'my_required_prop' => array(
					'type'     => 'string',
					'required' => true,
				),
			),
		);

		$valid = rest_validate_value_from_schema( $data, $schema );

		if ( $expected ) {
			$this->assertTrue( $valid );
		} else {
			$this->assertWPError( $valid );
		}
	}

	/**
	 * @ticket 48818
	 *
	 * @dataProvider data_required_property
	 */
	public function test_property_is_required_v4( $data, $expected ) {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'my_prop'          => array(
					'type' => 'string',
				),
				'my_required_prop' => array(
					'type' => 'string',
				),
			),
			'required'   => array( 'my_required_prop' ),
		);

		$valid = rest_validate_value_from_schema( $data, $schema );

		if ( $expected ) {
			$this->assertTrue( $valid );
		} else {
			$this->assertWPError( $valid );
		}
	}

	public function data_required_property() {
		return array(
			array(
				array(
					'my_required_prop' => 'test',
					'my_prop'          => 'test',
				),
				true,
			),
			array( array( 'my_prop' => 'test' ), false ),
			array( array(), false ),
		);
	}

	/**
	 * @ticket 48818
	 *
	 * @dataProvider data_required_nested_property
	 */
	public function test_nested_property_is_required( $data, $expected ) {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'my_object' => array(
					'type'       => 'object',
					'properties' => array(
						'my_nested_prop'          => array(
							'type' => 'string',
						),
						'my_required_nested_prop' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			),
		);

		$valid = rest_validate_value_from_schema( $data, $schema );

		if ( $expected ) {
			$this->assertTrue( $valid );
		} else {
			$this->assertWPError( $valid );
		}
	}

	/**
	 * @ticket 48818
	 *
	 * @dataProvider data_required_nested_property
	 */
	public function test_nested_property_is_required_v4( $data, $expected ) {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'my_object' => array(
					'type'       => 'object',
					'properties' => array(
						'my_nested_prop'          => array(
							'type' => 'string',
						),
						'my_required_nested_prop' => array(
							'type' => 'string',
						),
					),
					'required'   => array( 'my_required_nested_prop' ),
				),
			),
		);

		$valid = rest_validate_value_from_schema( $data, $schema );

		if ( $expected ) {
			$this->assertTrue( $valid );
		} else {
			$this->assertWPError( $valid );
		}
	}

	public function data_required_nested_property() {
		return array(
			array(
				array(
					'my_object' => array(
						'my_required_nested_prop' => 'test',
						'my_nested_prop'          => 'test',
					),
				),
				true,
			),
			array(
				array(
					'my_object' => array(
						'my_nested_prop' => 'test',
					),
				),
				false,
			),
			array(
				array(),
				true,
			),
		);
	}

	/**
	 * @ticket 48818
	 *
	 * @dataProvider data_required_deeply_nested_property
	 */
	public function test_deeply_nested_v3_required_property( $value, $expected ) {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'propA' => array(
					'type'       => 'object',
					'required'   => true,
					'properties' => array(
						'propB' => array(
							'type'       => 'object',
							'required'   => true,
							'properties' => array(
								'propC' => array(
									'type'     => 'string',
									'required' => true,
								),
								'propD' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			),
		);

		$valid = rest_validate_value_from_schema( $value, $schema );

		if ( $expected ) {
			$this->assertTrue( $valid );
		} else {
			$this->assertWPError( $valid );
		}
	}

	/**
	 * @ticket 48818
	 *
	 * @dataProvider data_required_deeply_nested_property
	 */
	public function test_deeply_nested_v4_required_property( $value, $expected ) {
		$schema = array(
			'type'       => 'object',
			'required'   => array( 'propA' ),
			'properties' => array(
				'propA' => array(
					'type'       => 'object',
					'required'   => array( 'propB' ),
					'properties' => array(
						'propB' => array(
							'type'       => 'object',
							'required'   => array( 'propC' ),
							'properties' => array(
								'propC' => array(
									'type' => 'string',
								),
								'propD' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			),
		);

		$valid = rest_validate_value_from_schema( $value, $schema );

		if ( $expected ) {
			$this->assertTrue( $valid );
		} else {
			$this->assertWPError( $valid );
		}
	}

	/**
	 * @ticket 48818
	 *
	 * @dataProvider data_required_deeply_nested_property
	 */
	public function test_deeply_nested_mixed_version_required_property( $value, $expected ) {
		$schema = array(
			'type'       => 'object',
			'required'   => array( 'propA' ),
			'properties' => array(
				'propA' => array(
					'type'       => 'object',
					'required'   => array( 'propB' ),
					'properties' => array(
						'propB' => array(
							'type'       => 'object',
							'properties' => array(
								'propC' => array(
									'type'     => 'string',
									'required' => true,
								),
								'propD' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			),
		);

		$valid = rest_validate_value_from_schema( $value, $schema );

		if ( $expected ) {
			$this->assertTrue( $valid );
		} else {
			$this->assertWPError( $valid );
		}
	}

	public function data_required_deeply_nested_property() {
		return array(
			array(
				array(),
				false,
			),
			array(
				array(
					'propA' => array(),
				),
				false,
			),
			array(
				array(
					'propA' => array(
						'propB' => array(),
					),
				),
				false,
			),
			array(
				array(
					'propA' => array(
						'propB' => array(
							'propD' => 'd',
						),
					),
				),
				false,
			),
			array(
				array(
					'propA' => array(
						'propB' => array(
							'propC' => 'c',
						),
					),
				),
				true,
			),
		);
	}

	/**
	 * @ticket 51023
	 */
	public function test_object_min_properties() {
		$schema = array(
			'type'          => 'object',
			'minProperties' => 1,
		);

		$this->assertTrue(
			rest_validate_value_from_schema(
				array(
					'propA' => 'a',
					'propB' => 'b',
				),
				$schema
			)
		);
		$this->assertTrue( rest_validate_value_from_schema( array( 'propA' => 'a' ), $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( array(), $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( '', $schema ) );
	}

	/**
	 * @ticket 51023
	 */
	public function test_object_max_properties() {
		$schema = array(
			'type'          => 'object',
			'maxProperties' => 2,
		);

		$this->assertTrue( rest_validate_value_from_schema( array( 'propA' => 'a' ), $schema ) );
		$this->assertTrue(
			rest_validate_value_from_schema(
				array(
					'propA' => 'a',
					'propB' => 'b',
				),
				$schema
			)
		);
		$this->assertWPError(
			rest_validate_value_from_schema(
				array(
					'propA' => 'a',
					'propB' => 'b',
					'propC' => 'c',
				),
				$schema
			)
		);
		$this->assertWPError( rest_validate_value_from_schema( 'foobar', $schema ) );
	}

	/**
	 * @ticket 44949
	 */
	public function test_string_pattern() {
		$schema = array(
			'type'    => 'string',
			'pattern' => '^a*$',
		);

		$this->assertTrue( rest_validate_value_from_schema( 'a', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 'b', $schema ) );
	}

	/**
	 * @ticket 44949
	 */
	public function test_string_pattern_with_escaped_delimiter() {
		$schema = array(
			'type'    => 'string',
			'pattern' => '#[0-9]+',
		);

		$this->assertTrue( rest_validate_value_from_schema( '#123', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( '#abc', $schema ) );
	}

	/**
	 * @ticket 44949
	 */
	public function test_string_pattern_with_utf8() {
		$schema = array(
			'type'    => 'string',
			'pattern' => '^â{1}$',
		);

		$this->assertTrue( rest_validate_value_from_schema( 'â', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 'ââ', $schema ) );
	}

	/**
	 * @ticket 48821
	 */
	public function test_array_min_items() {
		$schema = array(
			'type'     => 'array',
			'minItems' => 1,
			'items'    => array(
				'type' => 'number',
			),
		);

		$this->assertTrue( rest_validate_value_from_schema( array( 1, 2 ), $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( array( 1 ), $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( array(), $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( '', $schema ) );
	}

	/**
	 * @ticket 48821
	 */
	public function test_array_max_items() {
		$schema = array(
			'type'     => 'array',
			'maxItems' => 2,
			'items'    => array(
				'type' => 'number',
			),
		);

		$this->assertTrue( rest_validate_value_from_schema( array( 1 ), $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( array( 1, 2 ), $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( array( 1, 2, 3 ), $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 'foobar', $schema ) );
	}

	/**
	 * @ticket 48821
	 *
	 * @dataProvider data_unique_items
	 */
	public function test_unique_items( $test, $suite ) {
		$test_description = $suite['description'] . ': ' . $test['description'];
		$message          = $test_description . ': ' . var_export( $test['data'], true );

		$valid = rest_validate_value_from_schema( $test['data'], $suite['schema'] );

		if ( $test['valid'] ) {
			$this->assertTrue( $valid, $message );
		} else {
			$this->assertWPError( $valid, $message );
		}
	}

	public function data_unique_items() {
		$all_types = array( 'object', 'array', 'null', 'number', 'integer', 'boolean', 'string' );

		// the following test suites is not supported at the moment
		$skip   = array(
			'uniqueItems with an array of items',
			'uniqueItems with an array of items and additionalItems=false',
			'uniqueItems=false with an array of items',
			'uniqueItems=false with an array of items and additionalItems=false',
		);
		$suites = json_decode( file_get_contents( __DIR__ . '/json_schema_test_suite/uniqueitems.json' ), true );

		$tests = array();

		foreach ( $suites as $suite ) {
			if ( in_array( $suite['description'], $skip, true ) ) {
				continue;
			}
			// type is required for our implementation
			if ( ! isset( $suite['schema']['type'] ) ) {
				$suite['schema']['type'] = 'array';
			}
			// items is required for our implementation
			if ( ! isset( $suite['schema']['items'] ) ) {
				$suite['schema']['items'] = array(
					'type'  => $all_types,
					'items' => array(
						'type' => $all_types,
					),
				);
			}
			foreach ( $suite['tests'] as $test ) {
				$tests[] = array( $test, $suite );
			}
		}

		return $tests;
	}

	/**
	 * @ticket 48821
	 */
	public function test_unique_items_deep_objects() {
		$schema = array(
			'type'        => 'array',
			'uniqueItems' => true,
			'items'       => array(
				'type'       => 'object',
				'properties' => array(
					'release' => array(
						'type'       => 'object',
						'properties' => array(
							'name'    => array(
								'type' => 'string',
							),
							'version' => array(
								'type' => 'string',
							),
						),
					),
				),
			),
		);

		$data = array(
			array(
				'release' => array(
					'name'    => 'Kirk',
					'version' => '5.3',
				),
			),
			array(
				'release' => array(
					'version' => '5.3',
					'name'    => 'Kirk',
				),
			),
		);

		$this->assertWPError( rest_validate_value_from_schema( $data, $schema ) );

		$data[0]['release']['version'] = '5.3.0';
		$this->assertTrue( rest_validate_value_from_schema( $data, $schema ) );
	}

	/**
	 * @ticket 48821
	 */
	public function test_unique_items_deep_arrays() {
		$schema = array(
			'type'        => 'array',
			'uniqueItems' => true,
			'items'       => array(
				'type'  => 'array',
				'items' => array(
					'type' => 'string',
				),
			),
		);

		$data = array(
			array(
				'Kirk',
				'Jaco',
			),
			array(
				'Kirk',
				'Jaco',
			),
		);

		$this->assertWPError( rest_validate_value_from_schema( $data, $schema ) );

		$data[1] = array_reverse( $data[1] );
		$this->assertTrue( rest_validate_value_from_schema( $data, $schema ) );
	}

	/**
	 * @ticket 50300
	 */
	public function test_string_or_integer() {
		$schema = array(
			'type' => array( 'integer', 'string' ),
		);

		$this->assertTrue( rest_validate_value_from_schema( 'garbage', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( 15, $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( '15', $schema ) );
		$this->assertTrue( rest_validate_value_from_schema( '15.5', $schema ) );
		$this->assertWPError( rest_validate_value_from_schema( 15.5, $schema ) );
	}

	/**
	 * @ticket 51025
	 *
	 * @dataProvider data_any_of
	 *
	 * @param array $data
	 * @param array $schema
	 * @param bool $valid
	 */
	public function test_any_of( $data, $schema, $valid ) {
		$is_valid = rest_validate_value_from_schema( $data, $schema );

		if ( $valid ) {
			$this->assertTrue( $is_valid );
		} else {
			$this->assertWPError( $is_valid );
		}
	}

	/**
	 * @return array
	 */
	public function data_any_of() {
		$suites = json_decode( file_get_contents( __DIR__ . '/json_schema_test_suite/anyof.json' ), true );
		$skip   = array(
			'anyOf with boolean schemas, all true',
			'anyOf with boolean schemas, some true',
			'anyOf with boolean schemas, all false',
			'anyOf with one empty schema',
			'nested anyOf, to check validation semantics',
		);

		$tests = array();

		foreach ( $suites as $suite ) {
			if ( in_array( $suite['description'], $skip, true ) ) {
				continue;
			}

			foreach ( $suite['tests'] as $test ) {
				$tests[ $suite['description'] . ': ' . $test['description'] ] = array(
					$test['data'],
					$suite['schema'],
					$test['valid'],
				);
			}
		}

		return $tests;
	}

	/**
	 * @ticket 51025
	 *
	 * @dataProvider data_one_of
	 *
	 * @param array $data
	 * @param array $schema
	 * @param bool $valid
	 */
	public function test_one_of( $data, $schema, $valid ) {
		$is_valid = rest_validate_value_from_schema( $data, $schema );

		if ( $valid ) {
			$this->assertTrue( $is_valid );
		} else {
			$this->assertWPError( $is_valid );
		}
	}

	/**
	 * @return array
	 */
	public function data_one_of() {
		$suites = json_decode( file_get_contents( __DIR__ . '/json_schema_test_suite/oneof.json' ), true );
		$skip   = array(
			'oneOf with boolean schemas, all true',
			'oneOf with boolean schemas, one true',
			'oneOf with boolean schemas, more than one true',
			'oneOf with boolean schemas, all false',
			'oneOf with empty schema',
			'nested oneOf, to check validation semantics',
		);

		$tests = array();

		foreach ( $suites as $suite ) {
			if ( in_array( $suite['description'], $skip, true ) ) {
				continue;
			}

			foreach ( $suite['tests'] as $test ) {
				$tests[ $suite['description'] . ': ' . $test['description'] ] = array(
					$test['data'],
					$suite['schema'],
					$test['valid'],
				);
			}
		}

		return $tests;
	}

	/**
	 * @ticket 51025
	 *
	 * @dataProvider data_combining_operation_error_message
	 *
	 * @param $data
	 * @param $schema
	 * @param $expected
	 */
	public function test_combining_operation_error_message( $data, $schema, $expected ) {
		$is_valid = rest_validate_value_from_schema( $data, $schema, 'foo' );

		$this->assertWPError( $is_valid );
		$this->assertSame( $expected, $is_valid->get_error_message() );
	}

	/**
	 * @return array
	 */
	public function data_combining_operation_error_message() {
		return array(
			array(
				10,
				array(
					'anyOf' => array(
						array(
							'title'   => 'circle',
							'type'    => 'integer',
							'maximum' => 5,
						),
					),
				),
				'foo is not a valid circle. Reason: foo must be less than or equal to 5',
			),
			array(
				10,
				array(
					'anyOf' => array(
						array(
							'type'    => 'integer',
							'maximum' => 5,
						),
					),
				),
				'foo does not match the expected format. Reason: foo must be less than or equal to 5',
			),
			array(
				array( 'a' => 1 ),
				array(
					'anyOf' => array(
						array( 'type' => 'boolean' ),
						array(
							'title'      => 'circle',
							'type'       => 'object',
							'properties' => array(
								'a' => array( 'type' => 'string' ),
							),
						),
					),
				),
				'foo is not a valid circle. Reason: foo[a] is not of type string.',
			),
			array(
				array( 'a' => 1 ),
				array(
					'anyOf' => array(
						array( 'type' => 'boolean' ),
						array(
							'type'       => 'object',
							'properties' => array(
								'a' => array( 'type' => 'string' ),
							),
						),
					),
				),
				'foo does not match the expected format. Reason: foo[a] is not of type string.',
			),
			array(
				array(
					'a' => 1,
					'b' => 2,
					'c' => 3,
				),
				array(
					'anyOf' => array(
						array( 'type' => 'boolean' ),
						array(
							'type'       => 'object',
							'properties' => array(
								'a' => array( 'type' => 'string' ),
							),
						),
						array(
							'title'      => 'square',
							'type'       => 'object',
							'properties' => array(
								'b' => array( 'type' => 'string' ),
								'c' => array( 'type' => 'string' ),
							),
						),
						array(
							'type'       => 'object',
							'properties' => array(
								'b' => array( 'type' => 'boolean' ),
								'x' => array( 'type' => 'boolean' ),
							),
						),
					),
				),
				'foo is not a valid square. Reason: foo[b] is not of type string.',
			),
			array(
				array(
					'a' => 1,
					'b' => 2,
					'c' => 3,
				),
				array(
					'anyOf' => array(
						array( 'type' => 'boolean' ),
						array(
							'type'       => 'object',
							'properties' => array(
								'a' => array( 'type' => 'string' ),
							),
						),
						array(
							'type'       => 'object',
							'properties' => array(
								'b' => array( 'type' => 'string' ),
								'c' => array( 'type' => 'string' ),
							),
						),
						array(
							'type'       => 'object',
							'properties' => array(
								'b' => array( 'type' => 'boolean' ),
								'x' => array( 'type' => 'boolean' ),
							),
						),
					),
				),
				'foo does not match the expected format. Reason: foo[b] is not of type string.',
			),
			array(
				'test',
				array(
					'anyOf' => array(
						array(
							'title' => 'circle',
							'type'  => 'boolean',
						),
						array(
							'title' => 'square',
							'type'  => 'integer',
						),
						array(
							'title' => 'triangle',
							'type'  => 'null',
						),
					),
				),
				'foo is not a valid circle, square, and triangle.',
			),
			array(
				'test',
				array(
					'anyOf' => array(
						array( 'type' => 'boolean' ),
						array( 'type' => 'integer' ),
						array( 'type' => 'null' ),
					),
				),
				'foo does not match any of the expected formats.',
			),
			array(
				'test',
				array(
					'oneOf' => array(
						array(
							'title' => 'circle',
							'type'  => 'string',
						),
						array( 'type' => 'integer' ),
						array(
							'title' => 'triangle',
							'type'  => 'string',
						),
					),
				),
				'foo matches circle and triangle, but should match only one.',
			),
			array(
				'test',
				array(
					'oneOf' => array(
						array( 'type' => 'string' ),
						array( 'type' => 'integer' ),
						array( 'type' => 'string' ),
					),
				),
				'foo matches more than one of the expected formats.',
			),
		);
	}
}
