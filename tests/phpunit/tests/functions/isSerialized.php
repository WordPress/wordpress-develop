<?php

/**
 * Tests for `is_serialized()`.
 *
 * @ticket 53299
 *
 * @group functions.php
 * @covers ::is_serialized
 */
class Tests_Functions_IsSerialized extends WP_UnitTestCase {

	/**
	 * @dataProvider data_is_serialized
	 * @dataProvider data_is_not_serialized
	 *
	 * @param mixed $data     Data value to test.
	 * @param bool  $expected Expected function result.
	 */
	public function test_is_serialized( $data, $expected ) {
		$this->assertSame( $expected, is_serialized( $data ) );
	}

	/**
	 * Data provider for `test_is_serialized()`.
	 *
	 * @return array
	 */
	public function data_is_serialized() {
		return array(
			'serialized empty array'            => array(
				'data'     => serialize( array() ),
				'expected' => true,
			),
			'serialized non-empty array'        => array(
				'data'     => serialize( array( 1, 1, 2, 3, 5, 8, 13 ) ),
				'expected' => true,
			),
			'serialized empty object'           => array(
				'data'     => serialize( new stdClass() ),
				'expected' => true,
			),
			'serialized non-empty object'       => array(
				'data'     => serialize(
					(object) array(
						'test' => true,
						'1',
						2,
					)
				),
				'expected' => true,
			),
			'serialized null'                   => array(
				'data'     => serialize( null ),
				'expected' => true,
			),
			'serialized boolean true'           => array(
				'data'     => serialize( true ),
				'expected' => true,
			),
			'serialized boolean false'          => array(
				'data'     => serialize( false ),
				'expected' => true,
			),
			'serialized integer -1'             => array(
				'data'     => serialize( -1 ),
				'expected' => true,
			),
			'serialized integer 1'              => array(
				'data'     => serialize( -1 ),
				'expected' => true,
			),
			'serialized float 1.1'              => array(
				'data'     => serialize( 1.1 ),
				'expected' => true,
			),
			'serialized string'                 => array(
				'data'     => serialize( 'this string will be serialized' ),
				'expected' => true,
			),
			'serialized string with line break' => array(
				'data'     => serialize( "a\nb" ),
				'expected' => true,
			),
			'serialized string with leading and trailing spaces' => array(
				'data'     => '   s:25:"this string is serialized";   ',
				'expected' => true,
			),
			'serialized enum'                   => array(
				'data'     => 'E:7:"Foo:bar";',
				'expected' => true,
			),
		);
	}

	/**
	 * Data provider for `test_is_serialized()`.
	 *
	 * @return array
	 */
	public function data_is_not_serialized() {
		return array(
			'an empty array'                             => array(
				'data'     => array(),
				'expected' => false,
			),
			'a non-empty array'                          => array(
				'data'     => array( 1, 1, 2, 3, 5, 8, 13 ),
				'expected' => false,
			),
			'an empty object'                            => array(
				'data'     => new stdClass(),
				'expected' => false,
			),
			'a non-empty object'                         => array(
				'data'     => (object) array(
					'test' => true,
					'1',
					2,
				),
				'expected' => false,
			),
			'null'                                       => array(
				'data'     => null,
				'expected' => false,
			),
			'a boolean true'                             => array(
				'data'     => true,
				'expected' => false,
			),
			'a boolean false'                            => array(
				'data'     => false,
				'expected' => false,
			),
			'an integer -1'                              => array(
				'data'     => -1,
				'expected' => false,
			),
			'an integer 0'                               => array(
				'data'     => 0,
				'expected' => false,
			),
			'an integer 1'                               => array(
				'data'     => 1,
				'expected' => false,
			),
			'a float 0.0'                                => array(
				'data'     => 0.0,
				'expected' => false,
			),
			'a float 1.1'                                => array(
				'data'     => 1.1,
				'expected' => false,
			),
			'a string'                                   => array(
				'data'     => 'a string',
				'expected' => false,
			),
			'a string with line break'                   => array(
				'data'     => "a\nb",
				'expected' => false,
			),
			'a string with leading and trailing garbage' => array(
				'data'     => 'garbage:a:0:garbage;',
				'expected' => false,
			),
			'a string with missing double quotes'        => array(
				'data'     => 's:4:test;',
				'expected' => false,
			),
			'a string that is too short'                 => array(
				'data'     => 's:3',
				'expected' => false,
			),
			'not a colon in second position'             => array(
				'data'     => 's!3:"foo";',
				'expected' => false,
			),
			'no trailing semicolon (strict check)'       => array(
				'data'     => 's:3:"foo"',
				'expected' => false,
			),
		);
	}

	/**
	 * @ticket 46570
	 * @dataProvider data_is_serialized_should_return_true_for_large_floats
	 */
	public function test_is_serialized_should_return_true_for_large_floats( $value ) {
		$this->assertTrue( is_serialized( $value ) );
	}

	public function data_is_serialized_should_return_true_for_large_floats() {
		return array(
			array( serialize( 1.7976931348623157E+308 ) ),
			array( serialize( array( 1.7976931348623157E+308, 1.23e50 ) ) ),
		);
	}

	/**
	 * @ticket 17375
	 */
	public function test_no_new_serializable_types() {
		$this->assertFalse( is_serialized( 'C:16:"Serialized_Class":6:{a:0:{}}' ) );
	}
}
