<?php

/**
 * @group compat
 *
 * @covers ::array_first
 */
class Tests_Compat_arrayFirst extends WP_UnitTestCase {

	/**
	 * @ticket 63853
	 *
	 * Test that array_first() is always available (either from PHP or WP).
	 */
	public function test_array_first_availability(): void {
		$this->assertTrue( function_exists( 'array_first' ) );
	}

	/**
	 * @ticket 63853
	 *
	 * @dataProvider data_array_first
	 *
	 * @param mixed $expected The value extracted from the given array.
	 * @param array $arr      The array to get the first value from.
	 */
	public function test_array_first( $expected, $arr ): void {
		$this->assertSame( $expected, array_first( $arr ) );
	}


	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_array_first(): array {
		$obj = new \stdClass();
		return array(
			'string values'        => array(
				'expected' => 'a',
				'arr'      => array( 'a', 'b', 'c' ),
			),
			'associative array'    => array(
				'expected' => 10,
				'arr'      => array(
					'foo' => 10,
					'bar' => 20,
				),
			),
			'empty array'          => array(
				'expected' => null,
				'arr'      => array(),
			),
			'single element array' => array(
				'expected' => 42,
				'arr'      => array( 42 ),
			),
			'null values'          => array(
				'expected' => null,
				'arr'      => array( null, 'b', 'c' ),
			),
			'objects'              => array(
				'expected' => $obj,
				'arr'      => array(
					$obj,
					1,
					2,
				),
			),
			'boolean values'       => array(
				'expected' => false,
				'arr'      => array( false, true, 1, 2, 3 ),
			),
		);
	}

	/**
	 * Test that array_first() returns the pointer is not the first element.
	 *
	 * @ticket 63853
	 */
	public function test_array_first_with_end_pointer() {
		$arr = array(
			'key1' => 'val1',
			'key2' => 'val2',
		);
		// change the pointer to the last element
		end( $arr );

		$val = array_first( $arr );
		$this->assertSame( 'val2', current( $arr ) );
		$this->assertSame( 'val1', $val );
	}
}
