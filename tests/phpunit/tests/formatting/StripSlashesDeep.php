<?php

/**
 * @group formatting
 * @group slashes
 */
class Tests_Formatting_StripSlashesDeep extends WP_UnitTestCase {
	/**
	 * @ticket 18026
	 */
	function test_preserves_original_datatype() {

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

		$obj = new stdClass;
		foreach ( $arr as $k => $v ) {
			$obj->$k = $v;
		}
		$this->assertSame( $obj, stripslashes_deep( $obj ) );
	}

	function test_strips_slashes() {
		$old = "I can\'t see, isn\'t that it?";
		$new = "I can't see, isn't that it?";
		$this->assertSame( $new, stripslashes_deep( $old ) );
		$this->assertSame( $new, stripslashes_deep( "I can\\'t see, isn\\'t that it?" ) );
		$this->assertSame( array( 'a' => $new ), stripslashes_deep( array( 'a' => $old ) ) ); // Keyed array.
		$this->assertSame( array( $new ), stripslashes_deep( array( $old ) ) ); // Non-keyed.

		$obj_old    = new stdClass;
		$obj_old->a = $old;
		$obj_new    = new stdClass;
		$obj_new->a = $new;
		$this->assertEquals( $obj_new, stripslashes_deep( $obj_old ) );
	}

	function test_permits_escaped_slash() {
		$txt = "I can't see, isn\'t that it?";
		$this->assertSame( $txt, stripslashes_deep( "I can\'t see, isn\\\'t that it?" ) );
		$this->assertSame( $txt, stripslashes_deep( "I can\'t see, isn\\\\\'t that it?" ) );
	}
}
