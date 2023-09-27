<?php

/**
 * Tests for `is_serialized_string()`.
 *
 * @ticket 42870
 *
 * @group functions.php
 * @covers ::is_serialized_string
 */
class Tests_Functions_IsSerializedString extends WP_UnitTestCase {

	/**
	 * @dataProvider data_is_serialized_string
	 *
	 * @param array|object|int|string $data     Data value to test.
	 * @param bool                    $expected Expected function result.
	 */
	public function test_is_serialized_string( $data, $expected ) {
		$this->assertSame( $expected, is_serialized_string( $data ) );
	}

	/**
	 * Data provider for `test_is_serialized_string()`.
	 *
	 * @return array
	 */
	public function data_is_serialized_string() {
		return array(
			'an array'                                => array(
				'data'     => array(),
				'expected' => false,
			),
			'an object'                               => array(
				'data'     => new stdClass(),
				'expected' => false,
			),
			'an integer 0'                            => array(
				'data'     => 0,
				'expected' => false,
			),
			'a string that is too short when trimmed' => array(
				'data'     => 's:3       ',
				'expected' => false,
			),
			'a string that is too short'              => array(
				'data'     => 's:3',
				'expected' => false,
			),
			'not a colon in second position'          => array(
				'data'     => 's!3:"foo";',
				'expected' => false,
			),
			'no trailing semicolon'                   => array(
				'data'     => 's:3:"foo"',
				'expected' => false,
			),
			'wrong type of serialized data'           => array(
				'data'     => 'a:3:"foo";',
				'expected' => false,
			),
			'no closing quote'                        => array(
				'data'     => 'a:3:"foo;',
				'expected' => false,
			),
			'single quotes instead of double'         => array(
				'data'     => "s:12:'foo';",
				'expected' => false,
			),
			'wrong number of characters (should not matter)' => array(
				'data'     => 's:12:"foo";',
				'expected' => true,
			),
			'valid serialized string'                 => array(
				'data'     => 's:3:"foo";',
				'expected' => true,
			),
		);
	}
}
