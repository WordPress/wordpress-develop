<?php
/**
 * Unit tests covering schema validation and sanitization functionality.
 *
 * @package    WordPress
 * @subpackage REST API
 */

/**
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
		$this->assertWPError( rest_validate_value_from_schema( 'some random string', $schema ) );
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
		$this->assertWPError( rest_validate_value_from_schema( array( 'raw' => array( 'a list' ) ), $schema ) );
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
	 * @ticket       48818
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
	 * @ticket       48818
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
	 * @ticket       48818
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
	 * @ticket       48818
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
	 * @ticket       48818
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
	 * @ticket       48818
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
	 * @ticket       48818
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
}
