<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;
use stdClass;

/**
 * Tests for the `maybe_unserialize()` function.
 *
 * @group functions
 *
 * @covers ::maybe_unserialize
 */
class MaybeUnserializeTest extends WP_UnitTestCase {
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
	 * @return array[]
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
	 * @return array[]
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
}
