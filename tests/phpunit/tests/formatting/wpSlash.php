<?php

/**
 * @group formatting
 *
 * @covers ::wp_slash
 */
class Tests_Formatting_wpSlash extends WP_UnitTestCase {

	/**
	 * @ticket 42195
	 *
	 * @dataProvider data_wp_slash
	 *
	 * @param string $value
	 * @param string $expected
	 */
	public function test_wp_slash( $value, $expected ) {
		$this->assertSame( $expected, wp_slash( $value ) );
	}

	/**
	 * Data provider for test_wp_slash().
	 *
	 * @return array {
	 *     @type array {
	 *         @type mixed  $value    The value passed to wp_slash().
	 *         @type string $expected The expected output of wp_slash().
	 *     }
	 * }
	 */
	public function data_wp_slash() {
		return array(
			array( 123, 123 ),
			array( 123.4, 123.4 ),
			array( true, true ),
			array( false, false ),
			array(
				array(
					'hello',
					null,
					'"string"',
					125.41,
				),
				array(
					'hello',
					null,
					'\"string\"',
					125.41,
				),
			),
			array( "first level 'string'", "first level \'string\'" ),
		);
	}

	/**
	 * @ticket 24106
	 */
	public function test_adds_slashes() {
		$old = "I can't see, isn't that it?";
		$new = "I can\'t see, isn\'t that it?";
		$this->assertSame( $new, wp_slash( $old ) );
		$this->assertSame( "I can\\\\\'t see, isn\\\\\'t that it?", wp_slash( $new ) );
		$this->assertSame( array( 'a' => $new ), wp_slash( array( 'a' => $old ) ) ); // Keyed array.
		$this->assertSame( array( $new ), wp_slash( array( $old ) ) ); // Non-keyed.
	}

	/**
	 * @ticket 24106
	 */
	public function test_preserves_original_datatype() {

		$this->assertTrue( wp_slash( true ) );
		$this->assertFalse( wp_slash( false ) );
		$this->assertSame( 4, wp_slash( 4 ) );
		$this->assertSame( 'foo', wp_slash( 'foo' ) );
		$arr      = array(
			'a' => true,
			'b' => false,
			'c' => 4,
			'd' => 'foo',
		);
		$arr['e'] = $arr; // Add a sub-array.
		$this->assertSame( $arr, wp_slash( $arr ) ); // Keyed array.
		$this->assertSame( array_values( $arr ), wp_slash( array_values( $arr ) ) ); // Non-keyed.

		$obj = new stdClass;
		foreach ( $arr as $k => $v ) {
			$obj->$k = $v;
		}
		$this->assertSame( $obj, wp_slash( $obj ) );
	}

	/**
	 * @ticket 24106
	 */
	public function test_add_even_more_slashes() {
		$old = 'single\\slash double\\\\slash triple\\\\\\slash';
		$new = 'single\\\\slash double\\\\\\\\slash triple\\\\\\\\\\\\slash';
		$this->assertSame( $new, wp_slash( $old ) );
		$this->assertSame( array( 'a' => $new ), wp_slash( array( 'a' => $old ) ) ); // Keyed array.
		$this->assertSame( array( $new ), wp_slash( array( $old ) ) ); // Non-keyed.
	}

}
