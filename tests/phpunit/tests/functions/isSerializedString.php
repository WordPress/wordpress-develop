<?php

/**
 * Tests for `is_serialized_string()`.
 *
 * @group functions.php
 * @ticket 42870
 */
class Tests_Functions_IsSerializedString extends WP_UnitTestCase {

	/**
	 * Data provider method for testing `is_serialized_string()`.
	 *
	 * @return array
	 */
	public function _is_serialized_string() {
		return array(

			// pass array.
			array( array(), false ),

			// pass a class.
			array( new stdClass(), false ),

			// Not a string.
			array( 0, false ),

			// Too short when trimmed.
			array( 's:3       ', false ),

			// Too short.
			array( 's:3', false ),

			// No colon in second position.
			array( 's!3:"foo";', false ),

			// No trailing semicolon.
			array( 's:3:"foo"', false ),

			// Wrong type.
			array( 'a:3:"foo";', false ),

			// No closing quote.
			array( 'a:3:"foo;', false ),

			// have to use double Quotes.
			array( "s:12:'foo';", false ),

			// Wrong number of characters is close enough for is_serialized_string().
			array( 's:12:"foo";', true ),

			// Okay.
			array( 's:3:"foo";', true ),
		);
	}

	/**
	 * Run tests on `is_serialized_string()`.
	 *
	 * @dataProvider _is_serialized_string
	 *
	 * @param array|object|int|string $data     Data value to test.
	 * @param bool                    $expected Expected function result.
	 */
	public function test_is_serialized_string( $data, $expected ) {
		$this->assertSame( $expected, is_serialized_string( $data ) );
	}
}
