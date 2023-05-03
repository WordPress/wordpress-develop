<?php

/**
 * Tests for wp_decode_value_from_db().
 *
 * @since 6.3.0
 *
 * @group functions.php
 * @group meta
 * @group option
 *
 * @ticket 55942
 *
 * @covers ::wp_decode_value_from_db
 */
class Tests_Functions_WpDecodeValueFromDb extends WP_UnitTestCase {

	/**
	 * Tests that wp_decode_value_from_db() returns the correct value.
	 *
	 * @dataProvider data_wp_decode_value_from_db
	 *
	 * @param mixed  $value      The value to decode.
	 * @param string $value_type The type of the value.
	 * @param mixed  $expected   The expected decoded value.
	 */
	public function test_wp_decode_value_from_db( $value, $value_type, $expected ) {
		$this->assertSame( $expected, wp_decode_value_from_db( $value, $value_type ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_wp_decode_value_from_db() {
		return array(
			// boolean
			'boolean "1"'      => array( '1', 'boolean', true ),
			'boolean true'     => array( true, 'boolean', true ),
			'boolean "string"' => array( 'string', 'boolean', true ),
			'boolean "0"'      => array( '0', 'boolean', false ),
			'boolean ""'       => array( '', 'boolean', false ),
			'boolean false'    => array( false, 'boolean', false ),
			// integer
			'integer 42'   => array( 42, 'integer', 42 ),
			'integer "42"' => array( '42', 'integer', 42 ),
			'integer 0'    => array( 0, 'integer', 0 ),
			'integer "0"'  => array( '0', 'integer', 0 ),
			'integer 1'    => array( 1, 'integer', 1 ),
			'integer "1"'  => array( '1', 'integer', 1 ),
			// float
			'float 12.50'   => array( 12.50, 'float', 12.50 ),
			'float "12.50"' => array( '12.50', 'float', 12.50 ),
			'float 0'       => array( 0, 'float', 0.0 ),
			'float 1'       => array( 1, 'float', 1.0 ),
			// string
			'string "test"' => array( 'test', 'string', 'test' ),
			'string 12'     => array( 12, 'string', '12' ),
			'string true'   => array( true, 'string', '1' ),
			'string false'  => array( false, 'string', '' ),
			'string 12.435' => array( 12.435, 'string', '12.435' ),
			// array
			'serialized array'        => array( serialize( array( 'test' => 'value' ) ), 'array', array( 'test' => 'value' ) ),
			'serialized object array' => array( serialize( (object) array( 'test' => 'value' ) ), 'array', array( 'test' => 'value' ) ),
			'array'                   => array( array( 'test' => 'value' ), 'array', array( 'test' => 'value' ) ),
			'object array'            => array( (object) array( 'test' => 'value' ), 'array', array( 'test' => 'value' ) ),
		);
	}

	/**
	 * Tests that wp_decode_value_from_db() returns the correct value for objects.
	 */
	public function test_object_wp_decode_value_from_db() {
		$obj       = new \stdClass();
		$obj->test = 'value';
		$this->assertEquals( $obj, wp_decode_value_from_db( serialize( $obj ), 'object' ) );
		$this->assertEquals( $obj, wp_decode_value_from_db( serialize( (array) $obj ), 'object' ) );
		$this->assertEquals( $obj, wp_decode_value_from_db( $obj, 'object' ) );
		$this->assertEquals( $obj, wp_decode_value_from_db( (array) $obj, 'object' ) );
	}
}
