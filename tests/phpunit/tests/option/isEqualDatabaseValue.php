<?php
/**
 * Test is_equal_database_value().
 *
 * @covers ::is_equal_database_value
 */
class Tests_Is_Equal_Database_Value extends WP_UnitTestCase {

	/**
	 * @ticket 22192
	 *
	 * @dataProvider data_is_equal_database_value
	 *
	 * @param mixed $old_value The old value to compare.
	 * @param mixed $new_value The new value to compare.
	 * @param int   $expected  The expected result.
	 */
	public function test_is_equal_database_value( $old_value, $new_value, $expected ) {
		$this->assertEquals( $expected, is_equal_database_value( $old_value, $new_value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_is_equal_database_value() {
		return array(
			// Equal values.
			array( '123', '123', true ),

			// Not equal values.
			array( '123', '456', false ),

			// Truthy.
			array( 1, '1', true ),
			array( 1.0, '1', true ),
			array( '1', '1', true ),
			array( true, '1', true ),
			array( '1.0', '1', false ),
			array( '    ', '1', false ),
			array( array( '0' ), '1', false ),
			array( new stdClass(), '1', false ),
			array( 'Howdy, admin!', '1', false ),

			// False-ish values and empty strings.
			array( 0, '0', true ),
			array( 0.0, '0', true ),
			array( '0', '0', true ),
			array( '', '0', true ),
			array( false, '0', true ),
			array( null, '0', false ),
			array( array(), '0', false ),

			// Object values.
			array( (object) array( 'foo' => 'bar' ), (object) array( 'foo' => 'bar' ), true ),
			array( (object) array( 'foo' => 'bar' ), (object) array( 'foo' => 'baz' ), false ),
			array( (object) array( 'foo' => 'bar' ), serialize( (object) array( 'foo' => 'bar' ) ), false ),
			array( serialize( (object) array( 'foo' => 'bar' ) ), (object) array( 'foo' => 'bar' ), false ),
			array( serialize( (object) array( 'foo' => 'bar' ) ), (object) array( 'foo' => 'baz' ), false ),

			// Serialized values.
			array( array( 'foo' => 'bar' ), serialize( array( 'foo' => 'bar' ) ), false ),
			array( array( 'foo' => 'bar' ), serialize( array( 'foo' => 'baz' ) ), false ),
			array( serialize( (object) array( 'foo' => 'bar' ) ), serialize( (object) array( 'foo' => 'bar' ) ), true ),
			array( serialize( (object) array( 'foo' => 'bar' ) ), serialize( (object) array( 'foo' => 'baz' ) ), false ),
		);
	}
}
