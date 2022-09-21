<?php

class Tests_Database_Types extends WP_UnitTestCase {

	public function test_database_types_list() {
		$this->assertEquals(
			array(
				WP_TYPE_BOOLEAN,
				WP_TYPE_INTEGER,
				WP_TYPE_FLOAT,
				WP_TYPE_STRING,
				WP_TYPE_ARRAY,
				WP_TYPE_OBJECT,
				WP_TYPE_UNKNOWN,
			),
			wp_get_database_types()
		);
	}

	public function test_database_default_type() {
		$this->assertEquals( WP_TYPE_UNKNOWN, wp_get_database_default_type() );
	}

	public function test_get_value_type_boolean() {
		$this->assertEquals( WP_TYPE_BOOLEAN, wp_get_database_type_for_value( true ) );
		$this->assertEquals( WP_TYPE_BOOLEAN, wp_get_database_type_for_value( false ) );
		$this->assertNotEquals( WP_TYPE_BOOLEAN, wp_get_database_type_for_value( 1 ) );
		$this->assertNotEquals( WP_TYPE_BOOLEAN, wp_get_database_type_for_value( 0 ) );
		$this->assertNotEquals( WP_TYPE_BOOLEAN, wp_get_database_type_for_value( '' ) );
		$this->assertNotEquals( WP_TYPE_BOOLEAN, wp_get_database_type_for_value( null ) );
	}

	public function test_get_value_type_integer() {
		$this->assertEquals( WP_TYPE_INTEGER, wp_get_database_type_for_value( 12 ) );
		$this->assertEquals( WP_TYPE_INTEGER, wp_get_database_type_for_value( 0 ) );
		$this->assertEquals( WP_TYPE_INTEGER, wp_get_database_type_for_value( 1 ) );
		$this->assertNotEquals( WP_TYPE_INTEGER, wp_get_database_type_for_value( '12' ) );
		$this->assertNotEquals( WP_TYPE_INTEGER, wp_get_database_type_for_value( 12.5 ) );
		$this->assertNotEquals( WP_TYPE_INTEGER, wp_get_database_type_for_value( '' ) );
		$this->assertNotEquals( WP_TYPE_INTEGER, wp_get_database_type_for_value( null ) );
	}

	public function test_get_value_type_float() {
		$this->assertEquals( WP_TYPE_FLOAT, wp_get_database_type_for_value( 12.50 ) );
		$this->assertNotEquals( WP_TYPE_FLOAT, wp_get_database_type_for_value( '12' ) );
		$this->assertNotEquals( WP_TYPE_FLOAT, wp_get_database_type_for_value( 12 ) );
		$this->assertNotEquals( WP_TYPE_FLOAT, wp_get_database_type_for_value( 0 ) );
		$this->assertNotEquals( WP_TYPE_FLOAT, wp_get_database_type_for_value( '' ) );
		$this->assertNotEquals( WP_TYPE_FLOAT, wp_get_database_type_for_value( null ) );
	}

	public function test_get_value_type_string() {
		$this->assertEquals( WP_TYPE_STRING, wp_get_database_type_for_value( 'string' ) );
		$this->assertEquals( WP_TYPE_STRING, wp_get_database_type_for_value( '' ) );
		$this->assertNotEquals( WP_TYPE_STRING, wp_get_database_type_for_value( 12 ) );
		$this->assertNotEquals( WP_TYPE_STRING, wp_get_database_type_for_value( 0 ) );
		$this->assertNotEquals( WP_TYPE_STRING, wp_get_database_type_for_value( null ) );
	}

	public function test_get_value_type_array() {
		$obj       = new \stdClass();
		$obj->test = 'test';
		$this->assertEquals( WP_TYPE_ARRAY, wp_get_database_type_for_value( array() ) );
		$this->assertEquals(
			WP_TYPE_ARRAY,
			wp_get_database_type_for_value(
				array(
					'test'  => 1,
					'test2' => 'value',
				)
			)
		);
		$this->assertNotEquals( WP_TYPE_ARRAY, wp_get_database_type_for_value( $obj ) );
		$this->assertNotEquals( WP_TYPE_ARRAY, wp_get_database_type_for_value( (object) array( 'test' => 'value' ) ) );
		$this->assertNotEquals(
			WP_TYPE_ARRAY,
			wp_get_database_type_for_value(
				serialize(
					array(
						'test'  => 1,
						'test2' => 'value',
					)
				)
			)
		);
	}

