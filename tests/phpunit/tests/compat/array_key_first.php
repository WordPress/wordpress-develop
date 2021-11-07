<?php

/**
 * @group compat
 *
 * @covers ::array_key_first
 */
class Tests_Compat_array_key_first extends WP_UnitTestCase {

	/**
	 * Test that array_key_first() is always available (either from PHP or WP).
	 * @ticket 45055
	 */
	public function test_array_key_first_availability() {
		$this->assertTrue( function_exists( 'array_key_first' ) );
	}

	/**
	 * @ticket 45055
	 * @dataProvider data_array_key_first
	 * @param bool $expected the value of the key extracted to extracted from given array.
	 * @param array $arr The array to get first key from.
	 */
	public function test_array_key_first( $expected, $arr ) {
		if ( ! function_exists( 'array_key_first' ) ) {
			$this->markTestSkipped( 'array_key_first() is not available.' );
		} else {
			$this->assertSame(
				$expected,
				array_key_first( $arr )
			);
		}

	}

	/**
	 * Data provider for test_array_key_first().
	 *
	 * @return array
	 */
	public function data_array_key_first() {
		return array(
			'string_key'  => array(
				'expected' => 'key1',
				'arr'      => array(
					'key1' => 'val1',
					'key2' => 'val2',
				),
			),
			'int_key'     => array(
				'expected' => 99,
				'arr'      => array(
					99 => 'val1',
					1  => 'val2',
				),
			),
			'no_key'      => array(
				'expected' => 0,
				'arr'      => array( 'val1', 'val2' ),
			),
			'multi_array' => array(
				'expected' => 99,
				'arr'      => array(
					99 => array( 22 => 'val1' ),
					1  => 'val2',
				),
			),
			'empty_array' => array(
				'expected' => null,
				'arr'      => array(),
			),
		);
	}

}
