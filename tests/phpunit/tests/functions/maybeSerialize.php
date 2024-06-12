<?php

/**
 * Tests for `maybe_serialize()` and `maybe_unserialize()`.
 *
 * @group functions.php
 * @covers ::maybe_serialize
 * @covers ::maybe_unserialize
 */
class Tests_Functions_MaybeSerialize extends WP_UnitTestCase {

	/**
	 * @dataProvider data_is_not_serialized
	 */
	public function test_maybe_serialize( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			$expected = serialize( $value );
		} else {
			$expected = $value;
		}

		$this->assertSame( $expected, maybe_serialize( $value ) );
	}

	/**
	 * @dataProvider data_is_serialized
	 */
	public function test_maybe_serialize_with_double_serialization( $value ) {
		$expected = serialize( $value );

		$this->assertSame( $expected, maybe_serialize( $value ) );
	}

	/**
	 * @dataProvider data_is_serialized
	 * @dataProvider data_is_not_serialized
	 */
	public function test_maybe_unserialize( $value, $is_serialized ) {
		if ( $is_serialized ) {
			$expected = unserialize( trim( $value ) );
		} else {
			$expected = $value;
		}

		if ( is_object( $expected ) ) {
			$this->assertEquals( $expected, maybe_unserialize( $value ) );
		} else {
			$this->assertSame( $expected, maybe_unserialize( $value ) );
		}
	}

	/**
	 * Data provider for `test_maybe_unserialize()`.
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
		);
	}

	/**
	 * Data provider for `test_maybe_serialize()`.
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
	 * @dataProvider data_serialize_deserialize_objects
	 */
	public function test_deserialize_request_utility_filtered_iterator_objects( $value ) {
		$serialized = maybe_serialize( $value );

		if ( get_class( $value ) === 'Requests_Utility_FilteredIterator' ) {
			$new_value = unserialize( $serialized );
			$property  = ( new ReflectionClass( 'Requests_Utility_FilteredIterator' ) )->getProperty( 'callback' );
			$property->setAccessible( true );
			$callback_value = $property->getValue( $new_value );

			$this->assertSame( null, $callback_value );
		} else {
			$this->assertSame( $value->count(), unserialize( $serialized )->count() );
		}
	}

	/**
	 * Data provider for test_deserialize_request_utility_filtered_iterator_objects().
	 *
	 * @return array
	 */
	public function data_serialize_deserialize_objects() {
		return array(
			'filtered iterator using md5'  => array(
				new Requests_Utility_FilteredIterator( array( 1 ), 'md5' ),
			),
			'filtered iterator using sha1' => array(
				new Requests_Utility_FilteredIterator( array( 1, 2 ), 'sha1' ),
			),
			'array iterator'               => array(
				new ArrayIterator( array( 1, 2, 3 ) ),
			),
		);
	}
}
