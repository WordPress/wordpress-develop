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
	 * Run tests on `is_serialized()`.
	 *
	 * @dataProvider data_is_serialized
	 *
	 * @param mixed $data     Data value to test.
	 * @param bool  $expected Expected function result.
	 */
	public function test_is_serialized_string( $data, $expected ) {
		$this->assertSame( $expected, is_serialized( $data ) );
	}

	/**
	 * Data provider method for testing `is_serialized()`.
	 *
	 * @return array
	 */
	public function data_is_serialized() {
		return array(
			'an array'                             => array(
				'data'     => array(),
				'expected' => false,
			),
			'an object'                            => array(
				'data'     => new stdClass(),
				'expected' => false,
			),
			'a boolean false'                      => array(
				'data'     => false,
				'expected' => false,
			),
			'a boolean true'                       => array(
				'data'     => true,
				'expected' => false,
			),
			'an integer 0'                         => array(
				'data'     => 0,
				'expected' => false,
			),
			'an integer 1'                         => array(
				'data'     => 1,
				'expected' => false,
			),
			'a float 0.0'                          => array(
				'data'     => 0.0,
				'expected' => false,
			),
			'a float 1.0'                          => array(
				'data'     => 1.0,
				'expected' => false,
			),
			'string that is too short'             => array(
				'data'     => 's:3',
				'expected' => false,
			),
			'not a colon in second position'       => array(
				'data'     => 's!3:"foo";',
				'expected' => false,
			),
			'no trailing semicolon (strict check)' => array(
				'data'     => 's:3:"foo"',
				'expected' => false,
			),
			'valid serialized null'                => array(
				'data'     => 'N;',
				'expected' => true,
			),
			'valid serialized Enum'                => array(
				'data'     => 'E:7:"Foo:bar";',
				'expected' => true,
			),
		);
	}
}
