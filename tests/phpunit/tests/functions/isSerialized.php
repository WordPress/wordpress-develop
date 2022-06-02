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
	 * Data provider method for testing `is_serialized()`.
	 *
	 * @return array
	 */
	public function _is_serialized() {
		return array(

			// pass array, not a string.
			array( array(), false ),

			// pass a class, not a string.
			array( new stdClass(), false ),

			// pass an integer, not a string.
			array( 0, false ),

			// Too short.
			array( 's:3', false ),

			// No colon in second position.
			array( 's!3:"foo";', false ),

			// Strict check: No trailing semicolon.
			array( 's:3:"foo"', false ),

			// Okay.
			array( 'N;', true ),

			// Enum.
			array( 'E:7:"Foo:bar";', true ),
		);
	}

	/**
	 * Run tests on `is_serialized()`.
	 *
	 * @dataProvider _is_serialized
	 *
	 * @param array|object|int|string $data     Data value to test.
	 * @param bool                    $expected Expected function result.
	 */
	public function test_is_serialized_string( $data, $expected ) {
		$this->assertSame( $expected, is_serialized( $data ) );
	}
}
