<?php

/**
 * @group compat
 *
 * @covers ::array_key_first
 */
class Tests_Compat_arrayKeyFirst extends WP_UnitTestCase {

	/**
	 * Test that array_key_first() is always available (either from PHP or WP).
	 * @ticket 45055
	 */
	public function test_array_key_first_availability() {
		$this->assertTrue( function_exists( 'array_key_first' ) );
	}

	/**
	 * Test that array_key_first() returns the pointer is not the first element.
	 *
	 * @ticket 45055
	 */
	public function test_array_key_first_with_end_pointer() {
		$arr = array( 'key1' => 'val1', 'key2' => 'val2' );
		// change the pointer to the last element
		end( $arr );

		$key = array_key_first( $arr );
		$this->assertSame( 'key2', key( $arr ) );
		$this->assertSame( 'key1', $key );
	}

	/**
	 * @dataProvider data_array_key_first
	 *
	 * @ticket 45055
	 *
	 * @param bool $expected The value of the key extracted to extracted from given array.
	 * @param array $arr     The array to get first key from.
	 */
	public function test_array_key_first( $expected, $arr ) {
		$this->assertSame( $expected, array_key_first( $arr ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_array_key_first() {
		return array(
			'string key'  => array(
				'expected' => 'key1',
				'arr'      => array(
					'key1' => 'val1',
					'key2' => 'val2',
				),
			),
			'int key'     => array(
				'expected' => 99,
				'arr'      => array(
					99 => 'val1',
					1  => 'val2',
				),
			),
			'no key'      => array(
				'expected' => 0,
				'arr'      => array( 'val1', 'val2' ),
			),
			'multi array' => array(
				'expected' => 99,
				'arr'      => array(
					99 => array( 22 => 'val1' ),
					1  => 'val2',
				),
			),
			'empty array' => array(
				'expected' => null,
				'arr'      => array(),
			),
		);
	}
}
