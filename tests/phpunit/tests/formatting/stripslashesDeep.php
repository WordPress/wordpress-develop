<?php

/**
 * @group formatting
 * @group slashes
 *
 * @covers ::stripslashes_deep
 * @covers ::stripslashes_from_strings_only
 */
class Tests_Formatting_StripslashesDeep extends WP_UnitTestCase {
	/**
	 * @ticket 18026
	 */
	public function test_preserves_original_datatype() {

		$this->assertTrue( stripslashes_deep( true ) );
		$this->assertFalse( stripslashes_deep( false ) );
		$this->assertSame( 4, stripslashes_deep( 4 ) );
		$this->assertSame( 'foo', stripslashes_deep( 'foo' ) );
		$arr      = array(
			'a' => true,
			'b' => false,
			'c' => 4,
			'd' => 'foo',
		);
		$arr['e'] = $arr; // Add a sub-array.
		$this->assertSame( $arr, stripslashes_deep( $arr ) ); // Keyed array.
		$this->assertSame( array_values( $arr ), stripslashes_deep( array_values( $arr ) ) ); // Non-keyed.

		$obj = new stdClass();
		foreach ( $arr as $k => $v ) {
			$obj->$k = $v;
		}
		$this->assertSame( $obj, stripslashes_deep( $obj ) );
	}

	public function test_strips_slashes() {
		$old = "I can\'t see, isn\'t that it?";
		$new = "I can't see, isn't that it?";
		$this->assertSame( $new, stripslashes_deep( $old ) );
		$this->assertSame( $new, stripslashes_deep( "I can\\'t see, isn\\'t that it?" ) );
		$this->assertSame( array( 'a' => $new ), stripslashes_deep( array( 'a' => $old ) ) ); // Keyed array.
		$this->assertSame( array( $new ), stripslashes_deep( array( $old ) ) ); // Non-keyed.

		$obj_old    = new stdClass();
		$obj_old->a = $old;
		$obj_new    = new stdClass();
		$obj_new->a = $new;

		// Keep assertEquals() because the objects are intentionally compared by value.
		$this->assertEquals( $obj_new, stripslashes_deep( $obj_old ) );
	}

	public function test_permits_escaped_slash() {
		$txt = "I can't see, isn\'t that it?";
		$this->assertSame( $txt, stripslashes_deep( "I can\'t see, isn\\\'t that it?" ) );
		$this->assertSame( $txt, stripslashes_deep( "I can\'t see, isn\\\\\'t that it?" ) );
	}

	/**
	 * Tests that stripslashes_from_strings_only() strips slashes from strings.
	 *
	 * @ticket 65819
	 *
	 * @covers ::stripslashes_from_strings_only
	 */
	public function test_stripslashes_from_strings_only_with_string() {
		$this->assertSame( "I can't see, isn't that it?", stripslashes_from_strings_only( "I can\'t see, isn\'t that it?" ) );
	}

	/**
	 * Tests that stripslashes_from_strings_only() preserves non-string values.
	 *
	 * @ticket 65819
	 *
	 * @covers ::stripslashes_from_strings_only
	 *
	 * @dataProvider data_non_string_values
	 *
	 * @param mixed $value Non-string value.
	 */
	public function test_stripslashes_from_strings_only_with_non_string( $value ) {
		$this->assertSame( $value, stripslashes_from_strings_only( $value ) );
	}

	/**
	 * Data provider for test_stripslashes_from_strings_only_with_non_string().
	 *
	 * @return array[]
	 */
	public static function data_non_string_values() {
		return array(
			'integer'      => array( 42 ),
			'float'        => array( 3.14 ),
			'boolean true' => array( true ),
			'null'         => array( null ),
			'array'        => array( array( 'a', 'b' ) ),
		);
	}
}
