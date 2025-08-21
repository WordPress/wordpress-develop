<?php

/**
 * @group compat
 *
 * @covers ::array_first
 */
class Tests_Compat_arrayFirst extends WP_UnitTestCase {

	/**
	 * Test that array_first() is always available (either from PHP or WP).
	 */
	public function test_array_first_availability(): void {
		$this->assertTrue( function_exists( 'array_first' ) );
	}

	/**
	 * @dataProvider data_array_first
	 *
	 * @ticket 63853
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
}