	public function test_get_value_type_object() {
		$obj       = new \stdClass();
		$obj->test = 'test';
		$this->assertEquals( WP_TYPE_OBJECT, wp_get_database_type_for_value( $obj ) );
		$this->assertEquals( WP_TYPE_OBJECT, wp_get_database_type_for_value( (object) array( 'test' => 'value' ) ) );
		$this->assertNotEquals( WP_TYPE_OBJECT, wp_get_database_type_for_value( array() ) );
		$this->assertNotEquals(
			WP_TYPE_OBJECT,
			wp_get_database_type_for_value(
				array(
					'test'  => 1,
					'test2' => 'value',
				)
			)
		);
		$this->assertNotEquals( WP_TYPE_OBJECT, wp_get_database_type_for_value( serialize( $obj ) ) );
	}

	public function test_prepare_value_for_db() {
		$obj       = new \stdClass();
		$obj->test = 'value';
		$this->assertEquals( true, wp_prepare_value_for_db( true ) );
		$this->assertEquals( 12, wp_prepare_value_for_db( 12 ) );
		$this->assertEquals( 12.43, wp_prepare_value_for_db( 12.43 ) );
		$this->assertEquals( 'test', wp_prepare_value_for_db( 'test' ) );
		$this->assertEquals( serialize( array( 'test' => 'value' ) ), wp_prepare_value_for_db( array( 'test' => 'value' ) ) );
		$this->assertEquals( serialize( $obj ), wp_prepare_value_for_db( $obj ) );
		$this->assertEquals( null, wp_prepare_value_for_db( null ) );
	}

	public function test_format_value_from_db() {

		$this->assertEquals( true, wp_format_value_from_db( WP_TYPE_BOOLEAN, 1 ) );
		$this->assertEquals( true, wp_format_value_from_db( WP_TYPE_BOOLEAN, true ) );
		$this->assertEquals( false, wp_format_value_from_db( WP_TYPE_BOOLEAN, 0 ) );
		$this->assertEquals( false, wp_format_value_from_db( WP_TYPE_BOOLEAN, false ) );

		$this->assertEquals( 12, wp_format_value_from_db( WP_TYPE_INTEGER, 12 ) );
		$this->assertEquals( 12, wp_format_value_from_db( WP_TYPE_INTEGER, '12' ) );
		$this->assertEquals( 0, wp_format_value_from_db( WP_TYPE_INTEGER, 0 ) );
		$this->assertEquals( 0, wp_format_value_from_db( WP_TYPE_INTEGER, '0' ) );
		$this->assertEquals( 1, wp_format_value_from_db( WP_TYPE_INTEGER, 1 ) );
		$this->assertEquals( 1, wp_format_value_from_db( WP_TYPE_INTEGER, '1' ) );

		$this->assertEquals( 12.50, wp_format_value_from_db( WP_TYPE_FLOAT, 12.50 ) );
		$this->assertEquals( 12.50, wp_format_value_from_db( WP_TYPE_FLOAT, '12.50' ) );
		$this->assertEquals( 0, wp_format_value_from_db( WP_TYPE_FLOAT, 0 ) );
		$this->assertEquals( 1, wp_format_value_from_db( WP_TYPE_FLOAT, 1 ) );

		$this->assertEquals( 'test', wp_format_value_from_db( WP_TYPE_STRING, 'test' ) );
		$this->assertEquals( '12', wp_format_value_from_db( WP_TYPE_STRING, 12 ) );
		$this->assertEquals( '1', wp_format_value_from_db( WP_TYPE_STRING, true ) );
		$this->assertEquals( '', wp_format_value_from_db( WP_TYPE_STRING, false ) );
		$this->assertEquals( '12.435', wp_format_value_from_db( WP_TYPE_STRING, 12.435 ) );

		$this->assertEquals( array( 'test' => 'value' ), wp_format_value_from_db( WP_TYPE_ARRAY, serialize( array( 'test' => 'value' ) ) ) );
		$this->assertEquals( array( 'test' => 'value' ), wp_format_value_from_db( WP_TYPE_ARRAY, serialize( (object) array( 'test' => 'value' ) ) ) );
		$this->assertEquals( array( 'test' => 'value' ), wp_format_value_from_db( WP_TYPE_ARRAY, array( 'test' => 'value' ) ) );
		$this->assertEquals( array( 'test' => 'value' ), wp_format_value_from_db( WP_TYPE_ARRAY, (object) array( 'test' => 'value' ) ) );

		$obj       = new \stdClass();
		$obj->test = 'value';
		$this->assertEquals( $obj, wp_format_value_from_db( WP_TYPE_OBJECT, serialize( $obj ) ) );
		$this->assertEquals( $obj, wp_format_value_from_db( WP_TYPE_OBJECT, serialize( (array) $obj ) ) );
		$this->assertEquals( $obj, wp_format_value_from_db( WP_TYPE_OBJECT, $obj ) );
		$this->assertEquals( $obj, wp_format_value_from_db( WP_TYPE_OBJECT, (array) $obj ) );
	}
}
